<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE pesanans MODIFY status ENUM('booking','menunggu','sedang_diubah','diproses','siap','selesai','batal') NOT NULL DEFAULT 'menunggu'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE pesanans MODIFY status ENUM('booking','menunggu','diproses','siap','selesai','batal') NOT NULL DEFAULT 'menunggu'");
    }
};
