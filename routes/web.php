<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt;
use Illuminate\Support\Carbon;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::post('locale', function (\Illuminate\Http\Request $request) {
    $validated = $request->validate([
        'locale' => ['required', 'in:en,id'],
    ]);

    $request->session()->put('locale', $validated['locale']);
    app()->setLocale($validated['locale']);
    Carbon::setLocale($validated['locale']);

    return back();
})->name('locale.set');

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
            $url = $host . '/' . $m->qr_token;
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
    $url = $host . '/' . $token;

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

// Customer (QR scan) - public (no login)
// Token is generated as 10 uppercase alphanumeric characters.
Route::get('{token}/cart', function (\Illuminate\Http\Request $request, string $token) {
    // Cart is handled as a drawer inside the order page.
    // Keep this route for convenience/backward links and just redirect to open the drawer.
    return redirect()->route('customer.order', ['token' => $token, 'cart' => 1]);
})
    ->where('token', '[A-Za-z0-9]{10}')
    ->name('customer.cart');

Route::get('{token}/checkout', function (\Illuminate\Http\Request $request, string $token) {
    $token = \Illuminate\Support\Str::upper($token);

    $meja = \App\Models\Meja::query()
        ->select(['id', 'nomor_meja', 'qr_token'])
        ->where('qr_token', $token)
        ->firstOrFail();

    $order = \App\Models\Pesanan::query()
        ->where('meja_id', $meja->id)
        ->whereIn('status', ['menunggu', 'diproses', 'siap'])
        ->where(function ($q) {
            $q->whereNull('metode_pembayaran')->orWhere('metode_pembayaran', '');
        })
        ->orderByDesc('waktu_pesan')
        ->first();

    if ($order) {
        return redirect()->route('customer.status', ['token' => $token]);
    }

    $menus = \App\Models\Menu::query()
        ->with(['addons' => function ($q) {
            $q->where('status', 'tersedia')->orderBy('nama_addon');
        }])
        ->orderBy('kategori_id')
        ->orderBy('nama_menu')
        ->get(['id', 'nama_menu', 'nama_menu_en', 'harga', 'gambar', 'status']);

    $diskons = \App\Models\Diskon::query()
        ->where('is_active', true)
        ->orderBy('kode')
        ->get(['id', 'kode', 'tipe', 'nilai', 'min_subtotal', 'tanggal_mulai', 'tanggal_selesai', 'is_active']);

    $taxes = \App\Models\Pajak::query()
        ->where('is_active', true)
        ->orderBy('nama')
        ->get(['id', 'nama', 'persentase', 'is_active']);

    return view('customer.checkout', [
        'token' => $token,
        'meja' => $meja,
        'menus' => $menus,
        'diskons' => $diskons,
        'taxes' => $taxes,
    ]);
})
    ->where('token', '[A-Za-z0-9]{10}')
    ->name('customer.checkout');

