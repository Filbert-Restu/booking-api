<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'meta_data' => 'array',
        'completed_at' => 'datetime',
    ];

    /**
     * Relasi: Dokumen ini menggunakan workflow apa?
     */
    public function workflow()
    {
        return $this->belongsTo(Workflow::class);
    }

    /**
     * Relasi: Siapa yang sedang memegang dokumen ini?
     */
    public function currentHolder()
    {
        return $this->belongsTo(User::class, 'current_holder_id');
    }

    /**
     * Relasi: Siapa pembuat dokumen ini?
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * Relasi: Dokumen ini berasal dari unit mana?
     */
    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Relasi: Log history dokumen
     */
    public function logs()
    {
        return $this->hasMany(DocumentLog::class)->orderBy('created_at', 'asc');
    }

    /**
     * Scope: Filter dokumen berdasarkan status
     */
    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Dokumen yang sedang dalam proses
     */
    public function scopeInProgress($query)
    {
        return $query->where('status', 'IN_PROGRESS');
    }

    /**
     * Scope: Dokumen yang sudah selesai (approved/rejected)
     */
    public function scopeCompleted($query)
    {
        return $query->whereIn('status', ['PUBLISHED', 'REJECTED']);
    }
}

