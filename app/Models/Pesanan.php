<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pesanan extends Model
{
    use HasFactory;

    protected $fillable = [
        'meja_id',
        'kode_pesanan',
        'customer_name',
        'customer_note',
        'subtotal',
        'discount_total',
        'tax_total',
        'total_harga',
        'status',
        'metode_pembayaran',
        'dibayar',
        'kembalian',
        'referensi_pembayaran',
        'kasir_id',
        'chef_id',
        'diskon_id',
        'pajak_id',
        'waktu_pesan',
        'waktu_selesai',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'total_harga' => 'decimal:2',
        'dibayar' => 'decimal:2',
        'kembalian' => 'decimal:2',
        'waktu_pesan' => 'datetime',
        'waktu_selesai' => 'datetime',
    ];

    public function meja()
    {
        return $this->belongsTo(Meja::class);
    }

    public function diskon()
    {
        return $this->belongsTo(Diskon::class);
    }

    public function pajak()
    {
        return $this->belongsTo(Pajak::class);
    }

    public function kasir()
    {
        return $this->belongsTo(User::class, 'kasir_id');
    }

    public function chef()
    {
        return $this->belongsTo(User::class, 'chef_id');
    }

    public function details()
    {
        return $this->hasMany(PesananDetail::class);
    }
}
