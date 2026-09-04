<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentLog;
use App\Models\RoomBooking;
use App\Models\Sign;
use App\Models\User;
use App\Services\WorkflowEngine;
use App\Services\DocumentGenerationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\TemplateProcessor;

class DocumentService
{
    protected WorkflowEngine $workflowEngine;
    protected DocumentGenerationService $documentGenerationService;

    public function __construct(WorkflowEngine $workflowEngine, DocumentGenerationService $documentGenerationService)
    {
        $this->workflowEngine = $workflowEngine;
        $this->documentGenerationService = $documentGenerationService;
    }

    public function listDocuments(User $user, array $filters): array
    {
        $isAdmin = $user->role->slug === 'admin';
        $perPage = $filters['per_page'] ?? 10;
        $search = $filters['q'] ?? null;

        if ($isAdmin) {
            return $this->listDocumentsForAdmin($filters, $perPage, $search);
        }

        return $this->listDocumentsForUser($user, $filters, $perPage, $search);
    }

    private function listDocumentsForAdmin(array $filters, int $perPage, ?string $search): array
    {
        $baseQuery = Document::with(['workflow', 'currentHolder', 'creator', 'unit', 'logs']);

        if (!empty($filters['workflow_id'])) {
            $baseQuery->where('workflow_id', $filters['workflow_id']);
        }
        if (!empty($filters['unit_id'])) {
            $baseQuery->where('unit_id', $filters['unit_id']);
        }

        $myQuery = clone $baseQuery;
        if (!empty($filters['status'])) {
            $myQuery->where('status', $filters['status']);
        }
        $this->applySearch($myQuery, $search);
        $myDocuments = $myQuery->latest()->paginate($perPage, ['*'], 'page_my');

        $pendingQuery = clone $baseQuery;
        $pendingQuery->whereIn('status', ['IN_PROGRESS', 'REVISION']);
        $this->applySearch($pendingQuery, $search);
        $pendingDocuments = $pendingQuery->latest()->paginate($perPage, ['*'], 'page_pending');

        $processedQuery = clone $baseQuery;
        $processedQuery->whereIn('status', ['APPROVED', 'REJECTED', 'RETURNED']);
        $this->applySearch($processedQuery, $search);
        $processedDocuments = $processedQuery->latest()->paginate($perPage, ['*'], 'page_processed');

        return [
            'my_documents' => $myDocuments,
            'pending_documents' => $pendingDocuments,
            'processed_documents' => $processedDocuments,
        ];
    }

    private function listDocumentsForUser(User $user, array $filters, int $perPage, ?string $search): array
    {
        $myDocumentsQuery = Document::with(['workflow', 'currentHolder.role', 'creator', 'unit', 'logs.user.unit'])
            ->where('creator_id', $user->id);

        if (!empty($filters['status'])) {
            $myDocumentsQuery->where('status', $filters['status']);
        }
        if (!empty($filters['workflow_id'])) {
            $myDocumentsQuery->where('workflow_id', $filters['workflow_id']);
        }

        $this->applySearch($myDocumentsQuery, $search);
        $myDocuments = $myDocumentsQuery->latest()->paginate($perPage, ['*'], 'page_my');

        $myDocuments->getCollection()->transform(function ($doc) {
            $doc->current_holder_info = $doc->currentHolder ? [
                'id' => $doc->currentHolder->id,
                'name' => $doc->currentHolder->name,
                'role' => $doc->currentHolder->role->name ?? 'Unknown',
            ] : null;
            return $doc;
        });

        $pendingDocumentsQuery = Document::with(['workflow', 'creator', 'unit'])
            ->where('current_holder_id', $user->id)
            ->whereIn('status', ['IN_PROGRESS', 'REVISION']);

        if (!empty($filters['workflow_id'])) {
            $pendingDocumentsQuery->where('workflow_id', $filters['workflow_id']);
        }

        $this->applySearch($pendingDocumentsQuery, $search);
        $pendingDocuments = $pendingDocumentsQuery->latest()->paginate($perPage, ['*'], 'page_pending');

        $processedDocumentIds = DocumentLog::where('user_id', $user->id)
            ->whereIn('action', ['APPROVED', 'REJECTED', 'RETURNED'])
            ->pluck('document_id')
            ->unique();

        $processedDocumentsQuery = Document::with(['workflow', 'currentHolder', 'creator', 'unit', 'logs.user.role'])
            ->whereIn('id', $processedDocumentIds)
            ->where(function ($query) use ($user) {
                $query->where('current_holder_id', '!=', $user->id)
                      ->orWhereNull('current_holder_id');
            });

        if (!empty($filters['status'])) {
            $processedDocumentsQuery->where('status', $filters['status']);
        }
        if (!empty($filters['workflow_id'])) {
            $processedDocumentsQuery->where('workflow_id', $filters['workflow_id']);
        }

        $this->applySearch($processedDocumentsQuery, $search);
        $processedDocuments = $processedDocumentsQuery->latest()->paginate($perPage, ['*'], 'page_processed');

        return [
            'my_documents' => $myDocuments,
            'pending_documents' => $pendingDocuments,
            'processed_documents' => $processedDocuments,
        ];
    }

