<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('profile.edit');
    Volt::route('settings/password', 'settings.password')->name('user-password.edit');
    Volt::route('settings/appearance', 'settings.appearance')->name('appearance.edit');

    Volt::route('settings/two-factor', 'settings.two-factor')
        ->middleware(
            when(
                Features::canManageTwoFactorAuthentication()
                    && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword'),
                ['password.confirm'],
                [],
            ),
        )
        ->name('two-factor.show');

    // Tables (Meja) CRUD
    Volt::route('meja', 'meja.index')->name('meja.index');
    Volt::route('meja/print', 'meja.print')->name('meja.print');
    Volt::route('meja/create', 'meja.create')->name('meja.create');
    Volt::route('meja/{meja}/edit', 'meja.edit')->name('meja.edit');

    // Kategori Menu CRUD
    Volt::route('kategori', 'kategori.index')->name('kategori.index');
    Volt::route('kategori/create', 'kategori.create')->name('kategori.create');
    Volt::route('kategori/{kategori}/edit', 'kategori.edit')->name('kategori.edit');

    // Menu
    Volt::route('menu', 'menu.index')->name('menu.index');
    Volt::route('menu/create', 'menu.create')->name('menu.create');
    Volt::route('menu/{menu}/edit', 'menu.edit')->name('menu.edit');

    // Pajak
    Volt::route('pajak', 'pajak.index')->name('pajak.index');
    Volt::route('pajak/create', 'pajak.create')->name('pajak.create');
    Volt::route('pajak/{pajak}/edit', 'pajak.edit')->name('pajak.edit');

    // Diskon
    Volt::route('diskon', 'diskon.index')->name('diskon.index');
    Volt::route('diskon/create', 'diskon.create')->name('diskon.create');
    Volt::route('diskon/{diskon}/edit', 'diskon.edit')->name('diskon.edit');

});

// QR image (PNG) generator (public path to avoid auth/cookie issues when embedding in <img>)
Route::get('meja/qr', function (\Illuminate\Http\Request $request) {
    $token = (string) $request->query('token', '');

    if ($token === '' && $request->filled('meja')) {
        $meja = \App\Models\Meja::findOrFail($request->query('meja'));
        $token = $meja->qr_token;
    }

    abort_unless($token !== '', 404);

    $host = request()->getSchemeAndHttpHost();
    $url = $host . '/order/' . $token;

    $size = (int) $request->query('size', 512);
    if ($size < 120) { $size = 120; }
    if ($size > 2048) { $size = 2048; }

    $format = strtolower((string) $request->query('format', 'svg'));

    if ($format === 'png') {
        try {
            $renderer = new \BaconQrCode\Renderer\ImageRenderer(
                new \BaconQrCode\Renderer\RendererStyle\RendererStyle($size),
                new \BaconQrCode\Renderer\Image\PngImageBackEnd()
            );
            $writer = new \BaconQrCode\Writer($renderer);
            $data = $writer->writeString($url);

            $response = response($data, 200, [
                'Content-Type' => 'image/png',
                'Cache-Control' => 'public, max-age=86400',
            ]);

            if ($request->boolean('download')) {
                $filename = 'qr-meja-' . substr($token, 0, 8) . '.png';
                $response->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
            }

            return $response;
        } catch (\Throwable $e) {
            // Fall through to SVG below if PNG backend not available
        }
    }

    // Default SVG output (browser-friendly, no ext dependencies)
    $renderer = new \BaconQrCode\Renderer\ImageRenderer(
        new \BaconQrCode\Renderer\RendererStyle\RendererStyle($size),
        new \BaconQrCode\Renderer\Image\SvgImageBackEnd()
    );
    $writer = new \BaconQrCode\Writer($renderer);
    $svg = $writer->writeString($url);

    $response = response($svg, 200, [
        'Content-Type' => 'image/svg+xml',
        'Cache-Control' => 'public, max-age=86400',
    ]);

    if ($request->boolean('download')) {
        $filename = 'qr-meja-' . substr($token, 0, 8) . '.svg';
        $response->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    return $response;
})->name('meja.qr');
