<?php

namespace Database\Seeders;

use App\Models\DocumentTemplate;
use App\Models\User;
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

        // Create document-templates directory if not exists
        $templateDir = storage_path('app/document-templates');
        if (!file_exists($templateDir)) {
            mkdir($templateDir, 0755, true);
            echo "📁 Created directory: $templateDir\n";
        }

        // Seed templates
        $templates = [
            [
                'template_type' => 'executive_summary',
                'template_name' => 'Template Executive Summary 2026',
                'file_path' => 'document-templates/1770096494_executive_summary_executive.docx',
                'organization_type' => null,
                'description' => 'test',
            ],
            [
                'template_type' => 'lembar_pengesahan',
                'template_name' => 'Lembar Pengesahan HMD',
                'file_path' => 'document-templates/1770096526_lembar_pengesahan_LEMBAR PENGESAHAN.docx',
                'organization_type' => null,
                'description' => 'test',
            ],
        ];

        foreach ($templates as $template) {
            // Check if template already exists
            $existing = DocumentTemplate::where('template_type', $template['template_type'])
                ->where('organization_type', $template['organization_type'])
                ->first();

            if ($existing) {
                echo "⏭️ Template already exists: {$template['template_name']}\n";
                continue;
            }

            // Create placeholder DOCX file
            $fullPath = storage_path('app/' . $template['file_path']);
            if (!file_exists($fullPath)) {
                // Create a simple text file as placeholder
                // In production, replace with actual DOCX files
                $placeholderContent = "PLACEHOLDER - Replace with actual DOCX template\n\n";
                $placeholderContent .= "Template Type: {$template['template_type']}\n";
                $placeholderContent .= "Organization: " . ($template['organization_type'] ?? 'All') . "\n\n";
                $placeholderContent .= "This is a placeholder file. Upload the actual .docx template file.\n";

                file_put_contents($fullPath, $placeholderContent);
                echo "📄 Created placeholder: {$template['file_path']}\n";
            }

            // Insert to database
            DocumentTemplate::create([
                'template_type' => $template['template_type'],
                'template_name' => $template['template_name'],
                'file_path' => $template['file_path'],
                'organization_type' => $template['organization_type'],
                'description' => $template['description'],
                'is_active' => true,
                'version' => 1,
                'uploaded_by' => $admin->id,
            ]);

            echo "✅ Created template: {$template['template_name']}\n";
        }

        echo "\n🎉 Template seeding completed!\n";
        echo "⚠️ IMPORTANT: Replace placeholder files with actual DOCX templates\n";
        echo "📍 Location: storage/app/document-templates/\n";
    }
}
