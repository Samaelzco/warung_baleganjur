<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified', 'can:dashboard.access'])
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
    Volt::route('meja', 'meja.index')->middleware('can:meja.access')->name('meja.index');
    Volt::route('meja/print', 'meja.print')->middleware('can:meja.access')->name('meja.print');
    Volt::route('meja/create', 'meja.create')->middleware('can:meja.manage')->name('meja.create');
    Volt::route('meja/{meja}/edit', 'meja.edit')->middleware('can:meja.manage')->name('meja.edit');

    // Kategori Menu CRUD
    Volt::route('kategori', 'kategori.index')->middleware('can:kategori.access')->name('kategori.index');
    Volt::route('kategori/create', 'kategori.create')->middleware('can:kategori.manage')->name('kategori.create');
    Volt::route('kategori/{kategori}/edit', 'kategori.edit')->middleware('can:kategori.manage')->name('kategori.edit');

    // Menu
    Volt::route('menu', 'menu.index')->middleware('can:menu.access')->name('menu.index');
    Volt::route('menu/create', 'menu.create')->middleware('can:menu.manage')->name('menu.create');
    Volt::route('menu/{menu}/edit', 'menu.edit')->middleware('can:menu.manage')->name('menu.edit');

    // Add-ons
    Volt::route('addon', 'addon.index')->middleware('can:addon.access')->name('addon.index');

    // Pajak
    Volt::route('pajak', 'pajak.index')->middleware('can:pajak.access')->name('pajak.index');
    Volt::route('pajak/create', 'pajak.create')->middleware('can:pajak.manage')->name('pajak.create');
    Volt::route('pajak/{pajak}/edit', 'pajak.edit')->middleware('can:pajak.manage')->name('pajak.edit');

    // Diskon
    Volt::route('diskon', 'diskon.index')->middleware('can:diskon.access')->name('diskon.index');
    Volt::route('diskon/create', 'diskon.create')->middleware('can:diskon.manage')->name('diskon.create');
    Volt::route('diskon/{diskon}/edit', 'diskon.edit')->middleware('can:diskon.manage')->name('diskon.edit');

    // Pesanan
    Volt::route('pesanan', 'pesanan.index')->middleware('can:pesanan.access')->name('pesanan.index');
    Volt::route('pesanan/create', 'pesanan.create')->middleware('can:pesanan.manage')->name('pesanan.create');
    Volt::route('pesanan/{pesanan}/edit', 'pesanan.edit')->middleware('can:pesanan.manage')->name('pesanan.edit');

    // Kitchen
    Volt::route('kitchen', 'kitchen.index')->middleware('can:kitchen.access')->name('kitchen.index');

    // Pembayaran
    Volt::route('pembayaran', 'pembayaran.index')->middleware('can:pembayaran.access')->name('pembayaran.index');
    Route::get('pembayaran/{pesanan}/receipt', function (\App\Models\Pesanan $pesanan) {
        $pesanan->loadMissing(['meja', 'details.menu', 'details.addons', 'kasir']);

        abort_unless($pesanan->status === 'selesai' && !blank($pesanan->metode_pembayaran), 404);

        return view('pembayaran.receipt', [
            'pesanan' => $pesanan,
        ]);
    })->middleware('can:pembayaran.access')->name('pembayaran.receipt');

    // Users
    Volt::route('users', 'users.index')->middleware('can:users.access')->name('users.index');
    Volt::route('users/create', 'users.create')->middleware('can:users.manage')->name('users.create');
    Volt::route('users/{user}/edit', 'users.edit')->middleware('can:users.manage')->name('users.edit');

    // Roles
    Volt::route('roles', 'roles.index')->middleware('can:roles.access')->name('roles.index');
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
