<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\RoomBooking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
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
        $query = Room::query()
            ->select('id', 'name', 'code', 'capacity', 'status', 'facilities');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('capacity_min')) {
            $query->where('capacity', '>=', $request->capacity_min);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $rooms = $query->latest()->paginate($request->input('per_page', 15));

        if ($request->has(['available_date', 'available_start', 'available_end'])) {
            $rooms->getCollection()->transform(function ($room) use ($request) {
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
        $room = Room::select('id', 'name', 'code', 'capacity', 'location', 'building', 'floor', 'status', 'facilities', 'images')
            ->findOrFail($id);

        // Load upcoming bookings (7 hari ke depan)
        $startDate = Carbon::today();
        $endDate = Carbon::today()->addDays(7);

        $upcomingBookings = RoomBooking::with([
                'document:id,title,status',
                'bookedBy:id,name,email'
            ])
            ->select('id', 'document_id', 'room_id', 'booked_by', 'booking_date', 'start_time', 'end_time', 'purpose', 'status')
            ->where('room_id', $id)
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
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:rooms,code',
            'capacity' => 'nullable|integer|min:1',
            'facilities' => 'nullable|array',
            'status' => 'nullable|in:ACTIVE,MAINTENANCE,INACTIVE',
            'description' => 'nullable|string',
            'images' => 'nullable|array',
            'images.*' => 'file|image|mimes:png,jpg,jpeg|max:5120',
        ]);

        $uploadedPaths = [];

        DB::beginTransaction();

        try {
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $path = $image->store('rooms', 'private');
                    $uploadedPaths[] = $path;
                }
            }

            $validated['images'] = $uploadedPaths;
            $room = Room::create($validated);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Ruangan berhasil dibuat',
                'data' => $room,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            // 4. Cleanup (Rollback File)
            // Jika DB gagal, hapus file yang telanjur ter-upload ke MinIO
            foreach ($uploadedPaths as $path) {
                if (Storage::disk('private')->exists($path)) {
                    Storage::disk('private')->delete($path);
                }
            }

            \Log::error('Room Store Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat ruangan. Silakan coba lagi.',
            ], 500);
        }
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
            'facilities' => 'nullable|array',
            'description' => 'nullable|string',
            'status' => 'nullable|in:ACTIVE,MAINTENANCE,INACTIVE',
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
            'exclude_document_id' => 'nullable|integer', // Parameter baru untuk exclude document
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $room = Room::findOrFail($id);
        $excludeDocumentId = $request->exclude_document_id;

        \Log::debug('RoomController.checkAvailability()', [
            'room_id' => $id,
            'date' => $request->date,
            'exclude_document_id' => $excludeDocumentId,
        ]);

        $isAvailable = $room->isAvailable(
            $request->date,
            $request->start_time,
            $request->end_time,
            null, // excludeBookingId - tidak digunakan disini
            $excludeDocumentId // excludeDocumentId - untuk edit mode
        );

        \Log::debug('Availability result', ['available' => $isAvailable]);

        // Get conflicting bookings if not available
        $conflicts = null;
        if (!$isAvailable) {
            $conflictQuery = RoomBooking::with(['document', 'bookedBy'])
                ->where('room_id', $id)
                ->where('booking_date', $request->date)
                ->where('start_time', '<', $request->end_time)
                ->where('end_time', '>', $request->start_time);

            // Exclude bookings from the document being edited
            if ($excludeDocumentId) {
                $conflictQuery->where('document_id', '!=', $excludeDocumentId);
            }

            $conflicts = $conflictQuery->get();

            \Log::debug('Conflicts found', [
                'count' => $conflicts->count(),
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

        $room = Room::select('id', 'name', 'code', 'capacity')
            ->findOrFail($id);

        $bookings = RoomBooking::with([
                'document:id,title,status,content',
                'bookedBy:id,name,email,unit_id',
                'bookedBy.unit:id,name,code'
            ])
            ->select('id', 'document_id', 'room_id', 'booked_by', 'booking_date', 'start_time', 'end_time', 'purpose', 'status')
            ->where('room_id', $id)
            ->whereBetween('booking_date', [$request->start_date, $request->end_date])
            ->orderBy('booking_date')
            ->orderBy('start_time')
            ->get()
            ->map(function ($booking) {
                $data = $booking->toArray();
                // bookedBy relation conflicts with booked_by column (both serialize to booked_by)
                // So we add user info under a separate key
                $data['booked_by_user'] = $booking->bookedBy ? [
                    'id' => $booking->bookedBy->id,
                    'name' => $booking->bookedBy->name,
                    'email' => $booking->bookedBy->email,
                    'unit_code' => $booking->bookedBy->unit?->code,
                    'unit_name' => $booking->bookedBy->unit?->name,
                ] : null;
                return $data;
            });

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
        $path = $request->file('image')->store('rooms', 'private');

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
                'url' => route('api.rooms.image', ['id' => $room->id, 'path' => urlencode($path)]),
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

        // Validasi path: pastikan path ada di array images room ini
        if (!in_array($pathToDelete, $images)) {
            return response()->json([
                'success' => false,
                'message' => 'Path gambar tidak valid untuk ruangan ini',
            ], 400);
        }

        // Hapus dari storage
        if (Storage::disk('private')->exists($pathToDelete)) {
            Storage::disk('private')->delete($pathToDelete);
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

    /**
     * Serve room image from private storage
     *
     * GET /rooms/{id}/image?path=rooms/xxx.jpg
     */
    public function serveImage(Request $request, $id)
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
        $path = $request->path;

        // Verify path belongs to this room
        if (!in_array($path, $room->images ?? [])) {
            return response()->json([
                'success' => false,
                'message' => 'Image not found in room',
            ], 404);
        }

        // Check if file exists in private storage
        if (!Storage::disk('private')->exists($path)) {
            return response()->json([
                'success' => false,
                'message' => 'File not found in storage',
            ], 404);
        }

        // Serve file from MinIO
        $fileContent = Storage::disk('private')->get($path);
        $mimeType = Storage::disk('private')->mimeType($path) ?: 'image/jpeg';

        return response($fileContent, 200, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'public, max-age=31536000',
        ]);
    }
}
