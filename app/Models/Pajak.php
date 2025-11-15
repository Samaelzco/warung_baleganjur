<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pajak extends Model
{
    use HasFactory;

    protected $fillable = [
        'nama',
        'persentase',
        'is_active',
    ];

    protected $casts = [
        'persentase' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}

