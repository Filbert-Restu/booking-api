<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomBooking extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'document_id',
        'room_id',
        'booked_by',
        'booking_date',
        'start_time',
        'end_time',
        'purpose',
        'status',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'special_requirements',
        'expected_participants',
    ];

    protected $casts = [
        'booking_date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'approved_at' => 'datetime',
        'expected_participants' => 'integer',
    ];

    /**
     * Dokumen yang terkait dengan booking ini (WAJIB)
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * Ruangan yang dibooking
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * User yang melakukan booking
     */
    public function bookedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'booked_by');
    }

    /**
     * User yang approve booking
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Helper: Get unit peminjam via user yang booking
     */
    public function getUnitAttribute(): ?Unit
    {
        return $this->bookedBy?->unit;
    }

    /**
     * Approve booking
     */
    public function approve(User $approver): bool
    {
        // Cek apakah ruangan masih available
        if (!$this->room->isAvailable(
            $this->booking_date->format('Y-m-d'),
            $this->start_time->format('H:i'),
            $this->end_time->format('H:i'),
            $this->id
        )) {
            return false;
        }

        $this->update([
            'status' => 'APPROVED',
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);

        return true;
    }

    /**
     * Reject booking
     */
    public function reject(User $rejector, string $reason): void
    {
        $this->update([
            'status' => 'REJECTED',
            'approved_by' => $rejector->id,
            'approved_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }

    /**
     * Cancel booking
     */
    public function cancel(string $reason = null): void
    {
        $this->update([
            'status' => 'CANCELLED',
            'rejection_reason' => $reason,
        ]);
    }

    /**
     * Complete booking (setelah acara selesai)
     */
    public function complete(): void
    {
        $this->update([
            'status' => 'COMPLETED',
        ]);
    }

    /**
     * Scope untuk booking yang pending
     */
    public function scopePending($query)
    {
        return $query->where('status', 'PENDING');
    }

    /**
     * Scope untuk booking yang approved
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'APPROVED');
    }

    /**
     * Scope untuk booking pada tanggal tertentu
     */
    public function scopeOnDate($query, string $date)
    {
        return $query->whereDate('booking_date', $date);
    }

    /**
     * Scope untuk booking pada range tanggal
     */
    public function scopeDateRange($query, string $startDate, string $endDate)
    {
        return $query->whereBetween('booking_date', [$startDate, $endDate]);
    }

    /**
     * Cek apakah booking ini bentrok dengan booking lain
     */
    public function hasConflict(): bool
    {
        return !$this->room->isAvailable(
            $this->booking_date->format('Y-m-d'),
            $this->start_time->format('H:i'),
            $this->end_time->format('H:i'),
            $this->id
        );
    }

    /**
     * Get duration in hours
     */
    public function getDurationInHours(): float
    {
        $start = \Carbon\Carbon::parse($this->start_time);
        $end = \Carbon\Carbon::parse($this->end_time);

        return $start->diffInHours($end, true);
    }
}
