<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentTemplate extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
        'version' => 'integer',
        'detected_placeholders' => 'array',
        'placeholder_metadata' => 'array',
    ];

    protected $fillable = [
        'template_type',
        'organization_type',
        'template_name',
        'file_path',
        'file_url',
        'version',
        'is_active',
        'uploaded_by',
        'description',
        'detected_placeholders',
        'placeholder_metadata',
    ];

    /**
     * Relasi: Siapa yang upload template ini?
     */
    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Scope: Filter template berdasarkan tipe
     */
    public function scopeType($query, $type)
    {
        return $query->where('template_type', $type);
    }

    /**
     * Scope: Template yang aktif
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Template yang tidak aktif
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * Helper: Set template ini sebagai active dan non-aktifkan yang lain
     */
    public function setAsActive()
    {
        // Non-aktifkan semua template dengan tipe yang sama
        static::where('template_type', $this->template_type)
              ->where('id', '!=', $this->id)
              ->update(['is_active' => false]);

        // Aktifkan template ini
        $this->update(['is_active' => true]);
    }

    /**
     * Helper: Get active template berdasarkan tipe
     */
    public static function getActiveTemplate($type)
    {
        return static::where('template_type', $type)
                    ->where('is_active', true)
                    ->first();
    }
}
