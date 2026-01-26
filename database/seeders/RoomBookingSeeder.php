<?php

namespace Database\Seeders;

use App\Models\Room;
use App\Models\Document;
use App\Models\RoomBooking;
use App\Models\Unit;
use App\Models\User;
use App\Models\Workflow;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class RoomBookingSeeder extends Seeder
{
    public function run(): void
    {
        // 1. ROOMS - Buat beberapa ruangan
        $fakultas = Unit::where('code', 'FT')->first();
        $prodiIF = Unit::where('code', 'PRODI-IF')->first();
        $prodiTE = Unit::where('code', 'PRODI-TE')->first();

        $aulaUtama = Room::create([
            'name' => 'Aula Utama',
            'code' => 'AU-01',
            'capacity' => 200,
            'facilities' => ['Proyektor', 'Sound System', 'AC', 'Wifi', 'Panggung'],
            'status' => 'ACTIVE',
            'description' => 'Aula utama untuk acara besar fakultas - Gedung A Lantai 1',
        ]);

        $labKomputer1 = Room::create([
            'name' => 'Lab Komputer 1',
            'code' => 'LK-01',
            'capacity' => 40,
            'facilities' => ['Proyektor', 'AC', 'Wifi', '40 PC'],
            'status' => 'ACTIVE',
            'description' => 'Lab komputer untuk praktikum - Gedung B Lantai 2',
        ]);

        $ruangRapat = Room::create([
            'name' => 'Ruang Rapat A',
            'code' => 'RR-A01',
            'capacity' => 20,
            'facilities' => ['Proyektor', 'AC', 'Wifi', 'Whiteboard', 'Meja Conference'],
            'status' => 'ACTIVE',
            'description' => 'Ruang rapat untuk pertemuan - Gedung A Lantai 2',
        ]);

        $labElektro = Room::create([
            'name' => 'Lab Elektronika',
            'code' => 'LE-01',
            'capacity' => 30,
            'facilities' => ['Proyektor', 'AC', 'Peralatan Lab'],
            'status' => 'ACTIVE',
            'description' => 'Lab untuk praktikum elektronika - Gedung C Lantai 1',
        ]);

        $aula2 = Room::create([
            'name' => 'Aula Lantai 2',
            'code' => 'AU-02',
            'capacity' => 100,
            'facilities' => ['Proyektor', 'AC', 'Sound System'],
            'status' => 'MAINTENANCE',
            'description' => 'Sedang renovasi - Gedung A Lantai 2',
        ]);

        // 2. DOCUMENTS - Buat beberapa dokumen pengajuan
        $workflow = Workflow::first();
        $himaIF = Unit::where('code', 'HIMA-IF')->first();
        $sekretarisHima = User::where('email', 'sekretaris.hima.if@student.ac.id')->first();
        $ketuaHima = User::where('email', 'ketua.hima.if@student.ac.id')->first();

        $doc1 = Document::create([
            'title' => 'Pengajuan Workshop Web Development',
            'content' => 'Permohonan peminjaman ruangan untuk workshop web development',
            'workflow_id' => $workflow->id,
            'unit_id' => $himaIF->id,
            'creator_id' => $sekretarisHima->id,
            'current_holder_id' => $ketuaHima->id,
            'current_step_order' => 1,
            'status' => 'IN_PROGRESS',
            'meta_data' => [
                'event_type' => 'Workshop',
                'budget' => 5000000,
            ],
        ]);

        $doc2 = Document::create([
            'title' => 'Pengajuan Seminar Teknologi',
            'content' => 'Permohonan peminjaman aula untuk seminar teknologi',
            'workflow_id' => $workflow->id,
            'unit_id' => $himaIF->id,
            'creator_id' => $sekretarisHima->id,
            'current_holder_id' => $ketuaHima->id,
            'current_step_order' => 1,
            'status' => 'IN_PROGRESS',
            'meta_data' => [
                'event_type' => 'Seminar',
                'budget' => 10000000,
            ],
        ]);

        $doc3 = Document::create([
            'title' => 'Rapat Koordinasi HIMA',
            'content' => 'Peminjaman ruang rapat untuk koordinasi pengurus HIMA',
            'workflow_id' => $workflow->id,
            'unit_id' => $himaIF->id,
            'creator_id' => $sekretarisHima->id,
            'current_holder_id' => $sekretarisHima->id,
            'current_step_order' => 1,
            'status' => 'DRAFT',
        ]);

        // 3. ROOM BOOKINGS - Buat beberapa booking contoh

        // Booking 1: Workshop (APPROVED)
        $booking1 = RoomBooking::create([
            'document_id' => $doc1->id,
            'room_id' => $labKomputer1->id,
            'booked_by' => $sekretarisHima->id,
            'booking_date' => Carbon::now()->addDays(7)->format('Y-m-d'),
            'start_time' => '09:00',
            'end_time' => '15:00',
            'purpose' => 'Workshop Web Development untuk mahasiswa HIMA Informatika',
            'special_requirements' => 'Perlu setup proyektor dan pastikan semua PC berfungsi',
            'expected_participants' => 35,
            'status' => 'APPROVED',
            'approved_by' => User::where('email', 'kaprodi.if@ft.ac.id')->first()->id,
            'approved_at' => now(),
        ]);

        // Booking 2: Seminar (PENDING)
        $booking2 = RoomBooking::create([
            'document_id' => $doc2->id,
            'room_id' => $aulaUtama->id,
            'booked_by' => $sekretarisHima->id,
            'booking_date' => Carbon::now()->addDays(14)->format('Y-m-d'),
            'start_time' => '08:00',
            'end_time' => '17:00',
            'purpose' => 'Seminar Nasional Teknologi Informasi 2026',
            'special_requirements' => 'Setup panggung, sound system profesional, 200 kursi, backdrop',
            'expected_participants' => 200,
            'status' => 'PENDING',
        ]);

        // Booking 3: Rapat (PENDING)
        $booking3 = RoomBooking::create([
            'document_id' => $doc3->id,
            'room_id' => $ruangRapat->id,
            'booked_by' => $sekretarisHima->id,
            'booking_date' => Carbon::now()->addDays(3)->format('Y-m-d'),
            'start_time' => '13:00',
            'end_time' => '15:00',
            'purpose' => 'Rapat koordinasi pengurus HIMA bulanan',
            'expected_participants' => 15,
            'status' => 'PENDING',
        ]);

        // Booking 4: Past booking (COMPLETED)
        $booking4 = RoomBooking::create([
            'document_id' => $doc1->id,
            'room_id' => $ruangRapat->id,
            'booked_by' => $sekretarisHima->id,
            'booking_date' => Carbon::now()->subDays(5)->format('Y-m-d'),
            'start_time' => '10:00',
            'end_time' => '12:00',
            'purpose' => 'Rapat persiapan workshop',
            'expected_participants' => 10,
            'status' => 'COMPLETED',
            'approved_by' => User::where('email', 'kaprodi.if@ft.ac.id')->first()->id,
            'approved_at' => Carbon::now()->subDays(7),
        ]);

        $this->command->info('✅ Room & Booking Seeder berhasil!');
        $this->command->info('📊 5 Rooms, 3 Documents, 4 Room Bookings dibuat');
        $this->command->info('🔑 Document IDs: 1, 2, 3');
        $this->command->info('🏢 Room IDs: 1-5 (AU-01, LK-01, RR-A01, LE-01, AU-02)');
    }
}
