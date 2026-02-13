<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Addon extends Model
{
    use HasFactory;

    protected $fillable = [
        'nama_addon',
        'nama_addon_en',
        'harga',
        'status',
    ];

    protected $casts = [
        'harga' => 'decimal:2',
    ];

    public function getNamaAddonLocalizedAttribute(): string
    {
        $en = trim((string) ($this->nama_addon_en ?? ''));

        if (app()->getLocale() === 'en' && $en !== '') {
            return $en;
        }

        return (string) $this->nama_addon;
    }

    public function menus()
    {
        return $this->belongsToMany(Menu::class, 'addon_menu');
    }
}
