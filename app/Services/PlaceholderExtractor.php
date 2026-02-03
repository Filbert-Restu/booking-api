<?php

namespace App\Services;

class PlaceholderExtractor
{
    /**
     * Extract all ${...} placeholders from DOCX file
     */
    public static function extractFromDocx(string $docxPath): array
    {
        try {
            $zip = new \ZipArchive();

            if ($zip->open($docxPath) !== true) {
                \Log::warning('[PlaceholderExtractor] Failed to open DOCX file', [
                    'file' => $docxPath
                ]);
                return [];
            }

            // Read document.xml which contains the actual content
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();

            if ($xml === false) {
                \Log::warning('[PlaceholderExtractor] document.xml not found in DOCX');
                return [];
            }

            // Extract all ${field_name} patterns
            preg_match_all('/\$\{([a-zA-Z0-9_]+)\}/', $xml, $matches);

            if (empty($matches[1])) {
                \Log::info('[PlaceholderExtractor] No placeholders found in template');
                return [];
            }

            // Return unique placeholders
            $placeholders = array_values(array_unique($matches[1]));

            \Log::info('[PlaceholderExtractor] Placeholders extracted', [
                'count' => count($placeholders),
                'placeholders' => $placeholders
            ]);

            return $placeholders;

        } catch (\Exception $e) {
            \Log::error('[PlaceholderExtractor] Failed to extract placeholders', [
                'file' => $docxPath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    /**
     * Get all available fields from DocumentGenerationService
     * This should match the fields in prepareData() method
     */
    public static function getAvailableFields(): array
    {
        return [
            // Room data
            'room_id' => [
                'label' => 'ID Ruangan',
                'source' => 'content.room_id',
                'example' => '1',
                'category' => 'room'
            ],
            'room_code' => [
                'label' => 'Kode Ruangan',
                'source' => 'content.room_code',
                'example' => 'LT1',
                'category' => 'room'
            ],
            'room_name' => [
                'label' => 'Nama Ruangan',
                'source' => 'room.name',
                'example' => 'Laboratorium Teknik 1',
                'category' => 'room'
            ],

            // Booking data
            'booking_date' => [
                'label' => 'Tanggal Peminjaman',
                'source' => 'content.booking_date',
                'example' => '15 Februari 2026',
                'category' => 'booking'
            ],
            'start_time' => [
                'label' => 'Waktu Mulai',
                'source' => 'content.start_time',
                'example' => '08:00',
                'category' => 'booking'
            ],
            'end_time' => [
                'label' => 'Waktu Selesai',
                'source' => 'content.end_time',
                'example' => '12:00',
                'category' => 'booking'
            ],
            'purpose' => [
                'label' => 'Tujuan Peminjaman',
                'source' => 'content.purpose',
                'example' => 'Kegiatan workshop mahasiswa',
                'category' => 'booking'
            ],

            // Ketua Pelaksana
            'ketua_pelaksana_nama' => [
                'label' => 'Nama Ketua Pelaksana',
                'source' => 'content.ketua_pelaksana_nama',
                'example' => 'Ahmad Fauzi',
                'category' => 'ketua_pelaksana'
            ],
            'ketua_pelaksana_nim' => [
                'label' => 'NIM Ketua Pelaksana',
                'source' => 'content.ketua_pelaksana_nim',
                'example' => '12345678901234',
                'category' => 'ketua_pelaksana'
            ],
            'ketua_pelaksana_hp' => [
                'label' => 'No HP Ketua Pelaksana',
                'source' => 'content.ketua_pelaksana_hp',
                'example' => '628123456789',
                'category' => 'ketua_pelaksana'
            ],

            // Event data
            'event_name' => [
                'label' => 'Nama Kegiatan',
                'source' => 'content.event_name',
                'example' => 'Workshop Mobile Development',
                'category' => 'event'
            ],
            'event_nature' => [
                'label' => 'Sifat Kegiatan',
                'source' => 'content.event_nature',
                'example' => 'Teknologi & Pendidikan',
                'category' => 'event'
            ],
            'event_form' => [
                'label' => 'Bentuk Kegiatan',
                'source' => 'content.event_form',
                'example' => 'Workshop/Seminar',
                'category' => 'event'
            ],
            'objectives' => [
                'label' => 'Tujuan Kegiatan',
                'source' => 'content.objectives',
                'example' => 'Meningkatkan skill mahasiswa dalam pengembangan aplikasi mobile',
                'category' => 'event'
            ],
            'benefits' => [
                'label' => 'Manfaat Kegiatan',
                'source' => 'content.benefits',
                'example' => 'Mahasiswa mendapat pengetahuan praktis tentang pembuatan aplikasi',
                'category' => 'event'
            ],
            'target_audience' => [
                'label' => 'Target Peserta',
                'source' => 'content.target_audience',
                'example' => 'Mahasiswa Teknik Informatika semester 5-8',
                'category' => 'event'
            ],
            'schedule' => [
                'label' => 'Jadwal Kegiatan',
                'source' => 'content.schedule',
                'example' => '08:00-12:00 Registrasi, 13:00-17:00 Workshop',
                'category' => 'event'
            ],
            'location' => [
                'label' => 'Lokasi Kegiatan',
                'source' => 'content.location',
                'example' => 'Ruang Aula Lantai 2',
                'category' => 'event'
            ],
            'equipment' => [
                'label' => 'Peralatan yang Dibutuhkan',
                'source' => 'content.equipment',
                'example' => 'Proyektor, Sound system, Meja 20 buah',
                'category' => 'event'
            ],
            'committee_head' => [
                'label' => 'Susunan Panitia',
                'source' => 'content.committee_head',
                'example' => 'Ahmad Fauzi (Ketua), Budi Santoso (Sekretaris)',
                'category' => 'event'
            ],
            'invitations' => [
                'label' => 'Daftar Undangan',
                'source' => 'content.invitations',
                'example' => 'Dekan, Kaprodi, Dosen Pembimbing',
                'category' => 'event'
            ],

            // User data
            'user_name' => [
                'label' => 'Nama Pembuat Dokumen',
                'source' => 'document.creator.name',
                'example' => 'Ahmad Fauzi',
                'category' => 'user'
            ],
            'user_email' => [
                'label' => 'Email Pembuat Dokumen',
                'source' => 'document.creator.email',
                'example' => 'ahmad@student.ac.id',
                'category' => 'user'
            ],

            // Unit data
            'unit_name' => [
                'label' => 'Nama Unit/Organisasi',
                'source' => 'document.unit.name',
                'example' => 'HMIF - Himpunan Mahasiswa Informatika',
                'category' => 'unit'
            ],
            'unit_code' => [
                'label' => 'Kode Unit',
                'source' => 'document.unit.code',
                'example' => 'HMIF',
                'category' => 'unit'
            ],
            'unit_category' => [
                'label' => 'Kategori Unit',
                'source' => 'document.unit.category',
                'example' => 'HMD',
                'category' => 'unit'
            ],

            // Dates
            'created_date' => [
                'label' => 'Tanggal Dibuat',
                'source' => 'document.created_at',
                'example' => '15 Februari 2026',
                'category' => 'date'
            ],
            'submission_date' => [
                'label' => 'Tanggal Disubmit',
                'source' => 'document.submitted_at',
                'example' => '16 Februari 2026',
                'category' => 'date'
            ],
            'current_date' => [
                'label' => 'Tanggal Sekarang',
                'source' => 'now()',
                'example' => '03 Februari 2026',
                'category' => 'date'
            ],

            // Approver placeholders
            'approver_1' => [
                'label' => 'Nama Approver 1',
                'source' => 'workflow.steps[0].approver.name',
                'example' => 'Nama Approver atau garis bawah',
                'category' => 'approver'
            ],
            'approver_2' => [
                'label' => 'Nama Approver 2',
                'source' => 'workflow.steps[1].approver.name',
                'example' => 'Nama Approver atau garis bawah',
                'category' => 'approver'
            ],
            'approver_3' => [
                'label' => 'Nama Approver 3',
                'source' => 'workflow.steps[2].approver.name',
                'example' => 'Nama Approver atau garis bawah',
                'category' => 'approver'
            ],

            // Signature placeholders (images)
            'signature_ketua_pelaksana' => [
                'label' => 'Tanda Tangan Ketua Pelaksana',
                'source' => 'signs.user_id=creator_id',
                'example' => '[Gambar tanda tangan]',
                'category' => 'signature',
                'type' => 'image'
            ],
            'signature_approver_1' => [
                'label' => 'Tanda Tangan Approver 1',
                'source' => 'signs.user_id=approver_1_id',
                'example' => '[Gambar tanda tangan]',
                'category' => 'signature',
                'type' => 'image'
            ],
            'signature_approver_2' => [
                'label' => 'Tanda Tangan Approver 2',
                'source' => 'signs.user_id=approver_2_id',
                'example' => '[Gambar tanda tangan]',
                'category' => 'signature',
                'type' => 'image'
            ],
            'signature_approver_3' => [
                'label' => 'Tanda Tangan Approver 3',
                'source' => 'signs.user_id=approver_3_id',
                'example' => '[Gambar tanda tangan]',
                'category' => 'signature',
                'type' => 'image'
            ],
        ];
    }

    /**
     * Build placeholder metadata by comparing detected vs available
     */
    public static function buildMetadata(array $detectedPlaceholders): array
    {
        $availableFields = self::getAvailableFields();
        $metadata = [];

        foreach ($detectedPlaceholders as $placeholder) {
            $metadata[$placeholder] = [
                'label' => $availableFields[$placeholder]['label'] ?? ucwords(str_replace('_', ' ', $placeholder)),
                'available' => isset($availableFields[$placeholder]),
                'example' => $availableFields[$placeholder]['example'] ?? null,
                'category' => $availableFields[$placeholder]['category'] ?? 'custom',
                'type' => $availableFields[$placeholder]['type'] ?? 'text',
                'source' => $availableFields[$placeholder]['source'] ?? null,
            ];
        }

        return $metadata;
    }
}
