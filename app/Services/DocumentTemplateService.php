<?php

namespace App\Services;

use App\Models\DocumentTemplate;
use App\Models\User;
use App\Services\PlaceholderExtractor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DocumentTemplateService
{
    public function listTemplates(array $filters)
    {
        $query = DocumentTemplate::with('uploader:id,name,email');

        if (!empty($filters['type'])) {
            $query->type($filters['type']);
        }

        if (isset($filters['is_active'])) {
            if ($filters['is_active'] === 'true' || $filters['is_active'] === '1') {
                $query->active();
            } else {
                $query->inactive();
            }
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function storeTemplate(User $user, array $data, UploadedFile $file): array
    {
        $path = null;

        DB::beginTransaction();

        try {
            $fileName = time() . '_' . $data['template_type'] . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('document-templates', $fileName, 'private');

            if ($path === false || empty($path)) {
                \Log::error('[TemplateStore] storeAs() failed', [
                    'fileName' => $fileName,
                    'disk' => 'private',
                    'bucket' => config('filesystems.disks.private.bucket'),
                    'endpoint' => config('filesystems.disks.private.endpoint'),
                ]);
                throw new \RuntimeException('Gagal mengupload file ke storage. Periksa koneksi MinIO/S3.');
            }

            $fileUrl = Storage::disk('private')->url($path);

            // Get next version number
            $latestVersion = DocumentTemplate::where('template_type', $data['template_type'])
                                            ->max('version') ?? 0;

            // Extract placeholders from DOCX
            $fullPath = $file->getRealPath();
            $detectedPlaceholders = PlaceholderExtractor::extractFromDocx($fullPath);
            $placeholderMetadata = PlaceholderExtractor::buildMetadata($detectedPlaceholders);

            \Log::info('[TemplateUpload] Placeholders extracted', [
                'template_name' => $data['template_name'],
                'total_placeholders' => count($detectedPlaceholders),
                'available_count' => count(array_filter($placeholderMetadata, fn($m) => $m['available'])),
                'placeholders' => $detectedPlaceholders
            ]);

            $template = DocumentTemplate::create([
                'template_type'          => $data['template_type'],
                'organization_type'      => $data['organization_type'] ?? null,
                'template_name'          => $data['template_name'],
                'file_path'              => $path,
                'file_url'               => $fileUrl,
                'version'                => $latestVersion + 1,
                'is_active'              => false,
                'uploaded_by'            => $user->id,
                'description'            => $data['description'] ?? null,
                'detected_placeholders'  => $detectedPlaceholders,
                'placeholder_metadata'   => $placeholderMetadata,
            ]);

            if (!empty($data['set_as_active'])) {
                $template->setAsActive();
            }

            DB::commit();

            $unavailablePlaceholders = array_filter(
                $placeholderMetadata,
                fn($m) => !$m['available']
            );

            return [
                'template' => $template,
                'placeholders' => [
                    'total' => count($detectedPlaceholders),
                    'available' => count($detectedPlaceholders) - count($unavailablePlaceholders),
                    'unavailable' => array_keys($unavailablePlaceholders),
                ],
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            if (is_string($path) && !empty($path)) {
                try {
                    if (Storage::disk('private')->exists($path)) {
                        Storage::disk('private')->delete($path);
                    }
                } catch (\Exception $cleanupEx) {
                    \Log::warning('[TemplateStore] Gagal menghapus file saat rollback', [
                        'file_path' => $path,
                        'error' => $cleanupEx->getMessage()
                    ]);
                }
            }

            throw $e;
        }
    }

    public function updateTemplate(DocumentTemplate $template, array $data, ?UploadedFile $file = null): DocumentTemplate
    {
        DB::beginTransaction();

        try {
            $updateData = [];

            if ($file) {
                // Delete old file
                if (!empty($template->file_path)) {
                    try {
                        if (Storage::disk('private')->exists($template->file_path)) {
                            Storage::disk('private')->delete($template->file_path);
                        }
                    } catch (\Exception $deleteEx) {
                        \Log::warning('[TemplateUpdate] Gagal menghapus file lama dari storage', [
                            'file_path' => $template->file_path,
                            'error' => $deleteEx->getMessage()
                        ]);
                    }
                }

                // Upload new file
                $fileName = time() . '_' . $template->template_type . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('document-templates', $fileName, 'private');

                if ($path === false || empty($path)) {
                    throw new \RuntimeException('Gagal mengupload file ke storage. Periksa koneksi MinIO/S3.');
                }

                $updateData['file_path'] = $path;
                $updateData['file_url'] = Storage::disk('private')->url($path);

                // Extract placeholders from new file
                $fullPath = $file->getRealPath();
                $detectedPlaceholders = PlaceholderExtractor::extractFromDocx($fullPath);
                $placeholderMetadata = PlaceholderExtractor::buildMetadata($detectedPlaceholders);

                $updateData['detected_placeholders'] = $detectedPlaceholders;
                $updateData['placeholder_metadata'] = $placeholderMetadata;

                \Log::info('[TemplateUpdate] Placeholders re-extracted', [
                    'template_id' => $template->id,
                    'total_placeholders' => count($detectedPlaceholders)
                ]);

                $updateData['version'] = $template->version + 1;
            }

            if (isset($data['template_name'])) {
                $updateData['template_name'] = $data['template_name'];
            }
            if (isset($data['description'])) {
                $updateData['description'] = $data['description'];
            }

            $template->update($updateData);

            DB::commit();

            return $template;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function deleteTemplate(DocumentTemplate $template): void
    {
        if ($template->is_active) {
            throw new \Exception('Cannot delete active template. Please activate another template first.', 422);
        }

        if ($template->file_path) {
            try {
                if (Storage::disk('private')->exists($template->file_path)) {
                    Storage::disk('private')->delete($template->file_path);
                }
            } catch (\Exception $deleteEx) {
                \Log::warning('[TemplateDelete] Gagal menghapus file dari storage', [
                    'file_path' => $template->file_path,
                    'error' => $deleteEx->getMessage()
                ]);
            }
        }

        $template->delete();
    }

    public function activateTemplate(DocumentTemplate $template): DocumentTemplate
    {
        $template->setAsActive();

        return $template;
    }

    public function deactivateTemplate(DocumentTemplate $template): DocumentTemplate
    {
        $template->update(['is_active' => false]);

        return $template;
    }

    public function previewPdf(DocumentTemplate $template)
    {
        if (empty($template->file_path)) {
            throw new \Exception('Template file path tidak tersedia', 404);
        }

        if (!Storage::disk('private')->exists($template->file_path)) {
            throw new \Exception('Template file not found', 404);
        }

        $tempDir = storage_path('app/temp');
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $tempDocxPath = $tempDir . DIRECTORY_SEPARATOR . 'temp_' . $template->id . '.docx';
        $fileContents = Storage::disk('private')->get($template->file_path);
        file_put_contents($tempDocxPath, $fileContents);

        $pdfPath = $tempDir . DIRECTORY_SEPARATOR . 'preview_' . $template->id . '.pdf';

        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $sofficeCommand = $this->findLibreOffice($isWindows);

        if (!$sofficeCommand) {
            throw new \Exception('LibreOffice tidak ditemukan. Pastikan sudah terinstall.', 503);
        }

        $outputDir = dirname($pdfPath);
        $docxPath = $tempDocxPath;

        if ($isWindows) {
            $docxPath = str_replace('/', '\\', $docxPath);
            $outputDir = str_replace('/', '\\', $outputDir);
        }

        $command = '"' . $sofficeCommand . '"' .
                  ' --headless' .
                  ' --convert-to pdf:writer_pdf_Export' .
                  ' --outdir "' . $outputDir . '"' .
                  ' "' . $docxPath . '"';

        if ($isWindows) {
            $fullCommand = 'cmd /c "' . $command . '"';
            exec($fullCommand . ' 2>&1', $execOutput, $execReturn);
        } else {
            exec($command . ' 2>&1', $execOutput, $execReturn);
        }

        $baseFilename = pathinfo($docxPath, PATHINFO_FILENAME);
        $tempPdfPath = $outputDir . DIRECTORY_SEPARATOR . $baseFilename . '.pdf';

        sleep(1);

        if (file_exists($tempPdfPath)) {
            if ($tempPdfPath !== $pdfPath) {
                if (file_exists($pdfPath)) {
                    unlink($pdfPath);
                }
                rename($tempPdfPath, $pdfPath);
            }
        }

        // Cleanup temp DOCX
        if (file_exists($tempDocxPath)) {
            @unlink($tempDocxPath);
        }

        if (!file_exists($pdfPath)) {
            \Log::error('PDF conversion failed', [
                'command' => $command,
                'output' => $execOutput,
                'return_code' => $execReturn,
            ]);
            throw new \Exception('Gagal mengkonversi DOCX ke PDF', 500);
        }

        return $pdfPath;
    }

    public function testLibreOffice(): array
    {
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $results = [
            'os' => PHP_OS,
            'is_windows' => $isWindows,
            'php_version' => PHP_VERSION,
            'tests' => []
        ];

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

        if ($isWindows) {
            $paths = [
                'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
                'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
                getenv('ProgramFiles') . '\\LibreOffice\\program\\soffice.exe',
                getenv('ProgramFiles(x86)') . '\\LibreOffice\\program\\soffice.exe',
            ];

            foreach ($paths as $path) {
                if ($path && strpos($path, 'false') === false) {
                    $results['tests']['path_check'][] = [
                        'path' => $path,
                        'exists' => file_exists($path),
                        'readable' => is_readable($path)
                    ];

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

        $tempDir = storage_path('app/temp');
        $results['temp_directory'] = [
            'path' => $tempDir,
            'exists' => file_exists($tempDir),
            'writable' => is_writable($tempDir) || is_writable(storage_path('app'))
        ];

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

        return $results;
    }

    public function getAvailableFields(): array
    {
        $fields = PlaceholderExtractor::getAvailableFields();

        $grouped = [];
        foreach ($fields as $key => $field) {
            $category = $field['category'] ?? 'other';
            $grouped[$category][] = [
                'key' => $key,
                ...$field
            ];
        }

        return [
            'all' => $fields,
            'grouped' => $grouped,
            'total' => count($fields)
        ];
    }

    private function findLibreOffice(bool $isWindows): ?string
    {
        if ($isWindows) {
            exec('where soffice 2>NUL', $output, $returnCode);
            if ($returnCode === 0 && !empty($output)) {
                return trim($output[0]);
            }

            $commonPaths = [
                'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
                'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
                getenv('ProgramFiles') . '\\LibreOffice\\program\\soffice.exe',
                getenv('ProgramFiles(x86)') . '\\LibreOffice\\program\\soffice.exe',
                base_path('libreoffice-portable\\App\\libreoffice\\program\\soffice.exe'),
                base_path('LibreOfficePortable\\App\\libreoffice\\program\\soffice.exe'),
            ];

            foreach ($commonPaths as $path) {
                if (file_exists($path)) {
                    return $path;
                }
            }
        } else {
            exec('which soffice 2>/dev/null', $output, $returnCode);
            if ($returnCode === 0 && !empty($output)) {
                return 'soffice';
            }

            exec('which libreoffice 2>/dev/null', $output2, $returnCode2);
            if ($returnCode2 === 0 && !empty($output2)) {
                return 'libreoffice';
            }
        }

        return null;
    }
}
