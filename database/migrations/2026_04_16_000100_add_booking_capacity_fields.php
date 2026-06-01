<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mejas', function (Blueprint $table) {
            $table->unsignedSmallInteger('kapasitas')->default(4)->after('status');
        });

        Schema::table('pesanans', function (Blueprint $table) {
            $table->unsignedSmallInteger('jumlah_orang')->default(1)->after('customer_note');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE pesanans MODIFY status ENUM('booking','menunggu','diproses','siap','selesai','batal') NOT NULL DEFAULT 'menunggu'");
        }

        Schema::table('pesanans', function (Blueprint $table) {
            $table->index(['meja_id', 'status', 'waktu_pesan', 'id'], 'pesanans_meja_status_waktu_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('pesanans', function (Blueprint $table) {
            $table->dropIndex('pesanans_meja_status_waktu_id_idx');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE pesanans MODIFY status ENUM('menunggu','diproses','siap','selesai','batal') NOT NULL DEFAULT 'menunggu'");
        }

        Schema::table('pesanans', function (Blueprint $table) {
            $table->dropColumn('jumlah_orang');
        });

        Schema::table('mejas', function (Blueprint $table) {
            $table->dropColumn('kapasitas');
        });
    }
};
