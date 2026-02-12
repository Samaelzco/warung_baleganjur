<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt;
use Illuminate\Support\Carbon;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Volt::route('dashboard', 'dashboard.index')
    ->middleware(['auth', 'verified', 'can:dashboard.access'])
    ->name('dashboard');

Route::get('dashboard/report', function (\Illuminate\Http\Request $request) {
    $startRaw = (string) $request->query('start', '');
    $endRaw = (string) $request->query('end', '');

    try {
        $start = $startRaw !== '' ? Carbon::parse($startRaw)->startOfDay() : now()->startOfDay();
    } catch (\Throwable) {
        $start = now()->startOfDay();
    }

    try {
        $end = $endRaw !== '' ? Carbon::parse($endRaw)->endOfDay() : now()->endOfDay();
    } catch (\Throwable) {
        $end = now()->endOfDay();
    }

    if ($end->lt($start)) {
        [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
    }

    $paidQuery = \App\Models\Pesanan::query()
        ->where('status', 'selesai')
        ->whereNotNull('metode_pembayaran')
        ->whereBetween('waktu_selesai', [$start, $end]);

    $paidCount = (int) (clone $paidQuery)->count();
    $revenue = (float) (clone $paidQuery)->sum('total_harga');
    $discountTotal = (float) (clone $paidQuery)->sum('discount_total');
    $taxTotal = (float) (clone $paidQuery)->sum('tax_total');
    $avgOrder = $paidCount > 0 ? ($revenue / $paidCount) : 0;

    $byMethod = (clone $paidQuery)
        ->selectRaw('metode_pembayaran as method, COUNT(*) as cnt, COALESCE(SUM(total_harga),0) as total, COALESCE(SUM(dibayar),0) as paid_in, COALESCE(SUM(kembalian),0) as change_out')
        ->groupBy('method')
        ->get()
        ->keyBy('method');

    $methodKeys = ['tunai', 'qris', 'transfer'];
    $methodLabels = [
        'tunai' => __('Cash'),
        'qris' => __('QRIS'),
        'transfer' => __('Transfer'),
    ];

    $methodRows = collect($methodKeys)->map(function (string $k) use ($byMethod, $methodLabels) {
        return [
            'key' => $k,
            'label' => $methodLabels[$k] ?? $k,
            'count' => (int) ($byMethod[$k]->cnt ?? 0),
            'total' => (float) ($byMethod[$k]->total ?? 0),
            'paid_in' => (float) ($byMethod[$k]->paid_in ?? 0),
            'change_out' => (float) ($byMethod[$k]->change_out ?? 0),
        ];
    })->all();

    $topMenus = \Illuminate\Support\Facades\DB::table('pesanan_details')
        ->join('pesanans', 'pesanans.id', '=', 'pesanan_details.pesanan_id')
        ->join('menus', 'menus.id', '=', 'pesanan_details.menu_id')
        ->where('pesanans.status', 'selesai')
        ->whereNotNull('pesanans.metode_pembayaran')
        ->whereBetween('pesanans.waktu_selesai', [$start, $end])
        ->groupBy('menus.id', 'menus.nama_menu')
        ->selectRaw('menus.id, menus.nama_menu, COALESCE(SUM(pesanan_details.qty),0) as qty, COALESCE(SUM(pesanan_details.subtotal),0) as total')
        ->orderByDesc('qty')
        ->limit(10)
        ->get();

    $topAddons = \Illuminate\Support\Facades\DB::table('pesanan_detail_addons')
        ->join('pesanan_details', 'pesanan_details.id', '=', 'pesanan_detail_addons.pesanan_detail_id')
        ->join('pesanans', 'pesanans.id', '=', 'pesanan_details.pesanan_id')
        ->join('addons', 'addons.id', '=', 'pesanan_detail_addons.addon_id')
        ->where('pesanans.status', 'selesai')
        ->whereNotNull('pesanans.metode_pembayaran')
        ->whereBetween('pesanans.waktu_selesai', [$start, $end])
        ->groupBy('addons.id', 'addons.nama_addon')
        ->selectRaw('addons.id, addons.nama_addon, COALESCE(SUM(pesanan_details.qty),0) as qty, COALESCE(SUM(pesanan_detail_addons.harga * pesanan_details.qty),0) as total')
        ->orderByDesc('qty')
        ->limit(10)
        ->get();

    $payments = (clone $paidQuery)
        ->with(['meja', 'kasir'])
        ->orderBy('waktu_selesai')
        ->get();

    $html = view('reports.dashboard-report', [
        'start' => $start,
        'end' => $end,
        'generatedAt' => now(),
        'generatedBy' => auth()->user()?->name,
        'paidCount' => $paidCount,
        'revenue' => $revenue,
        'discountTotal' => $discountTotal,
        'taxTotal' => $taxTotal,
        'avgOrder' => $avgOrder,
        'methodRows' => $methodRows,
        'topMenus' => $topMenus,
        'topAddons' => $topAddons,
        'payments' => $payments,
    ])->render();

    $dompdf = new \Dompdf\Dompdf([
        'defaultFont' => 'DejaVu Sans',
        'isRemoteEnabled' => false,
    ]);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $filename = 'dashboard-report-' . $start->format('Ymd') . '-' . $end->format('Ymd') . '.pdf';

    return response($dompdf->output(), 200, [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => 'inline; filename="' . $filename . '"',
    ]);
})->middleware(['auth', 'verified', 'can:dashboard.access'])->name('dashboard.report');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('profile.edit');
    Volt::route('settings/password', 'settings.password')->name('user-password.edit');
    Volt::route('settings/appearance', 'settings.appearance')->name('appearance.edit');
    Volt::route('settings/language', 'settings.language')->name('settings.language');

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
    Route::get('meja/print', function (\Illuminate\Http\Request $request) {
        $ids = [];
        $q = (string) $request->query('ids', '');
        if ($q !== '') {
            $ids = collect(explode(',', $q))
                ->filter()
                ->map(fn ($v) => (int) $v)
                ->filter(fn ($v) => $v > 0)
                ->values()
                ->all();
        }

        $items = empty($ids)
            ? \App\Models\Meja::orderBy('nomor_meja')->get()
            : \App\Models\Meja::whereIn('id', $ids)->orderBy('nomor_meja')->get();

        $host = $request->getSchemeAndHttpHost();
        $qrSize = 600;

        // Use SVG backend to avoid relying on PNG backend availability (GD/Imagick).
        $renderer = new \BaconQrCode\Renderer\ImageRenderer(
            new \BaconQrCode\Renderer\RendererStyle\RendererStyle($qrSize),
            new \BaconQrCode\Renderer\Image\SvgImageBackEnd()
        );
        $writer = new \BaconQrCode\Writer($renderer);

        $pdfItems = $items->map(function ($m) use ($writer, $host) {
            $url = $host . '/order/' . $m->qr_token;
            $svg = $writer->writeString($url);

            return [
                'id' => $m->id,
                'nomor_meja' => $m->nomor_meja,
                'qr_data_uri' => 'data:image/svg+xml;base64,' . base64_encode($svg),
            ];
        })->all();

        $html = view('meja.print-pdf', ['items' => $pdfItems])->render();

        $dompdf = new \Dompdf\Dompdf([
            'defaultFont' => 'DejaVu Sans',
            'isRemoteEnabled' => false,
        ]);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'qr-meja.pdf';
        if ($request->filled('ids')) {
            $filename = 'qr-meja-selected.pdf';
        }

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    })->middleware('can:meja.access')->name('meja.print');
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