    private function applySearch($query, ?string $search): void
    {
        if (!$search) return;

        $search = trim($search);

        $query->where(function ($q) use ($search) {
            $q->where('id', $search)
              ->orWhere('title', 'like', "%{$search}%")
              ->orWhereHas('creator', function ($q2) use ($search) {
                  $q2->where('name', 'like', "%{$search}%");
              })
              ->orWhereHas('unit', function ($q2) use ($search) {
                  $q2->where('name', 'like', "%{$search}%");
              });
        });
    }

    public function showDocument(int $id, User $user): Document
    {
        $document = Document::with([
            'workflow.steps',
            'currentHolder.role',
            'currentHolder.unit',
            'creator.role',
            'unit',
            'logs.user.role'
        ])->findOrFail($id);

        $isAdmin = $user->role->slug === 'admin';
        $isCreator = $document->creator_id === $user->id;
        $isCurrentHolder = $document->current_holder_id === $user->id;

        $hasProcessed = DocumentLog::where('document_id', $document->id)
            ->where('user_id', $user->id)
            ->whereIn('action', ['APPROVED', 'REJECTED', 'SUBMITTED', 'REVISED', 'RETURNED'])
            ->exists();

        if (!$isAdmin && !$isCreator && !$isCurrentHolder && !$hasProcessed) {
            throw new \Exception('Anda tidak memiliki akses untuk melihat dokumen ini', 403);
        }

        return $document;
    }

    public function createDocument(User $user, array $validated, Request $request): ?Document
    {
        $content = $request->input('content', []);

        // NIM/HP validation
        if (isset($content['ketua_pelaksana_nim']) && !empty($content['ketua_pelaksana_nim'])) {
            if (!preg_match('/^\d{14}$/', $content['ketua_pelaksana_nim'])) {
                throw new \Illuminate\Validation\ValidationException(
                    \Illuminate\Support\Facades\Validator::make([], []),
                    response()->json([
                        'success' => false,
                        'message' => 'NIM harus 14 digit angka',
                        'errors' => ['content.ketua_pelaksana_nim' => ['NIM harus 14 digit angka']],
                    ], 422)
                );
            }
        }
        if (isset($content['ketua_pelaksana_hp']) && !empty($content['ketua_pelaksana_hp'])) {
            if (!preg_match('/^\d{12,13}$/', $content['ketua_pelaksana_hp'])) {
                throw new \Illuminate\Validation\ValidationException(
                    \Illuminate\Support\Facades\Validator::make([], []),
                    response()->json([
                        'success' => false,
                        'message' => 'HP harus 12-13 digit angka',
                        'errors' => ['content.ketua_pelaksana_hp' => ['HP harus 12-13 digit angka']],
                    ], 422)
                );
            }
        }

        $validated['content'] = $content;

        // Prevent duplicate reservations
        $metaData = $validated['meta_data'] ?? [];
        if (isset($metaData['step']) && $metaData['step'] === 'reservation') {
            $existingDoc = $this->findDuplicateReservation($user, $content);
            if ($existingDoc) {
                return $existingDoc; // Return existing doc, controller decides response
            }
        }

        $document = DB::transaction(function () use ($request, $validated, $user) {
            $pathExecutive = null;
            if ($request->hasFile('executive_summary')) {
                $pathExecutive = $request->file('executive_summary')->store('documents/executive_summaries', 'private');
            }

            $pathApproval = null;
            if ($request->hasFile('approval_sheet')) {
                $pathApproval = $request->file('approval_sheet')->store('documents/approval_sheets', 'private');
            }

            $pathProposal = null;
            if ($request->hasFile('proposal')) {
                $pathProposal = $request->file('proposal')->store('documents/proposals', 'private');
            }

            $doc = Document::create([
                'workflow_id' => $validated['workflow_id'],
                'title'       => $validated['title'],
                'content'     => $validated['content'] ?? null,
                'meta_data'   => $validated['meta_data'] ?? null,
                'file_executive_summary' => $pathExecutive,
                'file_approval_sheet'    => $pathApproval,
                'file_proposal'          => $pathProposal,
                'unit_id'            => $user->unit_id,
                'creator_id'         => $user->id,
                'status'             => 'DRAFT',
                'current_step_order' => 1,
            ]);

            DocumentLog::create([
                'document_id' => $doc->id,
                'user_id'     => $user->id,
                'action'      => 'CREATED',
                'note'        => 'Dokumen dibuat',
            ]);

            return $doc;
        });

        return $document;
    }

