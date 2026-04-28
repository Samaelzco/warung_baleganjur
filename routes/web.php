<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use App\Services\OrderStatusService;
use App\Services\QrCodeService;
use App\Services\TableWaitingListService;

Route::redirect('/', '/login')->name('home');

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
    Volt::route('addon/create', 'addon.create')->middleware('can:addon.manage')->name('addon.create');
    Volt::route('addon/{addon}/edit', 'addon.edit')->middleware('can:addon.manage')->name('addon.edit');

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

    // Waiting list
    Volt::route('admin/waiting-list', 'waiting-list.index')->middleware('can:waiting-list.access')->name('waiting-list.index');
    Volt::route('admin/waiting-list/create', 'waiting-list.create')->middleware('can:waiting-list.manage')->name('waiting-list.create');
    Volt::route('admin/waiting-list/{pesanan}/edit', 'waiting-list.edit')->middleware('can:waiting-list.manage')->name('waiting-list.edit');

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
    Volt::route('roles/create', 'roles.create')->middleware('can:roles.manage')->name('roles.create');
    Volt::route('roles/{role}/edit', 'roles.edit')->middleware('can:roles.manage')->name('roles.edit');
});

// QR image (PNG) generator (public path to avoid auth/cookie issues when embedding in <img>)
Route::get('meja/qr', function (\Illuminate\Http\Request $request, QrCodeService $qrCode) {
    $token = (string) $request->query('token', '');

    if ($token === '' && $request->filled('meja')) {
        $meja = \App\Models\Meja::findOrFail($request->query('meja'));
        $token = $meja->qr_token;
    }

    abort_unless($token !== '', 404);

    $host = request()->getSchemeAndHttpHost();
    $url = $host . '/' . $token;

    $size = (int) $request->query('size', 512);
    $format = strtolower((string) $request->query('format', 'svg'));
    $filename = $request->boolean('download')
        ? 'qr-meja-' . substr($token, 0, 8) . '.' . ($format === 'png' ? 'png' : 'svg')
        : null;

    return $qrCode->response($url, $size, $format, $filename);
})->name('meja.qr');

Route::get('waiting-list', function () {
    $mejas = \App\Models\Meja::query()
        ->select(['id', 'nomor_meja', 'status', 'kapasitas'])
        ->where('status', '!=', 'nonaktif')
        ->orderBy('nomor_meja')
        ->get();

    $occupiedByTable = \App\Models\Pesanan::query()
        ->whereIn('status', TableWaitingListService::ACTIVE_STATUSES)
        ->where(function ($q) {
            $q->whereNull('metode_pembayaran')->orWhere('metode_pembayaran', '');
        })
        ->select('meja_id')
        ->selectRaw('COALESCE(SUM(jumlah_orang), 0) as agg')
        ->groupBy('meja_id')
        ->pluck('agg', 'meja_id')
        ->all();

    $waitingListCounts = \App\Models\Pesanan::query()
        ->where('status', 'booking')
        ->select('meja_id')
        ->selectRaw('count(*) as agg')
        ->groupBy('meja_id')
        ->pluck('agg', 'meja_id')
        ->all();

    return view('customer.waiting-list', [
        'mejas' => $mejas,
        'occupiedByTable' => $occupiedByTable,
        'waitingListCounts' => $waitingListCounts,
    ]);
})->name('customer.waiting-list.index');

Route::get('waiting-list/data', function () {
    $mejas = \App\Models\Meja::query()
        ->select(['id', 'nomor_meja', 'status', 'kapasitas'])
        ->where('status', '!=', 'nonaktif')
        ->orderBy('nomor_meja')
        ->get();

    $occupiedByTable = \App\Models\Pesanan::query()
        ->whereIn('status', TableWaitingListService::ACTIVE_STATUSES)
        ->where(function ($q) {
            $q->whereNull('metode_pembayaran')->orWhere('metode_pembayaran', '');
        })
        ->select('meja_id')
        ->selectRaw('COALESCE(SUM(jumlah_orang), 0) as agg')
        ->groupBy('meja_id')
        ->pluck('agg', 'meja_id')
        ->all();

    $waitingListCounts = \App\Models\Pesanan::query()
        ->where('status', 'booking')
        ->select('meja_id')
        ->selectRaw('count(*) as agg')
        ->groupBy('meja_id')
        ->pluck('agg', 'meja_id')
        ->all();

    $tables = $mejas->map(function ($meja) use ($occupiedByTable, $waitingListCounts) {
        $capacity = (int) ($meja->kapasitas ?? 4);
        $occupied = (int) ($occupiedByTable[$meja->id] ?? 0);
        $waiting = (int) ($waitingListCounts[$meja->id] ?? 0);
        $seatsLeft = max($capacity - $occupied, 0);

        return [
            'id' => (int) $meja->id,
            'number' => (string) $meja->nomor_meja,
            'capacity' => $capacity,
            'occupied' => $occupied,
            'seats_left' => $seatsLeft,
            'waiting' => $waiting,
            'is_full' => $seatsLeft <= 0,
        ];
    })->values();

    return response()->json([
        'ok' => true,
        'updated_at' => now()->toIso8601String(),
        'summary' => [
            'tables' => $tables->count(),
            'seats_left' => (int) $tables->sum('seats_left'),
            'waiting' => (int) $tables->sum('waiting'),
        ],
        'tables' => $tables,
    ], 200, [
        'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
    ]);
})->name('customer.waiting-list.data');

Route::get('waiting-list/qr', function (\Illuminate\Http\Request $request, QrCodeService $qrCode) {
    $host = $request->getSchemeAndHttpHost();
    $url = $host . '/waiting-list';

    return $qrCode->response($url, (int) $request->query('size', 512), 'svg');
})->name('customer.waiting-list.qr');

Route::get('waiting-list/qr/pdf', function (\Illuminate\Http\Request $request) {
    $host = $request->getSchemeAndHttpHost();
    $url = $host . '/waiting-list';
    $qrSize = 720;

    $renderer = new \BaconQrCode\Renderer\ImageRenderer(
        new \BaconQrCode\Renderer\RendererStyle\RendererStyle($qrSize),
        new \BaconQrCode\Renderer\Image\SvgImageBackEnd()
    );
    $writer = new \BaconQrCode\Writer($renderer);
    $svg = $writer->writeString($url);

    $html = view('waiting-list.qr-pdf', [
        'url' => $url,
        'qrDataUri' => 'data:image/svg+xml;base64,' . base64_encode($svg),
        'generatedAt' => now(),
    ])->render();

    $dompdf = new \Dompdf\Dompdf([
        'defaultFont' => 'DejaVu Sans',
        'isRemoteEnabled' => false,
    ]);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    return response($dompdf->output(), 200, [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => 'attachment; filename="waiting-list-qr.pdf"',
    ]);
})->name('customer.waiting-list.qr.pdf');

