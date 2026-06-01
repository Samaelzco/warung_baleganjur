<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pesanan_details', function (Blueprint $table) {
            $table->id();

            $table->foreignId('pesanan_id')->constrained('pesanans')->cascadeOnDelete();
            $table->foreignId('menu_id')->constrained('menus');

            $table->integer('qty');
            $table->decimal('harga', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->text('catatan')->nullable();

            $table->timestamps();

            $table->index('pesanan_id');
            $table->index('menu_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pesanan_details');
    }
};
