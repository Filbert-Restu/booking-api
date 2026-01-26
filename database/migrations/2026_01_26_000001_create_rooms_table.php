<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();

            // Informasi Dasar Ruangan
            $table->string('name'); // "Aula Utama", "Lab Komputer 1"
            $table->string('code')->unique(); // "AU-01", "LK-01"

            // Kapasitas dan Lokasi
            $table->integer('capacity')->nullable(); // Kapasitas orang

            // Fasilitas (JSON Array)
            // ["Proyektor", "AC", "Wifi", "Whiteboard", "Sound System"]
            $table->json('facilities')->nullable();

            // Status Ruangan
            $table->enum('status', ['ACTIVE', 'MAINTENANCE', 'INACTIVE'])
                  ->default('ACTIVE');

            // Deskripsi tambahan
            $table->text('description')->nullable();

            // Images/Photos path (JSON array untuk multiple images)
            // ["rooms/room1_photo1.jpg", "rooms/room1_photo2.jpg"]
            $table->json('images')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
