<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pesanans', function (Blueprint $table) {
            $table->id();

            $table->foreignId('meja_id')->constrained('mejas');

            $table->string('kode_pesanan', 20)->unique();
            $table->string('customer_name', 100);
            $table->string('customer_note', 255)->nullable();

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('total_harga', 12, 2)->default(0);

            $table->enum('status', ['menunggu', 'diproses', 'siap', 'selesai', 'batal'])->default('menunggu');
            $table->enum('metode_pembayaran', ['tunai', 'transfer', 'qris'])->nullable();

            $table->decimal('dibayar', 12, 2)->nullable();
            $table->decimal('kembalian', 12, 2)->nullable();

            $table->foreignId('kasir_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('chef_id')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('diskon_id')->nullable()->constrained('diskons')->nullOnDelete();
            $table->foreignId('pajak_id')->nullable()->constrained('pajaks')->nullOnDelete();

            $table->dateTime('waktu_pesan');
            $table->dateTime('waktu_selesai')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('waktu_pesan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pesanans');
    }
};

