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
use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Settings;

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
        $myDocumentsQuery = Document::with(['workflow', 'currentHolder', 'creator', 'unit'])
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

        // Dokumen yang sedang menunggu action dari user ini
        $pendingDocumentsQuery = Document::with(['workflow', 'creator', 'unit'])
            ->where('current_holder_id', $user->id)
            ->where('status', 'IN_PROGRESS');

        // Filter by workflow untuk pending
        if ($request->has('workflow_id')) {
            $pendingDocumentsQuery->where('workflow_id', $request->workflow_id);
        }

        $this->applySearchFilter($pendingDocumentsQuery, $request);
        $pendingDocuments = $pendingDocumentsQuery->latest()->get();

        // Dokumen yang sudah diproses oleh user ini (approved/rejected)
        // Ambil document_id dari logs dimana user ini melakukan action
        $processedDocumentIds = DocumentLog::where('user_id', $user->id)
            ->whereIn('action', ['APPROVED', 'REJECTED'])
            ->pluck('document_id')
            ->unique();

        $processedDocumentsQuery = Document::with(['workflow', 'currentHolder', 'creator', 'unit'])
            ->whereIn('id', $processedDocumentIds)
            ->where('creator_id', '!=', $user->id); // Hindari duplikasi dengan my_documents

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

            // --- VALIDASI CONTENT FIELDS ---
            'content.ketua_pelaksana_nim' => 'nullable|string|regex:/^\d{14}$/',
            'content.ketua_pelaksana_hp' => 'nullable|string|regex:/^\d{12,13}$/',
        ]);

        $user = $request->user();


        $document = DB::transaction(function () use ($request, $validated, $user) {
            // 1. Handle Executive Summary (PRIVATE storage for security)
            $pathExecutive = null;
            if ($request->hasFile('executive_summary')) {
                $pathExecutive = $request->file('executive_summary')->store('documents/executive_summaries', 'private');
            }

            // 2. Handle Approval Sheet (PRIVATE storage for security)
            $pathApproval = null;
            if ($request->hasFile('approval_sheet')) {
                $pathApproval = $request->file('approval_sheet')->store('documents/approval_sheets', 'private');
            }

            // 3. Handle Proposal (PRIVATE storage for security)
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

        if (!$document) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat dokumen',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Dokumen berhasil dibuat',
            'data'    => $document->fresh(['creator'])
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

        // Validasi: Dokumen harus dalam status DRAFT
        if ($document->status !== 'DRAFT') {
            return response()->json([
                'success' => false,
                'message' => 'Dokumen sudah disubmit sebelumnya'
            ], 400);
        }

        DB::transaction(function () use ($document, $user) {
            // Cari langkah pertama workflow
            $firstStep = $document->workflow->steps()->where('step_order', 1)->first();

            if (!$firstStep) {
                throw new \Exception('Workflow tidak memiliki langkah');
            }

            // Cari approver pertama
            $firstApprover = $this->workflowEngine->findApprover($document, $firstStep);

            if (!$firstApprover) {
                throw new \Exception('Tidak dapat menemukan approver untuk langkah pertama');
            }

            // Update dokumen
            $document->update([
                'status' => 'IN_PROGRESS',
                'current_step_order' => 1,
                'current_holder_id' => $firstApprover->id,
            ]);

            // Log submit
            DocumentLog::create([
                'document_id' => $document->id,
                'user_id' => $user->id,
                'action' => 'SUBMITTED',
                'note' => 'Dokumen diajukan untuk diproses',
                'step_snapshot' => 0,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Dokumen berhasil disubmit dan diteruskan ke approver pertama',
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

        // Validasi Status
        if (!in_array($document->status, ['DRAFT', 'REVISED'])) {
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
            ->whereIn('action', ['APPROVED', 'REJECTED', 'SUBMITTED', 'REVISED'])
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

        // Read from private disk (storage/app)
        if (!Storage::disk('private')->exists($path)) {
            return response()->json(['success' => false, 'message' => 'File not found on disk'], 404);
        }

        // Serve file securely with authentication check
        return response()->file(Storage::disk('private')->path($path));
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
            ->whereIn('action', ['APPROVED', 'REJECTED', 'SUBMITTED', 'REVISED'])
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

        // Read from private disk (storage/app)
        if (!Storage::disk('private')->exists($path)) {
            return response()->json(['success' => false, 'message' => 'File not found on disk'], 404);
        }

        $fullPath = Storage::disk('private')->path($path);
        $fileExtension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

        // If already PDF, serve directly
        if ($fileExtension === 'pdf') {
            return response()->file($fullPath);
        }

        // Convert DOCX to PDF using PhpWord + Dompdf
        if ($fileExtension === 'docx') {
            try {
                // Load DOCX file
                $phpWord = IOFactory::load($fullPath);

                // Convert to HTML
                Settings::setOutputEscapingEnabled(true);
                $htmlWriter = IOFactory::createWriter($phpWord, 'HTML');

                // Save HTML to temp file
                $tempDir = storage_path('app/temp');
                if (!file_exists($tempDir)) {
                    mkdir($tempDir, 0755, true);
                }

                $htmlPath = $tempDir . '/' . pathinfo($path, PATHINFO_FILENAME) . '_' . time() . '.html';
                $htmlWriter->save($htmlPath);

                // Read HTML content
                $htmlContent = file_get_contents($htmlPath);

                // Configure dompdf
                $options = new Options();
                $options->set('isHtml5ParserEnabled', true);
                $options->set('isRemoteEnabled', true);
                $options->set('defaultFont', 'Arial');

                // Create dompdf instance
                $dompdf = new Dompdf($options);

                // Add some CSS for better formatting
                $styledHtml = '
                    <html>
                    <head>
                        <style>
                            body { font-family: Arial, sans-serif; font-size: 12pt; }
                            table { border-collapse: collapse; width: 100%; margin: 10px 0; }
                            table, th, td { border: 1px solid #000; padding: 5px; }
                            img { max-width: 100%; height: auto; }
                            p { margin: 5px 0; }
                        </style>
                    </head>
                    <body>' . $htmlContent . '</body>
                    </html>
                ';

                // Load HTML content
                $dompdf->loadHtml($styledHtml);

                // Set paper size and orientation
                $dompdf->setPaper('A4', 'portrait');

                // Render PDF
                $dompdf->render();

                // Clean up HTML temp file
                @unlink($htmlPath);

                // Output PDF to browser
                return response($dompdf->output(), 200)
                    ->header('Content-Type', 'application/pdf')
                    ->header('Content-Disposition', 'inline; filename="' . pathinfo($path, PATHINFO_FILENAME) . '.pdf"');

            } catch (\Exception $e) {
                \Log::error('PDF conversion error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Terjadi kesalahan saat konversi PDF: ' . $e->getMessage()
                ], 500);
            }
        }

        // Unsupported file type for conversion
        return response()->json([
            'success' => false,
            'message' => 'File type tidak didukung untuk konversi PDF'
        ], 400);
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
