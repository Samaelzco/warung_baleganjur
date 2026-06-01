<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MenuImageService
{
    public const THUMB_DIR = 'menus/thumbs';
    public const DEFAULT_WIDTHS = [160, 320, 480, 640];

    /**
     * @return array<int, string> width => relative path on disk
     */
    public static function thumbnailPaths(string $originalPath, array $widths = self::DEFAULT_WIDTHS): array
    {
        $originalPath = ltrim($originalPath, '/');

        $basename = pathinfo($originalPath, PATHINFO_FILENAME);
        $safeBasename = Str::slug($basename, '_');

        $paths = [];
        foreach ($widths as $w) {
            $w = (int) $w;
            if ($w <= 0) {
                continue;
            }
            $paths[$w] = self::THUMB_DIR . '/' . $safeBasename . '-' . $w . '.webp';
        }

        return $paths;
    }

    public static function deleteThumbnails(string $originalPath, array $widths = self::DEFAULT_WIDTHS, string $disk = 'public'): void
    {
        $paths = array_values(self::thumbnailPaths($originalPath, $widths));
        if (!empty($paths)) {
            Storage::disk($disk)->delete($paths);
        }
    }

    public static function generateThumbnails(string $originalPath, array $widths = self::DEFAULT_WIDTHS, string $disk = 'public', int $quality = 82): void
    {
        $originalPath = ltrim($originalPath, '/');
        $storage = Storage::disk($disk);

        if (!$storage->exists($originalPath)) {
            return;
        }

        $fullPath = $storage->path($originalPath);
        $bytes = @file_get_contents($fullPath);
        if ($bytes === false || $bytes === '') {
            return;
        }

        $src = @imagecreatefromstring($bytes);
        if (!$src) {
            return;
        }

        $srcW = imagesx($src);
        $srcH = imagesy($src);
        if ($srcW <= 0 || $srcH <= 0) {
            imagedestroy($src);
            return;
        }

        $storage->makeDirectory(self::THUMB_DIR);
        $pathsByWidth = self::thumbnailPaths($originalPath, $widths);

        foreach ($pathsByWidth as $targetW => $thumbPath) {
            $targetW = (int) $targetW;
            $targetW = min($targetW, $srcW);
            $targetH = (int) round($srcH * ($targetW / $srcW));
            $targetH = max(1, $targetH);

            $dst = imagecreatetruecolor($targetW, $targetH);
            if (!$dst) {
                continue;
            }

            // Preserve alpha for PNG/WebP sources.
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            imagefilledrectangle($dst, 0, 0, $targetW, $targetH, $transparent);

            imagecopyresampled($dst, $src, 0, 0, 0, 0, $targetW, $targetH, $srcW, $srcH);

            ob_start();
            $ok = imagewebp($dst, null, $quality);
            $webp = ob_get_clean();

            imagedestroy($dst);

            if ($ok && is_string($webp) && $webp !== '') {
                $storage->put($thumbPath, $webp, 'public');
            }
        }

        imagedestroy($src);
    }
}
