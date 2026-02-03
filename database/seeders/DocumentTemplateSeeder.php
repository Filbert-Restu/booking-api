<?php

namespace Database\Seeders;

use App\Models\DocumentTemplate;
use App\Models\User;
use App\Services\PlaceholderExtractor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

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

        // Create test directory if not exists
        $testDir = storage_path('app/test');
        if (!file_exists($testDir)) {
            mkdir($testDir, 0755, true);
            echo "📁 Created directory: $testDir\n";
        }

        // Seed templates
        $templates = [
            [
                'template_type' => 'executive_summary',
                'template_name' => 'Template Executive Summary Test',
                'file_name' => 'executive_summary_test.docx',
                'organization_type' => null,
                'description' => 'Template test untuk executive summary dengan placeholder',
                'content' => $this->getExecutiveSummaryContent(),
            ],
            [
                'template_type' => 'lembar_pengesahan',
                'template_name' => 'Lembar Pengesahan Test (HMD)',
                'file_name' => 'lembar_pengesahan_hmd_test.docx',
                'organization_type' => 'hmd',
                'description' => 'Template test untuk lembar pengesahan HMD dengan placeholder',
                'content' => $this->getLembarPengesahanContent(),
            ],
            [
                'template_type' => 'lembar_pengesahan',
                'template_name' => 'Lembar Pengesahan Test (BEM/UKM)',
                'file_name' => 'lembar_pengesahan_bem_ukm_test.docx',
                'organization_type' => 'bem_ukm',
                'description' => 'Template test untuk lembar pengesahan BEM/UKM dengan placeholder',
                'content' => $this->getLembarPengesahanContent(),
            ],
            [
                'template_type' => 'lembar_pengesahan',
                'template_name' => 'Lembar Pengesahan Test (Senat)',
                'file_name' => 'lembar_pengesahan_senat_test.docx',
                'organization_type' => 'senat',
                'description' => 'Template test untuk lembar pengesahan Senat dengan placeholder',
                'content' => $this->getLembarPengesahanContent(),
            ],
        ];

        foreach ($templates as $template) {
            // Check if template already exists
            $existing = DocumentTemplate::where('template_type', $template['template_type'])
                ->where('template_name', $template['template_name'])
                ->first();

            if ($existing) {
                echo "⏭️ Template already exists: {$template['template_name']}\n";
                continue;
            }

            // Create DOCX file
            $fileName = time() . '_' . $template['file_name'];
            $filePath = 'test/' . $fileName;
            $fullPath = storage_path('app/' . $filePath);

            $this->createDocxFile($fullPath, $template['content']);
            echo "📄 Created DOCX: {$filePath}\n";

            // Extract placeholders
            $detectedPlaceholders = PlaceholderExtractor::extractFromDocx($fullPath);
            $placeholderMetadata = PlaceholderExtractor::buildMetadata($detectedPlaceholders);

            echo "   📌 Detected " . count($detectedPlaceholders) . " placeholders\n";

            // Insert to database
            DocumentTemplate::create([
                'template_type' => $template['template_type'],
                'template_name' => $template['template_name'],
                'file_path' => $filePath,
                'file_url' => Storage::url($filePath),
                'organization_type' => $template['organization_type'],
                'description' => $template['description'],
                'is_active' => true,
                'version' => 1,
                'uploaded_by' => $admin->id,
                'detected_placeholders' => $detectedPlaceholders,
                'placeholder_metadata' => $placeholderMetadata,
            ]);

            echo "✅ Created template: {$template['template_name']}\n";
        }

        echo "\n🎉 Template seeding completed!\n";
        echo "📍 Location: storage/app/test/\n";
    }

    /**
     * Create a valid DOCX file with content
     */
    protected function createDocxFile(string $path, string $content): void
    {
        $zip = new \ZipArchive();

        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \Exception("Cannot create DOCX file: $path");
        }

        // Add [Content_Types].xml
        $zip->addFromString('[Content_Types].xml', $this->getContentTypesXml());

        // Add _rels/.rels
        $zip->addFromString('_rels/.rels', $this->getRelsXml());

        // Add word/document.xml with actual content
        $zip->addFromString('word/document.xml', $this->getDocumentXml($content));

        // Add word/_rels/document.xml.rels
        $zip->addFromString('word/_rels/document.xml.rels', $this->getDocumentRelsXml());

        // Add word/styles.xml (required by PHPWord)
        $zip->addFromString('word/styles.xml', $this->getStylesXml());

        // Add docProps/core.xml
        $zip->addFromString('docProps/core.xml', $this->getCoreXml());

        // Add docProps/app.xml
        $zip->addFromString('docProps/app.xml', $this->getAppXml());

        $zip->close();
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

    /**
     * Get [Content_Types].xml
     */
    protected function getContentTypesXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
    <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
    <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
    <Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
</Types>
XML;
    }

    /**
     * Get _rels/.rels
     */
    protected function getRelsXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
    <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>
</Relationships>
XML;
    }

    /**
     * Get word/document.xml with content
     */
    protected function getDocumentXml(string $content): string
    {
        // Escape XML special characters
        $content = htmlspecialchars($content, ENT_XML1, 'UTF-8');

        // Convert line breaks to paragraphs
        $paragraphs = explode("\n", $content);
        $xmlParagraphs = '';

        foreach ($paragraphs as $para) {
            if (trim($para) === '') {
                $xmlParagraphs .= '<w:p><w:r><w:t xml:space="preserve"> </w:t></w:r></w:p>';
            } else {
                $xmlParagraphs .= '<w:p><w:r><w:t xml:space="preserve">' . $para . '</w:t></w:r></w:p>';
            }
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:body>
        {$xmlParagraphs}
    </w:body>
</w:document>
XML;
    }

    /**
     * Get word/_rels/document.xml.rels
     */
    protected function getDocumentRelsXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>
XML;
    }

    /**
     * Get word/styles.xml
     */
    protected function getStylesXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:docDefaults>
        <w:rPrDefault>
            <w:rPr>
                <w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:eastAsia="Calibri" w:cs="Calibri"/>
                <w:sz w:val="22"/>
            </w:rPr>
        </w:rPrDefault>
        <w:pPrDefault/>
    </w:docDefaults>
</w:styles>
XML;
    }

    /**
     * Get docProps/core.xml
     */
    protected function getCoreXml(): string
    {
        $now = date('Y-m-d\TH:i:s\Z');
        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
    <dc:title>Template Document</dc:title>
    <dc:creator>System</dc:creator>
    <cp:lastModifiedBy>System</cp:lastModifiedBy>
    <dcterms:created xsi:type="dcterms:W3CDTF">{$now}</dcterms:created>
    <dcterms:modified xsi:type="dcterms:W3CDTF">{$now}</dcterms:modified>
</cp:coreProperties>
XML;
    }

    /**
     * Get docProps/app.xml
     */
    protected function getAppXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties">
    <Application>Microsoft Office Word</Application>
    <DocSecurity>0</DocSecurity>
    <ScaleCrop>false</ScaleCrop>
    <Company></Company>
    <LinksUpToDate>false</LinksUpToDate>
    <SharedDoc>false</SharedDoc>
    <HyperlinksChanged>false</HyperlinksChanged>
    <AppVersion>16.0000</AppVersion>
</Properties>
XML;
    }
}
