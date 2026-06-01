<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Meja extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'nomor_meja',
        'qr_token',
        'status',
        'kapasitas',
    ];

    protected $casts = [
        'kapasitas' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