Route::post('{token}/checkout/submit', function (\Illuminate\Http\Request $request, string $token) {
    $token = \Illuminate\Support\Str::upper($token);

    $meja = \App\Models\Meja::query()
        ->select(['id', 'nomor_meja', 'qr_token'])
        ->where('qr_token', $token)
        ->firstOrFail();

    $existing = \App\Models\Pesanan::query()
        ->where('meja_id', $meja->id)
        ->whereIn('status', ['menunggu', 'diproses', 'siap'])
        ->where(function ($q) {
            $q->whereNull('metode_pembayaran')->orWhere('metode_pembayaran', '');
        })
        ->orderByDesc('waktu_pesan')
        ->first();

    if ($existing) {
        return response()->json([
            'ok' => false,
            'message' => __('An order is already in progress for this table.'),
            'redirect' => route('customer.status', ['token' => $token]),
        ], 409);
    }

    $data = validator([
        'cart' => $request->input('cart', null),
        'voucher' => $request->input('voucher', null),
        'customer_name' => $request->input('customer_name', null),
    ], [
        'cart' => ['required', 'array', 'min:1'],
        'cart.*.qty' => ['required', 'integer', 'min:1'],
        'cart.*.addons' => ['nullable', 'array'],
        'cart.*.addons.*' => ['integer', 'exists:addons,id'],
        'voucher' => ['nullable', 'string', 'max:50'],
        'customer_name' => ['nullable', 'string', 'max:100'],
    ])->validate();

    $cart = $data['cart'];
    $voucherCode = strtoupper(trim((string) ($data['voucher'] ?? '')));

    $menuIds = collect(array_keys($cart))->map(fn ($v) => (int) $v)->filter()->unique()->values()->all();
    $menus = \App\Models\Menu::query()
        ->with(['addons' => fn ($q) => $q->where('status', 'tersedia')->orderBy('nama_addon')])
        ->whereIn('id', $menuIds)
        ->where('status', 'tersedia')
        ->get()
        ->keyBy('id');

    $items = [];
    foreach ($cart as $menuIdRaw => $row) {
        $menuId = (int) $menuIdRaw;
        $qty = (int) ($row['qty'] ?? 0);
        if ($menuId <= 0 || $qty <= 0 || !$menus->has($menuId)) {
            continue;
        }

        $menu = $menus[$menuId];
        $baseHarga = (float) $menu->harga;

        $addonIds = array_values(array_filter(array_map('intval', (array) ($row['addons'] ?? []))));
        $allowedAddonIds = $menu->addons->pluck('id')->all();
        $addonIds = array_values(array_intersect($addonIds, $allowedAddonIds));

        $addonTotal = 0.0;
        if (!empty($addonIds)) {
            $addonTotal = (float) $menu->addons->whereIn('id', $addonIds)->sum('harga');
        }

        $harga = $baseHarga + $addonTotal;
        $items[] = [
            'menu_id' => $menuId,
            'qty' => $qty,
            'harga' => $harga,
            'subtotal' => $qty * $harga,
            'addon_ids' => $addonIds,
        ];
    }

    if (empty($items)) {
        return response()->json([
            'ok' => false,
            'message' => __('Your cart is empty.'),
        ], 422);
    }

    $subtotal = collect($items)->sum(fn ($i) => (float) ($i['subtotal'] ?? 0));

    // Voucher (Diskon)
    $diskon = null;
    $discountTotal = 0.0;
    if ($voucherCode !== '') {
        $diskon = \App\Models\Diskon::query()
            ->where('is_active', true)
            ->where('kode', $voucherCode)
            ->first();

        $today = now()->toDateString();
        if (
            !$diskon ||
            ($diskon->tanggal_mulai && $diskon->tanggal_mulai->toDateString() > $today) ||
            ($diskon->tanggal_selesai && $diskon->tanggal_selesai->toDateString() < $today) ||
            ($diskon->min_subtotal !== null && $subtotal < (float) $diskon->min_subtotal)
        ) {
            $diskon = null;
        }

        if ($diskon) {
            if ($diskon->tipe === 'percent') {
                $discountTotal = max($subtotal * ((float) $diskon->nilai / 100), 0);
            } else {
                $discountTotal = max((float) $diskon->nilai, 0);
            }
            $discountTotal = min($discountTotal, $subtotal);
        }
    }

    $baseAfterDiscount = max($subtotal - $discountTotal, 0);

    // Taxes (Pajak) - sum active taxes
    $taxes = \App\Models\Pajak::query()
        ->where('is_active', true)
        ->orderBy('nama')
        ->get(['id', 'persentase']);

    $taxPercent = (float) $taxes->sum(fn ($t) => (float) $t->persentase);
    $taxTotal = max($baseAfterDiscount * ($taxPercent / 100), 0);

    $total = max($baseAfterDiscount + $taxTotal, 0);

    $customerName = trim((string) ($data['customer_name'] ?? ''));
    if ($customerName === '') {
        $customerName = __('Guest');
    }

    $pajakId = null;
    if ($taxes->count() === 1) {
        $pajakId = (int) $taxes->first()->id;
    }

    $pesananId = null;
    \Illuminate\Support\Facades\DB::transaction(function () use ($meja, $items, $subtotal, $discountTotal, $taxTotal, $total, $diskon, $pajakId, $customerName, &$pesananId) {
        $pesanan = \App\Models\Pesanan::create([
            'meja_id' => $meja->id,
            'kode_pesanan' => 'ORD-' . now()->format('YmdHis'),
            'customer_name' => $customerName,
            'customer_note' => null,
            'subtotal' => $subtotal,
            'discount_total' => max($discountTotal, 0),
            'tax_total' => max($taxTotal, 0),
            'total_harga' => $total,
            'status' => 'menunggu',
            'metode_pembayaran' => null,
            'dibayar' => null,
            'kembalian' => null,
            'referensi_pembayaran' => null,
            'kasir_id' => null,
            'chef_id' => null,
            'diskon_id' => $diskon?->id,
            'pajak_id' => $pajakId,
            'waktu_pesan' => now(),
        ]);

        $pesananId = $pesanan->id;

        $allAddonIds = collect($items)->pluck('addon_ids')->flatten()->filter()->unique()->values()->all();
        $addons = \App\Models\Addon::query()
            ->select(['id', 'harga'])
            ->whereIn('id', $allAddonIds)
            ->get()
            ->keyBy('id');

        foreach ($items as $item) {
            $detail = $pesanan->details()->create([
                'menu_id' => $item['menu_id'],
                'qty' => $item['qty'],
                'harga' => $item['harga'],
                'subtotal' => $item['subtotal'],
                'catatan' => null,
            ]);

            $addonIds = $item['addon_ids'] ?? [];
            if (!empty($addonIds)) {
                $sync = [];
                foreach ($addonIds as $addonId) {
                    if ($addons->has($addonId)) {
                        $sync[$addonId] = ['harga' => (float) $addons[$addonId]->harga];
                    }
                }
                if (!empty($sync)) {
                    $detail->addons()->sync($sync);
                }
            }
        }
    });

    session()->put('customer_order_submitted_' . $token, true);
    session()->put('customer_last_order_id_' . $token, $pesananId);

    // If JSON request, respond JSON; otherwise redirect.
    if ($request->expectsJson()) {
        return response()->json([
            'ok' => true,
            'redirect' => route('customer.status', ['token' => $token]),
            'order_id' => $pesananId,
        ]);
    }

    return redirect()->route('customer.status', ['token' => $token]);
})
    ->where('token', '[A-Za-z0-9]{10}')
    ->name('customer.checkout.submit');

