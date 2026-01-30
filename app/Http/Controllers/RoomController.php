<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\RoomBooking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class RoomController extends Controller
{
    /**
     * List semua ruangan (dengan filter)
     *
     * Query params:
     * - unit_id: Filter berdasarkan unit pengelola
     * - status: ACTIVE/MAINTENANCE/INACTIVE
     * - capacity_min: Minimal kapasitas
     * - available_date: Cek ketersediaan pada tanggal
     * - available_start: Jam mulai untuk cek ketersediaan
     * - available_end: Jam selesai untuk cek ketersediaan
     */
    public function index(Request $request)
    {
        $query = Room::query();

        // Filter by status (optional, jika tidak ada tampilkan semua)
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by minimal capacity
        if ($request->has('capacity_min')) {
            $query->where('capacity', '>=', $request->capacity_min);
        }

        // Search by name or code
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%");
            });
        }

        $rooms = $query->latest()->get();

        // Jika ada filter ketersediaan waktu, cek availability
        if ($request->has(['available_date', 'available_start', 'available_end'])) {
            $rooms = $rooms->map(function ($room) use ($request) {
                $room->is_available = $room->isAvailable(
                    $request->available_date,
                    $request->available_start,
                    $request->available_end
                );
                return $room;
            });
        }

        return response()->json([
            'success' => true,
            'data' => $rooms,
        ]);
    }

    /**
     * Detail ruangan beserta jadwal bookingnya
     */
    public function show(Request $request, $id)
    {
        $room = Room::findOrFail($id);

        // Load upcoming bookings (7 hari ke depan)
        $startDate = Carbon::today();
        $endDate = Carbon::today()->addDays(7);

        $upcomingBookings = RoomBooking::with(['document', 'bookedBy'])
            ->where('room_id', $id)
            ->whereIn('status', ['PENDING', 'APPROVED'])
            ->whereBetween('booking_date', [$startDate, $endDate])
            ->orderBy('booking_date')
            ->orderBy('start_time')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'room' => $room,
                'upcoming_bookings' => $upcomingBookings,
            ],
        ]);
    }

    /**
     * Buat ruangan baru (Admin/Unit Manager)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:rooms,code',
            'capacity' => 'nullable|integer|min:1',
            'facilities' => 'nullable|array',
            'status' => 'nullable|in:ACTIVE,MAINTENANCE,INACTIVE',
            'description' => 'nullable|string',
            'images' => 'nullable|array',
            'images.*' => 'nullable|string', // Path dari upload
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $room = Room::create($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Ruangan berhasil dibuat',
            'data' => $room,
        ], 201);
    }

    /**
     * Update ruangan
     */
    public function update(Request $request, $id)
    {
        $room = Room::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'code' => 'sometimes|required|string|max:50|unique:rooms,code,' . $id,
            'capacity' => 'nullable|integer|min:1',
            'location' => 'nullable|string|max:255',
            'building' => 'nullable|string|max:255',
            'floor' => 'nullable|string|max:50',
            'images.*' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $room->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Ruangan berhasil diupdate',
            'data' => $room,
        ]);
    }

    /**
     * Hapus ruangan (Soft delete)
     */
    public function destroy($id)
    {
        $room = Room::findOrFail($id);

        // Cek apakah ada booking aktif
        $hasActiveBookings = $room->activeBookings()->exists();

        if ($hasActiveBookings) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat menghapus ruangan yang masih memiliki booking aktif',
            ], 400);
        }

        $room->delete();

        return response()->json([
            'success' => true,
            'message' => 'Ruangan berhasil dihapus',
        ]);
    }

    /**
     * Cek ketersediaan ruangan pada waktu tertentu
     *
     * POST /rooms/{id}/check-availability
     * Body: { date, start_time, end_time }
     */
    public function checkAvailability(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date|after_or_equal:today',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $room = Room::findOrFail($id);

        \Log::info('🔍 RoomController.checkAvailability() - REQUEST', [
            'room_id' => $id,
            'room_name' => $room->name,
            'date' => $request->date,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
        ]);

        // First, let's see all bookings for this room on this date
        $allBookings = RoomBooking::where('room_id', $id)
            ->where('booking_date', $request->date)
            ->get();
        
        \Log::info('📋 All bookings for this room/date', [
            'count' => $allBookings->count(),
            'bookings' => $allBookings->map(fn($b) => [
                'id' => $b->id,
                'start_time' => $b->start_time,
                'end_time' => $b->end_time,
                'status' => $b->status,
            ])->toArray(),
        ]);

        $isAvailable = $room->isAvailable(
            $request->date,
            $request->start_time,
            $request->end_time
        );

        \Log::info('📊 Availability FINAL result', [
            'available' => $isAvailable,
        ]);

        // Get conflicting bookings if not available
        $conflicts = null;
        if (!$isAvailable) {
            $conflicts = RoomBooking::with(['document', 'bookedBy'])
                ->where('room_id', $id)
                ->where('booking_date', $request->date)
                ->whereIn('status', ['PENDING', 'APPROVED'])
                ->where('start_time', '<', $request->end_time)
                ->where('end_time', '>', $request->start_time)
                ->get();
            
            \Log::info('⚠️ Conflicts found', [
                'count' => $conflicts->count(),
                'conflicts' => $conflicts->map(fn($b) => [
                    'id' => $b->id,
                    'start' => $b->start_time,
                    'end' => $b->end_time,
                    'status' => $b->status,
                ]),
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'available' => $isAvailable,
                'room' => $room,
                'date' => $request->date,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'conflicts' => $conflicts,
            ],
        ]);
    }

    /**
     * Get jadwal booking ruangan pada range tanggal
     *
     * GET /rooms/{id}/schedule?start_date=2026-01-26&end_date=2026-02-26
     */
    public function schedule(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $room = Room::findOrFail($id);

        $bookings = RoomBooking::with(['document', 'bookedBy'])
            ->where('room_id', $id)
            ->whereBetween('booking_date', [$request->start_date, $request->end_date])
            ->whereIn('status', ['PENDING', 'APPROVED'])
            ->orderBy('booking_date')
            ->orderBy('start_time')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'room' => $room,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'bookings' => $bookings,
            ],
        ]);
    }

    /**
     * Upload foto ruangan
     *
     * POST /rooms/{id}/upload-image
     * Body: { image: file }
     */
    public function uploadImage(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048', // Max 2MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $room = Room::findOrFail($id);

        // Upload file
        $path = $request->file('image')->store('rooms', 'public');

        // Tambahkan ke array images
        $images = $room->images ?? [];
        $images[] = $path;
        $room->images = $images;
        $room->save();

        return response()->json([
            'success' => true,
            'message' => 'Foto berhasil diupload',
            'data' => [
                'path' => $path,
                'url' => Storage::url($path),
                'all_images' => $room->images,
            ],
        ]);
    }

    /**
     * Hapus foto ruangan
     *
     * DELETE /rooms/{id}/images
     * Body: { path: "rooms/xxx.jpg" }
     */
    public function deleteImage(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'path' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $room = Room::findOrFail($id);

        $images = $room->images ?? [];
        $pathToDelete = $request->path;

        // Hapus dari storage
        if (Storage::disk('public')->exists($pathToDelete)) {
            Storage::disk('public')->delete($pathToDelete);
        }

        // Hapus dari array
        $images = array_values(array_filter($images, function ($img) use ($pathToDelete) {
            return $img !== $pathToDelete;
        }));

        $room->images = $images;
        $room->save();

        return response()->json([
            'success' => true,
            'message' => 'Foto berhasil dihapus',
            'data' => [
                'all_images' => $room->images,
            ],
        ]);
    }
}
