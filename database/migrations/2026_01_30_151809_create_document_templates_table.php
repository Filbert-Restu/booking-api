<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            
            // Template type: executive_summary atau lembar_pengesahan
            $table->enum('template_type', ['executive_summary', 'lembar_pengesahan'])->index();
            
            // Nama template (untuk display)
            $table->string('template_name');
            
            // File path di MinIO
            $table->string('file_path');
            
            // File URL yang bisa diakses
            $table->string('file_url')->nullable();
            
            // Version number untuk tracking perubahan
            $table->integer('version')->default(1);
            
            // Status active - hanya 1 template per type yang bisa active
            $table->boolean('is_active')->default(false)->index();
            
            // Siapa yang upload template ini
            $table->foreignId('uploaded_by')->constrained('users')->onDelete('cascade');
            
            // Description/notes (optional)
            $table->text('description')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Index untuk cepat query active template
            $table->index(['template_type', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_templates');
    }
};
