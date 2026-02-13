<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KategoriMenu extends Model
{
    use HasFactory;

    protected $fillable = [
        'nama_kategori',
        'nama_kategori_en',
        'deskripsi',
    ];

    public function getNamaKategoriLocalizedAttribute(): string
    {
        $en = trim((string) ($this->nama_kategori_en ?? ''));

        if (app()->getLocale() === 'en' && $en !== '') {
            return $en;
        }

        return (string) $this->nama_kategori;
    }
}
