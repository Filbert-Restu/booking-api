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

        // 2. Load template DOCX from MinIO
        // Download template to temporary file for PhpWord processing
        \Log::info("Loading template file from MinIO", [
            'template_id' => $template->id,
            'template_name' => $template->template_name,
            'file_path' => $template->file_path,
        ]);

        // Check if template exists in MinIO
        if (!Storage::disk($this->getStorageDiskName())->exists($template->file_path)) {
            \Log::error("Template file not found in MinIO", [
                'template_id' => $template->id,
                'file_path' => $template->file_path,
            ]);
            throw new \Exception("File template tidak ditemukan di storage. Template: {$template->template_name} (ID: {$template->id}). Path: {$template->file_path}. Silakan upload ulang template.");
        }

        // Download template from MinIO to temporary file
        $tempDir = storage_path('app/temp/templates');
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $tempFileName = 'template_' . $template->id . '_' . time() . '.docx';
        $tempTemplatePath = $tempDir . '/' . $tempFileName;

        // Download from MinIO
        $templateContent = Storage::disk($this->getStorageDiskName())->get($template->file_path);
        file_put_contents($tempTemplatePath, $templateContent);

        \Log::info("Template downloaded to temp", [
            'temp_path' => $tempTemplatePath,
            'exists' => file_exists($tempTemplatePath),
            'size' => filesize($tempTemplatePath)
        ]);

        $templateProcessor = new TemplateProcessor($tempTemplatePath);

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
        $tempSignaturePaths = $this->insertSignatures($templateProcessor, $document);

        // 5. Save generated document to temp first, then upload to MinIO
        $outputFileName = $this->generateFileName($document, $templateType);
        $tempOutputPath = storage_path('app/temp/' . $outputFileName);

        // Create temp directory if not exists
        if (!file_exists(dirname($tempOutputPath))) {
            mkdir(dirname($tempOutputPath), 0755, true);
        }

        // Save to temp file
        $templateProcessor->saveAs($tempOutputPath);

        \Log::info('Generated document saved to temp', [
            'temp_path' => $tempOutputPath,
            'size' => filesize($tempOutputPath)
        ]);

        // 6. Upload to MinIO
        $minioPath = 'documents/' . $outputFileName;
        $fileContent = file_get_contents($tempOutputPath);
        Storage::disk($this->getStorageDiskName())->put($minioPath, $fileContent);

        \Log::info('Generated document uploaded to MinIO', [
            'minio_path' => $minioPath,
            'size' => strlen($fileContent)
        ]);

        // 7. Cleanup temporary files
        if (isset($tempTemplatePath) && file_exists($tempTemplatePath)) {
            unlink($tempTemplatePath);
            \Log::debug('Temporary template file cleaned up', ['path' => $tempTemplatePath]);
        }

        if (file_exists($tempOutputPath)) {
            unlink($tempOutputPath);
            \Log::debug('Temporary output file cleaned up', ['path' => $tempOutputPath]);
        }

        // 7b. Cleanup temporary signature files
        foreach ($tempSignaturePaths as $sigPath) {
            if (file_exists($sigPath)) {
                @unlink($sigPath);
                \Log::debug('Temporary signature file cleaned up', ['path' => $sigPath]);
            }
        }

        // 8. Return relative path for MinIO
        return $minioPath;
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

        \Log::info("Using template", [
            'id' => $template->id,
            'name' => $template->template_name,
            'type' => $template->template_type,
            'org_type' => $template->organization_type
        ]);

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

        // Handle case where content might be a string instead of array
        if (!is_array($content)) {
            \Log::warning('[prepareData] Content is not an array, converting...', [
                'document_id' => $document->id,
                'content_type' => gettype($content),
                'content_value' => $content,
            ]);
            $content = [];
        }

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

        // Handle nested ketua object from frontend
        if (isset($content['ketua']) && is_array($content['ketua'])) {
            $content['ketua_pelaksana_nama'] = $content['ketua']['nama'] ?? $content['ketua_pelaksana_nama'] ?? '';
            $content['ketua_pelaksana_nim'] = $content['ketua']['nim'] ?? $content['ketua_pelaksana_nim'] ?? '';
            $content['ketua_pelaksana_hp'] = $content['ketua']['hp'] ?? $content['ketua_pelaksana_hp'] ?? '';
            \Log::info('[prepareData] Extracted ketua data from nested object', [
                'nama' => $content['ketua_pelaksana_nama'],
                'nim' => $content['ketua_pelaksana_nim'],
                'hp' => $content['ketua_pelaksana_hp']
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
            'nama_ketua_pelaksana' => $content['ketua_pelaksana_nama'] ?? '',
            'nim_ketua_pelaksana' => $content['ketua_pelaksana_nim'] ?? '',
            'hp_ketua_pelaksana' => $content['ketua_pelaksana_hp'] ?? '',

            // Event data
            'nama_kegiatan' => $content['event_name'] ?? '',
            'sifat' => $content['event_nature'] ?? '',
            'bentuk' => $content['event_form'] ?? '',
            'tujuan' => $content['objectives'] ?? '',
            'manfaat' => $content['benefits'] ?? '',
            'sasaran' => $content['target_audience'] ?? '',
            'jadwal' => $content['schedule'] ?? '',
            'tempat' => $content['location'] ?? '',
            'alat' => $content['equipment'] ?? '',
            'undangan' => $content['invitations'] ?? '',

            // User data
            'nama_user' => $document->creator->name ?? '',
            'email_user' => $document->creator->email ?? '',

            // Unit data
            'nama_unit' => $document->unit->name ?? '',
            // Alias used by older templates
            'nama_ormawa' => $document->unit->name ?? '',
            'kode_unit' => $document->unit->code ?? '',
            'kategori_unit' => $document->unit->category ?? '',
            // Dates
            'created_date' => $this->formatDate($document->created_at),
            'submission_date' => $this->formatDate($document->submitted_at ?? $document->created_at),
            'current_date' => $this->formatDate(now()),
        ];

        // Lookup and fill approver data from workflow steps
        $this->fillApproverData($data, $document);

        // Auto-generate simple case variants for all data fields so templates
        // can request uppercase/lowercase without manually registering them.
        // Examples generated per key `foo`: `FOO`, `foo_upper`, `foo_lower`.
        $caseVariants = [];
        foreach ($data as $k => $v) {
            if (!is_scalar($v)) continue;
            $str = (string) $v;
            // Use multibyte-safe functions to support UTF-8 content
            $upper = mb_strtoupper($str, 'UTF-8');
            $lower = mb_strtolower($str, 'UTF-8');
            $title = mb_convert_case($str, MB_CASE_TITLE, 'UTF-8');
            $capitalized = ucfirst(mb_strtolower($str, 'UTF-8'));

            $caseVariants[strtoupper($k)] = $upper;
            $caseVariants[$k . '_upper'] = $upper;
            $caseVariants[$k . '_lower'] = $lower;
            // New variants: capitalized (first letter upper) and title case (each word capitalized)
            $caseVariants[$k . '_capitalized'] = $capitalized;
            $caseVariants[$k . '_title'] = $title;
        }
        // Merge generated variants but keep original $data keys preferred
        $data = array_merge($data, $caseVariants);

        // ============================================
        // ADD MULTIPLE CASE VARIANT MAPPINGS FOR USER TEMPLATES
        // ============================================
        // User-uploaded templates might use different case formats:
        // - UPPERCASE_WITH_UNDERSCORE: ${NAMA_KEGIATAN}
        // - PascalCase: ${Nama_Kegiatan}
        // - camelCase: ${namaKegiatan}
        // - lowercase: ${nama_kegiatan}
        $uppercaseMappings = [
            // Room - all variants
            'ROOM_CODE' => $data['room_code'] ?? '',
            'ROOM_NAME' => $data['room_name'] ?? '',
            'ROOM_CAPACITY' => $data['room_capacity'] ?? '',

            // Booking & Dates - all variants
            'TANGGAL' => $data['current_date'] ?? '',

            // Tanggal peminjaman ruangan (spesifik)
            'TANGGAL_PEMINJAMAN' => $data['booking_date'] ?? '',
            'WAKTU_MULAI' => $data['start_time'] ?? '',
            'WAKTU_SELESAI' => $data['end_time'] ?? '',
            'WAKTU' => (($data['start_time'] ?? '') && ($data['end_time'] ?? '')) ?
                "{$data['start_time']} - {$data['end_time']}" : '',
            'Waktu' => (($data['start_time'] ?? '') && ($data['end_time'] ?? '')) ?
                "{$data['start_time']} - {$data['end_time']}" : '',

            // Lowercase aliases so templates using ${waktu} or ${tanggal} work
            'waktu' => (($data['start_time'] ?? '') && ($data['end_time'] ?? '')) ?
                "{$data['start_time']} - {$data['end_time']}" : '',
            'tanggal' => $data['current_date'] ?? '',

            // Event - all case variants
            // Use the internal key `nama_kegiatan` (prepared earlier)
            'NAMA_KEGIATAN' => $data['nama_kegiatan'] ?? '',

            'SIFAT' => $data['event_nature'] ?? '',

            'BENTUK' => $data['event_form'] ?? '',

            'TUJUAN' => $data['objectives'] ?? '',

            'MANFAAT' => $data['benefits'] ?? '',

            'SASARAN' => $data['target_audience'] ?? '',

            'WAKTU_KEGIATAN' => $data['schedule'] ?? '',

            'TEMPAT' => $data['location'] ?? '',

            'ALAT' => $data['equipment'] ?? '',

            'UNDANGAN' => $data['invitations'] ?? '',

            // Ketua Pelaksana - all variants
            'NAMA_KETUA_PANITIA' => $data['ketua_pelaksana_nama'] ?? '',

            'NIM_KETUA_PANITIA' => $data['ketua_pelaksana_nim'] ?? '',
            'NIM_KETUA_PANITIA' => $data['ketua_pelaksana_nim'] ?? '',

            'HP_KETUA_PANITIA' => $data['ketua_pelaksana_hp'] ?? '',

            'NIM_KETUA_PANITIA' => $data['ketua_pelaksana_nim'] ?? '',
            // Note: ttd_ketuapanitia will be inserted as image, don't set as text

            // Unit/Ormawa - all variants
            // Prefer `nama_ormawa` (alias), fallback to `nama_unit`
            'NAMA_ORMAWA' => $data['nama_ormawa'] ?? $data['nama_unit'] ?? '',

            'KODE_ORMAWA' => $data['unit_code'] ?? '',

            'NAMA_SINGKAT_ORMAWA' => $data['unit_code'] ?? '',

            'NAMA_DEPARTEMEN' => $data['unit_name'] ?? '',
            // Lowercase alias for templates using ${nama_departemen}
            'nama_departemen' => $data['unit_name'] ?? '',
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
     * Resolve storage disk name from config or environment
     */
    protected function getStorageDiskName(): string
    {
        // Prefer Laravel filesystem default, fallback to FILESYSTEM_DISK env, then 'private'
        return config('filesystems.default') ?? env('FILESYSTEM_DISK', 'private');
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

        // Check if signature exists in configured storage disk
        if (!Storage::disk($this->getStorageDiskName())->exists($creatorSignature->signature)) {
            \Log::error("[SIGNATURE] File not found in MinIO", [
                'signature_field' => $creatorSignature->signature,
                'user_id' => $document->creator_id
            ]);
            throw new \Exception("File tanda tangan tidak ditemukan di storage. Silakan upload ulang tanda tangan Anda.");
        }
    }

    /**
     * Download signature from MinIO to temporary local file for PhpWord processing
     */
    protected function downloadSignatureToTemp(string $signaturePath): ?string
    {
        try {
            if (!Storage::disk($this->getStorageDiskName())->exists($signaturePath)) {
                \Log::warning("[SIGNATURE] File not found in storage", ['path' => $signaturePath, 'disk' => $this->getStorageDiskName()]);
                return null;
            }

            // Create temp directory if not exists
            $tempDir = storage_path('app/temp/signatures');
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            // Generate unique temp filename
            $extension = pathinfo($signaturePath, PATHINFO_EXTENSION);
            $tempFileName = 'sig_' . md5($signaturePath . time()) . '.' . $extension;
            $tempFilePath = $tempDir . '/' . $tempFileName;

            // Download from MinIO to temp file
            $content = Storage::disk($this->getStorageDiskName())->get($signaturePath);
            file_put_contents($tempFilePath, $content);

            \Log::debug("[SIGNATURE] Downloaded to temp", [
                'minio_path' => $signaturePath,
                'temp_path' => $tempFilePath,
                'exists' => file_exists($tempFilePath)
            ]);

            return $tempFilePath;
        } catch (\Exception $e) {
            \Log::error("[SIGNATURE] Failed to download signature", [
                'path' => $signaturePath,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Insert signature images into template
     *
     * @return array List of temporary file paths created during signature insertion (for cleanup)
     */
    protected function insertSignatures(TemplateProcessor $templateProcessor, Document $document): array
    {
        $tempFilePaths = [];

        \Log::info("[SIGNATURE] Starting signature insertion", [
            'document_id' => $document->id,
            'creator_id' => $document->creator_id
        ]);

        // Get ketua pelaksana signature (document creator)
        $creatorSignature = Sign::where('user_id', $document->creator_id)
                                 ->latest()
                                 ->first();

        if ($creatorSignature && $creatorSignature->signature) {
            // Download signature from MinIO to temp location
            $tempSignaturePath = $this->downloadSignatureToTemp($creatorSignature->signature);
            if ($tempSignaturePath) $tempFilePaths[] = $tempSignaturePath;

            \Log::info("[SIGNATURE] Creator signature found", [
                'user_id' => $document->creator_id,
                'signature_field' => $creatorSignature->signature,
                'temp_path' => $tempSignaturePath,
                'file_exists' => $tempSignaturePath ? file_exists($tempSignaturePath) : false
            ]);

            if ($tempSignaturePath && file_exists($tempSignaturePath)) {
                try {
                    // Try multiple placeholder formats for ketua pelaksana/panitia
                    // Include underscore variants so templates using ${ttd_ketua_panitia}
                    // or ${signature_ketua_panitia} are supported.
                    $placeholders = [
                        'signature_ketua_pelaksana',
                        'signature_ketua_panitia',
                        'ttd_ketuapanitia',
                        'ttd_ketua_panitia',
                        'TTD_KETUA',
                        'TTD_KETUA_PANITIA',
                    ];
                    foreach ($placeholders as $placeholder) {
                        try {
                            $templateProcessor->setImageValue($placeholder, [
                                'path' => $tempSignaturePath,
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
                \Log::error("[SIGNATURE] ❌ Creator signature file could not be downloaded");
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
                              ->with(['user.role', 'user.unit'])
                              ->orderBy('created_at')
                              ->get();

            \Log::info("[SIGNATURE] Checking approver signatures", [
                'approved_logs_count' => $logs->count()
            ]);

            // Map role slugs to signature placeholder names
            $roleToSignaturePlaceholder = [
                'ketua-ormawa' => ['ttd_ketua_ormawa', 'signature_ketua_ormawa'],
                'dosen-pendamping' => ['ttd_dosen_pendamping', 'signature_dosen_pendamping'],
                'senat' => ['ttd_ketua_senat', 'signature_ketua_senat'],
                'wadek1' => ['ttd_wadek1', 'signature_wadek1'],
                'ketua-departemen' => ['ttd_ketua_departemen', 'signature_ketua_departemen'],
                'kemahasiswaan' => ['ttd_kemahasiswaan', 'signature_kemahasiswaan'],
                'sumber-daya' => ['ttd_sumber_daya', 'signature_sumber_daya'],
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
                        // Download signature from MinIO to temp location
                        $tempSignaturePath = $this->downloadSignatureToTemp($approverSignature->signature);
                        if ($tempSignaturePath) $tempFilePaths[] = $tempSignaturePath;

                        if ($tempSignaturePath && file_exists($tempSignaturePath)) {
                            $unit = $log->user->unit;
                            $placeholders = [];
                            $isSenatUnit = ($unit && strtoupper($unit->category) === 'SENAT');

                            // --- SPECIAL MAPPING FOR SENAT UNIT SIGNATURE ---
                            if ($isSenatUnit && ($roleSlug === 'ketua-ormawa' || $roleSlug === 'senat')) {
                                $placeholders[] = 'ttd_ketua_senat';
                                $placeholders[] = 'signature_ketua_senat';
                                $placeholders[] = 'ttd_ketuasenat';

                                // Dual mapping: if document belongs to Senat, also fill ormawa/panitia spots
                                if ($document->unit && strtoupper($document->unit->category) === 'SENAT') {
                                    $placeholders[] = 'ttd_ketua_ormawa';
                                    $placeholders[] = 'signature_ketua_ormawa';
                                    $placeholders[] = 'ttd_ketua_panitia';
                                    $placeholders[] = 'signature_ketua_panitia';
                                }
                            } elseif ($isSenatUnit && $roleSlug === 'sekretaris') {
                                $placeholders[] = 'ttd_sekretaris_senat';
                                $placeholders[] = 'signature_sekretaris_senat';

                                // Dual mapping for Senat's own document
                                if ($document->unit && strtoupper($document->unit->category) === 'SENAT') {
                                    $placeholders[] = 'ttd_sekretaris';
                                    $placeholders[] = 'signature_sekretaris';
                                }
                            } else {
                                // Standard role-specific placeholders for non-Senat or other roles
                                if ($roleSlug && isset($roleToSignaturePlaceholder[$roleSlug])) {
                                    $placeholders = array_merge($placeholders, $roleToSignaturePlaceholder[$roleSlug]);
                                }

                                // Fallback for Ketua Panitia/Ormawa in applicant unit
                                if ($roleSlug === 'ketua-ormawa') {
                                    $placeholders[] = 'ttd_ketua_panitia';
                                    $placeholders[] = 'signature_ketua_panitia';
                                }
                            }

                            // Also try generic approver_N placeholder
                            $placeholders[] = "signature_approver_{$approverIndex}";
                            $placeholders[] = "ttd_approver_{$approverIndex}";

                            $insertedCount = 0;
                            foreach ($placeholders as $placeholder) {
                                try {
                                    $templateProcessor->setImageValue($placeholder, [
                                        'path' => $tempSignaturePath,
                                        'width' => 100,
                                        'height' => 50,
                                        'ratio' => false
                                    ]);
                                    $insertedCount++;
                                    \Log::debug("[SIGNATURE] Inserted at placeholder: {$placeholder}");
                                } catch (\Exception $e) {
                                    // Placeholder might not exist in template, that's ok
                                    \Log::debug("[SIGNATURE] Placeholder {$placeholder} not found");
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
                            \Log::warning("[SIGNATURE] ⚠️ Approver signature file could not be downloaded", [
                                'user' => $log->user->name,
                                'signature_path' => $approverSignature->signature
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

        \Log::info("[SIGNATURE] Signature insertion completed", [
            'temp_files_created' => count($tempFilePaths)
        ]);

        return $tempFilePaths;
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

        // Map role slugs to placeholder field names (multiple case variants)
        $roleToPlaceholder = [
            'ketua-ormawa' => [
                'nama' => ['nama_ketua_ormawa', 'NAMA_KETUA_ORMAWA'],
                'nip_nim' => ['nim_ketua_ormawa', 'NIM_KETUA_ORMAWA', 'nim_ketuapanitia', 'NIM']
            ],
            'sekretaris' => [
                'nama' => ['nama_sekretaris', 'NAMA_SEKRETARIS'],
                'nip_nim' => ['nim_sekretaris', 'NIM_SEKRETARIS', 'NIM']
            ],
            'dosen-pendamping' => [
                'nama' => ['nama_dosen_pendamping', 'NAMA_DOSEN_PENDAMPING'],
                'nip_nim' => ['nip_dosen_pendamping', 'NIP_DOSEN_PENDAMPING', 'NIP']
            ],
            'senat' => [
                'nama' => ['nama_ketua_senat', 'NAMA_KETUA_SENAT', 'nama_ketuasenat'],
                'nip_nim' => ['nim_ketua_senat', 'NIM_KETUA_SENAT', 'nim_ketuasenat', 'NIM']
            ],
            'kemahasiswaan' => [
                'nama' => ['nama_kemahasiswaan', 'NAMA_KEMAHASISWAAN'],
                'nip_nim' => ['nip_kemahasiswaan', 'NIP_KEMAHASISWAAN', 'NIP']
            ],
            'sumber-daya' => [
                'nama' => ['nama_sumber_daya', 'NAMA_SUMBER_DAYA'],
                'nip_nim' => ['nip_sumber_daya', 'NIP_SUMBER_DAYA', 'NIP']
            ],
            'wadek1' => [
                'nama' => ['nama_wadek1', 'NAMA_WADEK1'],
                'nip_nim' => ['nip_wadek1', 'NIP_WADEK1', 'NIP']
            ],
            'ketua-departemen' => [
                'nama' => ['nama_ketua_departemen', 'NAMA_KETUA_DEPARTEMEN'],
                'nip_nim' => ['nip_ketua_departemen', 'NIP_KETUA_DEPARTEMEN', 'NIP']
            ],
        ];

        foreach ($steps as $step) {
            try {
                $approver = $workflowEngine->findApprover($document, $step);

                if ($approver) {
                    $roleSlug = $step->target_role_slug;
                    $unit = $approver->unit;

                    // --- SPECIAL MAPPING FOR SENAT UNIT ---
                    // If unit category is SENAT, we map ketua-ormawa and sekretaris specifically
                    if ($unit && strtoupper($unit->category) === 'SENAT') {
                        $isSenatDocument = ($document->unit && strtoupper($document->unit->category) === 'SENAT');

                        if ($roleSlug === 'ketua-ormawa' || $roleSlug === 'senat') {
                            $data['nama_ketua_senat'] = $approver->name;
                            $data['nama_ketuasenat'] = $approver->name;
                            $data['nim_ketua_senat'] = $approver->nim_nip ?? '____________________';
                            $data['nim_ketuasenat'] = $approver->nim_nip ?? '____________________';

                            // If this is Senat's own document, also fill general ormawa placeholders
                            if ($isSenatDocument) {
                                $data['nama_ketua_ormawa'] = $approver->name;
                                $data['nim_ketua_ormawa'] = $approver->nim_nip ?? '____________________';
                            }
                        } elseif ($roleSlug === 'sekretaris') {
                            $data['nama_sekretaris_senat'] = $approver->name;
                            $data['nim_sekretaris_senat'] = $approver->nim_nip ?? '____________________';

                            // If this is Senat's own document, also fill general sekretaris placeholders
                            if ($isSenatDocument) {
                                $data['nama_sekretaris'] = $approver->name;
                                $data['nim_sekretaris'] = $approver->nim_nip ?? '____________________';
                            }
                        }
                    }

                    // --- AUTOMATIC PAIRING LOGIC (FIND SEKRETARIS) ---
                    // If we find a Ketua, automatically try to find the Sekretaris in the same unit
                    if ($roleSlug === 'ketua-ormawa' || $roleSlug === 'senat') {
                        $sekretaris = \App\Models\User::where('unit_id', $approver->unit_id)
                            ->whereHas('role', function($q) { $q->where('slug', 'sekretaris'); })
                            ->first();

                        if ($sekretaris) {
                            $prefix = (strtoupper($unit->category ?? '') === 'SENAT') ? 'sekretaris_senat' : 'sekretaris_ormawa';
                            $data["nama_{$prefix}"] = $sekretaris->name;
                            $data["nim_{$prefix}"] = $sekretaris->nim_nip ?? '____________________';

                            \Log::info("[fillApproverData] Paired Sekretaris found for unit", [
                                'unit' => $unit->name ?? 'Unknown',
                                'sekretaris_name' => $sekretaris->name
                            ]);
                        }
                    }

                    // --- STANDARD MAPPING ---
                    if (isset($roleToPlaceholder[$roleSlug])) {
                        $placeholders = $roleToPlaceholder[$roleSlug];

                        // Fill all nama variants
                        foreach ($placeholders['nama'] as $namaPlaceholder) {
                            $data[$namaPlaceholder] = $approver->name;
                        }

                        // Use NIM/NIP from nim_nip field
                        $nim_nip = $approver->nim_nip ?? '____________________';

                        // Fill all nip_nim variants
                        foreach ($placeholders['nip_nim'] as $nipNimPlaceholder) {
                            $data[$nipNimPlaceholder] = $nim_nip;
                        }
                    }

                    \Log::info("[fillApproverData] Approver found and mapped", [
                        'step' => $step->step_name,
                        'role' => $roleSlug,
                        'approver_name' => $approver->name
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
            foreach ($placeholders['nama'] as $namaPlaceholder) {
                if (!isset($data[$namaPlaceholder])) {
                    $data[$namaPlaceholder] = '____________________';
                }
            }
            foreach ($placeholders['nip_nim'] as $nipNimPlaceholder) {
                if (!isset($data[$nipNimPlaceholder])) {
                    $data[$nipNimPlaceholder] = '____________________';
                }
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
        $placeholders = [
            'nama_ketuaormawa', 'nim_ketuaormawa', 'nama_ketua_ormawa', 'nim_ketua_ormawa',
            'nama_sekretaris_ormawa', 'nim_sekretaris_ormawa',
            'nama_dosenpendamping', 'nip_dosenpendamping', 'nama_dosen_pendamping', 'nip_dosen_pendamping',
            'nama_ketuasenat', 'nim_ketuasenat', 'nama_ketua_senat', 'nim_ketua_senat',
            'nama_sekretaris_senat', 'nim_sekretaris_senat',
            'nama_wadek1', 'nip_wadek1',
            'nama_ketuadepartemen', 'nip_ketuadepartemen', 'nama_ketua_departemen', 'nip_ketua_departemen',
            'nama_kemahasiswaan', 'nip_kemahasiswaan',
            'nama_sumber_daya', 'nip_sumber_daya',
            'nama_sekretaris', 'nim_sekretaris'
        ];

        foreach ($placeholders as $p) {
            if (!isset($data[$p])) {
                $data[$p] = '____________________';
            }
        }
    }
}
