<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('kategori_menus', 'is_active')) {
            return;
        }

        Schema::table('kategori_menus', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('deskripsi');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('kategori_menus', 'is_active')) {
            return;
        }

        Schema::table('kategori_menus', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