Route::get('waiting-list/{meja}/menu-updates', function (\Illuminate\Http\Request $request, \App\Models\Meja $meja) {
    abort_if($meja->status === 'nonaktif', 404);

    $version = (int) Cache::get('customer:menu_version', 1);
    $clientVersion = (int) $request->query('version', 0);

    if ($clientVersion === $version) {
        return response()->json([
            'version' => $version,
            'changed' => false,
            'menus' => [],
        ]);
    }

    $menus = \App\Models\Menu::query()
        ->whereIn('status', ['tersedia', 'habis'])
        ->orderBy('id')
        ->get(['id', 'status'])
        ->map(fn ($menu) => [
            'id' => (int) $menu->id,
            'available' => $menu->status === 'tersedia',
        ])
        ->values();

    return response()->json([
        'version' => $version,
        'changed' => true,
        'menus' => $menus,
    ]);
})->name('customer.waiting-list.menu-updates');

Route::get('waiting-list/{meja}', function (\App\Models\Meja $meja) {
    abort_if($meja->status === 'nonaktif', 404);

    $categories = Cache::remember('customer:categories:v1', 900, function () {
        return \App\Models\KategoriMenu::query()
            ->where('is_active', true)
            ->orderBy('nama_kategori')
            ->get(['id', 'nama_kategori', 'nama_kategori_en']);
    });

    $menus = Cache::remember('customer:menus_orderable_display:v1', 900, function () {
        return \App\Models\Menu::query()
            ->with([
                'kategori:id,nama_kategori,nama_kategori_en',
                'addons' => function ($q) {
                    $q->where('status', 'tersedia')->orderBy('nama_addon');
                },
            ])
            ->whereIn('status', ['tersedia', 'habis'])
            ->orderBy('kategori_id')
            ->orderBy('nama_menu')
            ->get(['id', 'kategori_id', 'nama_menu', 'nama_menu_en', 'deskripsi', 'deskripsi_en', 'harga', 'gambar', 'status']);
    });

    $token = 'WAITING' . str_pad((string) $meja->id, 3, '0', STR_PAD_LEFT);

    return view('customer.order', [
        'token' => $token,
        'meja' => $meja,
        'order' => null,
        'addMode' => false,
        'baselineCart' => [],
        'baselineMinQty' => [],
        'categories' => $categories,
        'menus' => $menus,
        'checkoutUrl' => route('customer.waiting-list.checkout', ['meja' => $meja->id]),
        'menuUpdatesUrl' => route('customer.waiting-list.menu-updates', ['meja' => $meja->id]),
        'menuVersion' => (int) Cache::get('customer:menu_version', 1),
    ]);
})->name('customer.waiting-list.order');

Route::get('waiting-list/{meja}/checkout', function (\App\Models\Meja $meja) {
    abort_if($meja->status === 'nonaktif', 404);

    $menus = Cache::remember('customer:menus_available:v1', 900, function () {
        return \App\Models\Menu::query()
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
    });

    $diskons = \App\Models\Diskon::query()
        ->where('is_active', true)
        ->orderBy('kode')
        ->get(['id', 'kode', 'tipe', 'nilai', 'min_subtotal', 'tanggal_mulai', 'tanggal_selesai', 'is_active']);

    $taxes = \App\Models\Pajak::query()
        ->where('is_active', true)
        ->orderBy('nama')
        ->get(['id', 'nama', 'persentase', 'is_active']);

    $token = 'WAITING' . str_pad((string) $meja->id, 3, '0', STR_PAD_LEFT);

    return view('customer.checkout', [
        'token' => $token,
        'meja' => $meja,
        'order' => null,
        'addMode' => false,
        'menus' => $menus,
        'diskons' => $diskons,
        'taxes' => $taxes,
        'backUrl' => route('customer.waiting-list.order', ['meja' => $meja->id]),
        'submitUrl' => route('customer.waiting-list.submit', ['meja' => $meja->id]),
    ]);
})->name('customer.waiting-list.checkout');

