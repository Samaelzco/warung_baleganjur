<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('timezone:convert-utc-to-local {--timezone=Asia/Makassar : Target timezone (e.g. Asia/Makassar)} {--tables=pesanans : Comma-separated tables (currently: pesanans)} {--execute : Actually write changes (otherwise dry-run)}', function () {
    $tz = (string) $this->option('timezone');
    $tables = collect(explode(',', (string) $this->option('tables')))
        ->map(fn ($t) => trim($t))
        ->filter()
        ->values()
        ->all();

    $driver = DB::getDriverName();
    $offsetSeconds = now($tz)->offset;
    $offsetHours = (int) round($offsetSeconds / 3600);

    if ($offsetHours === 0) {
        $this->warn("Timezone {$tz} has 0 offset hours from UTC. Nothing to do.");
        return 0;
    }

    $direction = $offsetHours > 0 ? '+' : '-';
    $absHours = abs($offsetHours);

    $this->line("Driver: {$driver}");
    $this->line("Target timezone: {$tz}");
    $this->line("Offset: {$direction}{$absHours} hours");
    $this->line('Tables: ' . implode(', ', $tables));
    $this->newLine();

    if (!(bool) $this->option('execute')) {
        $this->warn('Dry-run only. Re-run with --execute to apply changes.');
    }

    $supported = ['pesanans'];
    foreach ($tables as $table) {
        if (!in_array($table, $supported, true)) {
            $this->error("Unsupported table: {$table}");
            return 1;
        }
    }

    if (!(bool) $this->option('execute')) {
        return 0;
    }

    DB::transaction(function () use ($driver, $offsetHours, $tables) {
        foreach ($tables as $table) {
            if ($driver === 'sqlite') {
                $modifier = ($offsetHours > 0 ? '+' : '') . $offsetHours . ' hours';
                DB::statement("
                    UPDATE {$table}
                    SET
                        waktu_pesan = datetime(waktu_pesan, '{$modifier}'),
                        waktu_selesai = CASE WHEN waktu_selesai IS NULL THEN NULL ELSE datetime(waktu_selesai, '{$modifier}') END,
                        created_at = CASE WHEN created_at IS NULL THEN NULL ELSE datetime(created_at, '{$modifier}') END,
                        updated_at = CASE WHEN updated_at IS NULL THEN NULL ELSE datetime(updated_at, '{$modifier}') END
                ");
                continue;
            }

            // MySQL/MariaDB
            DB::statement("
                UPDATE {$table}
                SET
                    waktu_pesan = DATE_ADD(waktu_pesan, INTERVAL {$offsetHours} HOUR),
                    waktu_selesai = CASE WHEN waktu_selesai IS NULL THEN NULL ELSE DATE_ADD(waktu_selesai, INTERVAL {$offsetHours} HOUR) END,
                    created_at = CASE WHEN created_at IS NULL THEN NULL ELSE DATE_ADD(created_at, INTERVAL {$offsetHours} HOUR) END,
                    updated_at = CASE WHEN updated_at IS NULL THEN NULL ELSE DATE_ADD(updated_at, INTERVAL {$offsetHours} HOUR) END
            ");
        }
    });

    $this->info('Done. NOTE: do not run this command twice, or times will be shifted again.');
    return 0;
})->purpose('Convert existing UTC datetimes in DB to a local timezone (one-time fix).');

Artisan::command('menus:thumbnails {--force : Regenerate thumbnails even if they exist}', function () {
    $force = (bool) $this->option('force');

    $menus = \App\Models\Menu::query()
        ->select(['id', 'gambar'])
        ->whereNotNull('gambar')
        ->where('gambar', '!=', '')
        ->get();

    if ($menus->isEmpty()) {
        $this->info('No menu images found.');
        return 0;
    }

    $disk = 'public';
    $storage = \Illuminate\Support\Facades\Storage::disk($disk);
    $widths = [160, 320, 480, 640];

    $this->line('Generating menu thumbnails (' . implode(', ', $widths) . 'px webp) on disk: ' . $disk);
    $this->newLine();

    $bar = $this->output->createProgressBar($menus->count());
    $bar->start();

    $generated = 0;
    foreach ($menus as $menu) {
        $path = ltrim((string) $menu->gambar, '/');
        if ($path === '' || Str::startsWith($path, ['http://', 'https://', '/'])) {
            $bar->advance();
            continue;
        }

        $thumbs = \App\Services\MenuImageService::thumbnailPaths($path, $widths);
        $allExist = collect($thumbs)->every(fn ($p) => $storage->exists($p));

        if (!$force && $allExist) {
            $bar->advance();
            continue;
        }

        \App\Services\MenuImageService::generateThumbnails($path, $widths, $disk);
        $generated++;
        $bar->advance();
    }

    $bar->finish();
    $this->newLine(2);

    $this->info("Done. Updated {$generated} menu(s).");
    return 0;
})->purpose('Generate WebP thumbnails for menu images (customer page optimization).');
