<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Room;
use App\Models\RoomBooking;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RoomBookingService
{
    public function listBookings(User $user, array $filters)
    {
        $query = RoomBooking::with(['room', 'document', 'bookedBy.unit', 'approvedBy']);

        if (!empty($filters['my_bookings'])) {
            $query->where('booked_by', $user->id);
        }

        if (!empty($filters['my_unit_bookings']) && $user->unit_id) {
            $query->whereHas('bookedBy', function ($q) use ($user) {
                $q->where('unit_id', $user->unit_id);
            });
        }

        if (isset($filters['document_id'])) {
            $query->where('document_id', $filters['document_id']);
        }

        if (isset($filters['room_id'])) {
            $query->where('room_id', $filters['room_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['date_from'])) {
            $query->where('booking_date', '>=', $filters['date_from']);
        }
        if (isset($filters['date_to'])) {
            $query->where('booking_date', '<=', $filters['date_to']);
        }

        $bookings = $query->latest('booking_date')->latest('start_time')->paginate($filters['per_page'] ?? 15);

        $bookings->getCollection()->transform(function ($booking) {
            $data = $booking->toArray();
            $data['booked_by_user'] = $booking->bookedBy ? [
                'id' => $booking->bookedBy->id,
                'name' => $booking->bookedBy->name,
                'email' => $booking->bookedBy->email,
                'unit_code' => $booking->bookedBy->unit?->code,
                'unit_name' => $booking->bookedBy->unit?->name,
            ] : null;
            return $data;
        });

        return $bookings;
    }

    public function createBooking(User $user, array $data): RoomBooking
    {
        $document = Document::findOrFail($data['document_id']);

        // Cek Tanggal & Hari Sabtu
        try {
            $bookingDate = Carbon::parse($data['booking_date']);
        } catch (\Exception $e) {
            throw new \Exception('Tanggal peminjaman tidak valid', 422);
        }

        if (!$bookingDate->isSaturday()) {
            throw new \Exception('Peminjaman hanya diperbolehkan pada hari Sabtu', 400);
        }

        // Cek Jam Operasional
        $startTime = Carbon::createFromFormat('H:i', $data['start_time']);
        $endTime = Carbon::createFromFormat('H:i', $data['end_time']);
        $open = Carbon::createFromTime(9, 0);
        $close = Carbon::createFromTime(17, 0);

        if ($startTime->lt($open) || $endTime->gt($close) || !$endTime->gt($startTime)) {
            throw new \Exception('Waktu peminjaman harus antara 09:00 - 17:00 dan waktu selesai harus valid', 400);
        }

        // Cek Akses Dokumen
        if ($document->creator_id !== $user->id && $document->current_holder_id !== $user->id) {
            throw new \Exception('Anda tidak memiliki akses ke dokumen ini', 403);
        }

        // Cek Double Booking Dokumen
        $existingBooking = RoomBooking::where('document_id', $data['document_id'])->first();
        if ($existingBooking) {
            throw new \Exception('Dokumen ini sudah memiliki booking ruangan', 400);
        }

        // Cek Ketersediaan Ruangan
        $room = Room::findOrFail($data['room_id']);
        $isAvailable = $room->isAvailable(
            $data['booking_date'],
            $data['start_time'],
            $data['end_time'],
            null,
            $data['document_id']
        );

        if (!$isAvailable) {
            throw new \Exception('Ruangan tidak tersedia pada waktu yang dipilih', 400);
        }

        // Cek Kapasitas
        if ($room->capacity && !empty($data['expected_participants'])) {
            if ($data['expected_participants'] > $room->capacity) {
                throw new \Exception("Jumlah peserta melebihi kapasitas ruangan ({$room->capacity})", 400);
            }
        }

        return RoomBooking::create([
            'document_id' => $data['document_id'],
            'room_id' => $data['room_id'],
            'booked_by' => $user->id,
            'booking_date' => $data['booking_date'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'purpose' => $data['purpose'],
            'special_requirements' => $data['special_requirements'] ?? null,
            'expected_participants' => $data['expected_participants'] ?? null,
            'status' => 'PENDING',
        ]);
    }

    public function updateBooking(RoomBooking $booking, array $data, array $requestParams): RoomBooking
    {
        // Jika ada perubahan waktu/ruangan, cek availability
        $hasTimeChange = isset($requestParams['booking_date']) || isset($requestParams['start_time']) || isset($requestParams['end_time']);
        $hasRoomChange = isset($requestParams['room_id']);

        if ($hasTimeChange || $hasRoomChange) {
            $roomId = $requestParams['room_id'] ?? $booking->room_id;
            $date = $requestParams['booking_date'] ?? $booking->booking_date->format('Y-m-d');
            $startTime = $requestParams['start_time'] ?? substr($booking->start_time, 0, 5);
            $endTime = $requestParams['end_time'] ?? substr($booking->end_time, 0, 5);

            $room = Room::findOrFail($roomId);
            $isAvailable = $room->isAvailable(
                $date,
                $startTime,
                $endTime,
                $booking->id,
                $booking->document_id
            );

            if (!$isAvailable) {
                throw new \Exception('Ruangan tidak tersedia pada waktu yang dipilih', 400);
            }
        }

        $booking->update($data);

        return $booking;
    }

    public function approveBooking(RoomBooking $booking, User $user): bool
    {
        return DB::transaction(function () use ($booking, $user) {
            $result = $booking->approve($user);

            if (!$result) {
                throw new \Exception('Ruangan tidak tersedia. Mungkin sudah ada booking lain pada waktu yang sama.', 400);
            }

            return true;
        });
    }

    public function rejectBooking(RoomBooking $booking, User $user, string $reason): void
    {
        $booking->reject($user, $reason);
    }

    public function cancelBooking(RoomBooking $booking, ?string $reason): void
    {
        $booking->cancel($reason);
    }

    public function completeBooking(RoomBooking $booking): void
    {
        $booking->complete();
    }

    public function deleteBooking(RoomBooking $booking): void
    {
        if ($booking->status === 'APPROVED') {
            throw new \Exception('Booking yang sudah disetujui tidak bisa dihapus. Silakan batalkan terlebih dahulu.', 400);
        }

        $booking->delete();
    }

    public function getStatistics(User $user): array
    {
        $stats = [
            'total' => RoomBooking::count(),
            'pending' => RoomBooking::pending()->count(),
            'approved' => RoomBooking::approved()->count(),
            'rejected' => RoomBooking::where('status', 'REJECTED')->count(),
            'cancelled' => RoomBooking::where('status', 'CANCELLED')->count(),
            'completed' => RoomBooking::where('status', 'COMPLETED')->count(),
            'my_bookings' => RoomBooking::where('booked_by', $user->id)->count(),
            'my_pending_bookings' => RoomBooking::where('booked_by', $user->id)->pending()->count(),
        ];

        if ($user->unit_id) {
            $stats['my_unit_bookings'] = RoomBooking::whereHas('bookedBy', function ($q) use ($user) {
                $q->where('unit_id', $user->unit_id);
            })->count();
        }

        return $stats;
    }

    public function rollbackDocumentIfOwned(Document $document, $user): void
    {
        try {
            if (!$document) return;
            if (!isset($user->id)) return;

            if ($document->creator_id !== $user->id) {
                \Log::info('[Rollback] Skip: request user is not creator', ['document_id' => $document->id ?? null, 'creator_id' => $document->creator_id ?? null, 'request_user_id' => $user->id ?? null]);
                return;
            }
            if ($document->status !== 'DRAFT') {
                \Log::info('[Rollback] Skip: document status is not DRAFT', ['document_id' => $document->id ?? null, 'status' => $document->status ?? null]);
                return;
            }

            DB::transaction(function () use ($document) {
                $cols = [
                    'file_executive_summary',
                    'file_approval_sheet',
                    'file_proposal',
                ];

                foreach ($cols as $col) {
                    $path = $document->{$col} ?? null;
                    if ($path) {
                        if (Storage::disk('private')->exists($path)) {
                            Storage::disk('private')->delete($path);
                            \Log::info('[Rollback] Deleted document file', ['document_id' => $document->id, 'col' => $col, 'path' => $path]);
                        } else {
                            \Log::info('[Rollback] File path not found on disk', ['document_id' => $document->id, 'col' => $col, 'path' => $path]);
                        }
                    } else {
                        \Log::debug('[Rollback] No path set for column', ['document_id' => $document->id, 'col' => $col]);
                    }
                }

                try {
                    $document->logs()->delete();
                } catch (\Exception $e) {
                    \Log::warning('[Rollback] Failed to delete document logs', ['document_id' => $document->id, 'error' => $e->getMessage()]);
                }

                try {
                    $document->forceDelete();
                    \Log::info('[Rollback] Document record permanently deleted after booking failure', ['document_id' => $document->id]);
                } catch (\Exception $e) {
                    \Log::warning('[Rollback] Failed to forceDelete document', ['document_id' => $document->id, 'error' => $e->getMessage()]);
                    try { $document->delete(); } catch (\Exception $_) {}
                }
            });
        } catch (\Exception $e) {
            \Log::warning('[Rollback] Failed to rollback document', ['document_id' => $document->id ?? null, 'error' => $e->getMessage()]);
        }
    }
}