Route::post('waiting-list/{meja}/submit', function (\Illuminate\Http\Request $request, \App\Models\Meja $meja, OrderStatusService $orderStatus) {
    abort_if($meja->status === 'nonaktif', 404);

    $data = validator([
        'cart' => $request->input('cart', null),
        'voucher' => $request->input('voucher', null),
        'customer_name' => $request->input('customer_name', null),
        'customer_note' => $request->input('customer_note', null),
        'jumlah_orang' => $request->input('jumlah_orang', 1),
    ], [
        'cart' => ['required', 'array', 'min:1'],
        'cart.*.qty' => ['required', 'integer', 'min:1'],
        'cart.*.addons' => ['nullable', 'array'],
        'cart.*.addons.*' => ['integer', 'exists:addons,id'],
        'cart.*.menu_id' => ['nullable', 'integer', 'exists:menus,id'],
        'voucher' => ['nullable', 'string', 'max:50'],
        'customer_name' => ['required', 'string', 'max:100'],
        'customer_note' => ['nullable', 'string', 'max:255'],
        'jumlah_orang' => ['required', 'integer', 'min:1', 'max:99'],
    ])->validate();

    $cartLines = [];
    foreach ((array) $data['cart'] as $row) {
        if (!is_array($row)) {
            continue;
        }
        $menuId = (int) ($row['menu_id'] ?? 0);
        $qty = (int) ($row['qty'] ?? 0);
        if ($menuId > 0 && $qty > 0) {
            $cartLines[] = [
                'menu_id' => $menuId,
                'qty' => $qty,
                'addons' => array_values(array_filter(array_map('intval', (array) ($row['addons'] ?? [])))),
            ];
        }
    }

    abort_if(empty($cartLines), 422, __('Your cart is empty.'));

    $menuIds = collect($cartLines)->pluck('menu_id')->unique()->values()->all();
    $menus = \App\Models\Menu::query()
        ->with(['addons' => fn ($q) => $q->where('status', 'tersedia')])
        ->whereIn('id', $menuIds)
        ->where('status', 'tersedia')
        ->get()
        ->keyBy('id');

    $items = [];
    $subtotal = 0.0;
    foreach ($cartLines as $line) {
        $menu = $menus[(int) $line['menu_id']] ?? null;
        if (!$menu) {
            continue;
        }

        $allowedAddonIds = $menu->addons->pluck('id')->map(fn ($v) => (int) $v)->all();
        $addonIds = array_values(array_intersect($line['addons'], $allowedAddonIds));
        $addonTotal = (float) $menu->addons->whereIn('id', $addonIds)->sum('harga');
        $harga = (float) $menu->harga + $addonTotal;
        $lineSubtotal = $harga * (int) $line['qty'];
        $subtotal += $lineSubtotal;

        $items[] = [
            'menu_id' => (int) $menu->id,
            'qty' => (int) $line['qty'],
            'harga' => $harga,
            'subtotal' => $lineSubtotal,
            'addon_ids' => $addonIds,
        ];
    }

    abort_if(empty($items), 422, __('Your cart is empty.'));

    $voucherCode = strtoupper(trim((string) ($data['voucher'] ?? '')));
    $diskon = null;
    $discountTotal = 0.0;
    if ($voucherCode !== '') {
        $today = now()->toDateString();
        $diskon = \App\Models\Diskon::query()
            ->where('kode', $voucherCode)
            ->where('is_active', true)
            ->first();

        if ($diskon && (!$diskon->tanggal_mulai || $diskon->tanggal_mulai->toDateString() <= $today) && (!$diskon->tanggal_selesai || $diskon->tanggal_selesai->toDateString() >= $today) && ($diskon->min_subtotal === null || $subtotal >= (float) $diskon->min_subtotal)) {
            $discountTotal = $diskon->tipe === 'percent'
                ? max($subtotal * ((float) $diskon->nilai / 100), 0)
                : max((float) $diskon->nilai, 0);
            $discountTotal = min($discountTotal, $subtotal);
        } else {
            $diskon = null;
        }
    }

    $taxes = \App\Models\Pajak::query()->where('is_active', true)->get(['id', 'persentase']);
    $taxPercent = (float) $taxes->sum('persentase');
    $baseAfterDiscount = max($subtotal - $discountTotal, 0);
    $taxTotal = max($baseAfterDiscount * ($taxPercent / 100), 0);
    $total = max($baseAfterDiscount + $taxTotal, 0);
    $pajakId = $taxes->count() === 1 ? (int) $taxes->first()->id : null;

    $pesananId = null;
    \Illuminate\Support\Facades\DB::transaction(function () use ($meja, $data, $items, $subtotal, $discountTotal, $taxTotal, $total, $diskon, $pajakId, $orderStatus, &$pesananId) {
        $pesanan = \App\Models\Pesanan::create([
            'meja_id' => $meja->id,
            'kode_pesanan' => 'WTL-' . now()->format('YmdHis'),
            'status_token' => $orderStatus->generateToken(),
            'customer_name' => trim((string) $data['customer_name']),
            'customer_note' => blank($data['customer_note'] ?? null) ? null : \Illuminate\Support\Str::substr(trim((string) $data['customer_note']), 0, 255),
            'jumlah_orang' => max((int) ($data['jumlah_orang'] ?? 1), 1),
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'tax_total' => $taxTotal,
            'total_harga' => $total,
            'status' => 'booking',
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
        $addons = \App\Models\Addon::query()->select(['id', 'harga'])->whereIn('id', $allAddonIds)->get()->keyBy('id');

        foreach ($items as $item) {
            $detail = $pesanan->details()->create([
                'menu_id' => $item['menu_id'],
                'qty' => $item['qty'],
                'harga' => $item['harga'],
                'subtotal' => $item['subtotal'],
            ]);

            $sync = [];
            foreach (($item['addon_ids'] ?? []) as $addonId) {
                if ($addons->has($addonId)) {
                    $sync[$addonId] = ['harga' => (float) $addons[$addonId]->harga];
                }
            }
            $detail->addons()->sync($sync);
        }
    });

    session()->put('waiting_list_last_order_id', $pesananId);

    return response()->json([
        'ok' => true,
        'redirect' => route('customer.order-status', [
            'pesanan' => $pesananId,
            'token' => \App\Models\Pesanan::query()->whereKey($pesananId)->value('status_token'),
        ]),
        'order_id' => $pesananId,
    ]);
})->name('customer.waiting-list.submit');

Route::get('waiting-list/status/{pesanan}', function (\App\Models\Pesanan $pesanan) {
    if (!blank($pesanan->status_token)) {
        return redirect()->route('customer.order-status', [
            'pesanan' => $pesanan->id,
            'token' => $pesanan->status_token,
        ]);
    }

    $pesanan->loadMissing(['meja', 'details.menu', 'details.addons']);

    return view('customer.status', [
        'token' => 'WAITING' . str_pad((string) $pesanan->meja_id, 3, '0', STR_PAD_LEFT),
        'meja' => $pesanan->meja,
        'order' => $pesanan,
        'justSubmitted' => false,
        'statusJsonUrl' => route('customer.waiting-list.status.json', ['pesanan' => $pesanan->id]),
        'orderUrl' => route('customer.waiting-list.order', ['meja' => $pesanan->meja_id]),
    ]);
})->name('customer.waiting-list.status');

Route::get('waiting-list/status/{pesanan}/json', function (\App\Models\Pesanan $pesanan) {
    $pesanan->loadMissing(['details.menu:id,nama_menu,nama_menu_en', 'details.addons:id,nama_addon,nama_addon_en']);

    $paid = $pesanan->status === 'selesai' && !blank($pesanan->metode_pembayaran);

    return response()->json([
        'ok' => true,
        'paid' => $paid,
        'paid_order' => $paid ? [
            'id' => (int) $pesanan->id,
            'kode_pesanan' => (string) $pesanan->kode_pesanan,
            'paid_at' => $pesanan->waktu_selesai?->toIso8601String(),
        ] : null,
        'redirect' => $paid ? route('customer.waiting-list.index') : null,
        'order' => in_array($pesanan->status, ['booking', 'menunggu', 'sedang_diubah', 'diproses', 'siap'], true) ? [
            'id' => (int) $pesanan->id,
            'kode_pesanan' => (string) $pesanan->kode_pesanan,
            'status' => (string) $pesanan->status,
            'subtotal' => (float) ($pesanan->subtotal ?? 0),
            'discount_total' => (float) ($pesanan->discount_total ?? 0),
            'tax_total' => (float) ($pesanan->tax_total ?? 0),
            'total_harga' => (float) ($pesanan->total_harga ?? 0),
            'items' => $pesanan->details->map(fn ($d) => [
                'menu' => (string) ($d->menu?->nama_menu_localized ?? __('Menu')),
                'qty' => (int) $d->qty,
                'subtotal' => (float) $d->subtotal,
                'addons' => $d->addons?->map(fn ($a) => (string) $a->nama_addon_localized)->filter()->values()->all() ?? [],
            ])->values()->all(),
        ] : null,
    ], 200, [
        'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
    ]);
})->name('customer.waiting-list.status.json');

Route::get('order-status/{pesanan}/{token}', function (\App\Models\Pesanan $pesanan, string $token, OrderStatusService $orderStatus) {
    abort_unless($orderStatus->tokenMatches($pesanan, $token), 404);

    $pesanan->loadMissing(['meja', 'details.menu', 'details.addons']);

    return view('customer.status', [
        'token' => (string) ($pesanan->meja?->qr_token ?? ''),
        'meja' => $pesanan->meja,
        'order' => $pesanan,
        'justSubmitted' => (bool) session()->pull('customer_order_submitted_' . $pesanan->id, false),
        'justCancelled' => (bool) session()->pull('customer_order_cancelled_' . $pesanan->id, false),
        'statusJsonUrl' => route('customer.order-status.json', ['pesanan' => $pesanan->id, 'token' => $token]),
        'orderUrl' => $pesanan->meja?->qr_token
            ? route('customer.order', ['token' => $pesanan->meja->qr_token])
            : route('customer.waiting-list.index'),
        'editOrderUrl' => $pesanan->status === 'menunggu'
            ? route('customer.order-status.edit', ['pesanan' => $pesanan->id, 'token' => $token])
            : null,
        'continueEditUrl' => $pesanan->status === 'sedang_diubah'
            ? route('customer.order', ['token' => $pesanan->meja->qr_token, 'edit' => 1, 'order' => $pesanan->id, 'status_token' => $token])
            : null,
        'cancelOrderUrl' => in_array($pesanan->status, ['menunggu', 'sedang_diubah'], true)
            ? route('customer.order-status.cancel', ['pesanan' => $pesanan->id, 'token' => $token])
            : null,
    ]);
})
    ->whereNumber('pesanan')
    ->where('token', '[A-Za-z0-9]{20,80}')
    ->name('customer.order-status');

Route::post('order-status/{pesanan}/{token}/edit', function (\App\Models\Pesanan $pesanan, string $token, OrderStatusService $orderStatus) {
    abort_unless($orderStatus->tokenMatches($pesanan, $token), 404);
    abort_unless($pesanan->meja?->qr_token, 404);

    abort_unless(in_array($pesanan->status, ['menunggu', 'sedang_diubah'], true), 409);

    if ($pesanan->status === 'menunggu') {
        $pesanan->forceFill(['status' => 'sedang_diubah'])->save();
        app(TableWaitingListService::class)->forgetKitchenCache();
    }

    return redirect()->route('customer.order', [
        'token' => $pesanan->meja->qr_token,
        'edit' => 1,
        'order' => $pesanan->id,
        'status_token' => $token,
    ]);
})
    ->whereNumber('pesanan')
    ->where('token', '[A-Za-z0-9]{20,80}')
    ->name('customer.order-status.edit');

Route::post('order-status/{pesanan}/{token}/cancel', function (\App\Models\Pesanan $pesanan, string $token, OrderStatusService $orderStatus) {
    abort_unless($orderStatus->tokenMatches($pesanan, $token), 404);
    abort_unless(in_array($pesanan->status, ['menunggu', 'sedang_diubah'], true), 409);

    $mejaId = (int) $pesanan->meja_id;
    $pesanan->forceFill(['status' => 'batal'])->save();

    $waitingListService = app(TableWaitingListService::class);
    if ($mejaId > 0) {
        $waitingListService->syncMejaStatus($mejaId);
        $waitingListService->activateNextWaitingLists($mejaId);
    }
    $waitingListService->forgetKitchenCache();

    if ($pesanan->meja?->qr_token) {
        session()->put('customer_order_cancelled_' . $pesanan->meja->qr_token, true);
        session()->forget('customer_last_order_id_' . $pesanan->meja->qr_token);
    }
    session()->put('customer_order_cancelled_' . $pesanan->id, true);

    return redirect()->route('customer.order-status', ['pesanan' => $pesanan->id, 'token' => $token]);
})
    ->whereNumber('pesanan')
    ->where('token', '[A-Za-z0-9]{20,80}')
    ->name('customer.order-status.cancel');

Route::get('order-status/{pesanan}/{token}/json', function (\App\Models\Pesanan $pesanan, string $token, OrderStatusService $orderStatus) {
    abort_unless($orderStatus->tokenMatches($pesanan, $token), 404);

    $paid = $pesanan->status === 'selesai' && !blank($pesanan->metode_pembayaran);

    return response()->json([
        'ok' => true,
        'paid' => $paid,
        'paid_order' => $paid ? [
            'id' => (int) $pesanan->id,
            'kode_pesanan' => (string) $pesanan->kode_pesanan,
            'paid_at' => $pesanan->waktu_selesai?->toIso8601String(),
        ] : null,
        'redirect' => $paid && $pesanan->meja?->qr_token
            ? route('customer.order', ['token' => $pesanan->meja->qr_token])
            : ($paid ? route('customer.waiting-list.index') : null),
        'order' => in_array($pesanan->status, ['booking', 'menunggu', 'sedang_diubah', 'diproses', 'siap'], true)
            ? $orderStatus->serializeCustomerOrder($pesanan)
            : null,
    ], 200, [
        'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
    ]);
})
    ->whereNumber('pesanan')
    ->where('token', '[A-Za-z0-9]{20,80}')
    ->name('customer.order-status.json');

// Customer (QR scan) - public (no login)
// Token is generated as 10 uppercase alphanumeric characters.
Route::get('{token}/cart', function (\Illuminate\Http\Request $request, string $token) {
    // Cart is handled as a drawer inside the order page.
    // Keep this route for convenience/backward links and just redirect to open the drawer.
    return redirect()->route('customer.order', ['token' => $token, 'cart' => 1]);
})
    ->where('token', '[A-Za-z0-9]{10}')
    ->name('customer.cart');

Route::get('{token}/menu-updates', function (\Illuminate\Http\Request $request, string $token) {
    $token = \Illuminate\Support\Str::upper($token);

    \App\Models\Meja::query()
        ->select(['id'])
        ->where('qr_token', $token)
        ->where('status', '!=', 'nonaktif')
        ->firstOrFail();

    $version = (int) Cache::get('customer:menu_version', 1);
    $clientVersion = (int) $request->query('version', 0);

    if ($clientVersion === $version) {
        return response()->json([
            'version' => $version,
            'changed' => false,
            'menus' => [],
        ]);
    }

    $menus = \App\Models\Menu::query()
        ->whereIn('status', ['tersedia', 'habis'])
        ->orderBy('id')
        ->get(['id', 'status'])
        ->map(fn ($menu) => [
            'id' => (int) $menu->id,
            'available' => $menu->status === 'tersedia',
        ])
        ->values();

    return response()->json([
        'version' => $version,
        'changed' => true,
        'menus' => $menus,
    ]);
})
    ->where('token', '[A-Za-z0-9]{10}')
    ->name('customer.menu-updates');

Route::get('{token}/checkout', function (\Illuminate\Http\Request $request, string $token) {
    $token = \Illuminate\Support\Str::upper($token);
    $addMode = (bool) $request->boolean('add');
    $editMode = (bool) $request->boolean('edit');
    $editOrderId = (int) $request->query('order', 0);
    $editStatusToken = (string) $request->query('status_token', '');

    $meja = \App\Models\Meja::query()
        ->select(['id', 'nomor_meja', 'qr_token', 'status'])
        ->where('qr_token', $token)
        ->where('status', '!=', 'nonaktif')
        ->firstOrFail();

    $order = $editMode ? \App\Models\Pesanan::query()
        ->with(['details.addons:id', 'diskon:id,kode'])
        ->whereKey($editOrderId)
        ->where('meja_id', $meja->id)
        ->where('status', 'sedang_diubah')
        ->first() : ($addMode ? \App\Models\Pesanan::query()
        ->select(['id', 'meja_id', 'status', 'subtotal', 'discount_total', 'tax_total', 'total_harga', 'diskon_id', 'pajak_id', 'customer_name'])
        ->where('meja_id', $meja->id)
        ->whereIn('status', ['menunggu', 'sedang_diubah', 'diproses', 'siap'])
        ->where(function ($q) {
            $q->whereNull('metode_pembayaran')->orWhere('metode_pembayaran', '');
        })
        ->orderByDesc('waktu_pesan')
        ->first() : null);

    if ($editMode) {
        abort_if(!$order || blank($editStatusToken) || !app(OrderStatusService::class)->tokenMatches($order, $editStatusToken), 404);
    }

    if (!$order) {
        $addMode = false;
        $editMode = false;
    }

    $menus = Cache::remember('customer:menus_available:v1', 900, function () {
        return \App\Models\Menu::query()
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
    });

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
        'order' => $order,
        'addMode' => $addMode,
        'editMode' => $editMode,
        'menus' => $menus,
        'diskons' => $diskons,
        'taxes' => $taxes,
        'backUrl' => $editMode
            ? route('customer.order', ['token' => $token, 'edit' => 1, 'order' => $order?->id, 'status_token' => $editStatusToken])
            : null,
        'submitUrl' => $editMode
            ? route('customer.checkout.submit', ['token' => $token, 'edit' => 1, 'order' => $order?->id, 'status_token' => $editStatusToken])
            : null,
    ]);
})
    ->where('token', '[A-Za-z0-9]{10}')
    ->name('customer.checkout');