    private function findDuplicateReservation(User $user, array $content): ?Document
    {
        return Document::where('creator_id', $user->id)
            ->where('status', 'DRAFT')
            ->whereJsonContains('meta_data->step', 'reservation')
            ->get()
            ->first(function ($doc) use ($content) {
                $docContent = $doc->content ?? [];
                return isset($docContent['room_id']) &&
                       isset($docContent['booking_date']) &&
                       isset($docContent['start_time']) &&
                       isset($docContent['end_time']) &&
                       $docContent['room_id'] == ($content['room_id'] ?? null) &&
                       $docContent['booking_date'] == ($content['booking_date'] ?? null) &&
                       $docContent['start_time'] == ($content['start_time'] ?? null) &&
                       $docContent['end_time'] == ($content['end_time'] ?? null);
            });
    }

    public function submitDocument(Document $document, User $user): Document
    {
        DB::transaction(function () use ($document, $user) {
            $userRole = $user->role->slug ?? '';

            // AUTO APPROVAL untuk Admin dan Sumber Daya
            if (in_array($userRole, ['admin', 'sumber-daya'])) {
                $document->update([
                    'status' => 'APPROVED',
                    'current_step_order' => 999,
                    'current_holder_id' => null,
                ]);

                DocumentLog::create([
                    'document_id' => $document->id,
                    'user_id' => $user->id,
                    'action' => 'APPROVED',
                    'note' => 'Dokumen disetujui secara otomatis (Manual Booking)',
                    'step_snapshot' => 999,
                ]);

                $content = $document->content;
                if (isset($content['room_id']) && isset($content['booking_date'])) {
                    RoomBooking::create([
                        'document_id' => $document->id,
                        'room_id' => $content['room_id'],
                        'booked_by' => $user->id,
                        'booking_date' => $content['booking_date'],
                        'start_time' => $content['start_time'] ?? '08:00',
                        'end_time' => $content['end_time'] ?? '16:00',
                        'purpose' => $content['event_name'] ?? 'Manual Booking',
                        'status' => 'APPROVED',
                        'approved_by' => $user->id,
                        'approved_at' => now(),
                    ]);
                }

                return;
            }

            $originalStatus = $document->status;
            $currentStep = 1;

            // HANDLE REVISION LOGIC (Requirements #8 & #9)
            if ($originalStatus === 'REVISION') {
                $lastBooking = RoomBooking::where('document_id', $document->id)
                    ->whereNotIn('status', ['CANCELLED', 'REJECTED'])
                    ->latest()
                    ->first();

                if ($lastBooking) {
                    $newContent = $document->content;
                    $newDate = $newContent['booking_date'] ?? null;
                    $newRoomId = $newContent['room_id'] ?? null;

                    $oldDate = $lastBooking->booking_date->format('Y-m-d');
                    $oldRoomId = $lastBooking->room_id;

                    if ($newDate && $newDate !== $oldDate) {
                        // Requirement #9: Ganti tanggal -> ulang dari awal
                        \Log::info("[Revision] Date changed from {$oldDate} to {$newDate}. Restarting workflow.");
                        $currentStep = 1;
                    } elseif ($newRoomId && $newRoomId != $oldRoomId) {
                        // Requirement #8: Ganti tempat saja -> langsung ke Sumber Daya
                        $sumberDayaStep = $document->workflow->steps()
                            ->where('target_role_slug', 'sumber-daya')
                            ->first();

                        if ($sumberDayaStep) {
                            \Log::info("[Revision] Room changed from {$oldRoomId} to {$newRoomId}. Jumping to Sumber Daya (Step {$sumberDayaStep->step_order}).");
                            $currentStep = $sumberDayaStep->step_order;
                        }
                    }
                }
            }

            $firstStep = $document->workflow->steps()->where('step_order', $currentStep)->first();

            if (!$firstStep) {
                throw new \Exception("Workflow tidak memiliki langkah ke-{$currentStep}");
            }

            $logNote = $originalStatus === 'REVISION'
                ? 'Dokumen diajukan ulang setelah revisi'
                : 'Dokumen diajukan untuk diproses';

            DocumentLog::create([
                'document_id' => $document->id,
                'user_id' => $user->id,
                'action' => 'SUBMITTED',
                'note' => $logNote,
                'step_snapshot' => 0,
            ]);

            // AUTO-APPROVE JIKA SUBMITTER ADALAH SEKRETARIS (berlaku untuk step 1)
            if ($currentStep === 1 && $userRole === 'sekretaris' && $firstStep->target_role_slug === 'sekretaris') {
                \Log::info('[DocumentService] Sekretaris auto-approve Step 1', [
                    'document_id' => $document->id,
                    'user' => $user->name,
                ]);

                DocumentLog::create([
                    'document_id' => $document->id,
                    'user_id' => $user->id,
                    'action' => 'APPROVED',
                    'note' => 'Pengajuan oleh Sekretaris (auto-approve)',
                    'step_snapshot' => 1,
                ]);

                $secondStep = $document->workflow->steps()->where('step_order', 2)->first();

                if (!$secondStep) {
                    $document->update([
                        'status' => 'APPROVED',
                        'completed_at' => now(),
                        'current_holder_id' => null,
                        'current_step_order' => 1,
                    ]);
                    return;
                }

                $secondApprover = $this->workflowEngine->findApprover($document, $secondStep);

                if (!$secondApprover) {
                    throw new \Exception("Tidak dapat menemukan approver untuk langkah '{$secondStep->step_name}'.");
                }

                $document->update([
                    'status' => 'IN_PROGRESS',
                    'current_step_order' => 2,
                    'current_holder_id' => $secondApprover->id,
                ]);

                return;
            }

            // ALUR NORMAL / JUMP
            $approver = $this->workflowEngine->findApprover($document, $firstStep);

            if (!$approver) {
                $roleName = $firstStep->target_role_slug;
                $unitName = $document->unit->name ?? 'Unknown Unit';

                throw new \Exception("Tidak dapat menemukan approver untuk langkah '{$firstStep->step_name}'. Diperlukan user dengan role '{$roleName}' di unit '{$unitName}'.");
            }

            $document->update([
                'status' => 'IN_PROGRESS',
                'current_step_order' => $currentStep,
                'current_holder_id' => $approver->id,
            ]);
        });

        return $document->fresh(['currentHolder', 'creator', 'logs']);
    }

