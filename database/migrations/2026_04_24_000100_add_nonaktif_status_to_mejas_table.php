<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('mejas')) {
            return;
        }

        DB::statement("ALTER TABLE mejas MODIFY COLUMN status ENUM('kosong', 'terisi', 'reservasi', 'nonaktif') NOT NULL DEFAULT 'kosong'");
    }

    public function down(): void
    {
        if (!Schema::hasTable('mejas')) {
            return;
        }

        DB::table('mejas')
            ->where('status', 'nonaktif')
            ->update(['status' => 'kosong']);

        DB::statement("ALTER TABLE mejas MODIFY COLUMN status ENUM('kosong', 'terisi', 'reservasi') NOT NULL DEFAULT 'kosong'");
    }
};