Route::post('{token}/checkout/submit', function (\Illuminate\Http\Request $request, string $token, OrderStatusService $orderStatus) {
    $token = \Illuminate\Support\Str::upper($token);
    $addMode = (bool) $request->boolean('add');
    $editMode = (bool) $request->boolean('edit');
    $editOrderId = (int) $request->query('order', 0);
    $editStatusToken = (string) $request->query('status_token', '');

    $meja = \App\Models\Meja::query()
        ->select(['id', 'nomor_meja', 'qr_token', 'status'])
        ->where('qr_token', $token)
        ->where('status', '!=', 'nonaktif')
        ->firstOrFail();

    $editingOrder = $editMode ? \App\Models\Pesanan::query()
        ->with(['details.addons:id'])
        ->whereKey($editOrderId)
        ->where('meja_id', $meja->id)
        ->where('status', 'sedang_diubah')
        ->first() : null;

    if ($editMode) {
        abort_if(!$editingOrder || blank($editStatusToken) || !$orderStatus->tokenMatches($editingOrder, $editStatusToken), 404);
    }

    $existing = \App\Models\Pesanan::query()
        ->where('meja_id', $meja->id)
        ->whereIn('status', ['menunggu', 'sedang_diubah', 'diproses', 'siap'])
        ->where(function ($q) {
            $q->whereNull('metode_pembayaran')->orWhere('metode_pembayaran', '');
        })
        ->orderByDesc('waktu_pesan')
        ->first();

    $data = validator([
        'cart' => $request->input('cart', null),
        'voucher' => $request->input('voucher', null),
        'customer_name' => $request->input('customer_name', null),
        'customer_note' => $request->input('customer_note', null),
        'jumlah_orang' => $request->input('jumlah_orang', 1),
    ], [
        'cart' => ['required', 'array', 'min:1'],
        'cart.*.qty' => ['required', 'integer', 'min:1'],
        'cart.*.addons' => ['nullable', 'array'],
        'cart.*.addons.*' => ['integer', 'exists:addons,id'],
        'cart.*.menu_id' => ['nullable', 'integer', 'exists:menus,id'],
        'voucher' => ['nullable', 'string', 'max:50'],
        'customer_name' => ['nullable', 'string', 'max:100'],
        'customer_note' => ['nullable', 'string', 'max:255'],
        'jumlah_orang' => ['required', 'integer', 'min:1', 'max:99'],
    ])->validate();

    $rawCart = $data['cart'];
    $voucherCode = strtoupper(trim((string) ($data['voucher'] ?? '')));

    $cartLines = [];
    if (is_array($rawCart) && array_is_list($rawCart)) {
        foreach ($rawCart as $row) {
            if (!is_array($row)) {
                continue;
            }
            $menuId = (int) ($row['menu_id'] ?? 0);
            $qty = (int) ($row['qty'] ?? 0);
            $addons = (array) ($row['addons'] ?? []);
            if ($menuId > 0 && $qty > 0) {
                $cartLines[] = ['menu_id' => $menuId, 'qty' => $qty, 'addons' => $addons];
            }
        }
    } else {
        foreach ((array) $rawCart as $menuIdRaw => $row) {
            if (!is_array($row)) {
                continue;
            }
            $menuId = (int) $menuIdRaw;
            $qty = (int) ($row['qty'] ?? 0);
            $addons = (array) ($row['addons'] ?? []);
            if ($menuId > 0 && $qty > 0) {
                $cartLines[] = ['menu_id' => $menuId, 'qty' => $qty, 'addons' => $addons];
            }
        }
    }

    $menuIds = collect($cartLines)->pluck('menu_id')->map(fn ($v) => (int) $v)->filter()->unique()->values()->all();
    $menus = \App\Models\Menu::query()
        ->with(['addons' => fn ($q) => $q->where('status', 'tersedia')->orderBy('nama_addon')])
        ->whereIn('id', $menuIds)
        ->where('status', 'tersedia')
        ->get()
        ->keyBy('id');

    $items = [];
    foreach ($cartLines as $row) {
        $menuId = (int) ($row['menu_id'] ?? 0);
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
    if ($voucherCode !== '' && !($existing && $addMode)) {
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
    if ($customerName === '' && !($existing && $addMode)) {
        $customerName = __('Guest');
    }

    $customerNote = (string) ($data['customer_note'] ?? '');
    $customerNote = str_replace(["\r\n", "\r"], "\n", $customerNote);
    $customerNote = trim($customerNote);
    if ($customerNote !== '') {
        $customerNote = \Illuminate\Support\Str::substr($customerNote, 0, 255);
    } else {
        $customerNote = null;
    }

    $jumlahOrang = max((int) ($data['jumlah_orang'] ?? 1), 1);

    $pajakId = null;
    if ($taxes->count() === 1) {
        $pajakId = (int) $taxes->first()->id;
    }

    $pesananId = null;
    $noDelta = false;

    try {
    \Illuminate\Support\Facades\DB::transaction(function () use ($meja, $existing, $editingOrder, $addMode, $editMode, $items, $subtotal, $discountTotal, $taxes, $taxPercent, $taxTotal, $total, $diskon, $pajakId, $customerName, $customerNote, $jumlahOrang, $orderStatus, &$pesananId, &$noDelta) {
        if ($editingOrder && $editMode) {
            $pesanan = \App\Models\Pesanan::query()
                ->whereKey((int) $editingOrder->id)
                ->where('status', 'sedang_diubah')
                ->lockForUpdate()
                ->firstOrFail();

            $lockedMeja = \App\Models\Meja::query()->whereKey($meja->id)->lockForUpdate()->firstOrFail();
            $waitingListService = app(TableWaitingListService::class);

            $occupiedSeats = (int) \App\Models\Pesanan::query()
                ->where('meja_id', $lockedMeja->id)
                ->whereKeyNot($pesanan->id)
                ->whereIn('status', TableWaitingListService::ACTIVE_STATUSES)
                ->where(function ($q) {
                    $q->whereNull('metode_pembayaran')->orWhere('metode_pembayaran', '');
                })
                ->sum('jumlah_orang');

            $remainingSeats = max(((int) ($lockedMeja->kapasitas ?? 0)) - $occupiedSeats, 0);
            if ($remainingSeats < $jumlahOrang) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'jumlah_orang' => __('This table is full. Please use the waiting list.'),
                ]);
            }

            $pesanan->details()->each(function ($detail) {
                $detail->addons()->detach();
            });
            $pesanan->details()->delete();

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

            $pesanan->forceFill([
                'customer_name' => $customerName,
                'customer_note' => $customerNote,
                'jumlah_orang' => $jumlahOrang,
                'subtotal' => $subtotal,
                'discount_total' => max($discountTotal, 0),
                'tax_total' => max($taxTotal, 0),
                'total_harga' => $total,
                'diskon_id' => $diskon?->id,
                'pajak_id' => $pajakId,
                'status' => 'menunggu',
            ])->save();

            $waitingListService->syncMejaStatus($lockedMeja);
            $waitingListService->forgetKitchenCache();
            $pesananId = $pesanan->id;
            return;
        }

        if ($existing && $addMode) {
            $pesanan = \App\Models\Pesanan::query()
                ->whereKey((int) $existing->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (blank($pesanan->status_token)) {
                $pesanan->forceFill(['status_token' => $orderStatus->generateToken()])->save();
            }

            $sigFromIds = static function (array $ids): string {
                $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn ($v) => $v > 0)));
                sort($ids);
                return implode(',', $ids);
            };

            $existingDetails = $pesanan->details()->with(['addons:id'])->get();
            $existingQtyByKey = $existingDetails
                ->groupBy(function ($d) use ($sigFromIds) {
                    $addonIds = $d->addons?->pluck('id')?->all() ?? [];
                    return (int) $d->menu_id . ':' . $sigFromIds($addonIds);
                })
                ->map(fn ($rows) => (int) $rows->sum('qty'));

            $deltaItems = [];
            foreach ($items as $item) {
                $menuId = (int) $item['menu_id'];
                $desiredQty = (int) $item['qty'];
                $addonSig = $sigFromIds($item['addon_ids'] ?? []);
                $key = $menuId . ':' . $addonSig;
                $existingQty = (int) ($existingQtyByKey[$key] ?? 0);
                $deltaQty = max($desiredQty - $existingQty, 0);
                if ($deltaQty <= 0) {
                    continue;
                }

                $deltaItems[] = [
                    'menu_id' => $menuId,
                    'qty' => $deltaQty,
                    'harga' => (float) $item['harga'],
                    'subtotal' => $deltaQty * (float) $item['harga'],
                    'addon_ids' => $item['addon_ids'] ?? [],
                ];
            }

            if (empty($deltaItems)) {
                $noDelta = true;
                $pesananId = $pesanan->id;
                return;
            }

            $allAddonIds = collect($deltaItems)->pluck('addon_ids')->flatten()->filter()->unique()->values()->all();
            $addons = \App\Models\Addon::query()
                ->select(['id', 'harga'])
                ->whereIn('id', $allAddonIds)
                ->get()
                ->keyBy('id');

            $detailRows = $existingDetails;
            $sig = static function (array $ids): string {
                $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn ($v) => $v > 0)));
                sort($ids);
                return implode(',', $ids);
            };

            foreach ($deltaItems as $item) {
                $addonIds = $item['addon_ids'] ?? [];
                $targetSig = $sig($addonIds);

                $match = $detailRows->first(function ($d) use ($item, $sig, $targetSig) {
                    if ((int) $d->menu_id !== (int) $item['menu_id']) {
                        return false;
                    }

                    $currentSig = $sig($d->addons?->pluck('id')?->all() ?? []);
                    return $currentSig === $targetSig;
                });

                if ($match) {
                    $currentQty = (int) $match->qty;
                    $deltaQty = (int) $item['qty'];
                    $unit = (float) ($match->harga ?? 0);
                    if ($unit <= 0) {
                        $unit = (float) $item['harga'];
                    }

                    $nextQty = max($currentQty + $deltaQty, 0);
                    $match->forceFill([
                        'qty' => $nextQty,
                        'harga' => $unit,
                        'subtotal' => $nextQty * $unit,
                    ])->save();

                    continue;
                }

                $detail = $pesanan->details()->create([
                    'menu_id' => $item['menu_id'],
                    'qty' => $item['qty'],
                    'harga' => $item['harga'],
                    'subtotal' => $item['subtotal'],
                ]);

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

                $detailRows->push($detail);
            }

            $newSubtotal = (float) $pesanan->details()->sum('subtotal');

            // Keep existing discount rule; recompute amount based on new subtotal.
            $newDiskonId = $pesanan->diskon_id;
            $newDiscountTotal = 0.0;
            if ($newDiskonId) {
                $diskon = \App\Models\Diskon::query()
                    ->whereKey((int) $newDiskonId)
                    ->where('is_active', true)
                    ->first();

                $today = now()->toDateString();
                if (
                    !$diskon ||
                    ($diskon->tanggal_mulai && $diskon->tanggal_mulai->toDateString() > $today) ||
                    ($diskon->tanggal_selesai && $diskon->tanggal_selesai->toDateString() < $today) ||
                    ($diskon->min_subtotal !== null && $newSubtotal < (float) $diskon->min_subtotal)
                ) {
                    $diskon = null;
                }

                if ($diskon) {
                    if ($diskon->tipe === 'percent') {
                        $newDiscountTotal = max($newSubtotal * ((float) $diskon->nilai / 100), 0);
                    } else {
                        $newDiscountTotal = max((float) $diskon->nilai, 0);
                    }
                    $newDiscountTotal = min($newDiscountTotal, $newSubtotal);
                } else {
                    $newDiskonId = null;
                }
            }

            $baseAfterDiscount = max($newSubtotal - $newDiscountTotal, 0);
            $newTaxTotal = max($baseAfterDiscount * ($taxPercent / 100), 0);
            $newTotal = max($baseAfterDiscount + $newTaxTotal, 0);

            $status = (string) $pesanan->status;
            if ($status === 'siap') {
                $status = 'diproses';
            }

            $update = [
                'subtotal' => $newSubtotal,
                'discount_total' => max($newDiscountTotal, 0),
                'tax_total' => max($newTaxTotal, 0),
                'total_harga' => $newTotal,
                'diskon_id' => $newDiskonId,
                'pajak_id' => $pajakId,
                'status' => $status,
            ];

            if ($customerName !== '') {
                $update['customer_name'] = $customerName;
            }

            if ($customerNote) {
                $existingNote = trim((string) ($pesanan->customer_note ?? ''));
                if ($existingNote === '') {
                    $update['customer_note'] = $customerNote;
                } else {
                    $same = mb_strtolower($existingNote) === mb_strtolower($customerNote);
                    if (!$same && !str_contains($existingNote, $customerNote)) {
                        $combined = trim($existingNote . "\n\n" . $customerNote);
                        $update['customer_note'] = \Illuminate\Support\Str::substr($combined, 0, 255);
                    }
                }
            }

            $pesanan->forceFill($update)->save();
            $pesananId = $pesanan->id;
            return;
        }

        $lockedMeja = \App\Models\Meja::query()->whereKey($meja->id)->lockForUpdate()->firstOrFail();
        $waitingListService = app(TableWaitingListService::class);
        if (!$waitingListService->hasCapacity($lockedMeja, $jumlahOrang)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'jumlah_orang' => __('This table is full. Please use the waiting list.'),
            ]);
        }

        $pesanan = \App\Models\Pesanan::create([
            'meja_id' => $meja->id,
            'kode_pesanan' => 'ORD-' . now()->format('YmdHis'),
            'status_token' => $orderStatus->generateToken(),
            'customer_name' => $customerName,
            'customer_note' => $customerNote,
            'jumlah_orang' => $jumlahOrang,
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

        $waitingListService->syncMejaStatus($lockedMeja);
        $waitingListService->forgetKitchenCache();
    });
    } catch (\Illuminate\Validation\ValidationException $e) {
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => false,
                'message' => collect($e->errors())->flatten()->first() ?: __('This table is full. Please use the waiting list.'),
                'redirect' => route('customer.waiting-list.index'),
            ], 409);
        }

        throw $e;
    }

    if ($existing && $addMode && $noDelta) {
        $statusToken = \App\Models\Pesanan::query()->whereKey((int) $existing->id)->value('status_token');
        $statusUrl = $statusToken
            ? route('customer.order-status', ['pesanan' => $existing->id, 'token' => $statusToken, 'noop' => 1])
            : route('customer.status', ['token' => $token, 'noop' => 1]);

        // No net changes (user added then reverted). Don't fail silently—return to status with a hint.
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'redirect' => $statusUrl,
                'order_id' => $existing->id,
            ]);
        }

        return redirect()->to($statusUrl);
    }

    session()->put('customer_order_submitted_' . $token, true);
    if ($pesananId) {
        session()->put('customer_order_submitted_' . $pesananId, true);
    }
    session()->put('customer_last_order_id_' . $token, $pesananId);

    // If JSON request, respond JSON; otherwise redirect.
    $statusToken = \App\Models\Pesanan::query()->whereKey((int) $pesananId)->value('status_token');
    $statusUrl = $statusToken
        ? route('customer.order-status', ['pesanan' => $pesananId, 'token' => $statusToken])
        : route('customer.status', ['token' => $token]);

    if ($request->expectsJson()) {
        return response()->json([
            'ok' => true,
            'redirect' => $statusUrl,
            'order_id' => $pesananId,
        ]);
    }

    return redirect()->to($statusUrl);
})
    ->where('token', '[A-Za-z0-9]{10}')
    ->name('customer.checkout.submit');

