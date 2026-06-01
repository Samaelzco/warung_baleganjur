<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addons', function (Blueprint $table) {
            $table->id();
            $table->string('nama_addon', 100)->unique();
            $table->decimal('harga', 12, 2)->default(0);
            $table->enum('status', ['tersedia', 'habis'])->default('tersedia');
            $table->timestamps();

            $table->index('status');
            $table->index('nama_addon');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addons');
    }
};

