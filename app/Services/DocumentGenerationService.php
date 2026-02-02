<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\Room;
use App\Models\Sign;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\TemplateProcessor;

class DocumentGenerationService
{
    /**
     * Generate document dari template dengan mengisi data
     */
    public function generateFromTemplate(
        Document $document,
        string $templateType,
        ?string $organizationType = null
    ): string {
        // 0. Validate user has uploaded signature
        $this->validateUserSignature($document);

        // 1. Get active template
        $template = $this->getActiveTemplate($templateType, $organizationType);

        if (!$template) {
            \Log::error("Template not found", [
                'template_type' => $templateType,
                'organization_type' => $organizationType
            ]);
            throw new \Exception("Template {$templateType} tidak ditemukan atau belum diaktifkan. Silakan upload dan aktifkan template terlebih dahulu.");
        }

        // 2. Load template DOCX
        $templatePath = Storage::path($template->file_path);

        \Log::info("Loading template file", [
            'template_id' => $template->id,
            'template_name' => $template->template_name,
            'file_path' => $template->file_path,
            'full_path' => $templatePath
        ]);

        if (!file_exists($templatePath)) {
            \Log::error("Template file not found in storage", [
                'template_id' => $template->id,
                'file_path' => $template->file_path,
                'full_path' => $templatePath
            ]);
            throw new \Exception("File template tidak ditemukan di storage. Template: {$template->template_name} (ID: {$template->id}). Path: {$template->file_path}. Silakan upload ulang template.");
        }

        $templateProcessor = new TemplateProcessor($templatePath);

        // 3. Get data untuk fill
        $data = $this->prepareData($document);

        // 4. Replace all placeholders
        foreach ($data as $key => $value) {
            // Ensure value is string, handle null values
            $value = $value ?? '-';
            $templateProcessor->setValue($key, $value);
        }

        // 4b. Insert signature images if placeholders exist
        $this->insertSignatures($templateProcessor, $document);

        // 5. Save generated document
        $outputFileName = $this->generateFileName($document, $templateType);
        $outputPath = storage_path('app/documents/' . $outputFileName);

        // Create directory if not exists
        if (!file_exists(dirname($outputPath))) {
            mkdir(dirname($outputPath), 0755, true);
        }

        $templateProcessor->saveAs($outputPath);

        // 6. Return relative path
        return 'documents/' . $outputFileName;
    }

    /**
     * Get active template based on type and organization
     */
    protected function getActiveTemplate(string $templateType, ?string $organizationType)
    {
        $query = DocumentTemplate::where('template_type', $templateType)
                                  ->where('is_active', true);

        if ($organizationType) {
            $query->where('organization_type', $organizationType);
        }

        return $query->first();
    }

    /**
     * Prepare data from document for template filling
     */
    protected function prepareData(Document $document): array
    {
        // Load relationships
        $document->load(['creator', 'unit', 'workflow.steps']);

        // Get content data
        $content = $document->content ?? [];

        // Get room data
        $room = null;
        if (isset($content['room_id'])) {
            $room = Room::find($content['room_id']);
        }

        // Prepare data array
        $data = [
            // Room data
            'room_id' => $content['room_id'] ?? '',
            'room_code' => $content['room_code'] ?? '',
            'room_name' => $room ? $room->name : ($content['room_name'] ?? ''),

            // Booking data
            'booking_date' => $this->formatDate($content['booking_date'] ?? null),
            'start_time' => $content['start_time'] ?? '',
            'end_time' => $content['end_time'] ?? '',
            'purpose' => $content['purpose'] ?? '',

            // Ketua Pelaksana
            'ketua_pelaksana_nama' => $content['ketua_pelaksana_nama'] ?? '',
            'ketua_pelaksana_nim' => $content['ketua_pelaksana_nim'] ?? '',
            'ketua_pelaksana_hp' => $content['ketua_pelaksana_hp'] ?? '',

            // Event data
            'event_name' => $content['event_name'] ?? '',
            'event_nature' => $content['event_nature'] ?? '',
            'event_form' => $content['event_form'] ?? '',
            'objectives' => $content['objectives'] ?? '',
            'benefits' => $content['benefits'] ?? '',
            'target_audience' => $content['target_audience'] ?? '',
            'schedule' => $content['schedule'] ?? '',
            'location' => $content['location'] ?? '',
            'equipment' => $content['equipment'] ?? '',
            'committee_head' => $content['committee_head'] ?? '',
            'invitations' => $content['invitations'] ?? '',

            // User data
            'user_name' => $document->creator->name ?? '',
            'user_email' => $document->creator->email ?? '',

            // Unit data
            'unit_name' => $document->unit->name ?? '',
            'unit_code' => $document->unit->code ?? '',
            'unit_category' => $document->unit->category ?? '',

            // Dates
            'created_date' => $this->formatDate($document->created_at),
            'submission_date' => $this->formatDate($document->submitted_at ?? $document->created_at),
            'current_date' => $this->formatDate(now()),
        ];

        // Add approver placeholders
        // Note: Approver names will be empty/placeholder since we don't have signature data yet
        $data['approver_1'] = '____________________';
        $data['approver_2'] = '____________________';
        $data['approver_3'] = '____________________';

        return $data;
    }

