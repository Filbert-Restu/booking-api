<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Workflow extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * Relasi: Workflow punya banyak langkah (Steps).
     * Kita urutkan otomatis berdasarkan step_order agar tidak acak.
     */
    public function steps()
    {
        return $this->hasMany(WorkflowStep::class)->orderBy('step_order', 'asc');
    }

    /**
     * Relasi: Workflow ini dipakai oleh dokumen apa saja?
     */
    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Scope Helper: Ambil workflow berdasarkan kategori unit (HIMA/UKM/etc)
     * Cara pakai: Workflow::forCategory('HIMA')->get();
     */
    public function scopeForCategory($query, $category)
    {
        return $query->where('applies_to_category', $category);
    }
}
