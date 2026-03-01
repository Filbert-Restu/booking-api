<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentLog;
use App\Models\Sign;
use App\Models\User;
use App\Models\WorkflowStep;
use App\Models\Unit;
use App\Services\DocumentGenerationService;
use App\Models\RoomBooking;
use Illuminate\Support\Facades\DB;

class WorkflowEngine
{
    /**
     * Logika Utama: Approve & Oper ke orang berikutnya
     */
    public function approveDocument(Document $document, User $actor, $note = null, $signaturePath = null)
    {
        return DB::transaction(function () use ($document, $actor, $note, $signaturePath) {
            // 1. Catat Log "APPROVED"
            DocumentLog::create([
                'document_id' => $document->id,
                'user_id' => $actor->id,
                'action' => 'APPROVED',
                'note' => $note,
                'step_snapshot' => $document->current_step_order
            ]);

            // 2. Simpan tanda tangan jika ada
            if ($signaturePath) {
                Sign::create([
                    'user_id' => $actor->id,
                    'signature' => $signaturePath,
                    'signed_at' => now(),
                ]);
            }

            // 2b. REGENERATE lembar pengesahan dengan TTD yang baru
            try {
                $document->load(['unit', 'workflow']);
                $organizationType = strtolower($document->unit->category ?? 'hmd');

                \Log::info("[WorkflowEngine] Regenerating approval sheet after approve", [
                    'document_id' => $document->id,
                    'approver' => $actor->name,
                    'organization_type' => $organizationType
                ]);

                $generationService = app(DocumentGenerationService::class);
                
                // 1. Regenerate Lembar Pengesahan (Standard for all approvers)
                $filePathApproval = $generationService->generateFromTemplate(
                    $document,
                    'lembar_pengesahan',
                    $organizationType
                );
                
                $updateData = ['file_approval_sheet' => $filePathApproval];

                // 2. Jika approver adalah Wadek 1, regenerate juga Executive Summary
                // Karena Wadek 1 wajib tanda tangan di kedua tempat
                if ($actor->role && $actor->role->slug === 'wadek1') {
                    \Log::info("[WorkflowEngine] Wadek 1 detected, also regenerating executive summary");
                    $filePathExecutive = $generationService->generateFromTemplate(
                        $document,
                        'executive_summary',
                        null
                    );
                    $updateData['file_executive_summary'] = $filePathExecutive;
                }

                $document->update($updateData);

                \Log::info("[WorkflowEngine] ✅ Documents regenerated successfully");
            } catch (\Exception $e) {
                // Don't fail the approval if regeneration fails, just log
                \Log::error("[WorkflowEngine] Failed to regenerate approval sheet", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }

            // 3. Cari Langkah Selanjutnya
            $nextStepOrder = $document->current_step_order + 1;
            $nextStepConfig = WorkflowStep::where('workflow_id', $document->workflow_id)
                                          ->where('step_order', $nextStepOrder)
                                          ->first();

            // 4. Jika TIDAK ADA langkah selanjutnya -> SELESAI
            if (!$nextStepConfig) {
                $document->update([
                    'status' => 'APPROVED',
                    'completed_at' => now(),
                    'current_holder_id' => null,
                ]);

                // Update or Create RoomBooking
                $content = $document->content;
                $booking = RoomBooking::where('document_id', $document->id)->first();

                if ($booking) {
                    $booking->update([
                        'status' => 'APPROVED',
                        'approved_by' => $actor->id,
                        'approved_at' => now(),
                    ]);
                } else if (isset($content['room_id']) && isset($content['booking_date'])) {
                    // Create if not exists (e.g. from Proposal Only -> Booking flow if implemented later, or backup)
                    RoomBooking::create([
                        'document_id' => $document->id,
                        'room_id' => $content['room_id'],
                        'booked_by' => $document->creator_id,
                        'booking_date' => $content['booking_date'],
                        'start_time' => $content['start_time'] ?? '08:00',
                        'end_time' => $content['end_time'] ?? '16:00',
                        'purpose' => $content['event_name'] ?? ($document->title ?? 'Booking'),
                        'status' => 'APPROVED',
                        'approved_by' => $actor->id,
                        'approved_at' => now(),
                    ]);
                }

                return 'Dokumen telah disetujui sepenuhnya dan proses selesai.';
            }

            // 5. Jika ADA, Cari SIAPA Orangnya (Logic Swimlane/Cross-Unit)
            $nextUser = $this->findApprover($document, $nextStepConfig);

            if (!$nextUser) {
                throw new \Exception("User untuk langkah selanjutnya tidak ditemukan. Cek konfigurasi Unit/Role.");
            }

            // Load role relationship
            $nextUser->load('role');

            // 6. Update Dokumen (Oper Bola)
            $document->update([
                'current_holder_id' => $nextUser->id,
                'current_step_order' => $nextStepOrder,
                'status' => 'IN_PROGRESS'
            ]);

            $roleName = $nextUser->role ? $nextUser->role->name : 'Unknown Role';
            return "Dokumen diteruskan ke: " . $nextUser->name . " (" . $roleName . ")";
        });
    }

    /**
     * Logika Revisi: Kembalikan ke masa lalu
     */
    public function reviseDocument(Document $document, User $actor, $targetUserId, $note)
    {
        return DB::transaction(function () use ($document, $actor, $targetUserId, $note) {
            // 1. Validasi: Apakah target benar-benar pernah pegang surat ini?
            $isValidTarget = DocumentLog::where('document_id', $document->id)
                                        ->where('user_id', $targetUserId)
                                        ->exists();

            if (!$isValidTarget && $document->creator_id != $targetUserId) {
                throw new \Exception("User target tidak ada dalam riwayat dokumen ini.");
            }

            // 2. Catat Log "RETURNED"
            DocumentLog::create([
                'document_id' => $document->id,
                'user_id' => $actor->id, // Manager
                'action' => 'RETURNED',
                'note' => $note, // "Salah ketik, tolong perbaiki"
            ]);

            // 3. Kembalikan Bola (Update Master)
            // Note: Kita tidak mereset 'current_step_order' secara hardcode,
            // karena bisa jadi alurnya loncat. Biarkan status REVISION menanganinya.
            $document->update([
                'current_holder_id' => $targetUserId,
                'status' => 'REVISION'
            ]);

            return "Dokumen dikembalikan untuk revisi.";
        });
    }

    /**
     * Logic Pencarian User (Swimlanes & Cross-Unit)
     */
    public function findApprover(Document $doc, WorkflowStep $step)
    {
        $originUnit = $doc->unit; // Unit pembuat surat (misal: HIMA)

        if (!$originUnit) {
            throw new \Exception("Dokumen tidak memiliki unit. Tidak dapat menentukan approver.");
        }

        // Query Builder Awal
        $query = User::query()->whereHas('role', function($q) use ($step) {
            $q->where('slug', $step->target_role_slug);
        });

        $approver = null;

        switch ($step->scope_type) {
            case 'SELF':
                // Cari di unit pengirim (HIMA)
                $approver = $query->where('unit_id', $originUnit->id)->first();

                if (!$approver) {
                    throw new \Exception(
                        "Tidak dapat menemukan approver untuk langkah '{$step->step_name}'. " .
                        "Diperlukan user dengan role '{$step->target_role_slug}' di unit '{$originUnit->name}'. " .
                        "Silakan hubungi admin untuk menambahkan user dengan role tersebut."
                    );
                }
                return $approver;

            case 'PARENT':
                // Cari di induk (Prodi)
                if (!$originUnit->parent_id) {
                    throw new \Exception("Unit {$originUnit->name} tidak memiliki parent unit. Tidak dapat menentukan approver untuk scope PARENT.");
                }
                $approver = $query->where('unit_id', $originUnit->parent_id)->first();

                if (!$approver) {
                    $parentUnit = Unit::find($originUnit->parent_id);
                    throw new \Exception(
                        "Tidak dapat menemukan approver untuk langkah '{$step->step_name}'. " .
                        "Diperlukan user dengan role '{$step->target_role_slug}' di unit parent '{$parentUnit->name}'. " .
                        "Silakan hubungi admin untuk menambahkan user dengan role tersebut."
                    );
                }
                return $approver;

            case 'FACULTY_LEADER':
                // Cari di Fakultas (Unit tanpa parent / Root)
                $facultyUnit = Unit::where('category', 'FAKULTAS')->first();
                if (!$facultyUnit) {
                    throw new \Exception("Unit dengan category FAKULTAS tidak ditemukan.");
                }
                $approver = $query->where('unit_id', $facultyUnit->id)->first();

                if (!$approver) {
                    throw new \Exception(
                        "Tidak dapat menemukan approver untuk langkah '{$step->step_name}'. " .
                        "Diperlukan user dengan role '{$step->target_role_slug}' di unit fakultas '{$facultyUnit->name}'. " .
                        "Silakan hubungi admin untuk menambahkan user dengan role tersebut."
                    );
                }
                return $approver;

            case 'SPECIFIC_CATEGORY':
                // Cari Unit lain (Misal: SENAT)
                // Asumsi: Kita cari unit 'SENAT' yang satu fakultas/kampus
                if (!$step->target_category_lookup) {
                    throw new \Exception("target_category_lookup tidak didefinisikan untuk step dengan scope SPECIFIC_CATEGORY.");
                }
                // Case-insensitive search untuk menghindari mismatch 'Senat' vs 'SENAT'
                $targetUnit = Unit::whereRaw('LOWER(category) = ?', [strtolower($step->target_category_lookup)])->first();
                if (!$targetUnit) {
                    throw new \Exception("Unit dengan category {$step->target_category_lookup} tidak ditemukan. Pastikan unit sudah dibuat di database.");
                }
                $approver = $query->where('unit_id', $targetUnit->id)->first();

                if (!$approver) {
                    throw new \Exception(
                        "Tidak dapat menemukan approver untuk langkah '{$step->step_name}'. " .
                        "Diperlukan user dengan role '{$step->target_role_slug}' di unit '{$targetUnit->name}'. " .
                        "Silakan hubungi admin untuk menambahkan user dengan role tersebut."
                    );
                }
                return $approver;

            default:
                return null;
        }
    }
}
