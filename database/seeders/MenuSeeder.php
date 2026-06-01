<?php

namespace Database\Seeders;

use App\Models\KategoriMenu;
use App\Models\Menu;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('seeders/data/seedmenu_rapi_warung_baleganjur_revisi.csv');

        if (! is_file($path)) {
            throw new RuntimeException("Menu seed CSV not found: {$path}");
        }

        $userId = User::query()
            ->where('email', 'superadmin@gmail.com')
            ->value('id') ?? User::query()->value('id');

        DB::transaction(function () use ($path, $userId): void {
            foreach ($this->rows($path) as $row) {
                $category = KategoriMenu::query()->updateOrCreate(
                    ['nama_kategori' => $row['Kategori']],
                    [
                        'nama_kategori_en' => $row['Category (EN)'] ?: null,
                        'deskripsi' => null,
                        'is_active' => true,
                    ],
                );

                Menu::query()->updateOrCreate(
                    [
                        'kategori_id' => $category->id,
                        'nama_menu' => $row['Nama Menu'],
                    ],
                    [
                        'user_id' => $userId,
                        'nama_menu_en' => $row['Menu Name (EN)'] ?: null,
                        'deskripsi' => $row['Deskripsi (ID)'] ?: null,
                        'deskripsi_en' => $row['Description (EN)'] ?: null,
                        'harga' => $this->parseRupiah($row['Harga']),
                        'gambar' => null,
                        'status' => 'tersedia',
                    ],
                );
            }
        });
    }

    /**
     * @return iterable<array<string, string>>
     */
    private function rows(string $path): iterable
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Unable to open menu seed CSV: {$path}");
        }

        try {
            $headers = fgetcsv($handle);

            if ($headers === false) {
                return;
            }

            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);

            while (($values = fgetcsv($handle)) !== false) {
                if ($values === [null] || $values === false) {
                    continue;
                }

                $row = array_combine($headers, $values);

                if (! is_array($row)) {
                    continue;
                }

                $row = array_map(
                    static fn ($value) => trim((string) $value),
                    $row,
                );

                if (($row['Nama Menu'] ?? '') === '' || ($row['Kategori'] ?? '') === '') {
                    continue;
                }

                yield $row;
            }
        } finally {
            fclose($handle);
        }
    }

    private function parseRupiah(string $value): float
    {
        $numeric = preg_replace('/[^\d]/', '', $value);

        return (float) ($numeric ?: 0);
    }
}
