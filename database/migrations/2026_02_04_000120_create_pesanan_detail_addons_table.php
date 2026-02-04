<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pesanan_detail_addons', function (Blueprint $table) {
            $table->foreignId('pesanan_detail_id')->constrained('pesanan_details')->cascadeOnDelete();
            $table->foreignId('addon_id')->constrained('addons')->restrictOnDelete();
            $table->decimal('harga', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['pesanan_detail_id', 'addon_id']);
            $table->index(['pesanan_detail_id', 'addon_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pesanan_detail_addons');
    }
};

