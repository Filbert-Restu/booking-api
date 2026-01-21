<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Jenis Workflow (Resep)
        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // "Pengajuan Dana HIMA", "Surat Izin UKM"
            $table->text('description')->nullable();

            // Workflow ini muncul untuk unit tipe apa?
            // Misal: Kalau user dari unit 'UKM' login, dia cuma lihat workflow kategori 'UKM'
            $table->string('applies_to_category');

            $table->timestamps();
        });

        // 2. Langkah-langkah Workflow (The Logic Core)
        Schema::create('workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('workflows')->onDelete('cascade');

            $table->integer('step_order'); // 1, 2, 3
            $table->string('step_name');   // "Review Ketua", "Persetujuan Senat"

            // LOGIC PENCARIAN USER (Swimlanes)

            // Siapa targetnya? (Slug dari tabel roles)
            $table->string('target_role_slug');

            // Dimana mencarinya?
            // SELF: Di unit pengirim
            // PARENT: Di unit induk (Prodi/Fakultas)
            // FACULTY_LEADER: Cari di unit Fakultas (Puncak)
            // SPECIFIC_CATEGORY: Cari unit lain (misal Senat)
            $table->enum('scope_type', ['SELF', 'PARENT', 'FACULTY_LEADER', 'SPECIFIC_CATEGORY']);

            // Jika scope SPECIFIC_CATEGORY, cari kategori apa? (Misal: 'SENAT')
            $table->string('target_category_lookup')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_steps');
        Schema::dropIfExists('workflows');
    }
};
