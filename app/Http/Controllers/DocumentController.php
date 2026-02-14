<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentLog;
use App\Models\Sign;
use App\Services\WorkflowEngine;
use App\Services\DocumentGenerationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\TemplateProcessor;
use App\Models\RoomBooking;

class DocumentController extends Controller
{
    /**
     * Helper: apply search filter for 'q' param
     */
    protected function applySearchFilter($query, Request $request)
    {
        if (!$request->filled('q')) {
            return;
        }

        $search = trim($request->q);

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
    protected $workflowEngine;
    protected $documentGenerationService;

    public function __construct(WorkflowEngine $workflowEngine, DocumentGenerationService $documentGenerationService)
    {
        $this->workflowEngine = $workflowEngine;
        $this->documentGenerationService = $documentGenerationService;
    }

    /**
     * Daftar dokumen untuk user yang login
     * - Dokumen yang dibuat user
     * - Dokumen yang sedang dipegang user (perlu action)
     * - Dokumen yang sudah diproses user (history)
     * - Admin bisa melihat semua dokumen
     *
     * Query params:
     * - status: Filter berdasarkan status (DRAFT/IN_PROGRESS/APPROVED/REJECTED/REVISED)
     * - workflow_id: Filter berdasarkan workflow
     * - unit_id: Filter berdasarkan unit (khusus admin)
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $isAdmin = $user->role->slug === 'admin';

        // Jika Admin, return semua dokumen
        if ($isAdmin) {
            $query = Document::with(['workflow', 'currentHolder', 'creator', 'unit', 'logs']);

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by workflow
            if ($request->has('workflow_id')) {
                $query->where('workflow_id', $request->workflow_id);
            }

            // Filter by unit
            if ($request->has('unit_id')) {
                $query->where('unit_id', $request->unit_id);
            }

            // Tambahkan search filter
            $this->applySearchFilter($query, $request);

            $allDocuments = $query->latest()->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'all_documents' => $allDocuments,
                ]
            ]);
        }

        // Untuk user biasa
        // Dokumen yang dibuat user
        $myDocumentsQuery = Document::with(['workflow', 'currentHolder.role', 'creator', 'unit', 'logs.user.unit'])
            ->where('creator_id', $user->id);

        // Filter by status untuk my_documents
        if ($request->has('status')) {
            $myDocumentsQuery->where('status', $request->status);
        }

        // Filter by workflow
        if ($request->has('workflow_id')) {
            $myDocumentsQuery->where('workflow_id', $request->workflow_id);
        }

        $this->applySearchFilter($myDocumentsQuery, $request);
        $myDocuments = $myDocumentsQuery->latest()->get();

        // Transform documents to include currentHolder with role
        $myDocuments = $myDocuments->map(function ($doc) {
            $docArray = $doc->toArray();
            if ($doc->currentHolder) {
                $docArray['currentHolder'] = [
                    'id' => $doc->currentHolder->id,
                    'name' => $doc->currentHolder->name,
                    'email' => $doc->currentHolder->email,
                    'role' => $doc->currentHolder->role ? [
                        'id' => $doc->currentHolder->role->id,
                        'name' => $doc->currentHolder->role->name,
                    ] : null,
                ];
            }
            return $docArray;
        });

        // Dokumen yang sedang menunggu action dari user ini
        $pendingDocumentsQuery = Document::with(['workflow', 'creator', 'unit'])
            ->where('current_holder_id', $user->id)
            ->whereIn('status', ['IN_PROGRESS', 'REVISION']);

        // Filter by workflow untuk pending
        if ($request->has('workflow_id')) {
            $pendingDocumentsQuery->where('workflow_id', $request->workflow_id);
        }

        $this->applySearchFilter($pendingDocumentsQuery, $request);
        $pendingDocuments = $pendingDocumentsQuery->latest()->get();

        // Dokumen yang sudah diproses oleh user ini (approved/rejected/returned)
        // Ambil document_id dari logs dimana user ini melakukan action
        $processedDocumentIds = DocumentLog::where('user_id', $user->id)
            ->whereIn('action', ['APPROVED', 'REJECTED', 'RETURNED'])
            ->pluck('document_id')
            ->unique();

        $processedDocumentsQuery = Document::with(['workflow', 'currentHolder', 'creator', 'unit'])
            ->whereIn('id', $processedDocumentIds);

        // Filter by status untuk processed
        if ($request->has('status')) {
            $processedDocumentsQuery->where('status', $request->status);
        }

        // Filter by workflow
        if ($request->has('workflow_id')) {
            $processedDocumentsQuery->where('workflow_id', $request->workflow_id);
        }

        $this->applySearchFilter($processedDocumentsQuery, $request);
        $processedDocuments = $processedDocumentsQuery->latest()->get();

        return response()->json([
            'success' => true,
            'data' => [
                'my_documents' => $myDocuments,
                'pending_documents' => $pendingDocuments,
                'processed_documents' => $processedDocuments,
            ]
        ]);
    }

    /**
     * Detail dokumen beserta log history
     * Hanya bisa diakses oleh:
     * - Creator dokumen
     * - Current holder
     * - User yang pernah memproses dokumen (ada di logs)
     * - Admin
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $document = Document::with([
            'workflow.steps',
            'currentHolder.role',
            'currentHolder.unit',
            'creator.role',
            'unit',
            'logs.user.role'
        ])->findOrFail($id);

        // Cek apakah user adalah admin
        $isAdmin = $user->role->slug === 'admin';

        // Cek apakah user adalah creator
        $isCreator = $document->creator_id === $user->id;

        // Cek apakah user adalah current holder
        $isCurrentHolder = $document->current_holder_id === $user->id;

        // Cek apakah user pernah memproses dokumen ini (ada di logs)
        $hasProcessed = DocumentLog::where('document_id', $document->id)
            ->where('user_id', $user->id)
            ->whereIn('action', ['APPROVED', 'REJECTED', 'SUBMITTED', 'REVISED'])
            ->exists();

        // Validasi akses
        if (!$isAdmin && !$isCreator && !$isCurrentHolder && !$hasProcessed) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk melihat dokumen ini'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $document
        ]);
    }

/**
     * Buat dokumen baru (DRAFT)
     * DISESUAIKAN: Menangani 3 jenis lampiran terpisah
     */
    public function store(Request $request)
    {
        \Log::info('[DocumentController] store() called', [
            'has_content' => $request->has('content'),
            'content_value' => $request->input('content'),
            'all_input' => $request->all(),
        ]);

        $validated = $request->validate([
            'workflow_id' => 'required|exists:workflows,id',
            'title'       => 'required|string|max:255',
            'content'     => 'nullable|array',
            'meta_data'   => 'nullable|array',

            // --- VALIDASI 3 FILE BERBEDA ---
            // Executive Summary (Boleh PDF/Word, max 10MB)
            'executive_summary' => 'nullable|file|mimes:pdf,doc,docx|max:10240',

            // Lembar Pengesahan (Biasanya wajib PDF/Image scan, max 5MB)
            'approval_sheet'    => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',

            // Proposal (Wajib PDF agar tidak berantakan saat preview, max 20MB)
            'proposal'          => 'nullable|file|mimes:pdf|max:20480',
        ]);

        // Validasi manual untuk specific content fields (opsional, tidak membuang field lain)
        $content = $request->input('content', []);
        if (isset($content['ketua_pelaksana_nim']) && !empty($content['ketua_pelaksana_nim'])) {
            if (!preg_match('/^\d{14}$/', $content['ketua_pelaksana_nim'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'NIM harus 14 digit angka',
                    'errors' => ['content.ketua_pelaksana_nim' => ['NIM harus 14 digit angka']],
                ], 422);
            }
        }
        if (isset($content['ketua_pelaksana_hp']) && !empty($content['ketua_pelaksana_hp'])) {
            if (!preg_match('/^\d{12,13}$/', $content['ketua_pelaksana_hp'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'HP harus 12-13 digit angka',
                    'errors' => ['content.ketua_pelaksana_hp' => ['HP harus 12-13 digit angka']],
                ], 422);
            }
        }

        // Use the raw content from request (includes ALL fields)
        $validated['content'] = $content;

        \Log::info('[DocumentController] Validated data', [
            'content' => $validated['content'] ?? null,
            'content_keys' => array_keys($validated['content'] ?? []),
        ]);

        $user = $request->user();

        // ============================================
        // PREVENT DUPLICATE RESERVATIONS
        // ============================================
        // Check if user already has a DRAFT document with same reservation data
        $metaData = $validated['meta_data'] ?? [];
        if (isset($metaData['step']) && $metaData['step'] === 'reservation') {
            $existingDoc = Document::where('creator_id', $user->id)
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

            if ($existingDoc) {
                \Log::info('[DocumentController] Duplicate reservation detected, returning existing document', [
                    'existing_doc_id' => $existingDoc->id,
                    'room_id' => $content['room_id'] ?? null,
                    'booking_date' => $content['booking_date'] ?? null,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Reservasi sudah ada. Menggunakan dokumen yang sudah ada.',
                    'data'    => $existingDoc->load('creator:id,name,email')
                ], 200);
            }
        }


        $document = DB::transaction(function () use ($request, $validated, $user) {
            // 1. Handle Executive Summary (PRIVATE storage for security)
            $pathExecutive = null;
            if ($request->hasFile('executive_summary')) {
                $file = $request->file('executive_summary');
                $pathExecutive = $file->store('documents/executive_summaries', 'private');
            }

            // 2. Handle Approval Sheet (PRIVATE storage for security)
            $pathApproval = null;
            if ($request->hasFile('approval_sheet')) {
                $file = $request->file('approval_sheet');
                $pathApproval = $file->store('documents/approval_sheets', 'private');
            }

            // 3. Handle Proposal (PRIVATE storage for security)
            $pathProposal = null;
            if ($request->hasFile('proposal')) {
                $file = $request->file('proposal');
                $pathProposal = $file->store('documents/proposals', 'private');
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

        if (!$document) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat dokumen',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Dokumen berhasil dibuat',
            'data'    => $document->load('creator:id,name,email')
        ], 201);
    }

    /**
     * Submit dokumen (mulai workflow)
     */
    public function submit(Request $request, $id)
    {
        $document = Document::findOrFail($id);
        $user = $request->user();

        // Validasi: Hanya pembuat yang bisa submit
        if ($document->creator_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk submit dokumen ini'
            ], 403);
        }

        // Validasi: Dokumen harus dalam status DRAFT atau REVISION (untuk resubmit setelah revisi)
        if (!in_array($document->status, ['DRAFT', 'REVISION'])) {
            return response()->json([
                'success' => false,
                'message' => 'Dokumen dalam status ' . $document->status . ' tidak dapat diajukan ulang'
            ], 400);
        }

        DB::transaction(function () use ($document, $user) {
            // AUTO APPROVAL untuk Admin dan Sumber Daya
            $userRole = $user->role->slug ?? '';
            if (in_array($userRole, ['admin', 'sumber-daya'])) {
                $document->update([
                    'status' => 'APPROVED',
                    'current_step_order' => 999, // End of workflow
                    'current_holder_id' => null,
                ]);

                DocumentLog::create([
                    'document_id' => $document->id,
                    'user_id' => $user->id,
                    'action' => 'APPROVED',
                    'note' => 'Dokumen disetujui secara otomatis (Manual Booking)',
                    'step_snapshot' => 999,
                ]);

                // Create RoomBooking record
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

            // Cari langkah pertama workflow
            $firstStep = $document->workflow->steps()->where('step_order', 1)->first();

            if (!$firstStep) {
                throw new \Exception('Workflow tidak memiliki langkah');
            }

            // Cari approver pertama
            $firstApprover = $this->workflowEngine->findApprover($document, $firstStep);

            if (!$firstApprover) {
                // FALLBACK: Jika tidak ada users dengan role yang sesuai di unit yang sesuai
                // Kita coba cari user dengan role ketua-ormawa di unit pembuat dokumen
                // ATAU throw error yang lebih deskriptif
                $roleName = $firstStep->target_role_slug;
                $unitName = $document->unit->name ?? 'Unknown Unit';
                
                throw new \Exception("Tidak dapat menemukan approver untuk langkah '{$firstStep->step_name}'. Diperlukan user dengan role '{$roleName}' di unit '{$unitName}'. Silakan hubungi admin untuk menambahkan user dengan role tersebut.");
            }

            // Update dokumen
            $document->update([
                'status' => 'IN_PROGRESS',
                'current_step_order' => 1,
                'current_holder_id' => $firstApprover->id,
            ]);

            // Log submit dengan note yang berbeda untuk resubmit
            $logNote = $document->status === 'REVISION'
                ? 'Dokumen diajukan ulang setelah revisi'
                : 'Dokumen diajukan untuk diproses';

            DocumentLog::create([
                'document_id' => $document->id,
                'user_id' => $user->id,
                'action' => 'SUBMITTED',
                'note' => $logNote,
                'step_snapshot' => 0,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => ($document->fresh()->status === 'APPROVED') 
                ? 'Dokumen berhasil disubmit dan disetujui secara otomatis'
                : ($document->status === 'REVISION'
                    ? 'Dokumen berhasil diajukan ulang setelah revisi dan diteruskan ke approver pertama'
                    : 'Dokumen berhasil disubmit dan diteruskan ke approver pertama'),
            'data' => $document->fresh(['currentHolder', 'creator', 'logs'])
        ]);
    }

    /**
     * Approve dokumen (teruskan ke langkah berikutnya)
     */
    public function approve(Request $request, $id)
    {
        $validated = $request->validate([
            'note' => 'nullable|string',
        ]);

        $document = Document::with(['unit', 'workflow'])->findOrFail($id);
        $user = $request->user();

        // Validasi: Hanya current holder yang bisa approve
        if ($document->current_holder_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk approve dokumen ini'
            ], 403);
        }

        $signaturePath = null;
        $userRoleSlug = $user->role->slug ?? '';
        $rolesWithoutSignature = ['sumber-daya', 'kemahasiswaan'];

        if (!in_array($userRoleSlug, $rolesWithoutSignature)) {
            // Signature required for other roles
            $sign = Sign::where('user_id', $user->id)->latest()->first();
            if (!$sign || !$sign->signature) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda harus mengupload tanda tangan terlebih dahulu sebelum approve dokumen'
                ], 400);
            }
            $signaturePath = $sign->signature; // Already stored in database
        }

        try {
            $result = $this->workflowEngine->approveDocument(
                $document,
                $user,
                $validated['note'] ?? null,
                $signaturePath // Pass file path from database
            );

            return response()->json([
                'success' => true,
                'message' => $result,
                'data' => $document->fresh(['currentHolder', 'creator', 'logs'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Kembalikan dokumen untuk revisi
     */
    public function revise(Request $request, $id)
    {
        $validated = $request->validate([
            'target_user_id' => 'required|exists:users,id',
            'note' => 'required|string',
        ]);

        $document = Document::findOrFail($id);
        $user = $request->user();

        // Validasi: Hanya current holder yang bisa return
        if ($document->current_holder_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk mengembalikan dokumen ini'
            ], 403);
        }

        try {
            $result = $this->workflowEngine->reviseDocument(
                $document,
                $user,
                $validated['target_user_id'],
                $validated['note']
            );

            return response()->json([
                'success' => true,
                'message' => $result,
                'data' => $document->fresh(['currentHolder', 'creator', 'logs'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bubuhkan tanda tangan ke dokumen tanpa approve
     * Endpoint ini hanya menambahkan signature ke approval sheet
     * tanpa mengubah workflow status dokumen
     */
    public function applySignature(Request $request, $id)
    {
        $document = Document::findOrFail($id);
        $user = $request->user();

        // Validasi: Hanya current holder yang bisa bubuhkan tanda tangan
        if ($document->current_holder_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk membubuhkan tanda tangan pada dokumen ini'
            ], 403);
        }

        // Validasi: Dokumen harus memiliki approval sheet
        if (!$document->file_approval_sheet) {
            return response()->json([
                'success' => false,
                'message' => 'Dokumen belum memiliki lembar pengesahan'
            ], 400);
        }

        // Validasi: File harus berformat DOCX
        $fileExtension = pathinfo($document->file_approval_sheet, PATHINFO_EXTENSION);
        if (strtolower($fileExtension) !== 'docx') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya file DOCX yang dapat dibubuhkan tanda tangan'
            ], 400);
        }

        // Ambil signature user (ambil yang paling baru berdasarkan updated_at)
        $signature = Sign::where('user_id', $user->id)
            ->latest('updated_at')
            ->first();

        if (!$signature) {
            return response()->json([
                'success' => false,
                'message' => 'Anda belum memiliki tanda tangan. Silakan upload tanda tangan terlebih dahulu.'
            ], 400);
        }

        // Force refresh dari database untuk ensure data terbaru
        $signature->refresh();

        // Validasi: Signature path harus ada
        if (!$signature->signature) {
            return response()->json([
                'success' => false,
                'message' => 'File tanda tangan tidak ditemukan. Silakan upload ulang tanda tangan Anda.'
            ], 400);
        }

        // Validasi: File signature harus ada di storage
        if (!Storage::disk('private')->exists($signature->signature)) {
            return response()->json([
                'success' => false,
                'message' => 'File tanda tangan hilang dari storage. Silakan upload ulang tanda tangan Anda.'
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Log untuk debugging
            \Log::info('ApplySignature: Starting', [
                'document_id' => $document->id,
                'user_id' => $user->id,
                'signature_id' => $signature->id,
                'signature_path' => $signature->signature,
                'signature_updated_at' => $signature->updated_at,
            ]);

            // Regenerate approval sheet from template to ensure fresh placeholders
            // This is necessary because if signature was already embedded before,
            // the placeholder ${ttd_xxx} no longer exists (it's now an image)
            \Log::info('ApplySignature: Regenerating approval sheet from template');

            $newFilePath = $this->documentGenerationService->generateFromTemplate(
                $document,
                'lembar_pengesahan',
                null
            );

            // Update document with new approval sheet path
            $document->update([
                'file_approval_sheet' => $newFilePath
            ]);

            // Refresh document to get updated file path
            $document->refresh();
            $approvalSheetPath = $document->file_approval_sheet;

            \Log::info('ApplySignature: Approval sheet regenerated', [
                'new_path' => $approvalSheetPath,
            ]);

            // Now manually add current user's signature since they haven't approved yet
            // (insertSignatures only adds signatures from APPROVED logs)

            // Download regenerated approval sheet from MinIO ke temporary file
            $approvalSheetPath = $document->file_approval_sheet;
            $tempDocxPath = sys_get_temp_dir() . '/approval_sheet_' . uniqid() . '.docx';

            $docxContent = Storage::disk('private')->get($approvalSheetPath);
            file_put_contents($tempDocxPath, $docxContent);

            // Download signature dari MinIO ke temporary file
            $tempSignaturePath = sys_get_temp_dir() . '/signature_' . uniqid() . '.' . pathinfo($signature->signature, PATHINFO_EXTENSION);
            $signatureContent = Storage::disk('private')->get($signature->signature);
            file_put_contents($tempSignaturePath, $signatureContent);

            \Log::info('ApplySignature: Files downloaded', [
                'temp_docx' => $tempDocxPath,
                'temp_signature' => $tempSignaturePath,
                'signature_file_size' => strlen($signatureContent),
            ]);

            // Load template processor
            $templateProcessor = new TemplateProcessor($tempDocxPath);

            // Tentukan placeholder berdasarkan role user
            $userRole = $user->role->slug ?? '';
            $placeholders = [];

            switch ($userRole) {
                case 'ketua-ormawa':
                    $placeholders = ['ttd_ketua_ormawa', 'signature_ketua_ormawa'];
                    break;
                case 'dosen-pendamping':
                    $placeholders = ['ttd_dosen_pendamping', 'signature_dosen_pendamping'];
                    break;
                case 'ketua-departemen':
                    $placeholders = ['ttd_ketua_departemen', 'signature_ketua_departemen'];
                    break;
                case 'wadek1':
                    $placeholders = ['ttd_wadek1', 'signature_wadek1'];
                    break;
                case 'kemahasiswaan':
                    $placeholders = ['ttd_kemahasiswaan', 'signature_kemahasiswaan'];
                    break;
                case 'sumber-daya':
                    $placeholders = ['ttd_sumber_daya', 'signature_sumber_daya'];
                    break;
                case 'senat':
                    $placeholders = ['ttd_ketua_senat', 'signature_ketua_senat'];
                    break;
                default:
                    // Generic approver placeholders
                    $placeholders = ['signature_approver_1', 'signature_approver_2', 'signature_approver_3'];
                    break;
            }

            // Insert signature ke semua placeholder yang relevan
            \Log::info('ApplySignature: Attempting to insert signature', [
                'user_role' => $userRole,
                'placeholders' => $placeholders,
            ]);

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
                    // Placeholder tidak ditemukan di template, lanjut ke placeholder berikutnya
                    \Log::warning('ApplySignature: Placeholder not found', [
                        'placeholder' => $placeholder,
                        'error' => $e->getMessage(),
                    ]);
                    continue;
                }
            }

            if (!$inserted) {
                \Log::error('ApplySignature: No placeholders found in template', [
                    'tried_placeholders' => $placeholders,
                ]);
                throw new \Exception('Placeholder tanda tangan untuk role Anda tidak ditemukan di template');
            }

            \Log::info('ApplySignature: Signature inserted successfully', [
                'inserted_at' => $insertedPlaceholders,
            ]);

            // Save modified document
            $templateProcessor->saveAs($tempDocxPath);

            // Upload kembali ke MinIO (overwrite file lama)
            $modifiedContent = file_get_contents($tempDocxPath);
            $modifiedSize = strlen($modifiedContent);

            \Log::info('ApplySignature: Uploading modified document', [
                'path' => $approvalSheetPath,
                'size' => $modifiedSize,
            ]);

            Storage::disk('private')->put($approvalSheetPath, $modifiedContent);

            \Log::info('ApplySignature: Document uploaded successfully');

            // Log action
            DocumentLog::create([
                'document_id' => $document->id,
                'user_id' => $user->id,
                'action' => 'UPDATED',
                'note' => "Tanda tangan dibubuhkan oleh {$user->name}",
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tanda tangan berhasil dibubuhkan pada dokumen',
                'data' => $document->fresh(['currentHolder', 'creator', 'logs'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal membubuhkan tanda tangan: ' . $e->getMessage()
            ], 500);
        } finally {
            // Cleanup temporary files
            if (isset($tempDocxPath) && file_exists($tempDocxPath)) {
                @unlink($tempDocxPath);
            }
            if (isset($tempSignaturePath) && file_exists($tempSignaturePath)) {
                @unlink($tempSignaturePath);
            }
        }
    }

    /**
     * Update dokumen yang sedang revisi
     */
    public function update(Request $request, $id)
    {
        $document = Document::findOrFail($id);
        $user = $request->user();

        // Validasi Akses
        if ($document->creator_id !== $user->id && $document->current_holder_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        // Validasi Status - support both REVISION dan REVISED untuk backward compatibility
        if (!in_array($document->status, ['DRAFT', 'REVISED', 'REVISION'])) {
            return response()->json(['success' => false, 'message' => 'Dokumen sudah dikunci'], 400);
        }

        $validated = $request->validate([
            'title'             => 'sometimes|string|max:255',
            'content'           => 'nullable|array',
            'meta_data'         => 'nullable|array',

            // Validasi file update (semua nullable karena user mungkin cuma mau ganti judul)
            'executive_summary' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
            'approval_sheet'    => 'nullable|file|mimes:pdf,jpg,png|max:5120',
            'proposal'          => 'nullable|file|mimes:pdf|max:20480',
        ]);

        // Validasi manual untuk content fields (agar tidak membuang field lain)
        $content = $request->input('content', []);

        // Validasi NIM jika ada
        if (isset($content['ketua_pelaksana_nim']) && !empty($content['ketua_pelaksana_nim'])) {
            if (!preg_match('/^\d{14}$/', $content['ketua_pelaksana_nim'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'NIM harus 14 digit angka'
                ], 422);
            }
        }

        // Validasi HP jika ada
        if (isset($content['ketua_pelaksana_hp']) && !empty($content['ketua_pelaksana_hp'])) {
            if (!preg_match('/^\d{12,13}$/', $content['ketua_pelaksana_hp'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nomor HP harus 12-13 digit angka'
                ], 422);
            }
        }

        // Array untuk menampung data yang akan diupdate
        $dataToUpdate = [
            'title'     => $validated['title'] ?? $document->title,
            'content'   => array_merge($document->content ?? [], $content),
            'meta_data' => array_merge($document->meta_data ?? [], $validated['meta_data'] ?? []),
        ];

        // --- UPDATE FILE LOGIC ---

        // 1. Cek update Executive Summary
        if ($request->hasFile('executive_summary')) {
            // (Opsional) Hapus file lama jika ada
            $this->deleteOldFile($document->file_executive_summary);

            // Upload baru (PRIVATE storage for security)
            $dataToUpdate['file_executive_summary'] = $request->file('executive_summary')->store('documents/executive_summaries', 'private');
        }

        // 2. Cek update Approval Sheet
        if ($request->hasFile('approval_sheet')) {
            $this->deleteOldFile($document->file_approval_sheet);

            // Upload baru (PRIVATE storage for security)
            $dataToUpdate['file_approval_sheet'] = $request->file('approval_sheet')->store('documents/approval_sheets', 'private');
        }

        // 3. Cek update Proposal
        if ($request->hasFile('proposal')) {
            $this->deleteOldFile($document->file_proposal);

            // Upload baru (PRIVATE storage for security)
            $dataToUpdate['file_proposal'] = $request->file('proposal')->store('documents/proposals', 'private');
        }

        $document->update($dataToUpdate);

        DocumentLog::create([
            'document_id' => $document->id,
            'user_id'     => $user->id,
            'action'      => 'UPDATED',
            'note'        => 'Dokumen dan lampiran diperbarui',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Dokumen berhasil diupdate',
            'data'    => $document->fresh(['creator'])
        ]);
    }

    /**
     * Serve stored file for a document (proposal / executive_summary / approval_sheet)
     * Accessible only for users who can view the document (creator/current holder/processed/admin)
     * URL: GET /documents/{id}/file/{type}
     */
    public function file(Request $request, $id, $type)
    {
        $user = $request->user();
        $document = Document::findOrFail($id);

        // Reuse access checks from show()
        $isAdmin = $user->role->slug === 'admin';
        $isCreator = $document->creator_id === $user->id;
        $isCurrentHolder = $document->current_holder_id === $user->id;

        $hasProcessed = DocumentLog::where('document_id', $document->id)
            ->where('user_id', $user->id)
            ->whereIn('action', ['APPROVED', 'REJECTED', 'SUBMITTED', 'REVISED', 'RETURNED'])
            ->exists();

        if (!$isAdmin && !$isCreator && !$isCurrentHolder && !$hasProcessed) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk melihat dokumen ini'
            ], 403);
        }

        // Map type to column name
        $map = [
            'proposal' => 'file_proposal',
            'executive-summary' => 'file_executive_summary',
            'approval-sheet' => 'file_approval_sheet',
        ];

        if (!isset($map[$type])) {
            return response()->json(['success' => false, 'message' => 'Invalid file type'], 400);
        }

        $col = $map[$type];
        $path = $document->{$col};
        if (!$path) {
            return response()->json(['success' => false, 'message' => 'File not available'], 404);
        }

        // Read from private disk (MinIO)
        if (!Storage::disk('private')->exists($path)) {
            return response()->json(['success' => false, 'message' => 'File not found on disk'], 404);
        }

        // Get file content and mime type from MinIO
        $fileContent = Storage::disk('private')->get($path);
        $mimeType = Storage::disk('private')->mimeType($path);
        $fileName = basename($path);

        return response($fileContent, 200, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="' . $fileName . '"',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * Convert DOCX to PDF and serve
     * URL: GET /documents/{id}/file/{type}/pdf
     */
    public function filePdf(Request $request, $id, $type)
    {
        $user = $request->user();
        $document = Document::findOrFail($id);

        // Reuse access checks from file()
        $isAdmin = $user->role->slug === 'admin';
        $isCreator = $document->creator_id === $user->id;
        $isCurrentHolder = $document->current_holder_id === $user->id;

        $hasProcessed = DocumentLog::where('document_id', $document->id)
            ->where('user_id', $user->id)
            ->whereIn('action', ['APPROVED', 'REJECTED', 'SUBMITTED', 'REVISED', 'RETURNED'])
            ->exists();

        if (!$isAdmin && !$isCreator && !$isCurrentHolder && !$hasProcessed) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk melihat dokumen ini'
            ], 403);
        }

        // Map type to column name
        $map = [
            'proposal' => 'file_proposal',
            'executive-summary' => 'file_executive_summary',
            'approval-sheet' => 'file_approval_sheet',
        ];

        if (!isset($map[$type])) {
            return response()->json(['success' => false, 'message' => 'Invalid file type'], 400);
        }

        $col = $map[$type];
        $path = $document->{$col};
        if (!$path) {
            return response()->json(['success' => false, 'message' => 'File not available'], 404);
        }

        // Read from private disk (MinIO)
        if (!Storage::disk('private')->exists($path)) {
            return response()->json(['success' => false, 'message' => 'File not found on disk'], 404);
        }

        $fileExtension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        // If already PDF, serve directly from MinIO
        if ($fileExtension === 'pdf') {
            $fileContent = Storage::disk('private')->get($path);
            return response($fileContent, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline',
                'Cache-Control' => 'public, max-age=3600',
            ]);
        }

        // Convert DOCX to PDF using LibreOffice
        if ($fileExtension === 'docx') {
            try {
                // Download DOCX from MinIO to temp location for conversion
                $tempDocxPath = storage_path('app/temp/docx_' . $document->id . '_' . $type . '_' . time() . '.docx');
                if (!file_exists(dirname($tempDocxPath))) {
                    mkdir(dirname($tempDocxPath), 0755, true);
                }
                file_put_contents($tempDocxPath, Storage::disk('private')->get($path));

                try {
                    return $this->convertDocxToPdf($tempDocxPath, $document->id, $type);
                } finally {
                    // Cleanup temp file
                    if (file_exists($tempDocxPath)) {
                        unlink($tempDocxPath);
                    }
                }
            } catch (\Exception $e) {
                \Log::error('PDF conversion failed, falling back to DOCX download', [
                    'error' => $e->getMessage()
                ]);

                // Fallback: download DOCX if conversion fails
                $fileContent = Storage::disk('private')->get($path);
                return response($fileContent, 200, [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'Content-Disposition' => 'attachment; filename="' . basename($path) . '"',
                ]);
            }
        }

        // Unsupported file type
        return response()->json([
            'success' => false,
            'message' => 'File type tidak didukung'
        ], 400);
    }

    /**
     * Convert DOCX to PDF using LibreOffice
     */
    private function convertDocxToPdf(string $docxPath, int $documentId, string $type): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        \Log::info('[PDF Conversion] Starting conversion', [
            'document_id' => $documentId,
            'type' => $type,
            'docx_path' => $docxPath,
            'docx_exists' => file_exists($docxPath)
        ]);

        $pdfPath = storage_path('app/temp/doc_' . $documentId . '_' . $type . '_' . time() . '.pdf');

        // Create temp directory if not exists
        if (!file_exists(dirname($pdfPath))) {
            mkdir(dirname($pdfPath), 0755, true);
        }

        // Detect OS for proper command
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

        // Find LibreOffice executable
        $sofficeCommand = null;

        if ($isWindows) {
            $possiblePaths = [
                // Local portable installation in project folder (HIGHEST PRIORITY)
                base_path('libreoffice-portable/App/libreoffice/program/soffice.exe'),
                base_path('LibreOfficePortable/App/libreoffice/program/soffice.exe'),
                // System-wide installations
                'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
                'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
                getenv('ProgramFiles') . '\\LibreOffice\\program\\soffice.exe',
                getenv('ProgramFiles(x86)') . '\\LibreOffice\\program\\soffice.exe',
            ];

            foreach ($possiblePaths as $path) {
                if (file_exists($path)) {
                    $sofficeCommand = $path;
                    break;
                }
            }

            if (!$sofficeCommand) {
                exec('where soffice 2>NUL', $output, $returnCode);
                if ($returnCode === 0 && !empty($output)) {
                    $sofficeCommand = trim($output[0]);
                }
            }
        } else {
            exec('which libreoffice 2>/dev/null', $output, $returnCode);
            if ($returnCode === 0 && !empty($output)) {
                $sofficeCommand = 'libreoffice';
            } else {
                exec('which soffice 2>/dev/null', $output2, $returnCode2);
                if ($returnCode2 === 0 && !empty($output2)) {
                    $sofficeCommand = 'soffice';
                }
            }
        }

        if (!$sofficeCommand) {
            \Log::error('[PDF Conversion] LibreOffice not found');
            throw new \Exception('LibreOffice not found');
        }

        \Log::info('[PDF Conversion] LibreOffice found', ['command' => $sofficeCommand]);

        // Build conversion command
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

        // Execute conversion
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

        // Check for generated PDF
        $baseFilename = pathinfo($docxPath, PATHINFO_FILENAME);
        $tempPdfPath = $outputDir . DIRECTORY_SEPARATOR . $baseFilename . '.pdf';

        sleep(1); // Wait for file write

        if (!file_exists($tempPdfPath)) {
            \Log::error('[PDF Conversion] PDF file not created', [
                'expected_path' => $pdfPath,
                'temp_path' => $tempPdfPath,
                'temp_exists' => file_exists($tempPdfPath)
            ]);
            throw new \Exception('PDF conversion failed');
        }

        // Move temp PDF to final location with unique name
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

        // Return PDF file
        return response()->file($pdfPath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . pathinfo($docxPath, PATHINFO_FILENAME) . '.pdf"',
        ]);
    }

    /**
     * Get LibreOffice executable path based on OS
     */
    private function getLibreOfficePath()
    {
        // Check for Windows
        $windowsPaths = [
            'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
            'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
        ];

        foreach ($windowsPaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        // Check for Linux/Mac (should be in PATH)
        $unixCommands = ['libreoffice', 'soffice'];
        foreach ($unixCommands as $cmd) {
            exec("which $cmd 2>/dev/null", $output, $returnCode);
            if ($returnCode === 0 && !empty($output[0])) {
                return $output[0];
            }
        }

        // Default fallback
        return 'soffice';
    }

    /**
     * Helper: Hapus file lama dari storage jika ada
     * Path is relative to storage/app (private disk)
     */
    private function deleteOldFile($path)
    {
        if (!$path) return;

        // Delete from private disk
        if (Storage::disk('private')->exists($path)) {
            Storage::disk('private')->delete($path);
        }
    }

    /**
     * Generate executive summary dari template
     * POST /api/documents/{id}/generate/executive-summary
     */
    public function generateExecutiveSummary(Request $request, $id)
    {
        $document = Document::findOrFail($id);
        $user = $request->user();

        // Authorization check
        if ($document->creator_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses'
            ], 403);
        }

        try {
            // Generate document
            $filePath = $this->documentGenerationService->generateFromTemplate(
                $document,
                'executive_summary',
                null
            );

            // Update document record
            $document->update([
                'file_executive_summary' => $filePath
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Executive summary berhasil digenerate',
                'data' => [
                    'file_path' => $filePath,
                    'download_url' => route('api.documents.file', ['id' => $id, 'type' => 'executive-summary'])
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal generate executive summary: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate lembar pengesahan dari template
     * POST /api/documents/{id}/generate/approval-sheet
     */
    public function generateApprovalSheet(Request $request, $id)
    {
        $document = Document::with('unit')->findOrFail($id);
        $user = $request->user();

        if ($document->creator_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses'
            ], 403);
        }

        try {
            // Generate document (no organization_type needed, use general template)
            $filePath = $this->documentGenerationService->generateFromTemplate(
                $document,
                'lembar_pengesahan',
                null  // No organization_type filter - use general template
            );

            // Update document record
            $document->update([
                'file_approval_sheet' => $filePath
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Lembar pengesahan berhasil digenerate',
                'data' => [
                    'file_path' => $filePath,
                    'download_url' => route('api.documents.file', ['id' => $id, 'type' => 'approval-sheet'])
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal generate lembar pengesahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Map unit category to organization type
     */
    protected function mapCategoryToOrganizationType($category): string
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
