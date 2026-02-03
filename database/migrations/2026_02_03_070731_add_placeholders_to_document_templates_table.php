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
        Schema::table('document_templates', function (Blueprint $table) {
            $table->json('detected_placeholders')->nullable()->after('description')
                ->comment('Auto-extracted placeholders from DOCX file');
            $table->json('placeholder_metadata')->nullable()->after('detected_placeholders')
                ->comment('Metadata for each placeholder (label, available, example, etc)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_templates', function (Blueprint $table) {
            $table->dropColumn(['detected_placeholders', 'placeholder_metadata']);
        });
    }
};
