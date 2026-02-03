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
        Schema::create('document_logs', function (Blueprint $table) {
            $table->id();

            // Relasi: Log ini untuk dokumen apa?
            $table->foreignId('document_id')->constrained('documents')->onDelete('cascade');

            // Siapa yang melakukan aksi? (Actor)
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            // Jenis Aksi: CREATED, SUBMITTED, APPROVED, REJECTED, RETURNED, RESUBMITTED
            $table->enum('action', ['CREATED', 'SUBMITTED', 'APPROVED', 'REJECTED', 'RETURNED', 'RESUBMITTED']);

            // Catatan/Note dari user (misal: "Tolong perbaiki tanggal")
            $table->text('note')->nullable();

            // Snapshot: Di langkah ke berapa saat aksi ini terjadi?
            $table->integer('step_snapshot')->nullable();

            // Snapshot data dokumen saat itu (JSON)
            // Berguna untuk audit trail dan melihat perubahan data
            $table->json('data_snapshot')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_logs');
    }
};
