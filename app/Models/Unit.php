<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * Relasi: Siapa induk unit ini? (Self Join)
     * HIMA -> Parentnya Prodi
     */
    public function parent()
    {
        return $this->belongsTo(Unit::class, 'parent_id');
    }

    /**
     * Relasi: Siapa saja bawahan unit ini?
     * Prodi -> Childrennya [HIMA A, HIMA B]
     */
    public function children()
    {
        return $this->hasMany(Unit::class, 'parent_id');
    }

    /**
     * Relasi: User siapa saja yang ada di unit ini?
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * Relasi: Dokumen apa saja yang keluar dari unit ini?
     */
    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Relasi: Booking ruangan yang dilakukan unit ini (via users)
     */
    public function roomBookings()
    {
        return $this->hasManyThrough(
            RoomBooking::class,
            User::class,
            'unit_id',    // Foreign key on users table
            'booked_by',  // Foreign key on room_bookings table
            'id',         // Local key on units table
            'id'          // Local key on users table
        );
    }
}
