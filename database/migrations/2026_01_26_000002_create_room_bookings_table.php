<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_bookings', function (Blueprint $table) {
            $table->id();

            // WAJIB: Booking ini untuk dokumen mana?
            // Setiap peminjaman ruangan HARUS terkait dengan dokumen pengajuan
            $table->foreignId('document_id')
                  ->constrained('documents')
                  ->onDelete('cascade');

            // Ruangan mana yang dipinjam?
            $table->foreignId('room_id')
                  ->constrained('rooms')
                  ->onDelete('cascade');

            // Siapa yang melakukan booking?
            $table->foreignId('booked_by')
                  ->constrained('users')
                  ->onDelete('cascade');

            // --- JADWAL PEMINJAMAN ---
            $table->date('booking_date'); // Tanggal peminjaman
            $table->time('start_time');   // Jam mulai (08:00)
            $table->time('end_time');     // Jam selesai (17:00)

            // Tujuan peminjaman
            $table->text('purpose');

            // --- STATUS BOOKING ---
            $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED', 'CANCELLED', 'COMPLETED'])
                  ->default('PENDING')
                  ->index();

            // Approval tracking
            $table->foreignId('approved_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            // Rejection/cancellation reason
            $table->text('rejection_reason')->nullable();

            // Persiapan khusus yang dibutuhkan
            // Contoh: "Perlu setup 100 kursi", "Perlu sound system tambahan"
            $table->text('special_requirements')->nullable();

            // Jumlah peserta yang diharapkan
            $table->integer('expected_participants')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Index untuk performance query
            $table->index(['booking_date', 'room_id', 'status']);
            $table->index(['document_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_bookings');
    }
};
