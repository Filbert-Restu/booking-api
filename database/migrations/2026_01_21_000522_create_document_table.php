<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();

            // --- A. DATA SURAT ---
            // Nomor surat biasanya null saat draft, baru digenerate saat final approve
            $table->string('nomor_surat')->nullable()->index();
            $table->string('title'); // Perihal / Judul

            // Isi surat bisa text biasa atau JSON jika pakai editor blocks (e.g. Editor.js)
            $table->json('content')->nullable();

            // 1. Executive Summary
            $table->string('file_executive_summary')->nullable();

            // 2. Lembar Pengesahan
            $table->string('file_approval_sheet')->nullable();

            // 3. Proposal
            $table->string('file_proposal')->nullable();

            // --- B. CONTEXT (ASAL USUL) ---
            // PENTING: Surat ini milik Unit mana? (HIMA? BEM? UKM?)
            $table->foreignId('unit_id')
                  ->constrained('units')
                  ->onDelete('cascade');

            // Siapa pembuat surat ini? (Sekretaris/Ketupel)
            $table->foreignId('creator_id')
                  ->constrained('users');

            // --- C. WORKFLOW ENGINE (NAVIGASI) ---
            // Surat ini menggunakan peta/resep workflow yang mana?
            $table->foreignId('workflow_id')
                  ->constrained('workflows');

            // Penanda Langkah: Sekarang sedang di langkah nomor berapa? (1, 2, 3...)
            // Default 1 (biasanya langkah submit awal)
            $table->integer('current_step_order')->default(1);

            // --- D. STATE (POSISI BOLA) ---
            // Siapa User yang sedang memegang surat ini SEKARANG?
            $table->foreignId('current_holder_id')
                  ->nullable() // Nullable jika status FINAL/DRAFT belum submit
                  ->constrained('users');

            // --- E. STATUS UTAMA ---
            // Enum Strings: 'DRAFT', 'IN_PROGRESS', 'REVISION', 'APPROVED', 'REJECTED'
            $table->enum('status', ['DRAFT', 'IN_PROGRESS', 'REVISION', 'APPROVED', 'REJECTED'])->default('DRAFT')->index();

            // Tanggal selesai (Diset saat status jadi APPROVED/REJECTED)
            $table->timestamp('completed_at')->nullable();

            // Metadata JSON (Opsional)
            // Berguna menyimpan data form dinamis (misal: Tanggal Acara, Total Anggaran)
            // agar bisa di-query tanpa nambah kolom tabel.
            $table->json('meta_data')->nullable();

            $table->timestamps();
            $table->softDeletes(); // Fitur tong sampah (Restoreable)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
