<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\DocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\Document\StoreDocumentRequest;
use App\Http\Requests\Document\ApproveDocumentRequest;
use App\Http\Requests\Document\ReviseDocumentRequest;
use App\Http\Requests\Document\ApplySignatureRequest;

class DocumentController extends Controller
{
    protected DocumentService $documentService;

    public function __construct(DocumentService $documentService)
    {
        $this->documentService = $documentService;
    }

    public function index(Request $request)
    {
        $data = $this->documentService->listDocuments($request->user(), $request->all());

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function show(Request $request, $id)
    {
        try {
            $document = $this->documentService->showDocument($id, $request->user());

            return response()->json([
                'success' => true,
                'data' => $document,
            ]);
        } catch (\Exception $e) {
            $code = $e->getCode() ?: 500;
            if ($code < 100 || $code > 599) $code = 403;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $code);
        }
    }

    public function store(StoreDocumentRequest $request)
    {
        \Log::debug('[DocumentController] store() called', [
            'workflow_id' => $request->workflow_id,
            'title' => $request->title,
        ]);

        try {
            $document = $this->documentService->createDocument(
                $request->user(),
                $request->validated(),
                $request
            );

            if (!$document) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal membuat dokumen',
                ], 500);
            }

            // Check if this is a returned duplicate (existing doc has no wasRecentlyCreated)
            if (!$document->wasRecentlyCreated) {
                return response()->json([
                    'success' => true,
                    'message' => 'Reservasi sudah ada. Menggunakan dokumen yang sudah ada.',
                    'data'    => $document->load('creator:id,name,email'),
                ], 200);
            }

            return response()->json([
                'success' => true,
                'message' => 'Dokumen berhasil dibuat',
                'data'    => $document->load('creator:id,name,email'),
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e; // Let Laravel handle validation response
        }
    }

    public function submit(Request $request, $id)
    {
        $document = Document::findOrFail($id);
        $user = $request->user();

        // Authorization: Hanya pembuat yang bisa submit
        if ($document->creator_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk submit dokumen ini',
            ], 403);
        }

        // Status check
        if (!in_array($document->status, ['DRAFT', 'REVISION'])) {
            return response()->json([
                'success' => false,
                'message' => 'Dokumen dalam status ' . $document->status . ' tidak dapat diajukan ulang',
            ], 400);
        }

        try {
            $document = $this->documentService->submitDocument($document, $user);

            return response()->json([
                'success' => true,
                'message' => ($document->status === 'APPROVED')
                    ? 'Dokumen berhasil disubmit dan disetujui secara otomatis'
                    : 'Dokumen berhasil disubmit dan diteruskan ke approver',
                'data' => $document,
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to submit document', ['document_id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function approve(ApproveDocumentRequest $request, $id)
    {
        $validated = $request->validated();
        $document = Document::with(['unit', 'workflow'])->findOrFail($id);
        $user = $request->user();

        // Authorization: Hanya current holder
        if ($document->current_holder_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk approve dokumen ini',
            ], 403);
        }

        try {
            $result = $this->documentService->approveDocument($document, $user, $validated['note'] ?? null);

            return response()->json([
                'success' => true,
                'message' => $result,
                'data' => $document->fresh(['currentHolder', 'creator', 'logs']),
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to approve document', ['document_id' => $id, 'error' => $e->getMessage()]);

            $code = $e->getCode() ?: 500;
            if ($code < 100 || $code > 599) $code = 500;

            return response()->json([
                'success' => false,
                'message' => ($code === 400) ? $e->getMessage() : 'Gagal menyetujui dokumen. Silakan coba lagi.',
            ], $code);
        }
    }

    public function revise(ReviseDocumentRequest $request, $id)
    {
        $validated = $request->validated();
        $document = Document::findOrFail($id);
        $user = $request->user();

        // Authorization: Hanya current holder
        if ($document->current_holder_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk mengembalikan dokumen ini',
            ], 403);
        }

        try {
            $result = $this->documentService->reviseDocument($document, $user, $validated['target_user_id'], $validated['note']);

            return response()->json([
                'success' => true,
                'message' => $result,
                'data' => $document->fresh(['currentHolder', 'creator', 'logs']),
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to revise document', ['document_id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengembalikan dokumen. Silakan coba lagi.',
            ], 500);
        }
    }

    public function applySignature(ApplySignatureRequest $request, $id)
    {
        $validated = $request->validated();
        $type = $validated['type'] ?? 'approval-sheet';

        $document = Document::findOrFail($id);
        $user = $request->user();

        // Authorization: Hanya current holder
        if ($document->current_holder_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk membubuhkan tanda tangan pada dokumen ini',
            ], 403);
        }

        try {
            $this->documentService->applySignatureToDocument($document, $user, $type);

            return response()->json([
                'success' => true,
                'message' => 'Tanda tangan berhasil dibubuhkan pada dokumen',
                'data' => $document->fresh(['currentHolder', 'creator', 'logs']),
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to apply signature', ['document_id' => $id, 'error' => $e->getMessage()]);

            $code = $e->getCode() ?: 500;
            if ($code < 100 || $code > 599) $code = 500;

            return response()->json([
                'success' => false,
                'message' => ($code === 400) ? $e->getMessage() : 'Gagal membubuhkan tanda tangan. Silakan coba lagi.',
            ], $code);
        }
    }

    public function update(Request $request, $id)
    {
        $document = Document::findOrFail($id);
        $user = $request->user();

        // Authorization
        if ($document->creator_id !== $user->id && $document->current_holder_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        // Status check
        if (!in_array($document->status, ['DRAFT', 'REVISION'])) {
            return response()->json(['success' => false, 'message' => 'Dokumen sudah dikunci'], 400);
        }

        try {
            $document = $this->documentService->updateDocument($document, $user, $request);

            return response()->json([
                'success' => true,
                'message' => 'Dokumen berhasil diupdate',
                'data'    => $document,
            ]);
        } catch (\Exception $e) {
            $code = $e->getCode() ?: 500;
            if ($code < 100 || $code > 599) $code = 500;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $code);
        }
    }

    public function file(Request $request, $id, $type)
    {
        $document = Document::findOrFail($id);
        $user = $request->user();

        if (!$this->documentService->checkDocumentAccess($document, $user)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk melihat dokumen ini',
            ], 403);
        }

        try {
            $data = $this->documentService->serveFile($document, $type);

            return response($data['content'], 200, [
                'Content-Type' => $data['mimeType'],
                'Content-Disposition' => 'inline; filename="' . $data['fileName'] . '"',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
                'ETag' => $data['etag'],
            ]);
        } catch (\Exception $e) {
            $code = $e->getCode() ?: 500;
            if ($code < 100 || $code > 599) $code = 404;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $code);
        }
    }

    public function filePdf(Request $request, $id, $type)
    {
        $document = Document::findOrFail($id);
        $user = $request->user();

        if (!$this->documentService->checkDocumentAccess($document, $user)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk melihat dokumen ini',
            ], 403);
        }

        try {
            $data = $this->documentService->serveFile($document, $type);
        } catch (\Exception $e) {
            $code = $e->getCode() ?: 500;
            if ($code < 100 || $code > 599) $code = 404;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $code);
        }

        $fileExtension = strtolower(pathinfo($data['path'], PATHINFO_EXTENSION));

        // Already PDF — serve directly
        if ($fileExtension === 'pdf') {
            return response($data['content'], 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
                'ETag' => $data['etag'],
            ]);
        }

        // DOCX → convert to PDF
        if ($fileExtension === 'docx') {
            try {
                $tempDocxPath = storage_path('app/temp/docx_' . $document->id . '_' . $type . '_' . time() . '.docx');
                if (!file_exists(dirname($tempDocxPath))) {
                    mkdir(dirname($tempDocxPath), 0755, true);
                }
                file_put_contents($tempDocxPath, $data['content']);

                try {
                    return $this->documentService->convertDocxToPdf($tempDocxPath, $document->id, $type);
                } finally {
                    if (file_exists($tempDocxPath)) {
                        unlink($tempDocxPath);
                    }
                }
            } catch (\Exception $e) {
                \Log::error('PDF conversion failed, falling back to DOCX download', ['error' => $e->getMessage()]);

                return response($data['content'], 200, [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'Content-Disposition' => 'attachment; filename="' . $data['fileName'] . '"',
                ]);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'File type tidak didukung',
        ], 400);
    }

    public function generateExecutiveSummary(Request $request, $id)
    {
        $document = Document::findOrFail($id);
        $user = $request->user();

        if ($document->creator_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses',
            ], 403);
        }

        try {
            $filePath = $this->documentService->generateFromTemplate($document, 'executive_summary');

            $document->update(['file_executive_summary' => $filePath]);

            return response()->json([
                'success' => true,
                'message' => 'Executive summary berhasil digenerate',
                'data' => [
                    'file_path' => $filePath,
                    'download_url' => route('api.documents.file', ['id' => $id, 'type' => 'executive-summary']),
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to generate executive summary', ['document_id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal generate executive summary. Silakan coba lagi.',
            ], 500);
        }
    }

    public function generateApprovalSheet(Request $request, $id)
    {
        $document = Document::with('unit')->findOrFail($id);
        $user = $request->user();

        if ($document->creator_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses',
            ], 403);
        }

        try {
            $orgType = $this->documentService->mapCategoryToOrganizationType($document->unit->category ?? 'HMD');
            $filePath = $this->documentService->generateFromTemplate($document, 'lembar_pengesahan');

            $document->update(['file_approval_sheet' => $filePath]);

            return response()->json([
                'success' => true,
                'message' => 'Lembar pengesahan berhasil digenerate',
                'data' => [
                    'file_path' => $filePath,
                    'download_url' => route('api.documents.file', ['id' => $id, 'type' => 'approval-sheet']),
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to generate approval sheet', ['document_id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal generate lembar pengesahan. Silakan coba lagi.',
            ], 500);
        }
    }
}