Route::get('{token}/status', function (string $token) {
    $token = \Illuminate\Support\Str::upper($token);

    $meja = \App\Models\Meja::query()
        ->select(['id', 'nomor_meja', 'qr_token', 'status'])
        ->where('qr_token', $token)
        ->where('status', '!=', 'nonaktif')
        ->firstOrFail();

    $lastOrderId = session()->get('customer_last_order_id_' . $token);

    $order = \App\Models\Pesanan::query()
        ->with(['details.menu', 'details.addons'])
        ->where('meja_id', $meja->id)
        ->when($lastOrderId, fn ($q) => $q->whereKey((int) $lastOrderId))
        ->whereIn('status', ['menunggu', 'sedang_diubah', 'diproses', 'siap'])
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
        'justCancelled' => (bool) session()->pull('customer_order_cancelled_' . $token, false),
        'editOrderUrl' => ($order && $order->status === 'menunggu' && !blank($order->status_token))
            ? route('customer.order-status.edit', ['pesanan' => $order->id, 'token' => $order->status_token])
            : null,
        'continueEditUrl' => ($order && $order->status === 'sedang_diubah' && !blank($order->status_token))
            ? route('customer.order', ['token' => $token, 'edit' => 1, 'order' => $order->id, 'status_token' => $order->status_token])
            : null,
        'cancelOrderUrl' => ($order && in_array($order->status, ['menunggu', 'sedang_diubah'], true) && !blank($order->status_token))
            ? route('customer.order-status.cancel', ['pesanan' => $order->id, 'token' => $order->status_token])
            : null,
    ]);
})
    ->where('token', '[A-Za-z0-9]{10}')
    ->name('customer.status');

