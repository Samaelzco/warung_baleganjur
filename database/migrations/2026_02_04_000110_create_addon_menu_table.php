<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addon_menu', function (Blueprint $table) {
            $table->foreignId('menu_id')->constrained('menus')->cascadeOnDelete();
            $table->foreignId('addon_id')->constrained('addons')->cascadeOnDelete();

            $table->unique(['menu_id', 'addon_id']);
            $table->index(['menu_id', 'addon_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addon_menu');
    }
};

