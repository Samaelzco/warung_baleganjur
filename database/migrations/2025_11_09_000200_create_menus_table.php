<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kategori_id')->constrained('kategori_menus')->cascadeOnDelete();
            $table->string('nama_menu', 100);
            $table->text('deskripsi')->nullable();
            $table->decimal('harga', 10, 2);
            $table->string('gambar', 255)->nullable();
            $table->enum('status', ['tersedia', 'habis'])->default('tersedia');
            $table->timestamps();

            $table->index(['kategori_id', 'status']);
            $table->index('nama_menu');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menus');
    }
};

