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

        // Validasi minimal hari pengajuan
        $minDays = config('booking.min_booking_days', 8);
        $daysUntilBooking = Carbon::today()->diffInDays($bookingDate, false);
        if ($daysUntilBooking < $minDays) {
            throw new \Exception("Peminjaman harus diajukan minimal {$minDays} hari kalender sebelum hari-H", 400);
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

        // Cek duplikat: apakah dokumen ini sudah punya booking untuk ruangan yang sama
        $existingBooking = RoomBooking::where('document_id', $data['document_id'])
            ->where('room_id', $data['room_id'])
            ->whereNotIn('status', ['CANCELLED', 'REJECTED'])
            ->first();
        if ($existingBooking) {
            throw new \Exception('Dokumen ini sudah memiliki booking untuk ruangan yang sama', 400);
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

    /**
     * Rekapitulasi penggunaan ruangan per minggu
     */
    public function weeklyReport(array $filters): array
    {
        $weekStart = isset($filters['week_start'])
            ? Carbon::parse($filters['week_start'])->startOfWeek()
            : Carbon::now()->startOfWeek();

        $weekEnd = $weekStart->copy()->endOfWeek();

        $rooms = Room::where('status', 'ACTIVE')->orderBy('name')->get();

        $bookings = RoomBooking::with(['room', 'bookedBy.unit'])
            ->whereBetween('booking_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->whereIn('status', ['APPROVED', 'PENDING', 'COMPLETED'])
            ->get();

        $roomUsage = [];
        foreach ($rooms as $room) {
            $roomBookings = $bookings->where('room_id', $room->id);

            $totalHours = $roomBookings->sum(function ($b) {
                $start = Carbon::parse($b->start_time);
                $end = Carbon::parse($b->end_time);
                return $start->diffInMinutes($end) / 60;
            });

            $roomUsage[] = [
                'room_id' => $room->id,
                'room_name' => $room->name,
                'room_code' => $room->code,
                'total_bookings' => $roomBookings->count(),
                'approved' => $roomBookings->where('status', 'APPROVED')->count(),
                'pending' => $roomBookings->where('status', 'PENDING')->count(),
                'completed' => $roomBookings->where('status', 'COMPLETED')->count(),
                'total_hours' => round($totalHours, 1),
                'bookings' => $roomBookings->map(fn($b) => [
                    'id' => $b->id,
                    'date' => $b->booking_date->format('Y-m-d'),
                    'day' => $b->booking_date->translatedFormat('l'),
                    'start_time' => substr($b->start_time, 0, 5),
                    'end_time' => substr($b->end_time, 0, 5),
                    'purpose' => $b->purpose,
                    'status' => $b->status,
                    'booked_by' => $b->bookedBy?->name,
                    'unit' => $b->bookedBy?->unit?->name,
                ])->values(),
            ];
        }

        $summary = [
            'week_start' => $weekStart->toDateString(),
            'week_end' => $weekEnd->toDateString(),
            'total_bookings' => $bookings->count(),
            'total_approved' => $bookings->where('status', 'APPROVED')->count(),
            'total_pending' => $bookings->where('status', 'PENDING')->count(),
            'total_completed' => $bookings->where('status', 'COMPLETED')->count(),
            'rooms_used' => $bookings->pluck('room_id')->unique()->count(),
            'total_rooms' => $rooms->count(),
        ];

        return [
            'summary' => $summary,
            'room_usage' => $roomUsage,
        ];
    }

    /**
     * Batch create bookings (multi-waktu/multi-ruangan)
     */
    public function batchCreateBookings(User $user, int $documentId, array $bookingSlots): array
    {
        $document = Document::findOrFail($documentId);

        if ($document->creator_id !== $user->id && $document->current_holder_id !== $user->id) {
            throw new \Exception('Anda tidak memiliki akses ke dokumen ini', 403);
        }

        $createdBookings = [];
        $errors = [];

        DB::beginTransaction();

        try {
            foreach ($bookingSlots as $index => $slot) {
                try {
                    $slotData = array_merge($slot, ['document_id' => $documentId]);
                    $booking = $this->createBooking($user, $slotData);
                    $createdBookings[] = $booking;
                } catch (\Exception $e) {
                    $errors[] = [
                        'slot_index' => $index,
                        'room_id' => $slot['room_id'] ?? null,
                        'booking_date' => $slot['booking_date'] ?? null,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            if (!empty($errors) && empty($createdBookings)) {
                DB::rollBack();
                throw new \Exception('Semua slot booking gagal dibuat', 400);
            }

            DB::commit();

            return [
                'created' => $createdBookings,
                'errors' => $errors,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Generate receipt data for printing
     */
    public function getReceiptData(RoomBooking $booking): array
    {
        $booking->load(['room', 'document', 'bookedBy.unit', 'approvedBy']);

        return [
            'booking_id' => $booking->id,
            'booking_date' => $booking->booking_date->format('d F Y'),
            'day' => $booking->booking_date->translatedFormat('l'),
            'start_time' => substr($booking->start_time, 0, 5),
            'end_time' => substr($booking->end_time, 0, 5),
            'duration_hours' => $booking->getDurationInHours(),
            'purpose' => $booking->purpose,
            'special_requirements' => $booking->special_requirements,
            'expected_participants' => $booking->expected_participants,
            'status' => $booking->status,

            'room' => [
                'name' => $booking->room->name,
                'code' => $booking->room->code,
                'capacity' => $booking->room->capacity,
            ],

            'booked_by' => [
                'name' => $booking->bookedBy?->name,
                'email' => $booking->bookedBy?->email,
                'unit' => $booking->bookedBy?->unit?->name,
            ],

            'approved_by' => $booking->approvedBy ? [
                'name' => $booking->approvedBy->name,
                'approved_at' => $booking->approved_at?->format('d F Y H:i'),
            ] : null,

            'document' => $booking->document ? [
                'id' => $booking->document->id,
                'title' => $booking->document->title,
                'status' => $booking->document->status,
            ] : null,

            'created_at' => $booking->created_at->format('d F Y H:i'),
            'qr_url' => url("/api/room-bookings/{$booking->id}/qrcode"),
            'receipt_url' => url("/api/room-bookings/{$booking->id}/receipt"),
        ];
    }
}

