<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('addons', function (Blueprint $table) {
            if (!Schema::hasColumn('addons', 'nama_addon_en')) {
                $table->string('nama_addon_en', 100)->nullable()->after('nama_addon');
            }
        });
    }

    public function down(): void
    {
        Schema::table('addons', function (Blueprint $table) {
            if (Schema::hasColumn('addons', 'nama_addon_en')) {
                $table->dropColumn('nama_addon_en');
            }
        });
    }
};

