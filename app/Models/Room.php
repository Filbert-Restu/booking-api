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
     * @return bool
     */
    public function isAvailable(
        string $date,
        string $startTime,
        string $endTime,
        ?int $excludeBookingId = null
    ): bool {
        $query = $this->bookings()
            ->where('booking_date', $date)
            ->whereIn('status', ['PENDING', 'APPROVED'])
            ->where(function ($q) use ($startTime, $endTime) {
                // Cek overlap waktu
                $q->where(function ($q2) use ($startTime, $endTime) {
                    // Start time di antara booking yang ada
                    $q2->where('start_time', '<=', $startTime)
                       ->where('end_time', '>', $startTime);
                })->orWhere(function ($q2) use ($startTime, $endTime) {
                    // End time di antara booking yang ada
                    $q2->where('start_time', '<', $endTime)
                       ->where('end_time', '>=', $endTime);
                })->orWhere(function ($q2) use ($startTime, $endTime) {
                    // Booking yang ada di antara start dan end
                    $q2->where('start_time', '>=', $startTime)
                       ->where('end_time', '<=', $endTime);
                });
            });

        if ($excludeBookingId) {
            $query->where('id', '!=', $excludeBookingId);
        }

        return $query->count() === 0;
    }

    /**
     * Scope untuk ruangan yang aktif
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'ACTIVE');
    }

    /**
     * Scope untuk ruangan berdasarkan unit
     */
    public function scopeByUnit($query, int $unitId)
    {
        return $query->where('unit_id', $unitId);
    }
}
