<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pesanans', function (Blueprint $table) {
            $table->string('status_token', 64)->nullable()->unique()->after('kode_pesanan');
        });

        DB::table('pesanans')
            ->whereNull('status_token')
            ->select(['id'])
            ->orderBy('id')
            ->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    do {
                        $token = Str::random(48);
                    } while (DB::table('pesanans')->where('status_token', $token)->exists());

                    DB::table('pesanans')
                        ->where('id', $row->id)
                        ->update(['status_token' => $token]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('pesanans', function (Blueprint $table) {
            $table->dropUnique(['status_token']);
            $table->dropColumn('status_token');
        });
    }
};
