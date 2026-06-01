<?php

namespace App\Services;

use BaconQrCode\Renderer\Image\PngImageBackEnd;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Response;

class QrCodeService
{
    public function svg(string $data, int $size = 512): string
    {
        $writer = new Writer(new ImageRenderer(
            new RendererStyle($this->normalizeSize($size)),
            new SvgImageBackEnd()
        ));

        return $writer->writeString($data);
    }

    public function png(string $data, int $size = 512): ?string
    {
        try {
            $writer = new Writer(new ImageRenderer(
                new RendererStyle($this->normalizeSize($size)),
                new PngImageBackEnd()
            ));

            return $writer->writeString($data);
        } catch (\Throwable) {
            return null;
        }
    }

    public function response(string $data, int $size = 512, string $format = 'svg', ?string $downloadFilename = null): Response
    {
        $format = strtolower($format);

        if ($format === 'png') {
            $png = $this->png($data, $size);
            if ($png !== null) {
                $response = response($png, 200, [
                    'Content-Type' => 'image/png',
                    'Cache-Control' => 'public, max-age=86400',
                ]);

                if ($downloadFilename) {
                    $response->header('Content-Disposition', 'attachment; filename="' . $downloadFilename . '"');
                }

                return $response;
            }
        }

        $response = response($this->svg($data, $size), 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=86400',
        ]);

        if ($downloadFilename) {
            $response->header('Content-Disposition', 'attachment; filename="' . $downloadFilename . '"');
        }

        return $response;
    }

    protected function normalizeSize(int $size): int
    {
        return max(min($size, 2048), 120);
    }
}
