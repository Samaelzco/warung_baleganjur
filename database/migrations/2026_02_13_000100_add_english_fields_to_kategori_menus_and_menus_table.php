<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('kategori_menus', function (Blueprint $table) {
            if (!Schema::hasColumn('kategori_menus', 'nama_kategori_en')) {
                $table->string('nama_kategori_en', 100)->nullable()->after('nama_kategori');
            }
        });

        Schema::table('menus', function (Blueprint $table) {
            if (!Schema::hasColumn('menus', 'nama_menu_en')) {
                $table->string('nama_menu_en', 150)->nullable()->after('nama_menu');
            }
            if (!Schema::hasColumn('menus', 'deskripsi_en')) {
                $table->text('deskripsi_en')->nullable()->after('deskripsi');
            }
        });
    }

    public function down(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            if (Schema::hasColumn('menus', 'deskripsi_en')) {
                $table->dropColumn('deskripsi_en');
            }
            if (Schema::hasColumn('menus', 'nama_menu_en')) {
                $table->dropColumn('nama_menu_en');
            }
        });

        Schema::table('kategori_menus', function (Blueprint $table) {
            if (Schema::hasColumn('kategori_menus', 'nama_kategori_en')) {
                $table->dropColumn('nama_kategori_en');
            }
        });
    }
};

