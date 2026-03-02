<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'capacity',
        'facilities',
        'status',
        'description',
        'images',
    ];

    protected $casts = [
        'facilities' => 'array',
        'images' => 'array',
        'capacity' => 'integer',
    ];

    /**
     * Semua booking untuk ruangan ini
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(RoomBooking::class);
    }

    /**
     * Booking aktif (APPROVED dan belum COMPLETED)
     */
    public function activeBookings(): HasMany
    {
        return $this->hasMany(RoomBooking::class)
                    ->whereIn('status', ['APPROVED', 'PENDING']);
    }

    /**
     * Cek apakah ruangan tersedia pada waktu tertentu
     *
     * @param string $date Format: Y-m-d
     * @param string $startTime Format: H:i
     * @param string $endTime Format: H:i
     * @param int|null $excludeBookingId ID booking yang dikecualikan (untuk update)
     * @param int|null $excludeDocumentId ID document yang dikecualikan (untuk document yang baru submit)
     * @return bool
     */
    public function isAvailable(
        string $date,
        string $startTime,
        string $endTime,
        ?int $excludeBookingId = null,
        ?int $excludeDocumentId = null
    ): bool {
        // Normalize time format to include seconds for proper comparison
        $startTime = strlen($startTime) === 5 ? $startTime . ':00' : $startTime;
        $endTime = strlen($endTime) === 5 ? $endTime . ':00' : $endTime;

        // Check 1: RoomBookings with APPROVED status OR recent PENDING holds
        // PENDING bookings older than configured hold_days are ignored (released)
        $holdDays = config('booking.hold_days', 14);
        $threshold = now()->subDays($holdDays)->toDateTimeString();

        $query = $this->bookings()
            ->where('booking_date', $date)
            ->where(function ($q) use ($threshold) {
                $q->where('status', 'APPROVED')
                  ->orWhere(function ($q2) use ($threshold) {
                      $q2->where('status', 'PENDING')
                         ->where('created_at', '>=', $threshold);
                  });
            })
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime);

        if ($excludeBookingId) {
            $query->where('id', '!=', $excludeBookingId);
        }

        // Exclude bookings belonging to the document being edited (e.g. REVISION mode)
        if ($excludeDocumentId) {
            $query->where(function ($q) use ($excludeDocumentId) {
                $q->whereNull('document_id')
                  ->orWhere('document_id', '!=', $excludeDocumentId);
            });
        }

        $bookingConflicts = $query->get();

        // Check 2: Documents in workflow (IN_PROGRESS, REVISION) with room reservation
        $documentQuery = \DB::table('documents')
            ->whereIn('status', ['IN_PROGRESS', 'REVISION'])
            ->whereNotNull('content');

        // Exclude specific document if provided
        if ($excludeDocumentId) {
            $documentQuery->where('id', '!=', $excludeDocumentId);
        }

        $documentConflicts = $documentQuery->get()
            ->filter(function($doc) use ($date, $startTime, $endTime) {
                $content = json_decode($doc->content, true);

                // Check if this is a room reservation document
                if (!isset($content['room_id']) || !isset($content['booking_date'])) {
                    return false;
                }

                // Check if it's for this room
                if ($content['room_id'] != $this->id) {
                    return false;
                }

                // Check date match
                if ($content['booking_date'] !== $date) {
                    return false;
                }

                // Check time overlap
                $docStart = strlen($content['start_time']) === 5 ? $content['start_time'] . ':00' : $content['start_time'];
                $docEnd = strlen($content['end_time']) === 5 ? $content['end_time'] . ':00' : $content['end_time'];

                return ($docStart < $endTime) && ($docEnd > $startTime);
            });

        $totalConflicts = $bookingConflicts->count() + $documentConflicts->count();

        \Log::info('🔍 Room.isAvailable() - DETAILED', [
            'room_id' => $this->id,
            'date' => $date,
            'start_time_input' => $startTime,
            'end_time_input' => $endTime,
            'booking_conflicts' => $bookingConflicts->count(),
            'document_conflicts' => $documentConflicts->count(),
            'total_conflicts' => $totalConflicts,
            'booking_details' => $bookingConflicts->map(fn($b) => [
                'id' => $b->id,
                'start_time' => $b->start_time,
                'end_time' => $b->end_time,
                'status' => $b->status,
            ])->toArray(),
            'document_details' => $documentConflicts->map(fn($d) => [
                'id' => $d->id,
                'title' => $d->title,
                'status' => $d->status,
            ])->values()->toArray(),
        ]);

        return $totalConflicts === 0;
    }

    /**
     * Scope untuk ruangan yang aktif
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'ACTIVE');
    }

    // NOTE: scopeByUnit removed — rooms table has no unit_id column
}
