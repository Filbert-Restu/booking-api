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
            // Catatan: dokumen draft yang menyertai booking ini SENGAJA tidak dihapus
            // saat booking gagal dibuat (mis. tanggal terlalu dekat, ruangan hanya
            // boleh hari tertentu, dsb). Sebelumnya kegagalan apapun langsung
            // menghapus permanen dokumen yang baru dibuat, memaksa pengguna mengisi
            // ulang seluruh form dari awal hanya karena salah pilih tanggal/ruangan.
            // Dokumen DRAFT tanpa booking tidak berbahaya dibiarkan ada — pengguna
            // tinggal memperbaiki tanggal/ruangan dan mencoba lagi dengan
            // document_id yang sama.

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

    public function weeklyReport(Request $request)
    {
        $report = $this->bookingService->weeklyReport($request->all());

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    public function batchStore(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'document_id' => 'required|exists:documents,id',
            'bookings' => 'required|array|min:1',
            'bookings.*.room_id' => 'required|exists:rooms,id',
            'bookings.*.booking_date' => 'required|date',
            'bookings.*.start_time' => 'required|date_format:H:i',
            'bookings.*.end_time' => 'required|date_format:H:i|after:bookings.*.start_time',
            'bookings.*.purpose' => 'required|string',
            'bookings.*.special_requirements' => 'nullable|string',
            'bookings.*.expected_participants' => 'nullable|integer|min:1',
        ]);

        try {
            $result = $this->bookingService->batchCreateBookings(
                $user,
                $request->document_id,
                $request->bookings
            );

            return response()->json([
                'success' => true,
                'message' => count($result['created']) . ' booking berhasil dibuat',
                'data' => [
                    'created' => collect($result['created'])->map(fn($b) => $b->load(['room', 'document', 'bookedBy'])),
                    'errors' => $result['errors'],
                ],
            ], 201);
        } catch (\Exception $e) {
            $code = $e->getCode() ?: 500;
            if ($code < 100 || $code > 599) $code = 500;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $code);
        }
    }

    public function receipt(Request $request, $id)
    {
        $booking = RoomBooking::findOrFail($id);
        $user = $request->user();

        // Authorization: only booker, admin, or approver
        $isAuthorized = $booking->booked_by === $user->id
            || $user->role->slug === 'admin'
            || $booking->approved_by === $user->id;

        if (!$isAuthorized) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk melihat bukti peminjaman ini',
            ], 403);
        }

        $receiptData = $this->bookingService->getReceiptData($booking);

        return response()->json([
            'success' => true,
            'data' => $receiptData,
        ]);
    }

    public function qrcode(Request $request, $id)
    {
        $booking = RoomBooking::findOrFail($id);

        $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000'));
        $bookingUrl = "{$frontendUrl}/bookings/{$booking->id}";

        try {
            $qrCode = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('png')
                ->size(300)
                ->margin(2)
                ->generate($bookingUrl);

            return response($qrCode, 200, [
                'Content-Type' => 'image/png',
                'Content-Disposition' => 'inline; filename="booking-' . $booking->id . '-qr.png"',
                'Cache-Control' => 'public, max-age=86400',
            ]);
        } catch (\Throwable $e) {
            // Fallback: return URL as JSON if QR library not available
            // (\Throwable, not \Exception — a missing class raises \Error, not \Exception)
            \Log::warning('QR code generation failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => true,
                'data' => [
                    'booking_id' => $booking->id,
                    'url' => $bookingUrl,
                    'message' => 'QR code library not installed. Install simplesoftwareio/simple-qrcode for QR generation.',
                ],
            ]);
        }
    }
}