    /**
     * Format date to Indonesian format
     */
    protected function formatDate($date): string
    {
        if (!$date) return '-';

        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];

        $timestamp = is_string($date) ? strtotime($date) : $date->timestamp;
        $day = date('d', $timestamp);
        $month = $months[(int)date('m', $timestamp)];
        $year = date('Y', $timestamp);

        return "{$day} {$month} {$year}";
    }

    /**
     * Generate filename for output document
     */
    protected function generateFileName(Document $document, string $templateType): string
    {
        $timestamp = date('YmdHis');
        $documentId = $document->id;

        return "{$templateType}_{$documentId}_{$timestamp}.docx";
    }

    /**
     * Validate user has uploaded signature before generating document
     */
    protected function validateUserSignature(Document $document): void
    {
        $creatorSignature = Sign::where('user_id', $document->creator_id)
                                 ->latest()
                                 ->first();

        if (!$creatorSignature || !$creatorSignature->signature) {
            throw new \Exception("Anda belum mengupload tanda tangan. Silakan upload tanda tangan terlebih dahulu di menu 'Kelola Tanda Tangan' sebelum generate dokumen.");
        }

        $signaturePath = Storage::path($creatorSignature->signature);
        if (!file_exists($signaturePath)) {
            \Log::error("[SIGNATURE] File not found", [
                'signature_field' => $creatorSignature->signature,
                'full_path' => $signaturePath,
                'user_id' => $document->creator_id
            ]);
            throw new \Exception("File tanda tangan tidak ditemukan di storage. Silakan upload ulang tanda tangan Anda.");
        }
    }

    /**
     * Insert signature images into template
     */
    protected function insertSignatures(TemplateProcessor $templateProcessor, Document $document): void
    {
        \Log::info("[SIGNATURE] Starting signature insertion", [
            'document_id' => $document->id,
            'creator_id' => $document->creator_id
        ]);

        // Get ketua pelaksana signature (document creator)
        $creatorSignature = Sign::where('user_id', $document->creator_id)
                                 ->latest()
                                 ->first();

        if ($creatorSignature && $creatorSignature->signature) {
            $signaturePath = Storage::path($creatorSignature->signature);

            \Log::info("[SIGNATURE] Creator signature found", [
                'user_id' => $document->creator_id,
                'signature_field' => $creatorSignature->signature,
                'full_path' => $signaturePath,
                'file_exists' => file_exists($signaturePath)
            ]);

            if (file_exists($signaturePath)) {
                try {
                    $templateProcessor->setImageValue('signature_ketua_pelaksana', [
                        'path' => $signaturePath,
                        'width' => 150,
                        'height' => 75,
                        'ratio' => false
                    ]);
                    \Log::info("[SIGNATURE] ✅ Creator signature inserted successfully");
                } catch (\Exception $e) {
                    \Log::warning("[SIGNATURE] ⚠️ Failed to insert creator signature", [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            } else {
                \Log::error("[SIGNATURE] ❌ Creator signature file not found", [
                    'path' => $signaturePath
                ]);
            }
        } else {
            \Log::warning("[SIGNATURE] ⚠️ Creator signature not found in database", [
                'user_id' => $document->creator_id
            ]);
        }

        // Get approver signatures if document has been approved
        if ($document->workflow) {
            $completedSteps = $document->workflow->steps()
                                       ->where('status', 'APPROVED')
                                       ->with('approver')
                                       ->orderBy('step_order')
                                       ->get();

            \Log::info("[SIGNATURE] Checking approver signatures", [
                'completed_steps_count' => $completedSteps->count()
            ]);

            $approverIndex = 1;
            foreach ($completedSteps as $step) {
                if ($step->approver) {
                    $approverSignature = Sign::where('user_id', $step->approver_id)
                                             ->latest()
                                             ->first();

                    \Log::info("[SIGNATURE] Approver {$approverIndex}", [
                        'user_id' => $step->approver_id,
                        'has_signature' => $approverSignature ? 'yes' : 'no'
                    ]);

                    if ($approverSignature && $approverSignature->signature) {
                        $signaturePath = Storage::path($approverSignature->signature);

                        if (file_exists($signaturePath)) {
                            try {
                                $placeholderName = "signature_approver_{$approverIndex}";
                                $templateProcessor->setImageValue($placeholderName, [
                                    'path' => $signaturePath,
                                    'width' => 150,
                                    'height' => 75,
                                    'ratio' => false
                                ]);
                                \Log::info("[SIGNATURE] ✅ Approver {$approverIndex} signature inserted");
                            } catch (\Exception $e) {
                                \Log::warning("[SIGNATURE] ⚠️ Approver {$approverIndex} placeholder not found or failed", [
                                    'error' => $e->getMessage()
                                ]);
                            }
                        } else {
                            \Log::warning("[SIGNATURE] ⚠️ Approver {$approverIndex} signature file not found", [
                                'path' => $signaturePath
                            ]);
                        }
                    }
                }

                $approverIndex++;
                if ($approverIndex > 3) break; // Limit to 3 approvers
            }
        } else {
            \Log::info("[SIGNATURE] No workflow found, skipping approver signatures");
        }

        \Log::info("[SIGNATURE] Signature insertion completed");
    }
}