    public function approveDocument(Document $document, User $user, ?string $note): string
    {
        $signaturePath = null;
        $userRoleSlug = $user->role->slug ?? '';
        $rolesWithoutSignature = ['sumber-daya', 'kemahasiswaan'];

        if (!in_array($userRoleSlug, $rolesWithoutSignature)) {
            $sign = Sign::where('user_id', $user->id)->latest()->first();
            if (!$sign || !$sign->signature) {
                throw new \Exception('Anda harus mengupload tanda tangan terlebih dahulu sebelum approve dokumen', 400);
            }
            $signaturePath = $sign->signature;
        }

        return $this->workflowEngine->approveDocument($document, $user, $note, $signaturePath);
    }

    public function reviseDocument(Document $document, User $user, int $targetUserId, string $note): string
    {
        return $this->workflowEngine->reviseDocument($document, $user, $targetUserId, $note);
    }

    public function rejectDocument(Document $document, User $user, string $note): string
    {
        return $this->workflowEngine->rejectDocument($document, $user, $note);
    }

    public function applySignatureToDocument(Document $document, User $user, string $type): void
    {
        $columnMap = [
            'approval-sheet' => 'file_approval_sheet',
            'executive-summary' => 'file_executive_summary'
        ];
        $templateTypeMap = [
            'approval-sheet' => 'lembar_pengesahan',
            'executive-summary' => 'executive_summary'
        ];

        $column = $columnMap[$type];
        $templateType = $templateTypeMap[$type];

        // Validasi: Dokumen harus memiliki file target
        if (!$document->{$column}) {
            $typeName = $type === 'approval-sheet' ? 'lembar pengesahan' : 'executive summary';
            throw new \Exception("Dokumen belum memiliki {$typeName}", 400);
        }

        // Validasi: File harus berformat DOCX
        $fileExtension = pathinfo($document->{$column}, PATHINFO_EXTENSION);
        if (strtolower($fileExtension) !== 'docx') {
            throw new \Exception('Hanya file DOCX yang dapat dibubuhkan tanda tangan', 400);
        }

        // Ambil signature user
        $signature = Sign::where('user_id', $user->id)->latest('updated_at')->first();

        if (!$signature) {
            throw new \Exception('Anda belum memiliki tanda tangan. Silakan upload tanda tangan terlebih dahulu.', 400);
        }

        $signature->refresh();

        if (!$signature->signature) {
            throw new \Exception('File tanda tangan tidak ditemukan. Silakan upload ulang tanda tangan Anda.', 400);
        }

        if (!Storage::disk('private')->exists($signature->signature)) {
            throw new \Exception('File tanda tangan hilang dari storage. Silakan upload ulang tanda tangan Anda.', 400);
        }

        $tempDocxPath = null;
        $tempSignaturePath = null;

        try {
            DB::beginTransaction();

            \Log::info('ApplySignature: Starting', [
                'document_id' => $document->id,
                'user_id' => $user->id,
                'signature_id' => $signature->id,
                'signature_path' => $signature->signature,
                'signature_updated_at' => $signature->updated_at,
            ]);

            // Regenerate from template
            $orgType = $this->mapCategoryToOrganizationType($document->unit->category ?? 'HMD');
            $newFilePath = $this->documentGenerationService->generateFromTemplate(
                $document,
                $templateType,
                $orgType
            );

            $document->update([$column => $newFilePath]);
            $document->refresh();

            // Download files to temp
            $tempDir = storage_path('app/temp');
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $targetPath = $document->{$column};
            $tempDocxPath = $tempDir . '/' . $type . '_' . uniqid() . '.docx';
            $docxContent = Storage::disk('private')->get($targetPath);
            file_put_contents($tempDocxPath, $docxContent);

            $tempSignaturePath = $tempDir . '/signature_' . uniqid() . '.' . pathinfo($signature->signature, PATHINFO_EXTENSION);
            $signatureContent = Storage::disk('private')->get($signature->signature);
            file_put_contents($tempSignaturePath, $signatureContent);

            // Load template processor
            $templateProcessor = new TemplateProcessor($tempDocxPath);

            // Determine placeholders based on role
            $userRole = $user->role->slug ?? '';
            $userUnitCategory = strtoupper($user->unit->category ?? '');
            $placeholders = $this->getSignaturePlaceholders($userRole, $userUnitCategory);

            // Insert signature
            $inserted = false;
            $insertedPlaceholders = [];
            foreach ($placeholders as $placeholder) {
                try {
                    $templateProcessor->setImageValue(
                        $placeholder,
                        [
                            'path' => $tempSignaturePath,
                            'width' => 100,
                            'height' => 50,
                            'ratio' => false
                        ]
                    );
                    $inserted = true;
                    $insertedPlaceholders[] = $placeholder;
                    \Log::info('ApplySignature: Successfully inserted at placeholder', ['placeholder' => $placeholder]);
                } catch (\Exception $e) {
                    \Log::warning('ApplySignature: Placeholder not found', [
                        'placeholder' => $placeholder,
                        'error' => $e->getMessage(),
                    ]);
                    continue;
                }
            }

            if (!$inserted) {
                throw new \Exception('Placeholder tanda tangan untuk role Anda tidak ditemukan di template');
            }

            // Save + upload back to MinIO
            $templateProcessor->saveAs($tempDocxPath);
            $modifiedContent = file_get_contents($tempDocxPath);
            Storage::disk('private')->put($targetPath, $modifiedContent);

            $typeName = $type === 'approval-sheet' ? 'lembar pengesahan' : 'executive summary';
            DocumentLog::create([
                'document_id' => $document->id,
                'user_id' => $user->id,
                'action' => 'UPDATED',
                'note' => "Tanda tangan dibubuhkan pada {$typeName} oleh {$user->name}",
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        } finally {
            if ($tempDocxPath && file_exists($tempDocxPath)) {
                @unlink($tempDocxPath);
            }
            if ($tempSignaturePath && file_exists($tempSignaturePath)) {
                @unlink($tempSignaturePath);
            }
        }
    }

    private function getSignaturePlaceholders(string $userRole, string $userUnitCategory): array
    {
        if ($userUnitCategory === 'SENAT' && ($userRole === 'ketua-ormawa' || $userRole === 'senat')) {
            return ['ttd_ketua_senat', 'signature_ketua_senat', 'ttd_ketuasenat'];
        }

        switch ($userRole) {
            case 'sekretaris':
                return ['ttd_sekretaris', 'signature_sekretaris'];
            case 'ketua-ormawa':
                return ['ttd_ketua', 'signature_ketua', 'ttd_ketuaormawa'];
            case 'pembina':
                return ['ttd_pembina', 'signature_pembina'];
            case 'kemahasiswaan':
                return ['ttd_kemahasiswaan', 'signature_kemahasiswaan'];
            case 'wd3':
            case 'wakil-dekan':
                return ['ttd_wd3', 'signature_wd3', 'ttd_wakildekan'];
            case 'dekan':
                return ['ttd_dekan', 'signature_dekan'];
            default:
                return ['signature_approver_1', 'signature_approver_2', 'signature_approver_3'];
        }
    }

    public function updateDocument(Document $document, User $user, Request $request): Document
    {
        $validated = $request->validate([
            'title'             => 'sometimes|string|max:255',
            'content'           => 'nullable|array',
            'meta_data'         => 'nullable|array',
            'executive_summary' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
            'approval_sheet'    => 'nullable|file|mimes:pdf,jpg,png|max:5120',
            'proposal'          => 'nullable|file|mimes:pdf|max:20480',
        ]);

        $content = $request->input('content', []);

        if (isset($content['ketua_pelaksana_nim']) && !empty($content['ketua_pelaksana_nim'])) {
            if (!preg_match('/^\d{14}$/', $content['ketua_pelaksana_nim'])) {
                throw new \Exception('NIM harus 14 digit angka', 422);
            }
        }

        if (isset($content['ketua_pelaksana_hp']) && !empty($content['ketua_pelaksana_hp'])) {
            if (!preg_match('/^\d{12,13}$/', $content['ketua_pelaksana_hp'])) {
                throw new \Exception('Nomor HP harus 12-13 digit angka', 422);
            }
        }

        $dataToUpdate = [
            'title'     => $validated['title'] ?? $document->title,
            'content'   => array_merge($document->content ?? [], $content),
            'meta_data' => array_merge($document->meta_data ?? [], $validated['meta_data'] ?? []),
        ];

        // Update files
        if ($request->hasFile('executive_summary')) {
            $this->deleteOldFile($document->file_executive_summary);
            $dataToUpdate['file_executive_summary'] = $request->file('executive_summary')->store('documents/executive_summaries', 'private');
        }

        if ($request->hasFile('approval_sheet')) {
            $this->deleteOldFile($document->file_approval_sheet);
            $dataToUpdate['file_approval_sheet'] = $request->file('approval_sheet')->store('documents/approval_sheets', 'private');
        }

        if ($request->hasFile('proposal')) {
            $this->deleteOldFile($document->file_proposal);
            $dataToUpdate['file_proposal'] = $request->file('proposal')->store('documents/proposals', 'private');
        }

        $document->update($dataToUpdate);

        // Auto-regenerate if content changed
        if ($request->has('content')) {
            $this->autoRegenerateDocuments($document);
        }

        DocumentLog::create([
            'document_id' => $document->id,
            'user_id'     => $user->id,
            'action'      => 'UPDATED',
            'note'        => 'Dokumen diperbarui dan file di-generate ulang',
        ]);

        return $document->fresh(['creator']);
    }

    private function autoRegenerateDocuments(Document $document): void
    {
        try {
            $document->refresh();

            try {
                $newPathExec = $this->documentGenerationService->generateFromTemplate($document, 'executive_summary');
                $document->update(['file_executive_summary' => $newPathExec]);
            } catch (\Exception $ex) {
                \Log::warning('Skip auto-gen Executive Summary: ' . $ex->getMessage());
            }

            try {
                $orgType = $this->mapCategoryToOrganizationType($document->unit->category ?? 'HMD');
                $newPathApp = $this->documentGenerationService->generateFromTemplate($document, 'lembar_pengesahan', $orgType);
                $document->update(['file_approval_sheet' => $newPathApp]);
            } catch (\Exception $ex) {
                \Log::warning('Skip auto-gen Approval Sheet: ' . $ex->getMessage());
            }

            \Log::info('Documents auto-regenerated after update', ['document_id' => $document->id]);
        } catch (\Exception $e) {
            \Log::error('Auto-regeneration failed in update', [
                'document_id' => $document->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function checkDocumentAccess(Document $document, User $user): bool
    {
        $isAdmin = $user->role->slug === 'admin';
        $isCreator = $document->creator_id === $user->id;
        $isCurrentHolder = $document->current_holder_id === $user->id;

        $hasProcessed = DocumentLog::where('document_id', $document->id)
            ->where('user_id', $user->id)
            ->whereIn('action', ['APPROVED', 'REJECTED', 'SUBMITTED', 'REVISED', 'RETURNED'])
            ->exists();

        return $isAdmin || $isCreator || $isCurrentHolder || $hasProcessed;
    }

    public function serveFile(Document $document, string $type): array
    {
        $map = [
            'proposal' => 'file_proposal',
            'executive-summary' => 'file_executive_summary',
            'approval-sheet' => 'file_approval_sheet',
        ];

        if (!isset($map[$type])) {
            throw new \Exception('Invalid file type', 400);
        }

        $col = $map[$type];
        $path = $document->{$col};

        if (!$path) {
            throw new \Exception('File not available', 404);
        }

        if (!Storage::disk('private')->exists($path)) {
            throw new \Exception('File not found on disk', 404);
        }

        return [
            'content'     => Storage::disk('private')->get($path),
            'mimeType'    => Storage::disk('private')->mimeType($path),
            'fileName'    => basename($path),
            'path'        => $path,
            'etag'        => md5($path . $document->updated_at),
        ];
    }

    public function generateFromTemplate(Document $document, string $type): string
    {
        $orgType = $this->mapCategoryToOrganizationType($document->unit->category ?? 'HMD');

        return $this->documentGenerationService->generateFromTemplate($document, $type, $orgType);
    }

    public function convertDocxToPdf(string $docxPath, int $documentId, string $type): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        \Log::info('[PDF Conversion] Starting conversion', [
            'document_id' => $documentId,
            'type' => $type,
            'docx_path' => $docxPath,
            'docx_exists' => file_exists($docxPath)
        ]);

        $pdfPath = storage_path('app/temp/doc_' . $documentId . '_' . $type . '_' . time() . '.pdf');

        if (!file_exists(dirname($pdfPath))) {
            mkdir(dirname($pdfPath), 0755, true);
        }

        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $sofficeCommand = $this->findLibreOffice($isWindows);

        if (!$sofficeCommand) {
            \Log::error('[PDF Conversion] LibreOffice not found');
            throw new \Exception('LibreOffice not found');
        }

        \Log::info('[PDF Conversion] LibreOffice found', ['command' => $sofficeCommand]);

        $outputDir = dirname($pdfPath);

        if ($isWindows) {
            $docxPath = str_replace('/', '\\', $docxPath);
            $outputDir = str_replace('/', '\\', $outputDir);
        }

        $command = '"' . $sofficeCommand . '"' .
                  ' --headless' .
                  ' --convert-to pdf:writer_pdf_Export' .
                  ' --outdir "' . $outputDir . '"' .
                  ' "' . $docxPath . '"';

        \Log::info('[PDF Conversion] Executing command', ['command' => $command]);

        if ($isWindows) {
            $fullCommand = 'cmd /c "' . $command . '"';
            exec($fullCommand . ' 2>&1', $execOutput, $execReturn);
        } else {
            exec($command . ' 2>&1', $execOutput, $execReturn);
        }

        \Log::info('[PDF Conversion] Command executed', [
            'return_code' => $execReturn,
            'output' => $execOutput
        ]);

        $baseFilename = pathinfo($docxPath, PATHINFO_FILENAME);
        $tempPdfPath = $outputDir . DIRECTORY_SEPARATOR . $baseFilename . '.pdf';

        sleep(1);

        if (!file_exists($tempPdfPath)) {
            \Log::error('[PDF Conversion] PDF file not created', [
                'expected_path' => $pdfPath,
                'temp_path' => $tempPdfPath,
                'temp_exists' => file_exists($tempPdfPath)
            ]);
            throw new \Exception('PDF conversion failed');
        }

        if ($tempPdfPath !== $pdfPath) {
            if (file_exists($pdfPath)) {
                unlink($pdfPath);
            }
            rename($tempPdfPath, $pdfPath);
        }

        \Log::info('[PDF Conversion] Success', [
            'pdf_path' => $pdfPath,
            'file_size' => filesize($pdfPath)
        ]);

        if (!file_exists($pdfPath)) {
            throw new \Exception('PDF conversion failed');
        }

        return response()->file($pdfPath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . pathinfo($docxPath, PATHINFO_FILENAME) . '.pdf"',
        ])->deleteFileAfterSend(true);
    }

    private function findLibreOffice(bool $isWindows): ?string
    {
        if ($isWindows) {
            $possiblePaths = [
                base_path('libreoffice-portable/App/libreoffice/program/soffice.exe'),
                base_path('LibreOfficePortable/App/libreoffice/program/soffice.exe'),
                'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
                'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
                getenv('ProgramFiles') . '\\LibreOffice\\program\\soffice.exe',
                getenv('ProgramFiles(x86)') . '\\LibreOffice\\program\\soffice.exe',
            ];

            foreach ($possiblePaths as $path) {
                if (file_exists($path)) return $path;
            }

            exec('where soffice 2>NUL', $output, $returnCode);
            if ($returnCode === 0 && !empty($output)) return trim($output[0]);
        } else {
            exec('which libreoffice 2>/dev/null', $output, $returnCode);
            if ($returnCode === 0 && !empty($output)) return 'libreoffice';

            exec('which soffice 2>/dev/null', $output2, $returnCode2);
            if ($returnCode2 === 0 && !empty($output2)) return 'soffice';
        }

        return null;
    }

    private function deleteOldFile($path): void
    {
        if (!$path) return;

        if (Storage::disk('private')->exists($path)) {
            Storage::disk('private')->delete($path);
        }
    }

    public function mapCategoryToOrganizationType($category): string
    {
        $mapping = [
            'HMD' => 'hmd',
            'BEM' => 'bem_ukm',
            'SENAT' => 'senat',
            'Senat' => 'senat',
            'UKM' => 'bem_ukm',
        ];

        return $mapping[$category] ?? 'hmd';
    }
}
