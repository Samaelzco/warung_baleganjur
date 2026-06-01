<?php

namespace Database\Seeders;

use App\Models\Meja;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class MejaSeeder extends Seeder
{
    public function run(): void
    {
        $userId = User::query()
            ->where('email', 'superadmin@gmail.com')
            ->value('id') ?? User::query()->value('id');

        foreach (range(1, 6) as $number) {
            $meja = Meja::query()->firstOrNew([
                'nomor_meja' => 'A'.$number,
            ]);

            if (! $meja->exists) {
                $meja->qr_token = $this->generateUniqueToken();
            }

            $meja->forceFill([
                'user_id' => $userId,
                'status' => 'kosong',
                'kapasitas' => 4,
            ])->save();
        }

        Cache::forget('admin:meja:stats:v1');
    }

    private function generateUniqueToken(): string
    {
        do {
            $token = Str::upper(Str::random(10));
        } while (Meja::query()->where('qr_token', $token)->exists());

        return $token;
    }
}
