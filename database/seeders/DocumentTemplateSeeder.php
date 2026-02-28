<?php

namespace Database\Seeders;

use App\Models\DocumentTemplate;
use App\Models\User;
use App\Services\PlaceholderExtractor;
use Illuminate\Database\Seeder;

class DocumentTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get admin user for uploaded_by
        $admin = User::whereHas('role', function ($q) {
            $q->where('slug', 'admin');
        })->first();

        if (!$admin) {
            echo "⚠️ Admin user not found. Skipping template seeding.\n";
            return;
        }

        // Seed templates
        $templates = [
            [
                'template_type'     => 'executive_summary',
                'template_name'     => 'Template Executive Summary',
                'organization_type' => null,
                'description'       => 'Template executive summary dengan placeholder',
                'content'           => $this->getExecutiveSummaryContent(),
            ],
            [
                'template_type'     => 'lembar_pengesahan',
                'template_name'     => 'Lembar Pengesahan (HMD)',
                'organization_type' => 'hmd',
                'description'       => 'Template lembar pengesahan HMD dengan placeholder',
                'content'           => $this->getLembarPengesahanContent(),
            ],
            [
                'template_type'     => 'lembar_pengesahan',
                'template_name'     => 'Lembar Pengesahan (BEM/UKM)',
                'organization_type' => 'bem_ukm',
                'description'       => 'Template lembar pengesahan BEM/UKM dengan placeholder',
                'content'           => $this->getLembarPengesahanContent(),
            ],
            [
                'template_type'     => 'lembar_pengesahan',
                'template_name'     => 'Lembar Pengesahan (Senat)',
                'organization_type' => 'senat',
                'description'       => 'Template lembar pengesahan Senat dengan placeholder',
                'content'           => $this->getLembarPengesahanContent(),
            ],
        ];

        foreach ($templates as $template) {
            // Skip if already exists
            $existing = DocumentTemplate::where('template_type', $template['template_type'])
                ->where('template_name', $template['template_name'])
                ->first();

            if ($existing) {
                echo "⏭️ Template already exists: {$template['template_name']}\n";
                continue;
            }

            // Extract placeholders directly from text content — no file creation needed
            $detectedPlaceholders = PlaceholderExtractor::extractFromText($template['content']);
            $placeholderMetadata  = PlaceholderExtractor::buildMetadata($detectedPlaceholders);

            echo "   📌 Detected " . count($detectedPlaceholders) . " placeholders\n";

            // Simpan ke database (tanpa file fisik — template di-upload via admin UI)
            DocumentTemplate::create([
                'template_type'         => $template['template_type'],
                'template_name'         => $template['template_name'],
                'file_name'             => null,
                'file_path'             => null,
                'file_url'              => null,
                'organization_type'     => $template['organization_type'],
                'description'           => $template['description'],
                'is_active'             => true,
                'version'               => 1,
                'uploaded_by'           => $admin->id,
                'detected_placeholders' => $detectedPlaceholders,
                'placeholder_metadata'  => $placeholderMetadata,
            ]);

            echo "✅ Created template: {$template['template_name']}\n";
        }

        echo "\n🎉 Template seeding completed!\n";
    }

    /**
     * Get Executive Summary template content
     */
    protected function getExecutiveSummaryContent(): string
    {
        return <<<'EOT'
EXECUTIVE SUMMARY
PROPOSAL KEGIATAN ${event_name}

1. NAMA KEGIATAN
   ${event_name}

2. BENTUK KEGIATAN
   ${event_form}

3. SIFAT KEGIATAN
   ${event_nature}

4. WAKTU PELAKSANAAN
   Tanggal: ${booking_date}
   Waktu: ${start_time} - ${end_time}
   Tempat: ${room_name}

5. KETUA PELAKSANA
   Nama: ${ketua_pelaksana_nama}
   NIM: ${ketua_pelaksana_nim}
   No. HP: ${ketua_pelaksana_hp}

6. TUJUAN KEGIATAN
   ${objectives}

7. MANFAAT KEGIATAN
   ${benefits}

8. TARGET PESERTA
   ${target_audience}

9. JADWAL KEGIATAN
   ${schedule}

10. LOKASI KEGIATAN
    ${location}

11. PERALATAN YANG DIBUTUHKAN
    ${equipment}

12. SUSUNAN PANITIA
    ${committee_head}

13. UNDANGAN
    ${invitations}


Diajukan oleh:
${user_name}
${unit_name}

Tanggal Pengajuan: ${submission_date}
EOT;
    }

    /**
     * Get Lembar Pengesahan template content
     */
    protected function getLembarPengesahanContent(): string
    {
        return <<<'EOT'
LEMBAR PENGESAHAN
PROPOSAL KEGIATAN ${event_name}

Diajukan oleh:
Nama: ${ketua_pelaksana_nama}
NIM: ${ketua_pelaksana_nim}
Unit: ${unit_name}

Waktu Pelaksanaan:
Tanggal: ${booking_date}
Waktu: ${start_time} - ${end_time}
Tempat: ${room_name}

Tujuan: ${purpose}


Menyetujui,

Ketua Pelaksana
${ketua_pelaksana_nama}

Tanda Tangan:
${signature_ketua_pelaksana}


Approver 1
${approver_1}

Tanda Tangan:
${signature_approver_1}


Approver 2
${approver_2}

Tanda Tangan:
${signature_approver_2}


Approver 3
${approver_3}

Tanda Tangan:
${signature_approver_3}


${unit_name}
${current_date}
EOT;
    }
}
