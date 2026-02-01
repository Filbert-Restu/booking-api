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
            // Add organization_type column after template_type
            // Only applicable for lembar_pengesahan type: hmd, bem_ukm, senat
            $table->enum('organization_type', ['hmd', 'bem_ukm', 'senat'])
                  ->nullable()
                  ->after('template_type')
                  ->comment('Jenis organisasi untuk lembar pengesahan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_templates', function (Blueprint $table) {
            $table->dropColumn('organization_type');
        });
    }
};