Route::get('{token}/status', function (string $token) {
    $token = \Illuminate\Support\Str::upper($token);

    $meja = \App\Models\Meja::query()
        ->select(['id', 'nomor_meja', 'qr_token'])
        ->where('qr_token', $token)
        ->firstOrFail();

    $order = \App\Models\Pesanan::query()
        ->with(['details.menu', 'details.addons'])
        ->where('meja_id', $meja->id)
        ->whereIn('status', ['menunggu', 'diproses', 'siap'])
        ->where(function ($q) {
            $q->whereNull('metode_pembayaran')->orWhere('metode_pembayaran', '');
        })
        ->orderByDesc('waktu_pesan')
        ->first();

    return view('customer.status', [
        'token' => $token,
        'meja' => $meja,
        'order' => $order,
        'justSubmitted' => (bool) session()->pull('customer_order_submitted_' . $token, false),
    ]);
})
    ->where('token', '[A-Za-z0-9]{10}')
    ->name('customer.status');

Route::get('{token}/status.json', function (string $token) {
    $token = \Illuminate\Support\Str::upper($token);

    $meja = \App\Models\Meja::query()
        ->select(['id', 'nomor_meja', 'qr_token'])
        ->where('qr_token', $token)
        ->firstOrFail();

    $active = \App\Models\Pesanan::query()
        ->with(['details.menu:id,nama_menu,nama_menu_en', 'details.addons:id,nama_addon,nama_addon_en'])
        ->where('meja_id', $meja->id)
        ->whereIn('status', ['menunggu', 'diproses', 'siap'])
        ->where(function ($q) {
            $q->whereNull('metode_pembayaran')->orWhere('metode_pembayaran', '');
        })
        ->orderByDesc('waktu_pesan')
        ->first();

    $paid = false;
    $paidOrder = null;
    if (!$active) {
        $lastOrderId = session()->get('customer_last_order_id_' . $token);

        if ($lastOrderId) {
            $paidOrder = \App\Models\Pesanan::query()
                ->select(['id', 'kode_pesanan', 'status', 'metode_pembayaran', 'waktu_selesai'])
                ->whereKey((int) $lastOrderId)
                ->where('meja_id', $meja->id)
                ->where('status', 'selesai')
                ->whereNotNull('metode_pembayaran')
                ->first();

            if ($paidOrder && !blank($paidOrder->metode_pembayaran)) {
                $paid = true;
                session()->forget('customer_last_order_id_' . $token);
            }
        }
    }

    return response()->json([
        'ok' => true,
        'meja' => [
            'nomor_meja' => (string) $meja->nomor_meja,
        ],
        'paid' => $paid,
        'paid_order' => $paidOrder ? [
            'id' => (int) $paidOrder->id,
            'kode_pesanan' => (string) $paidOrder->kode_pesanan,
            'paid_at' => $paidOrder->waktu_selesai?->toIso8601String(),
        ] : null,
        'redirect' => $paid ? route('customer.order', ['token' => $token]) : null,
        'order' => $active ? [
            'id' => (int) $active->id,
            'kode_pesanan' => (string) $active->kode_pesanan,
            'status' => (string) $active->status,
            'subtotal' => (float) ($active->subtotal ?? 0),
            'discount_total' => (float) ($active->discount_total ?? 0),
            'tax_total' => (float) ($active->tax_total ?? 0),
            'total_harga' => (float) ($active->total_harga ?? 0),
            'items' => $active->details->map(function ($d) {
                return [
                    'menu' => (string) ($d->menu?->nama_menu ?? __('Menu')),
                    'qty' => (int) $d->qty,
                    'subtotal' => (float) $d->subtotal,
                    'addons' => $d->addons->map(fn ($a) => (string) $a->nama_addon_localized)->values()->all(),
                ];
            })->values()->all(),
        ] : null,
    ], 200, [
        'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
    ]);
})
    ->where('token', '[A-Za-z0-9]{10}')
    ->name('customer.status.json');

