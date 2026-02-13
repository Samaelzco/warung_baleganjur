<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
    use HasFactory;

    protected $fillable = [
        'kategori_id',
        'nama_menu',
        'nama_menu_en',
        'deskripsi',
        'deskripsi_en',
        'harga',
        'gambar',
        'status',
    ];

    public function getNamaMenuLocalizedAttribute(): string
    {
        $en = trim((string) ($this->nama_menu_en ?? ''));

        if (app()->getLocale() === 'en' && $en !== '') {
            return $en;
        }

        return (string) $this->nama_menu;
    }

    public function getDeskripsiLocalizedAttribute(): ?string
    {
        $en = trim((string) ($this->deskripsi_en ?? ''));

        if (app()->getLocale() === 'en' && $en !== '') {
            return $en;
        }

        return $this->deskripsi;
    }

    public function kategori()
    {
        return $this->belongsTo(KategoriMenu::class, 'kategori_id');
    }

    public function pesananDetails()
    {
        return $this->hasMany(PesananDetail::class);
    }

    public function addons()
    {
        return $this->belongsToMany(Addon::class, 'addon_menu');
    }
}
