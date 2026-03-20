<?php

namespace App\Http\Controllers;

use App\Models\DocumentTemplate;
use App\Services\DocumentTemplateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Http\Requests\DocumentTemplate\StoreDocumentTemplateRequest;
use App\Http\Requests\DocumentTemplate\UpdateDocumentTemplateRequest;

class DocumentTemplateController extends Controller
{
    protected DocumentTemplateService $templateService;

    public function __construct(DocumentTemplateService $templateService)
    {
        $this->templateService = $templateService;
    }

    public function index(Request $request)
    {
        $templates = $this->templateService->listTemplates($request->all());

        return response()->json([
            'success' => true,
            'data' => $templates
        ]);
    }

    public function store(StoreDocumentTemplateRequest $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        try {
            $result = $this->templateService->storeTemplate(
                $user,
                $request->validated(),
                $request->file('file')
            );

            return response()->json([
                'success' => true,
                'message' => 'Template berhasil diupload',
                'data' => $result['template']->load('uploader:id,name,email'),
                'placeholders' => $result['placeholders'],
            ], 201);
        } catch (\Exception $e) {
            \Log::error('Failed to upload template', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupload template. Silakan coba lagi.'
            ], 500);
        }
    }

    public function show($id)
    {
        $template = DocumentTemplate::with('uploader:id,name,email')->find($id);

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Template not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $template
        ]);
    }

    public function update(UpdateDocumentTemplateRequest $request, $id)
    {
        $template = DocumentTemplate::find($id);

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Template not found'
            ], 404);
        }

        try {
            $file = $request->hasFile('file') ? $request->file('file') : null;
            $template = $this->templateService->updateTemplate($template, $request->validated(), $file);

            return response()->json([
                'success' => true,
                'message' => 'Template berhasil diupdate',
                'data' => $template->load('uploader:id,name,email')
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to update template', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupdate template. Silakan coba lagi.'
            ], 500);
        }
    }

    public function destroy($id)
    {
        $template = DocumentTemplate::find($id);

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Template not found'
            ], 404);
        }

        try {
            $this->templateService->deleteTemplate($template);

            return response()->json([
                'success' => true,
                'message' => 'Template berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            $code = $e->getCode() ?: 500;
            if ($code < 100 || $code > 599) $code = 500;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $code);
        }
    }

    public function activate($id)
    {
        $template = DocumentTemplate::find($id);

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Template not found'
            ], 404);
        }

        try {
            $template = $this->templateService->activateTemplate($template);

            return response()->json([
                'success' => true,
                'message' => 'Template berhasil diaktifkan',
                'data' => $template->load('uploader:id,name,email')
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to activate template', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengaktifkan template. Silakan coba lagi.'
            ], 500);
        }
    }

    public function deactivate($id)
    {
        $template = DocumentTemplate::find($id);

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Template not found'
            ], 404);
        }

        try {
            $template = $this->templateService->deactivateTemplate($template);

            return response()->json([
                'success' => true,
                'message' => 'Template berhasil dinonaktifkan',
                'data' => $template->load('uploader:id,name,email')
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to deactivate template', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menonaktifkan template. Silakan coba lagi.'
            ], 500);
        }
    }

    public function getActiveTemplates()
    {
        $templates = DocumentTemplate::where('is_active', true)
            ->with('uploader:id,name,email')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $templates
        ]);
    }

    public function download($id)
    {
        $template = DocumentTemplate::find($id);

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Template not found'
            ], 404);
        }

        if (empty($template->file_path)) {
            return response()->json([
                'success' => false,
                'message' => 'Template file path tidak tersedia'
            ], 404);
        }

        try {
            if (!Storage::disk('private')->exists($template->file_path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Template file not found'
                ], 404);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengecek ketersediaan file: ' . $e->getMessage()
            ], 500);
        }

        return Storage::disk('private')->download($template->file_path, $template->template_name . '.docx');
    }

    public function previewPdf($id)
    {
        $template = DocumentTemplate::find($id);

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Template not found'
            ], 404);
        }

        try {
            $pdfPath = $this->templateService->previewPdf($template);

            return response()->file($pdfPath, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="preview.pdf"',
            ])->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            $code = $e->getCode() ?: 500;
            if ($code < 100 || $code > 599) $code = 500;

            \Log::error('Error during PDF conversion', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $code);
        }
    }

    public function testLibreOffice()
    {
        $results = $this->templateService->testLibreOffice();

        return response()->json($results);
    }

    public function getAvailableFields()
    {
        try {
            $data = $this->templateService->getAvailableFields();

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to get available fields', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data field yang tersedia.'
            ], 500);
        }
    }

    public function updatePlaceholderMetadata(Request $request, $id)
    {
        $template = DocumentTemplate::find($id);

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Template not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'metadata' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $template->update([
                'placeholder_metadata' => $request->metadata
            ]);

            \Log::info('[PlaceholderMetadata] Updated', [
                'template_id' => $template->id,
                'template_name' => $template->template_name
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Metadata placeholder berhasil diupdate',
                'data' => $template
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update metadata: ' . $e->getMessage()
            ], 500);
        }
    }
}
