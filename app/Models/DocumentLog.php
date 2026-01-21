<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentLog extends Model
{
    use HasFactory;

    // Gunakan guarded id agar mass assignment (create) lebih mudah
    protected $guarded = ['id'];

    /**
     * Konversi otomatis kolom JSON ke Array PHP.
     * Sangat penting agar Anda bisa langsung akses $log->data_snapshot['title']
     * tanpa perlu json_decode() manual.
     */
    protected $casts = [
        'data_snapshot' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Relasi: Log ini milik dokumen apa?
     */
    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * Relasi: Siapa yang melakukan aksi ini? (Actor)
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Helper: Format pesan log agar enak dibaca di Frontend
     * Contoh penggunaan: $log->formatted_message
     */
    public function getFormattedMessageAttribute()
    {
        $actorName = $this->user->name ?? 'Unknown';

        return match ($this->action) {
            'CREATED' => "{$actorName} membuat dokumen ini.",
            'SUBMITTED' => "{$actorName} mengirim dokumen.",
            'APPROVED' => "{$actorName} menyetujui dokumen.",
            'REJECTED' => "{$actorName} menolak dokumen.",
            'RETURNED' => "{$actorName} mengembalikan dokumen untuk revisi.",
            'RESUBMITTED' => "{$actorName} mengirim ulang revisi.",
            default => "{$actorName} melakukan aksi {$this->action}.",
        };
    }
}
