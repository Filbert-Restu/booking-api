<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkflowStep extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * Relasi: Langkah ini milik workflow mana?
     */
    public function workflow()
    {
        return $this->belongsTo(Workflow::class);
    }

    /**
     * Helper: Cek apakah langkah ini membutuhkan pencarian lintas unit?
     */
    public function isCrossUnit()
    {
        return $this->scope_type === 'SPECIFIC_CATEGORY';
    }

    /**
     * Helper: Cek apakah ini langkah persetujuan Pimpinan Fakultas?
     */
    public function isFacultyLevel()
    {
        return $this->scope_type === 'FACULTY_LEADER';
    }
}
