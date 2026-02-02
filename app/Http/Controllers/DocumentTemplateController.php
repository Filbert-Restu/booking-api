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
            'organization_type' => 'nullable|in:hmd,bem_ukm,senat',
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
                'organization_type' => $request->organization_type,
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
        // Get all active templates
        $templates = DocumentTemplate::where('is_active', true)
            ->with('uploader:id,name,email')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $templates
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

    /**
     * Preview template as PDF
     * GET /api/document-templates/{id}/preview-pdf
     */
    public function previewPdf($id)
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

        try {
            $docxPath = Storage::path($template->file_path);
            $pdfPath = storage_path('app/temp/preview_' . $template->id . '.pdf');

            // Create temp directory if not exists
            if (!file_exists(dirname($pdfPath))) {
                mkdir(dirname($pdfPath), 0755, true);
            }

            // Detect OS for proper command
            $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

            // Find LibreOffice executable
            $sofficeCommand = null;

            if ($isWindows) {
                // Windows: check common installation paths directly first
                $possiblePaths = [
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

                // If not found in common paths, try PATH
                if (!$sofficeCommand) {
                    exec('where soffice 2>NUL', $output, $returnCode);
                    if ($returnCode === 0 && !empty($output)) {
                        $sofficeCommand = trim($output[0]);
                    }
                }
            } else {
                // Linux/Mac: use which command
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

            // If LibreOffice not found, return fallback
            if (!$sofficeCommand) {
                return response()->json([
                    'success' => false,
                    'message' => 'LibreOffice tidak ditemukan. Pastikan sudah terinstall dan ada di PATH.',
                    'fallback' => 'docx',
                    'os' => PHP_OS,
                    'hint' => $isWindows
                        ? 'Install LibreOffice dan tambahkan ke PATH: C:\\Program Files\\LibreOffice\\program'
                        : 'Install dengan: sudo apt-get install libreoffice'
                ], 503);
            }

            // Build conversion command
            $outputDir = dirname($pdfPath);

            // Normalize paths for Windows
            if ($isWindows) {
                $docxPath = str_replace('/', '\\', $docxPath);
                $outputDir = str_replace('/', '\\', $outputDir);
            }

            // Build command - use quotes for paths with spaces
            $command = '"' . $sofficeCommand . '"' .
                      ' --headless --convert-to pdf' .
                      ' --outdir "' . $outputDir . '"' .
                      ' "' . $docxPath . '"';

            // Execute conversion
            if ($isWindows) {
                // On Windows, use cmd /c to ensure proper execution
                $fullCommand = 'cmd /c "' . $command . '"';
                exec($fullCommand . ' 2>&1', $execOutput, $execReturn);
            } else {
                exec($command . ' 2>&1', $execOutput, $execReturn);
            }

            // The output file from LibreOffice will have the same name as input but with .pdf extension
            $baseFilename = pathinfo($docxPath, PATHINFO_FILENAME);
            $tempPdfPath = $outputDir . DIRECTORY_SEPARATOR . $baseFilename . '.pdf';

            // Wait a bit for file to be written
            sleep(1);

            if (file_exists($tempPdfPath)) {
                // Rename to our target path if different
                if ($tempPdfPath !== $pdfPath) {
                    if (file_exists($pdfPath)) {
                        unlink($pdfPath);
                    }
                    rename($tempPdfPath, $pdfPath);
                }
            }

            if (!file_exists($pdfPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal mengkonversi DOCX ke PDF',
                    'debug' => [
                        'command' => $command,
                        'soffice' => $sofficeCommand,
                        'output' => $execOutput,
                        'return_code' => $execReturn,
                        'docx_path' => $docxPath,
                        'expected_pdf' => $tempPdfPath,
                        'target_pdf' => $pdfPath,
                        'os' => PHP_OS
                    ]
                ], 500);
            }

            // Return PDF file
            return response()->file($pdfPath, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="preview.pdf"',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error during PDF conversion: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Test LibreOffice installation
     * GET /api/document-templates/test/libreoffice
     */
    public function testLibreOffice()
    {
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $results = [
            'os' => PHP_OS,
            'is_windows' => $isWindows,
            'php_version' => PHP_VERSION,
            'tests' => []
        ];

        // Test 1: Check PATH
        if ($isWindows) {
            exec('where soffice 2>NUL', $output1, $code1);
            $results['tests']['where_soffice'] = [
                'command' => 'where soffice',
                'output' => $output1,
                'exit_code' => $code1,
                'found' => $code1 === 0
            ];
        } else {
            exec('which libreoffice 2>/dev/null', $output2, $code2);
            exec('which soffice 2>/dev/null', $output3, $code3);
            $results['tests']['which_libreoffice'] = [
                'command' => 'which libreoffice',
                'output' => $output2,
                'exit_code' => $code2,
                'found' => $code2 === 0
            ];
            $results['tests']['which_soffice'] = [
                'command' => 'which soffice',
                'output' => $output3,
                'exit_code' => $code3,
                'found' => $code3 === 0
            ];
        }

        // Test 2: Check common paths
        if ($isWindows) {
            $paths = [
                'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
                'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
                getenv('ProgramFiles') . '\\LibreOffice\\program\\soffice.exe',
                getenv('ProgramFiles(x86)') . '\\LibreOffice\\program\\soffice.exe',
            ];

            foreach ($paths as $path) {
                if ($path && strpos($path, 'false') === false) { // Skip if getenv returned false
                    $results['tests']['path_check'][] = [
                        'path' => $path,
                        'exists' => file_exists($path),
                        'readable' => is_readable($path)
                    ];

                    // If found, try to get version
                    if (file_exists($path)) {
                        exec('"' . $path . '" --version 2>&1', $vOut, $vCode);
                        $results['tests']['direct_execution'] = [
                            'path' => $path,
                            'command' => $path . ' --version',
                            'output' => $vOut,
                            'exit_code' => $vCode,
                            'success' => $vCode === 0
                        ];
                    }
                }
            }
        }

        // Test 3: Try to get version
        $versionCommands = $isWindows
            ? ['soffice --version', '"C:\\Program Files\\LibreOffice\\program\\soffice.exe" --version']
            : ['libreoffice --version', 'soffice --version'];

        foreach ($versionCommands as $cmd) {
            exec($cmd . ($isWindows ? ' 2>NUL' : ' 2>/dev/null'), $versionOutput, $versionCode);
            $results['tests']['version_' . md5($cmd)] = [
                'command' => $cmd,
                'output' => $versionOutput,
                'exit_code' => $versionCode,
                'success' => $versionCode === 0
            ];
        }

        // Test 4: Check temp directory
        $tempDir = storage_path('app/temp');
        $results['temp_directory'] = [
            'path' => $tempDir,
            'exists' => file_exists($tempDir),
            'writable' => is_writable($tempDir) || is_writable(storage_path('app'))
        ];

        // Recommendation
        $libreOfficeFound = false;
        foreach ($results['tests'] as $test) {
            if (isset($test['found']) && $test['found']) {
                $libreOfficeFound = true;
                break;
            }
            if (isset($test['exists']) && $test['exists']) {
                $libreOfficeFound = true;
                break;
            }
            if (isset($test['success']) && $test['success']) {
                $libreOfficeFound = true;
                break;
            }
        }

        $results['status'] = $libreOfficeFound ? 'ready' : 'not_installed';
        $results['recommendation'] = $libreOfficeFound
            ? 'LibreOffice terdeteksi dan siap digunakan!'
            : ($isWindows
                ? 'Install LibreOffice dan pastikan C:\\Program Files\\LibreOffice\\program ada di PATH environment variable. Restart terminal setelah update PATH.'
                : 'Install LibreOffice: sudo apt-get install libreoffice');

        return response()->json($results);
    }
}
