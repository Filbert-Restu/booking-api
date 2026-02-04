<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\Room;
use App\Models\Sign;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\TemplateProcessor;
use App\Models\DocumentLog;

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
        // Use 'private' disk to get correct path (app/ instead of app/private/)
        $templatePath = Storage::disk('private')->path($template->file_path);

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

        \Log::info('[DocumentGeneration] Prepared data for placeholders', [
            'document_id' => $document->id,
            'total_fields' => count($data),
            'sample_data' => array_slice($data, 0, 10),
            'has_event_name' => isset($data['event_name']),
            'event_name_value' => $data['event_name'] ?? 'NOT SET'
        ]);

        // 4. Replace all placeholders
        $replacedCount = 0;
        foreach ($data as $key => $value) {
            // Skip TTD placeholders - they will be replaced with images
            if (strpos($key, 'ttd_') === 0 || strpos($key, 'signature_') === 0) {
                \Log::debug("[Placeholder] Skipped (for image): {$key}");
                continue;
            }

            // Ensure value is string, handle null values
            $value = $value ?? '-';
            try {
                $templateProcessor->setValue($key, $value);
                $replacedCount++;
                \Log::debug("[Placeholder] Replaced: {$key} = " . substr($value, 0, 50));
            } catch (\Exception $e) {
                \Log::warning("[Placeholder] Failed to replace: {$key}", [
                    'error' => $e->getMessage()
                ]);
            }
        }

        \Log::info('[DocumentGeneration] Placeholder replacement complete', [
            'replaced_count' => $replacedCount,
            'total_data_fields' => count($data)
        ]);

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
            // Try to find template with specific organization_type first
            $template = $query->where('organization_type', $organizationType)->first();

            // If not found, fallback to general template (NULL organization_type)
            if (!$template) {
                \Log::info("Template with organization_type not found, trying general template", [
                    'template_type' => $templateType,
                    'organization_type' => $organizationType
                ]);

                $template = DocumentTemplate::where('template_type', $templateType)
                    ->where('is_active', true)
                    ->whereNull('organization_type')
                    ->first();
            }

            return $template;
        }

        // If no organization_type specified, prefer general template (NULL) first
        $template = $query->whereNull('organization_type')->first();

        // If no general template, take any active template
        if (!$template) {
            \Log::info("No general template found, using any active template", [
                'template_type' => $templateType
            ]);
            $template = DocumentTemplate::where('template_type', $templateType)
                ->where('is_active', true)
                ->first();
        }

        return $template;
    }

    /**
     * Prepare data from document for template filling
     */
    protected function prepareData(Document $document): array
    {
        // Load relationships
        $document->load(['creator', 'unit', 'workflow.steps']);

        // Get content data (PRIORITY SOURCE - always available during flow)
        $content = $document->content ?? [];

        \Log::info('[prepareData] Initial content from document', [
            'document_id' => $document->id,
            'content_keys' => array_keys($content),
            'has_booking_date' => isset($content['booking_date']),
            'has_room_id' => isset($content['room_id']),
        ]);

        // Get room booking data if exists (FALLBACK - only available after submission)
        $roomBooking = \DB::table('room_bookings')
            ->where('document_id', $document->id)
            ->first();

        \Log::info('[prepareData] Room booking query result', [
            'found' => $roomBooking !== null,
            'booking_data' => $roomBooking ? [
                'booking_date' => $roomBooking->booking_date,
                'start_time' => $roomBooking->start_time,
                'end_time' => $roomBooking->end_time,
                'room_id' => $roomBooking->room_id,
            ] : null,
        ]);

        // Get room data - priority: content -> room_booking -> null
        $room = null;
        $roomId = $content['room_id'] ?? $roomBooking->room_id ?? null;
        if ($roomId) {
            $room = Room::find($roomId);
            \Log::info('[prepareData] Room found', [
                'room_id' => $roomId,
                'room_code' => $room?->code,
                'room_name' => $room?->name,
            ]);
        }

        // Map peminjam_nama to ketua_pelaksana_nama if not set
        if (!isset($content['ketua_pelaksana_nama']) && isset($content['peminjam_nama'])) {
            $content['ketua_pelaksana_nama'] = $content['peminjam_nama'];
            \Log::info('[prepareData] Mapped peminjam_nama to ketua_pelaksana_nama');
        }

        // Merge booking data into content
        // PRIORITY: content (from step 1) > room_booking (from submission)
        // This ensures data is available during generate (before submission)
        if ($roomBooking) {
            $content['booking_date'] = $roomBooking->booking_date ?? $content['booking_date'];
            $content['start_time'] = $roomBooking->start_time ?? $content['start_time'];
            $content['end_time'] = $roomBooking->end_time ?? $content['end_time'];
            $content['purpose'] = $roomBooking->purpose ?? $content['purpose'];
            $content['room_id'] = $roomBooking->room_id ?? $content['room_id'];
            \Log::info('[prepareData] Merged room_booking data (room_booking takes priority)');
        }

        // Prepare data array with comprehensive logging
        $data = [
            // Room data
            'room_id' => $content['room_id'] ?? '',
            'room_code' => $room ? $room->code : ($content['room_code'] ?? ''),
            'room_name' => $room ? $room->name : ($content['room_name'] ?? ''),
            'room_capacity' => $room ? $room->capacity : '',
            'room_building' => $room ? $room->building : '',

            // Booking data - NOW AVAILABLE FROM CONTENT
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

        // Lookup and fill approver data from workflow steps
        $this->fillApproverData($data, $document);

        // ============================================
        // ADD UPPERCASE MAPPINGS FOR USER TEMPLATES
        // ============================================
        // User-uploaded templates might use UPPERCASE_WITH_UNDERSCORE format
        // Map all lowercase fields to UPPERCASE equivalents
        $uppercaseMappings = [
            // Room
            'ROOM_CODE' => $data['room_code'],
            'ROOM_NAME' => $data['room_name'],
            'ROOM_CAPACITY' => $data['room_capacity'],

            // Booking & Dates
            'tanggal' => $data['booking_date'],
            'TANGGAL' => $data['booking_date'],
            'WAKTU_MULAI' => $data['start_time'],
            'WAKTU_SELESAI' => $data['end_time'],
            'WAKTU' => ($data['start_time'] && $data['end_time']) ?
                "{$data['start_time']} - {$data['end_time']}" : '',

            // Event - Indonesian naming
            'NAMA_KEGIATAN' => $data['event_name'],
            'SIFAT' => $data['event_nature'],
            'BENTUK' => $data['event_form'],
            'TUJUAN' => $data['objectives'],
            'MANFAAT' => $data['benefits'],
            'SASARAN' => $data['target_audience'],
            'WAKTU_KEGIATAN' => $data['schedule'],
            'TEMPAT' => $data['location'],
            'ALAT' => $data['equipment'],
            'KETUA_PANITIA' => $data['ketua_pelaksana_nama'],
            'UNDANGAN' => $data['invitations'],

            // Ketua Pelaksana / Ketua Panitia (sama dengan ketua pelaksana)
            'NAMA_KETUA' => $data['ketua_pelaksana_nama'],
            'NIM_KETUA' => $data['ketua_pelaksana_nim'],
            'HP_KETUA' => $data['ketua_pelaksana_hp'],
            'nama_ketuapanitia' => $data['ketua_pelaksana_nama'],
            'nim_ketuapanitia' => $data['ketua_pelaksana_nim'],
            // Note: ttd_ketuapanitia will be inserted as image, don't set as text

            // Unit/Ormawa
            'NAMA_ORMAWA' => $data['unit_name'],
            'KODE_ORMAWA' => $data['unit_code'],
            'NAMA_SINGKAT_ORMAWA' => $data['unit_code'], // Using code as short name

            // Note: Approver names, NIM/NIP filled by fillApproverData()
            // Note: TTD placeholders are NOT filled with text here, will be replaced with images in insertSignatures()

            'nama_departemen' => 'Statistika', // Default, bisa diganti sesuai unit
        ];

        // Merge uppercase mappings into data
        $data = array_merge($data, $uppercaseMappings);

        \Log::info('[prepareData] Added uppercase field mappings', [
            'uppercase_fields_count' => count($uppercaseMappings),
            'total_fields' => count($data),
        ]);

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
                    // Try multiple placeholder formats for ketua pelaksana/panitia
                    $placeholders = ['signature_ketua_pelaksana', 'ttd_ketuapanitia', 'TTD_KETUA'];
                    foreach ($placeholders as $placeholder) {
                        try {
                            $templateProcessor->setImageValue($placeholder, [
                                'path' => $signaturePath,
                                'width' => 150,
                                'height' => 75,
                                'ratio' => false
                            ]);
                            \Log::debug("[SIGNATURE] Inserted creator signature at placeholder: {$placeholder}");
                        } catch (\Exception $e) {
                            // Placeholder might not exist in template, that's ok
                            \Log::debug("[SIGNATURE] Placeholder {$placeholder} not found in template");
                        }
                    }
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
            $logs = DocumentLog::where('document_id', $document->id)
                              ->where('action', 'APPROVED')
                              ->with('user.role')
                              ->orderBy('created_at')
                              ->get();

            \Log::info("[SIGNATURE] Checking approver signatures", [
                'approved_logs_count' => $logs->count()
            ]);

            // Map role slugs to signature placeholder names
            $roleToSignaturePlaceholder = [
                'ketua-ormawa' => ['ttd_ketuaormawa', 'signature_ketuaormawa'],
                'dosen-pendamping' => ['ttd_dosenpendamping', 'signature_dosenpendamping'],
                'senat' => ['ttd_ketuasenat', 'signature_ketuasenat'],
                'wadek1' => ['ttd_wadek1', 'signature_wadek1'],
                'ketua-departemen' => ['ttd_ketuadepartemen', 'signature_ketuadepartemen'],
                'kemahasiswaan' => ['ttd_kemahasiswaan', 'signature_kemahasiswaan'],
                'sumber-daya' => ['ttd_sumberdaya', 'signature_sumberdaya'],
            ];

            $approverIndex = 1;
            foreach ($logs as $log) {
                if ($log->user) {
                    $roleSlug = $log->user->role->slug ?? null;

                    $approverSignature = Sign::where('user_id', $log->user_id)
                                             ->latest()
                                             ->first();

                    \Log::info("[SIGNATURE] Processing approver", [
                        'user' => $log->user->name,
                        'role' => $roleSlug,
                        'has_signature' => $approverSignature ? 'yes' : 'no'
                    ]);

                    if ($approverSignature && $approverSignature->signature) {
                        $signaturePath = Storage::path($approverSignature->signature);

                        if (file_exists($signaturePath)) {
                            // Try role-specific placeholders first
                            $placeholders = [];
                            if ($roleSlug && isset($roleToSignaturePlaceholder[$roleSlug])) {
                                $placeholders = array_merge($placeholders, $roleToSignaturePlaceholder[$roleSlug]);
                            }
                            // Also try generic approver_N placeholder
                            $placeholders[] = "signature_approver_{$approverIndex}";
                            $placeholders[] = "ttd_approver_{$approverIndex}";

                            $insertedCount = 0;
                            foreach ($placeholders as $placeholder) {
                                try {
                                    $templateProcessor->setImageValue($placeholder, [
                                        'path' => $signaturePath,
                                        'width' => 100,
                                        'height' => 50,
                                        'ratio' => false
                                    ]);
                                    $insertedCount++;
                                    \Log::debug("[SIGNATURE] Inserted at placeholder: {$placeholder}");
                                } catch (\Exception $e) {
                                    // Placeholder might not exist in template, that's ok
                                    \Log::debug("[SIGNATURE] Placeholder {$placeholder} not found: " . $e->getMessage());
                                }
                            }

                            if ($insertedCount > 0) {
                                \Log::info("[SIGNATURE] ✅ Approver signature inserted", [
                                    'user' => $log->user->name,
                                    'role' => $roleSlug,
                                    'placeholders_filled' => $insertedCount
                                ]);
                            } else {
                                \Log::warning("[SIGNATURE] ⚠️ No placeholder found for approver", [
                                    'user' => $log->user->name,
                                    'role' => $roleSlug,
                                    'tried_placeholders' => $placeholders
                                ]);
                            }
                        } else {
                            \Log::warning("[SIGNATURE] ⚠️ Approver signature file not found", [
                                'path' => $signaturePath
                            ]);
                        }
                    } else {
                        \Log::warning("[SIGNATURE] ⚠️ Approver has no signature", [
                            'user' => $log->user->name,
                            'role' => $roleSlug
                        ]);
                    }
                }

                $approverIndex++;
            }
        } else {
            \Log::info("[SIGNATURE] No workflow found, skipping approver signatures");
        }

        \Log::info("[SIGNATURE] Signature insertion completed");
    }

    /**
     * Fill approver data by looking up workflow steps
     * This predicts who will approve based on workflow configuration
     */
    protected function fillApproverData(array &$data, Document $document): void
    {
        \Log::info("[fillApproverData] Starting approver lookup", [
            'document_id' => $document->id,
            'workflow_id' => $document->workflow_id
        ]);

        // Import WorkflowEngine to reuse findApprover logic
        $workflowEngine = app(\App\Services\WorkflowEngine::class);

        if (!$document->workflow) {
            \Log::warning("[fillApproverData] No workflow found");
            $this->setDefaultApproverPlaceholders($data);
            return;
        }

        $steps = $document->workflow->steps()->orderBy('step_order')->get();

        // Map role slugs to placeholder field names
        $roleToPlaceholder = [
            'ketua-ormawa' => ['nama' => 'nama_ketuaormawa', 'nip_nim' => 'nim_ketuaormawa'],
            'dosen-pendamping' => ['nama' => 'nama_dosenpendamping', 'nip_nim' => 'nip_dosenpendamping'],
            'senat' => ['nama' => 'nama_ketuasenat', 'nip_nim' => 'nim_ketuasenat'],
            'wadek1' => ['nama' => 'nama_wadek1', 'nip_nim' => 'nip_wadek1'],
            'ketua-departemen' => ['nama' => 'nama_ketuadepartemen', 'nip_nim' => 'nip_ketuadepartemen'],
        ];

        foreach ($steps as $step) {
            try {
                $approver = $workflowEngine->findApprover($document, $step);

                if ($approver && isset($roleToPlaceholder[$step->target_role_slug])) {
                    $placeholders = $roleToPlaceholder[$step->target_role_slug];
                    $data[$placeholders['nama']] = $approver->name;

                    // Use NIP for staff (dosen, wadek, kadep), NIM for students (ketua ormawa, senat)
                    if (in_array($step->target_role_slug, ['ketua-ormawa', 'senat'])) {
                        // For students, try to get NIM from user profile or use placeholder
                        $nim = $approver->profile['nim'] ?? $approver->email ?? '____________________';
                        $data[$placeholders['nip_nim']] = $nim;
                    } else {
                        // For staff, try to get NIP from user profile or use placeholder
                        $nip = $approver->profile['nip'] ?? '____________________';
                        $data[$placeholders['nip_nim']] = $nip;
                    }

                    \Log::info("[fillApproverData] Approver found", [
                        'step' => $step->step_name,
                        'role' => $step->target_role_slug,
                        'approver' => $approver->name,
                        'filled_nama' => $placeholders['nama'],
                        'filled_nip_nim' => $placeholders['nip_nim']
                    ]);
                }
            } catch (\Exception $e) {
                \Log::warning("[fillApproverData] Failed to find approver for step", [
                    'step' => $step->step_name,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Set defaults for any missing approver data
        foreach ($roleToPlaceholder as $role => $placeholders) {
            if (!isset($data[$placeholders['nama']])) {
                $data[$placeholders['nama']] = '____________________';
            }
            if (!isset($data[$placeholders['nip_nim']])) {
                $data[$placeholders['nip_nim']] = '____________________';
            }
        }

        \Log::info("[fillApproverData] Approver data filled", [
            'total_steps' => $steps->count()
        ]);
    }

    /**
     * Set default placeholders for approver data
     */
    protected function setDefaultApproverPlaceholders(array &$data): void
    {
        $data['nama_ketuaormawa'] = '____________________';
        $data['nim_ketuaormawa'] = '____________________';
        $data['nama_dosenpendamping'] = '____________________';
        $data['nip_dosenpendamping'] = '____________________';
        $data['nama_ketuasenat'] = '____________________';
        $data['nim_ketuasenat'] = '____________________';
        $data['nama_wadek1'] = '____________________';
        $data['nip_wadek1'] = '____________________';
        $data['nama_ketuadepartemen'] = '____________________';
        $data['nip_ketuadepartemen'] = '____________________';
    }
}