Route::get('{token}/status.json', function (string $token) {
    $token = \Illuminate\Support\Str::upper($token);

    $meja = \App\Models\Meja::query()
        ->select(['id', 'nomor_meja', 'qr_token', 'status'])
        ->where('qr_token', $token)
        ->where('status', '!=', 'nonaktif')
        ->firstOrFail();

    $lastOrderId = session()->get('customer_last_order_id_' . $token);

    $active = \App\Models\Pesanan::query()
        ->with(['details.menu:id,nama_menu,nama_menu_en', 'details.addons:id,nama_addon,nama_addon_en'])
        ->where('meja_id', $meja->id)
        ->when($lastOrderId, fn ($q) => $q->whereKey((int) $lastOrderId))
        ->whereIn('status', ['menunggu', 'sedang_diubah', 'diproses', 'siap'])
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
             'items' => $active->details
                ->groupBy(function ($d) {
                    $ids = $d->addons?->pluck('id')?->map(fn ($v) => (int) $v)->filter(fn ($v) => $v > 0)->unique()->sort()->values()->all() ?? [];
                    return (int) $d->menu_id . ':' . implode(',', $ids);
                })
                ->map(function ($rows) {
                     $first = $rows->first();
                     $addons = $first?->addons
                         ? $first->addons->map(fn ($a) => (string) $a->nama_addon_localized)->filter()->values()->all()
                         : [];
 
                     return [
                         'menu' => (string) ($first?->menu?->nama_menu_localized ?? __('Menu')),
                         'qty' => (int) $rows->sum('qty'),
                         'subtotal' => (float) $rows->sum('subtotal'),
                         'addons' => $addons,
                     ];
                 })
                ->sortBy(function ($row) {
                    $menu = strtolower((string) ($row['menu'] ?? ''));
                    $addons = strtolower(implode(',', (array) ($row['addons'] ?? [])));
                    return $menu . '|' . $addons;
                })
                 ->values()
                 ->all(),
         ] : null,
     ], 200, [
        'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
    ]);
})
    ->where('token', '[A-Za-z0-9]{10}')
    ->name('customer.status.json');

