<?php

namespace App\Http\Controllers;

use App\Models\RoomBooking;
use App\Models\Document;
use App\Services\RoomBookingService;
use Illuminate\Http\Request;
use App\Http\Requests\RoomBooking\StoreRoomBookingRequest;
use App\Http\Requests\RoomBooking\UpdateRoomBookingRequest;
use App\Http\Requests\RoomBooking\RejectRoomBookingRequest;
use App\Http\Requests\RoomBooking\CancelRoomBookingRequest;

class RoomBookingController extends Controller
{
    protected RoomBookingService $bookingService;

    public function __construct(RoomBookingService $bookingService)
    {
        $this->bookingService = $bookingService;
    }

    public function index(Request $request)
    {
        $bookings = $this->bookingService->listBookings($request->user(), $request->all());

        return response()->json([
            'success' => true,
            'data' => $bookings,
        ]);
    }

    public function show($id)
    {
        $booking = RoomBooking::with([
            'room',
            'document.workflow',
            'bookedBy.unit',
            'approvedBy',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $booking,
        ]);
    }

    public function store(StoreRoomBookingRequest $request)
    {
        $user = $request->user();
        $document = null;

        try {
            $document = Document::findOrFail($request->document_id);

            $booking = $this->bookingService->createBooking($user, $request->all());

            return response()->json([
                'success' => true,
                'message' => 'Booking ruangan berhasil dibuat',
                'data' => $booking->load(['room', 'document', 'bookedBy']),
            ], 201);

        } catch (\Throwable $e) {
            // Rollback dokumen jika ada error
            if ($document) {
                $this->bookingService->rollbackDocumentIfOwned($document, $user);
            } elseif ($request->has('document_id')) {
                $doc = Document::find($request->document_id);
                if ($doc) $this->bookingService->rollbackDocumentIfOwned($doc, $user);
            }

            if ($e instanceof \Illuminate\Validation\ValidationException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $e->errors(),
                ], 422);
            }

            $statusCode = $e->getCode();
            if ($statusCode < 100 || $statusCode > 599) $statusCode = 500;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    public function update(UpdateRoomBookingRequest $request, $id)
    {
        $booking = RoomBooking::findOrFail($id);
        $user = $request->user();

        // Authorization
        if ($booking->booked_by !== $user->id && $user->role->slug !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk mengupdate booking ini',
            ], 403);
        }

        // Status check
        if (in_array($booking->status, ['APPROVED', 'REJECTED', 'COMPLETED'])) {
            return response()->json([
                'success' => false,
                'message' => 'Booking dengan status ' . $booking->status . ' tidak bisa diupdate',
            ], 400);
        }

        try {
            $booking = $this->bookingService->updateBooking($booking, $request->validated(), $request->all());

            return response()->json([
                'success' => true,
                'message' => 'Booking berhasil diupdate',
                'data' => $booking->load(['room', 'document', 'bookedBy']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 400);
        }
    }

    public function approve(Request $request, $id)
    {
        $booking = RoomBooking::findOrFail($id);
        $user = $request->user();

        // Authorization
        if ($user->role->slug !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk menyetujui booking ini',
            ], 403);
        }

        // Status check
        if ($booking->status !== 'PENDING') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya booking dengan status PENDING yang bisa disetujui',
            ], 400);
        }

        try {
            $this->bookingService->approveBooking($booking, $user);

            return response()->json([
                'success' => true,
                'message' => 'Booking berhasil disetujui',
                'data' => $booking->fresh()->load(['room', 'document', 'bookedBy', 'approvedBy']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 500);
        }
    }

    public function reject(RejectRoomBookingRequest $request, $id)
    {
        $booking = RoomBooking::findOrFail($id);
        $user = $request->user();

        // Authorization
        if ($user->role->slug !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk menolak booking ini',
            ], 403);
        }

        // Status check
        if ($booking->status !== 'PENDING') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya booking dengan status PENDING yang bisa ditolak',
            ], 400);
        }

        $this->bookingService->rejectBooking($booking, $user, $request->reason);

        return response()->json([
            'success' => true,
            'message' => 'Booking ditolak',
            'data' => $booking->fresh()->load(['room', 'document', 'bookedBy', 'approvedBy']),
        ]);
    }

    public function cancel(CancelRoomBookingRequest $request, $id)
    {
        $booking = RoomBooking::findOrFail($id);
        $user = $request->user();

        // Authorization
        if ($booking->booked_by !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk membatalkan booking ini',
            ], 403);
        }

        // Status check
        if (in_array($booking->status, ['COMPLETED', 'REJECTED', 'CANCELLED'])) {
            return response()->json([
                'success' => false,
                'message' => 'Booking dengan status ' . $booking->status . ' tidak bisa dibatalkan',
            ], 400);
        }

        $this->bookingService->cancelBooking($booking, $request->reason);

        return response()->json([
            'success' => true,
            'message' => 'Booking berhasil dibatalkan',
            'data' => $booking->fresh()->load(['room', 'document', 'bookedBy']),
        ]);
    }

    public function complete(Request $request, $id)
    {
        $booking = RoomBooking::findOrFail($id);
        $user = $request->user();

        // Authorization
        $isAdmin = $user->role->slug === 'admin';
        $isBooker = $booking->booked_by === $user->id;

        if (!$isAdmin && !$isBooker) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk menyelesaikan booking ini',
            ], 403);
        }

        // Status check
        if ($booking->status !== 'APPROVED') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya booking dengan status APPROVED yang bisa diselesaikan',
            ], 400);
        }

        $this->bookingService->completeBooking($booking);

        return response()->json([
            'success' => true,
            'message' => 'Booking berhasil diselesaikan',
            'data' => $booking->fresh()->load(['room', 'document', 'bookedBy']),
        ]);
    }

    public function destroy($id)
    {
        $booking = RoomBooking::findOrFail($id);
        $user = request()->user();

        // Authorization
        if ($booking->booked_by !== $user->id && $user->role->slug !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk menghapus booking ini',
            ], 403);
        }

        try {
            $this->bookingService->deleteBooking($booking);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Booking berhasil dihapus',
        ]);
    }

    public function statistics(Request $request)
    {
        $stats = $this->bookingService->getStatistics($request->user());

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
