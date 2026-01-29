<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentLog;
use App\Models\Sign;
use App\Models\User;
use App\Models\WorkflowStep;
use App\Models\Unit;
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
                return 'Dokumen telah disetujui sepenuhnya dan proses selesai.';
            }

            // 5. Jika ADA, Cari SIAPA Orangnya (Logic Swimlane/Cross-Unit)
            $nextUser = $this->findApprover($document, $nextStepConfig);

            if (!$nextUser) {
                throw new \Exception("User untuk langkah selanjutnya tidak ditemukan. Cek konfigurasi Unit/Role.");
            }

            // 6. Update Dokumen (Oper Bola)
            $document->update([
                'current_holder_id' => $nextUser->id,
                'current_step_order' => $nextStepOrder,
                'status' => 'IN_PROGRESS'
            ]);

            return "Dokumen diteruskan ke: " . $nextUser->name . " (" . $nextUser->role->name . ")";
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

            if (!$isValidTarget && $document->user_id != $targetUserId) {
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

        // Query Builder Awal
        $query = User::query()->whereHas('role', function($q) use ($step) {
            $q->where('slug', $step->target_role_slug);
        });

        switch ($step->scope_type) {
            case 'SELF':
                // Cari di unit pengirim (HIMA)
                return $query->where('unit_id', $originUnit->id)->first();

            case 'PARENT':
                // Cari di induk (Prodi)
                return $query->where('unit_id', $originUnit->parent_id)->first();

            case 'FACULTY_LEADER':
                // Cari di Fakultas (Unit tanpa parent / Root)
                $facultyUnit = Unit::where('category', 'FAKULTAS')->first();
                return $query->where('unit_id', $facultyUnit->id)->first();

            case 'SPECIFIC_CATEGORY':
                // Cari Unit lain (Misal: SENAT)
                // Asumsi: Kita cari unit 'SENAT' yang satu fakultas/kampus
                $targetUnit = Unit::where('category', $step->target_category_lookup)->first();
                return $query->where('unit_id', $targetUnit->id)->first();

            default:
                return null;
        }
    }
}