Route::get('{token}', function (\Illuminate\Http\Request $request, string $token) {
    $token = \Illuminate\Support\Str::upper($token);

    $meja = \App\Models\Meja::query()
        ->select(['id', 'nomor_meja', 'qr_token'])
        ->where('qr_token', $token)
        ->firstOrFail();

    $order = \App\Models\Pesanan::query()
        ->where('meja_id', $meja->id)
        ->whereIn('status', ['menunggu', 'diproses', 'siap'])
        ->where(function ($q) {
            $q->whereNull('metode_pembayaran')->orWhere('metode_pembayaran', '');
        })
        ->orderByDesc('waktu_pesan')
        ->first();

    if ($order) {
        return redirect()->route('customer.status', ['token' => $token]);
    }

    $categories = \App\Models\KategoriMenu::query()
        ->orderBy('nama_kategori')
        ->get(['id', 'nama_kategori', 'nama_kategori_en']);

    $menus = \App\Models\Menu::query()
        ->with([
            'kategori:id,nama_kategori,nama_kategori_en',
            'addons' => function ($q) {
                $q->where('status', 'tersedia')->orderBy('nama_addon');
            },
        ])
        ->where('status', 'tersedia')
        ->orderBy('kategori_id')
        ->orderBy('nama_menu')
        ->get(['id', 'kategori_id', 'nama_menu', 'nama_menu_en', 'deskripsi', 'deskripsi_en', 'harga', 'gambar', 'status']);

    return view('customer.order', [
        'token' => $token,
        'meja' => $meja,
        'order' => $order,
        'categories' => $categories,
        'menus' => $menus,
    ]);
})
    ->where('token', '[A-Za-z0-9]{10}')
    ->name('customer.order');

// Backward-compatible redirect (old QR links)
Route::get('order/{token}', function (string $token) {
    return redirect()->route('customer.order', ['token' => $token]);
})->where('token', '[A-Za-z0-9]{10}');
