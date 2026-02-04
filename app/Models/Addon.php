<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Addon extends Model
{
    use HasFactory;

    protected $fillable = [
        'nama_addon',
        'harga',
        'status',
    ];

    protected $casts = [
        'harga' => 'decimal:2',
    ];

    public function menus()
    {
        return $this->belongsToMany(Menu::class, 'addon_menu');
    }
}

