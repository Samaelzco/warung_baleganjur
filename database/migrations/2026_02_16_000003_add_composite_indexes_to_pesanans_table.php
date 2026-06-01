<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pesanans', function (Blueprint $table) {
            // Optimizes kitchen/order list queries that filter by status and sort by waktu_pesan + id.
            $table->index(['status', 'waktu_pesan', 'id']);

            // Optimizes customer/status queries that scope by meja_id and active status, ordered by waktu_pesan.
            $table->index(['meja_id', 'status', 'waktu_pesan']);
        });
    }

    public function down(): void
    {
        Schema::table('pesanans', function (Blueprint $table) {
            $table->dropIndex(['status', 'waktu_pesan', 'id']);
            $table->dropIndex(['meja_id', 'status', 'waktu_pesan']);
        });
    }
};

