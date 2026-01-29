<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sign extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'signature',
        'signed_at',
    ];

    protected $dates = [
        'signed_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
