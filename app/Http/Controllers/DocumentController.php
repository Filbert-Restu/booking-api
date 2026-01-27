<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentLog;
use App\Services\WorkflowEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DocumentController extends Controller
{
    protected $workflowEngine;

    public function __construct(WorkflowEngine $workflowEngine)
    {
        $this->workflowEngine = $workflowEngine;
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
            $query = Document::with(['workflow', 'currentHolder', 'unit', 'logs']);

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
        $myDocumentsQuery = Document::with(['workflow', 'currentHolder', 'unit'])
            ->where('creator_id', $user->id);

        // Filter by status untuk my_documents
        if ($request->has('status')) {
            $myDocumentsQuery->where('status', $request->status);
        }

        // Filter by workflow
        if ($request->has('workflow_id')) {
            $myDocumentsQuery->where('workflow_id', $request->workflow_id);
        }

        $myDocuments = $myDocumentsQuery->latest()->get();

        // Dokumen yang sedang menunggu action dari user ini
        $pendingDocumentsQuery = Document::with(['workflow', 'unit'])
            ->where('current_holder_id', $user->id)
            ->where('status', 'IN_PROGRESS');

        // Filter by workflow untuk pending
        if ($request->has('workflow_id')) {
            $pendingDocumentsQuery->where('workflow_id', $request->workflow_id);
        }

        $pendingDocuments = $pendingDocumentsQuery->latest()->get();

        // Dokumen yang sudah diproses oleh user ini (approved/rejected)
        // Ambil document_id dari logs dimana user ini melakukan action
        $processedDocumentIds = DocumentLog::where('user_id', $user->id)
            ->whereIn('action', ['APPROVED', 'REJECTED'])
            ->pluck('document_id')
            ->unique();

        $processedDocumentsQuery = Document::with(['workflow', 'currentHolder', 'unit'])
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
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'workflow_id' => 'required|exists:workflows,id',
            'title' => 'required|string|max:255',
            'content' => 'nullable|array',
            'attachment_path' => 'nullable|string',
            'meta_data' => 'nullable|array',
        ]);

        $user = $request->user();

        $document = Document::create([
            'workflow_id' => $validated['workflow_id'],
            'title' => $validated['title'],
            'content' => $validated['content'] ?? null,
            'attachment_path' => $validated['attachment_path'] ?? null,
            'meta_data' => $validated['meta_data'] ?? null,
            'unit_id' => $user->unit_id,
            'creator_id' => $user->id,
            'status' => 'DRAFT',
            'current_step_order' => 0,
        ]);

        // Log pembuatan dokumen
        DocumentLog::create([
            'document_id' => $document->id,
            'user_id' => $user->id,
            'action' => 'CREATED',
            'note' => 'Dokumen dibuat',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Dokumen berhasil dibuat',
            'data' => $document
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
            'data' => $document->fresh(['currentHolder', 'logs'])
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

        $document = Document::findOrFail($id);
        $user = $request->user();

        // Validasi: Hanya current holder yang bisa approve
        if ($document->current_holder_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk approve dokumen ini'
            ], 403);
        }

        try {
            $result = $this->workflowEngine->approveDocument(
                $document,
                $user,
                $validated['note'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => $result,
                'data' => $document->fresh(['currentHolder', 'logs'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject dokumen
     */
    public function reject(Request $request, $id)
    {
        $validated = $request->validate([
            'note' => 'required|string',
        ]);

        $document = Document::findOrFail($id);
        $user = $request->user();

        // Validasi: Hanya current holder yang bisa reject
        if ($document->current_holder_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk reject dokumen ini'
            ], 403);
        }

        DB::transaction(function () use ($document, $user, $validated) {
            // Update status dokumen
            $document->update([
                'status' => 'REJECTED',
                'completed_at' => now(),
            ]);

            // Log rejection
            DocumentLog::create([
                'document_id' => $document->id,
                'user_id' => $user->id,
                'action' => 'REJECTED',
                'note' => $validated['note'],
                'step_snapshot' => $document->current_step_order,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Dokumen berhasil ditolak',
            'data' => $document->fresh(['logs'])
        ]);
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
                'data' => $document->fresh(['currentHolder', 'logs'])
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
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'content' => 'nullable|array',  // Changed from string to array
            'attachment_path' => 'nullable|string',
            'meta_data' => 'nullable|array',
        ]);

        $document = Document::findOrFail($id);
        $user = $request->user();

        // Validasi: Hanya pembuat atau current holder yang bisa update
        if ($document->creator_id !== $user->id && $document->current_holder_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk update dokumen ini'
            ], 403);
        }

        // Validasi: Dokumen harus dalam status DRAFT atau REVISED
        if (!in_array($document->status, ['DRAFT', 'REVISED'])) {
            return response()->json([
                'success' => false,
                'message' => 'Dokumen tidak dapat diupdate karena sudah disubmit'
            ], 400);
        }

        $document->update($validated);

        // Log update dokumen
        DocumentLog::create([
            'document_id' => $document->id,
            'user_id' => $user->id,
            'action' => 'UPDATED',
            'note' => 'Dokumen diupdate',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Dokumen berhasil diupdate',
            'data' => $document->fresh()
        ]);
    }
}