Route::get('{token}', function (\Illuminate\Http\Request $request, string $token) {
    $token = \Illuminate\Support\Str::upper($token);
    $addMode = (bool) $request->boolean('add');
    $editMode = (bool) $request->boolean('edit');
    $editOrderId = (int) $request->query('order', 0);
    $editStatusToken = (string) $request->query('status_token', '');

    $meja = \App\Models\Meja::query()
        ->select(['id', 'nomor_meja', 'qr_token', 'status'])
        ->where('qr_token', $token)
        ->where('status', '!=', 'nonaktif')
        ->firstOrFail();

    $orderQuery = $editMode ? \App\Models\Pesanan::query()
        ->with(['details.addons:id'])
        ->whereKey($editOrderId)
        ->where('meja_id', $meja->id)
        ->where('status', 'sedang_diubah')
        ->first() : ($addMode ? \App\Models\Pesanan::query()
        ->when($addMode, fn ($q) => $q->with(['details.addons:id']))
        ->where('meja_id', $meja->id)
        ->whereIn('status', ['menunggu', 'sedang_diubah', 'diproses', 'siap'])
        ->where(function ($q) {
            $q->whereNull('metode_pembayaran')->orWhere('metode_pembayaran', '');
        })
        ->orderByDesc('waktu_pesan')
        ->first() : null);

    $order = $orderQuery;

    if ($editMode) {
        abort_if(!$order || blank($editStatusToken) || !app(OrderStatusService::class)->tokenMatches($order, $editStatusToken), 404);
    }

    if (!$order) {
        $addMode = false;
        $editMode = false;
    }

    $categories = Cache::remember('customer:categories:v1', 900, function () {
        return \App\Models\KategoriMenu::query()
            ->where('is_active', true)
            ->orderBy('nama_kategori')
            ->get(['id', 'nama_kategori', 'nama_kategori_en']);
    });

    $menus = Cache::remember('customer:menus_orderable_display:v1', 900, function () {
        return \App\Models\Menu::query()
            ->with([
                'kategori:id,nama_kategori,nama_kategori_en',
                'addons' => function ($q) {
                    $q->where('status', 'tersedia')->orderBy('nama_addon');
                },
            ])
            ->whereIn('status', ['tersedia', 'habis'])
            ->orderBy('kategori_id')
            ->orderBy('nama_menu')
            ->get(['id', 'kategori_id', 'nama_menu', 'nama_menu_en', 'deskripsi', 'deskripsi_en', 'harga', 'gambar', 'status']);
    });

    $baselineCart = [];
    $baselineMinQty = [];
    $prefillCart = [];
    if ($order && $addMode) {
        $sig = static function (\App\Models\PesananDetail $d): string {
            $ids = $d->addons?->pluck('id')?->map(fn ($v) => (int) $v)->filter(fn ($v) => $v > 0)->unique()->sort()->values()->all() ?? [];
            return (int) $d->menu_id . ':' . implode(',', $ids);
        };

        $groups = $order->details->groupBy($sig);
        foreach ($groups as $key => $rows) {
            $key = (string) $key;
            $qty = (int) $rows->sum('qty');
            if ($qty <= 0) {
                continue;
            }

            $first = $rows->first();
            $addonIds = $first?->addons?->pluck('id')?->map(fn ($v) => (int) $v)->filter(fn ($v) => $v > 0)->unique()->sort()->values()->all() ?? [];

            $baselineCart[$key] = [
                'qty' => $qty,
                'addons' => $addonIds,
            ];
            $baselineMinQty[$key] = $qty;
        }
    }

    if ($order && $editMode) {
        $sig = static function (\App\Models\PesananDetail $d): string {
            $ids = $d->addons?->pluck('id')?->map(fn ($v) => (int) $v)->filter(fn ($v) => $v > 0)->unique()->sort()->values()->all() ?? [];
            return (int) $d->menu_id . ':' . implode(',', $ids);
        };

        foreach ($order->details->groupBy($sig) as $key => $rows) {
            $first = $rows->first();
            $addonIds = $first?->addons?->pluck('id')?->map(fn ($v) => (int) $v)->filter(fn ($v) => $v > 0)->unique()->sort()->values()->all() ?? [];

            $prefillCart[(string) $key] = [
                'qty' => (int) $rows->sum('qty'),
                'addons' => $addonIds,
            ];
        }
    }

    return view('customer.order', [
        'token' => $token,
        'meja' => $meja,
        'order' => $order,
        'addMode' => $addMode,
        'editMode' => $editMode,
        'baselineCart' => $baselineCart,
        'baselineMinQty' => $baselineMinQty,
        'prefillCart' => $prefillCart,
        'categories' => $categories,
        'menus' => $menus,
        'checkoutUrl' => $editMode
            ? route('customer.checkout', ['token' => $token, 'edit' => 1, 'order' => $order?->id, 'status_token' => $editStatusToken])
            : null,
        'menuUpdatesUrl' => route('customer.menu-updates', ['token' => $token]),
        'menuVersion' => (int) Cache::get('customer:menu_version', 1),
    ]);
})
    ->where('token', '[A-Za-z0-9]{10}')
    ->name('customer.order');

// Backward-compatible redirect (old QR links)
Route::get('order/{token}', function (string $token) {
    return redirect()->route('customer.order', ['token' => $token]);
})->where('token', '[A-Za-z0-9]{10}');
