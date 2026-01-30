<?php

namespace App\Http\Controllers;

use App\Models\DocumentTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class DocumentTemplateController extends Controller
{
    /**
     * Display a listing of templates
     * GET /api/document-templates
     */
    public function index(Request $request)
    {
        $query = DocumentTemplate::with('uploader:id,name,email');

        // Filter by type
        if ($request->has('type')) {
            $query->type($request->type);
        }

        // Filter by active status
        if ($request->has('is_active')) {
            if ($request->is_active === 'true' || $request->is_active === '1') {
                $query->active();
            } else {
                $query->inactive();
            }
        }

        $templates = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $templates
        ]);
    }

    /**
     * Store a newly created template
     * POST /api/document-templates
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'template_type' => 'required|in:executive_summary,lembar_pengesahan',
            'template_name' => 'required|string|max:255',
            'file' => 'required|file|mimes:docx,doc|max:10240', // max 10MB
            'description' => 'nullable|string',
            'set_as_active' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        try {
            DB::beginTransaction();

            // Upload file
            $file = $request->file('file');
            $fileName = time() . '_' . $request->template_type . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('document-templates', $fileName);
            
            // Generate URL
            $fileUrl = Storage::url($path);

            // Get next version number
            $latestVersion = DocumentTemplate::where('template_type', $request->template_type)
                                            ->max('version') ?? 0;

            // Create template
            $template = DocumentTemplate::create([
                'template_type' => $request->template_type,
                'template_name' => $request->template_name,
                'file_path' => $path,
                'file_url' => $fileUrl,
                'version' => $latestVersion + 1,
                'is_active' => false, // Will be set later if requested
                'uploaded_by' => $user->id,
                'description' => $request->description,
            ]);

            // Set as active if requested
            if ($request->set_as_active) {
                $template->setAsActive();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Template berhasil diupload',
                'data' => $template->load('uploader:id,name,email')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            // Delete uploaded file if exists
            if (isset($path) && Storage::exists($path)) {
                Storage::delete($path);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload template: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified template
     * GET /api/document-templates/{id}
     */
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

    /**
     * Update the specified template (replace file)
     * PUT/PATCH /api/document-templates/{id}
     */
    public function update(Request $request, $id)
    {
        $template = DocumentTemplate::find($id);

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Template not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'template_name' => 'nullable|string|max:255',
            'file' => 'nullable|file|mimes:docx,doc|max:10240',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $updateData = [];

            // Update file if provided
            if ($request->hasFile('file')) {
                // Delete old file
                if ($template->file_path && Storage::exists($template->file_path)) {
                    Storage::delete($template->file_path);
                }

                // Upload new file
                $file = $request->file('file');
                $fileName = time() . '_' . $template->template_type . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('document-templates', $fileName);
                
                $updateData['file_path'] = $path;
                $updateData['file_url'] = Storage::url($path);
                
                // Increment version
                $updateData['version'] = $template->version + 1;
            }

            // Update other fields
            if ($request->has('template_name')) {
                $updateData['template_name'] = $request->template_name;
            }
            if ($request->has('description')) {
                $updateData['description'] = $request->description;
            }

            $template->update($updateData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Template berhasil diupdate',
                'data' => $template->load('uploader:id,name,email')
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update template: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified template
     * DELETE /api/document-templates/{id}
     */
    public function destroy($id)
    {
        $template = DocumentTemplate::find($id);

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Template not found'
            ], 404);
        }

        // Check if this is the active template
        if ($template->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete active template. Please activate another template first.'
            ], 422);
        }

        try {
            // Delete file
            if ($template->file_path && Storage::exists($template->file_path)) {
                Storage::delete($template->file_path);
            }

            $template->delete();

            return response()->json([
                'success' => true,
                'message' => 'Template berhasil dihapus'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete template: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Set template as active
     * PATCH /api/document-templates/{id}/activate
     */
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
            $template->setAsActive();

            return response()->json([
                'success' => true,
                'message' => 'Template berhasil diaktifkan',
                'data' => $template->load('uploader:id,name,email')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to activate template: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get active templates
     * GET /api/document-templates/active
     */
    public function getActiveTemplates()
    {
        $executiveSummary = DocumentTemplate::getActiveTemplate('executive_summary');
        $lembarPengesahan = DocumentTemplate::getActiveTemplate('lembar_pengesahan');

        return response()->json([
            'success' => true,
            'data' => [
                'executive_summary' => $executiveSummary,
                'lembar_pengesahan' => $lembarPengesahan
            ]
        ]);
    }

    /**
     * Download template file
     * GET /api/document-templates/{id}/download
     */
    public function download($id)
    {
        $template = DocumentTemplate::find($id);

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Template not found'
            ], 404);
        }

        if (!Storage::exists($template->file_path)) {
            return response()->json([
                'success' => false,
                'message' => 'Template file not found'
            ], 404);
        }

        return Storage::download($template->file_path, $template->template_name . '.docx');
    }
}
