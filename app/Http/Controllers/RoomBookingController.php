<?php

namespace App\Http\Controllers;

use App\Models\RoomBooking;
use App\Models\Room;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class RoomBookingController extends Controller
{
    /**
     * List booking ruangan
     *
     * Query params:
     * - document_id: Filter by dokumen
     * - room_id: Filter by ruangan
     * - status: Filter by status
     * - date_from: Dari tanggal
     * - date_to: Sampai tanggal
     * - my_bookings: true (booking yang user buat)
     * - my_unit_bookings: true (booking dari unit user)
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = RoomBooking::with(['room', 'document', 'bookedBy.unit', 'approvedBy']);

        // Filter: My bookings
        if ($request->boolean('my_bookings')) {
            $query->where('booked_by', $user->id);
        }

        // Filter: My unit bookings (via hasManyThrough)
        if ($request->boolean('my_unit_bookings') && $user->unit_id) {
            $query->whereHas('bookedBy', function ($q) use ($user) {
                $q->where('unit_id', $user->unit_id);
            });
        }

        // Filter by document
        if ($request->has('document_id')) {
            $query->where('document_id', $request->document_id);
        }

        // Filter by room
        if ($request->has('room_id')) {
            $query->where('room_id', $request->room_id);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->where('booking_date', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->where('booking_date', '<=', $request->date_to);
        }

        $bookings = $query->latest('booking_date')->latest('start_time')->get();

        return response()->json([
            'success' => true,
            'data' => $bookings,
        ]);
    }

    /**
     * Detail booking ruangan
     */
    public function show($id)
    {
        $booking = RoomBooking::with([
            'room.unit',
            'document.workflow',
            'bookedBy.unit',
            'approvedBy',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $booking,
        ]);
    }

    /**
     * Buat booking ruangan baru (WAJIB ada document_id)
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'document_id' => 'required|exists:documents,id',
            'room_id' => 'required|exists:rooms,id',
            'booking_date' => 'required|date|after_or_equal:today',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'purpose' => 'required|string',
            'special_requirements' => 'nullable|string',
            'expected_participants' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Cek apakah user adalah creator atau terlibat dalam document
        $document = Document::findOrFail($request->document_id);
        if ($document->creator_id !== $user->id && $document->current_holder_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses ke dokumen ini',
            ], 403);
        }

        // Cek ketersediaan ruangan
        $room = Room::findOrFail($request->room_id);
        $isAvailable = $room->isAvailable(
            $request->booking_date,
            $request->start_time,
            $request->end_time
        );

        if (!$isAvailable) {
            return response()->json([
                'success' => false,
                'message' => 'Ruangan tidak tersedia pada waktu yang dipilih',
            ], 400);
        }

        // Cek kapasitas ruangan vs expected participants
        if ($room->capacity && $request->expected_participants) {
            if ($request->expected_participants > $room->capacity) {
                return response()->json([
                    'success' => false,
                    'message' => "Jumlah peserta ({$request->expected_participants}) melebihi kapasitas ruangan ({$room->capacity})",
                ], 400);
            }
        }

        // Create booking
        $booking = RoomBooking::create([
            'document_id' => $request->document_id,
            'room_id' => $request->room_id,
            'booked_by' => $user->id,
            'booking_date' => $request->booking_date,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'purpose' => $request->purpose,
            'special_requirements' => $request->special_requirements,
            'expected_participants' => $request->expected_participants,
            'status' => 'PENDING',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Booking ruangan berhasil dibuat',
            'data' => $booking->load(['room', 'document', 'bookedBy']),
        ], 201);
    }

    /**
     * Update booking ruangan
     */
    public function update(Request $request, $id)
    {
        $booking = RoomBooking::findOrFail($id);
        $user = $request->user();

        // Hanya yang booking atau admin yang bisa update
        if ($booking->booked_by !== $user->id && $user->role->slug !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk mengupdate booking ini',
            ], 403);
        }

        // Tidak bisa update booking yang sudah approved/rejected/completed
        if (in_array($booking->status, ['APPROVED', 'REJECTED', 'COMPLETED'])) {
            return response()->json([
                'success' => false,
                'message' => 'Booking dengan status ' . $booking->status . ' tidak bisa diupdate',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'room_id' => 'sometimes|required|exists:rooms,id',
            'booking_date' => 'sometimes|required|date|after_or_equal:today',
            'start_time' => 'sometimes|required|date_format:H:i',
            'end_time' => 'sometimes|required|date_format:H:i|after:start_time',
            'purpose' => 'sometimes|required|string',
            'special_requirements' => 'nullable|string',
            'expected_participants' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Jika ada perubahan waktu/ruangan, cek availability
        if ($request->has(['booking_date', 'start_time', 'end_time']) || $request->has('room_id')) {
            $roomId = $request->room_id ?? $booking->room_id;
            $date = $request->booking_date ?? $booking->booking_date->format('Y-m-d');
            $startTime = $request->start_time ?? $booking->start_time->format('H:i');
            $endTime = $request->end_time ?? $booking->end_time->format('H:i');

            $room = Room::findOrFail($roomId);
            $isAvailable = $room->isAvailable($date, $startTime, $endTime, $booking->id);

            if (!$isAvailable) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ruangan tidak tersedia pada waktu yang dipilih',
                ], 400);
            }
        }

        $booking->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Booking berhasil diupdate',
            'data' => $booking->load(['room', 'document', 'bookedBy']),
        ]);
    }

    /**
     * Approve booking (oleh pengelola ruangan atau admin)
     */
    public function approve(Request $request, $id)
    {
        $booking = RoomBooking::findOrFail($id);
        $user = $request->user();

        // Cek authorization: Hanya admin yang bisa approve
        $isAdmin = $user->role->slug === 'admin';

        if (!$isAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk menyetujui booking ini',
            ], 403);
        }

        // Cek status
        if ($booking->status !== 'PENDING') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya booking dengan status PENDING yang bisa disetujui',
            ], 400);
        }

        DB::beginTransaction();
        try {
            $result = $booking->approve($user);

            if (!$result) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Ruangan tidak tersedia. Mungkin sudah ada booking lain pada waktu yang sama.',
                ], 400);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Booking berhasil disetujui',
                'data' => $booking->fresh()->load(['room', 'document', 'bookedBy', 'approvedBy']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reject booking
     */
    public function reject(Request $request, $id)
    {
        $booking = RoomBooking::findOrFail($id);
        $user = $request->user();

        // Cek authorization: Hanya admin yang bisa reject
        $isAdmin = $user->role->slug === 'admin';

        if (!$isAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk menolak booking ini',
            ], 403);
        }

        // Cek status
        if ($booking->status !== 'PENDING') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya booking dengan status PENDING yang bisa ditolak',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|min:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $booking->reject($user, $request->reason);

        return response()->json([
            'success' => true,
            'message' => 'Booking ditolak',
            'data' => $booking->fresh()->load(['room', 'document', 'bookedBy', 'approvedBy']),
        ]);
    }

    /**
     * Cancel booking (oleh yang booking)
     */
    public function cancel(Request $request, $id)
    {
        $booking = RoomBooking::findOrFail($id);
        $user = $request->user();

        // Hanya yang booking yang bisa cancel
        if ($booking->booked_by !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk membatalkan booking ini',
            ], 403);
        }

        // Tidak bisa cancel yang sudah completed/rejected
        if (in_array($booking->status, ['COMPLETED', 'REJECTED', 'CANCELLED'])) {
            return response()->json([
                'success' => false,
                'message' => 'Booking dengan status ' . $booking->status . ' tidak bisa dibatalkan',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $booking->cancel($request->reason);

        return response()->json([
            'success' => true,
            'message' => 'Booking berhasil dibatalkan',
            'data' => $booking->fresh()->load(['room', 'document', 'bookedBy']),
        ]);
    }

    /**
     * Complete booking (selesai menggunakan ruangan)
     */
    public function complete(Request $request, $id)
    {
        $booking = RoomBooking::findOrFail($id);
        $user = $request->user();

        // Cek authorization
        $isAdmin = $user->role->slug === 'admin';
        $isRoomManager = $booking->room->unit_id === $user->unit_id;
        $isBooker = $booking->booked_by === $user->id;

        if (!$isAdmin && !$isRoomManager && !$isBooker) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk menyelesaikan booking ini',
            ], 403);
        }

        // Hanya booking yang APPROVED yang bisa di-complete
        if ($booking->status !== 'APPROVED') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya booking dengan status APPROVED yang bisa diselesaikan',
            ], 400);
        }

        $booking->complete();

        return response()->json([
            'success' => true,
            'message' => 'Booking berhasil diselesaikan',
            'data' => $booking->fresh()->load(['room', 'document', 'bookedBy']),
        ]);
    }

    /**
     * Delete booking (soft delete)
     */
    public function destroy($id)
    {
        $booking = RoomBooking::findOrFail($id);
        $user = request()->user();

        // Hanya admin atau yang booking yang bisa delete
        if ($booking->booked_by !== $user->id && $user->role->slug !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk menghapus booking ini',
            ], 403);
        }

        // Tidak bisa delete yang sudah approved
        if ($booking->status === 'APPROVED') {
            return response()->json([
                'success' => false,
                'message' => 'Booking yang sudah disetujui tidak bisa dihapus. Silakan batalkan terlebih dahulu.',
            ], 400);
        }

        $booking->delete();

        return response()->json([
            'success' => true,
            'message' => 'Booking berhasil dihapus',
        ]);
    }

    /**
     * Get statistik booking
     */
    public function statistics(Request $request)
    {
        $user = $request->user();

        $stats = [
            'total_bookings' => RoomBooking::count(),
            'pending_bookings' => RoomBooking::pending()->count(),
            'approved_bookings' => RoomBooking::approved()->count(),
            'my_bookings' => RoomBooking::where('booked_by', $user->id)->count(),
            'my_pending_bookings' => RoomBooking::where('booked_by', $user->id)->pending()->count(),
        ];

        // Jika user dari unit pengelola ruangan
        if ($user->unit_id) {
            $stats['unit_rooms_count'] = Room::where('unit_id', $user->unit_id)->count();
            $stats['my_unit_bookings'] = RoomBooking::whereHas('bookedBy', function ($q) use ($user) {
                $q->where('unit_id', $user->unit_id);
            })->count();
        }

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
