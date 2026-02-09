<?php

use App\Models\Diskon;
use App\Models\Meja;
use App\Models\Pajak;
use App\Models\Pesanan;
use App\Models\PesananDetail;
use App\Models\Menu;
use App\Models\Addon;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public ?int $confirmingDeleteId = null;
    public ?int $editingId = null;
    public array $form = [];
    public array $orderItems = [];
    public string $search = '';
    public string $statusFilter = 'all';
    public string $paymentFilter = 'all';

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => 'all'],
        'paymentFilter' => ['except' => 'all'],
        'page' => ['except' => 1],
    ];

    public function mount(): void
    {
        $this->resetCreateForm();
    }

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingStatusFilter(): void { $this->resetPage(); }
    public function updatingPaymentFilter(): void { $this->resetPage(); }

    public function openCreateModal(): void
    {
        $this->authorizeManage();
        $this->resetCreateForm();
        $this->orderItems = [];
        $this->addItem();
        $this->dispatch('modal-show', name: 'create-pesanan');
    }

    public function openEditModal(int $id): void
    {
        $this->authorizeManage();
        $pesanan = Pesanan::with(['details.menu', 'details.addons'])->findOrFail($id);
        $this->editingId = $pesanan->id;

        $this->form = [
            'meja_id' => $pesanan->meja_id,
            'kode_pesanan' => $pesanan->kode_pesanan,
            'customer_name' => $pesanan->customer_name,
            'customer_note' => $pesanan->customer_note,
            'subtotal' => $pesanan->subtotal,
            'discount_total' => $pesanan->discount_total,
            'tax_total' => $pesanan->tax_total,
            'total_harga' => $pesanan->total_harga,
            'status' => $pesanan->status,
            'metode_pembayaran' => $pesanan->metode_pembayaran,
            'dibayar' => $pesanan->dibayar,
            'kembalian' => $pesanan->kembalian,
            'kasir_id' => $pesanan->kasir_id,
            'chef_id' => $pesanan->chef_id,
            'diskon_id' => $pesanan->diskon_id,
            'pajak_id' => $pesanan->pajak_id,
        ];

        $this->orderItems = $pesanan->details->map(function (PesananDetail $detail) {
            return [
                'id' => $detail->id,
                'menu_id' => $detail->menu_id,
                'qty' => $detail->qty,
                'harga' => (float) $detail->harga,
                'subtotal' => (float) $detail->subtotal,
                'addon_ids' => $detail->addons->pluck('id')->all(),
                'catatan' => $detail->catatan,
            ];
        })->toArray();

        $this->recalculateTotals();

        $this->dispatch('modal-show', name: 'edit-pesanan');
    }

    public function save(): void
    {
        $this->authorizeManage();
        $data = array_merge($this->form, ['items' => $this->orderItems]);
        foreach (['customer_name', 'customer_note', 'discount_total', 'tax_total', 'chef_id', 'diskon_id', 'pajak_id'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === '') {
                $data[$field] = null;
            }
        }

        $validated = validator($data, [
            'meja_id' => ['required', 'exists:mejas,id'],
            'kode_pesanan' => ['nullable', 'string', 'max:20', 'unique:pesanans,kode_pesanan'],
            'customer_name' => ['nullable', 'string', 'max:100'],
            'customer_note' => ['nullable', 'string', 'max:255'],
            'discount_total' => ['nullable', 'numeric', 'min:0'],
            'tax_total' => ['nullable', 'numeric', 'min:0'],
            'chef_id' => ['nullable', 'exists:users,id'],
            'diskon_id' => ['nullable', 'exists:diskons,id'],
            'pajak_id' => ['nullable', 'exists:pajaks,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.menu_id' => ['required', 'exists:menus,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.addon_ids' => ['nullable', 'array'],
            'items.*.addon_ids.*' => ['integer', 'exists:addons,id'],
            'items.*.catatan' => ['nullable', 'string'],
        ])->validate();

        if (empty($validated['kode_pesanan'])) {
            $validated['kode_pesanan'] = 'ORD-' . now()->format('YmdHis');
        }

        $validated['customer_name'] = blank($validated['customer_name'] ?? null) ? __('Guest') : $validated['customer_name'];
        $validated['waktu_pesan'] = now();
        $validated['status'] = 'menunggu';

        $items = $this->normalizeItemsForSave($validated['items']);
        $totals = $this->calculateTotals(
            $items,
            $validated['diskon_id'] ?? null,
            $validated['pajak_id'] ?? null,
            $validated['discount_total'] ?? null,
            $validated['tax_total'] ?? null,
        );

        DB::transaction(function () use ($validated, $items, $totals) {
            $pesanan = Pesanan::create([
                'meja_id' => $validated['meja_id'],
                'kode_pesanan' => $validated['kode_pesanan'],
                'customer_name' => $validated['customer_name'],
                'customer_note' => $validated['customer_note'] ?? null,
                'subtotal' => $totals['subtotal'],
                'discount_total' => $totals['discount_total'],
                'tax_total' => $totals['tax_total'],
                'total_harga' => $totals['total_harga'],
                'status' => $validated['status'],
                'metode_pembayaran' => null,
                'dibayar' => null,
                'kembalian' => null,
                'kasir_id' => null,
                'chef_id' => $validated['chef_id'] ?? null,
                'diskon_id' => $validated['diskon_id'] ?? null,
                'pajak_id' => $validated['pajak_id'] ?? null,
                'waktu_pesan' => $validated['waktu_pesan'],
            ]);

            $allAddonIds = collect($items)->pluck('addon_ids')->flatten()->filter()->unique()->values()->all();
            $addons = Addon::query()
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
                    'catatan' => $item['catatan'] ?? null,
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

        $this->resetCreateForm();
        $this->dispatch('modal-close', name: 'create-pesanan');
        $this->dispatch('pesanan-toast', message: __('Order created successfully.'));
    }

    public function update(): void
    {
        $this->authorizeManage();
        if (!$this->editingId) {
            return;
        }

        $data = array_merge($this->form, ['items' => $this->orderItems]);
        foreach (['customer_name', 'customer_note', 'discount_total', 'tax_total', 'metode_pembayaran', 'dibayar', 'kasir_id', 'chef_id', 'diskon_id', 'pajak_id'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === '') {
                $data[$field] = null;
            }
        }

        $validated = validator($data, [
            'meja_id' => ['required', 'exists:mejas,id'],
            'kode_pesanan' => ['nullable', 'string', 'max:20', 'unique:pesanans,kode_pesanan,' . $this->editingId],
            'customer_name' => ['nullable', 'string', 'max:100'],
            'customer_note' => ['nullable', 'string', 'max:255'],
            'discount_total' => ['nullable', 'numeric', 'min:0'],
            'tax_total' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'in:menunggu,diproses,siap,selesai,batal'],
            'metode_pembayaran' => ['nullable', 'in:tunai,transfer,qris'],
            'dibayar' => ['nullable', 'numeric', 'min:0'],
            'kasir_id' => ['nullable', 'exists:users,id'],
            'chef_id' => ['nullable', 'exists:users,id'],
            'diskon_id' => ['nullable', 'exists:diskons,id'],
            'pajak_id' => ['nullable', 'exists:pajaks,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.menu_id' => ['required', 'exists:menus,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.addon_ids' => ['nullable', 'array'],
            'items.*.addon_ids.*' => ['integer', 'exists:addons,id'],
            'items.*.catatan' => ['nullable', 'string'],
        ])->validate();

        $pesanan = Pesanan::findOrFail($this->editingId);

        $validated['customer_name'] = blank($validated['customer_name'] ?? null) ? __('Guest') : $validated['customer_name'];

        $items = $this->normalizeItemsForSave($validated['items']);
        $totals = $this->calculateTotals(
            $items,
            $validated['diskon_id'] ?? null,
            $validated['pajak_id'] ?? null,
            $validated['discount_total'] ?? null,
            $validated['tax_total'] ?? null,
        );

        $status = $validated['status'];
        $method = $validated['metode_pembayaran'] ?? null;

        if ($status === 'selesai') {
            if (blank($method)) {
                $this->dispatch('pesanan-toast', message: __('Payment method is required to complete an order.'));
                return;
            }
        } else {
            if (!blank($method)) {
                $this->dispatch('pesanan-toast', message: __('Paid orders must be marked as completed.'));
                return;
            }
        }

        $dibayar = null;
        $kembalian = null;

        if ($status === 'selesai') {
            $total = (float) $totals['total_harga'];
            if (in_array($method, ['transfer', 'qris'], true)) {
                $dibayar = $total;
                $kembalian = 0;
            } else {
                $dibayar = (float) ($validated['dibayar'] ?? 0);
                if ($dibayar < $total) {
                    $this->dispatch('pesanan-toast', message: __('Paid amount is insufficient.'));
                    return;
                }
                $kembalian = max($dibayar - $total, 0);
            }

            if (!$pesanan->waktu_selesai) {
                $validated['waktu_selesai'] = now();
            }

            if (empty($validated['kasir_id']) && auth()->check()) {
                $validated['kasir_id'] = auth()->id();
            }
        }

        DB::transaction(function () use ($validated, $pesanan) {
            $items = $this->normalizeItemsForSave($validated['items']);
            $totals = $this->calculateTotals(
                $items,
                $validated['diskon_id'] ?? null,
                $validated['pajak_id'] ?? null,
                $validated['discount_total'] ?? null,
                $validated['tax_total'] ?? null,
            );

            $status = $validated['status'];
            $method = $validated['metode_pembayaran'] ?? null;

            $dibayar = null;
            $kembalian = null;
            if ($status === 'selesai') {
                $total = (float) $totals['total_harga'];
                if (in_array($method, ['transfer', 'qris'], true)) {
                    $dibayar = $total;
                    $kembalian = 0;
                } else {
                    $dibayar = (float) ($validated['dibayar'] ?? 0);
                    $kembalian = max($dibayar - $total, 0);
                }
            } else {
                $method = null;
            }

            $pesananData = collect($validated)
                ->except('items', 'subtotal', 'total_harga', 'kembalian')
                ->toArray();

            $pesananData['subtotal'] = $totals['subtotal'];
            $pesananData['discount_total'] = $totals['discount_total'];
            $pesananData['tax_total'] = $totals['tax_total'];
            $pesananData['total_harga'] = $totals['total_harga'];
            $pesananData['metode_pembayaran'] = $method;
            $pesananData['dibayar'] = $dibayar;
            $pesananData['kembalian'] = $kembalian;

            $pesanan->update($pesananData);

            $allAddonIds = collect($items)->pluck('addon_ids')->flatten()->filter()->unique()->values()->all();
            $addons = Addon::query()
                ->select(['id', 'harga'])
                ->whereIn('id', $allAddonIds)
                ->get()
                ->keyBy('id');

            $existingDetails = $pesanan->details()->get()->keyBy('id');
            $keptIds = [];

            foreach ($items as $item) {
                $payload = [
                    'menu_id' => $item['menu_id'],
                    'qty' => $item['qty'],
                    'harga' => $item['harga'],
                    'subtotal' => $item['subtotal'],
                    'catatan' => $item['catatan'] ?? null,
                ];

                if (!empty($item['id']) && $existingDetails->has($item['id'])) {
                    $detail = $existingDetails[$item['id']];
                    $detail->update($payload);
                } else {
                    $detail = $pesanan->details()->create($payload);
                }

                $addonIds = $item['addon_ids'] ?? [];
                $sync = [];
                if (!empty($addonIds)) {
                    foreach ($addonIds as $addonId) {
                        if ($addons->has($addonId)) {
                            $sync[$addonId] = ['harga' => (float) $addons[$addonId]->harga];
                        }
                    }
                }
                $detail->addons()->sync($sync);

                $keptIds[] = $detail->id;
            }

            if (!empty($keptIds)) {
                $pesanan->details()->whereNotIn('id', $keptIds)->delete();
            } else {
                $pesanan->details()->delete();
            }
        });

        $this->editingId = null;
        $this->dispatch('modal-close', name: 'edit-pesanan');
        $this->dispatch('pesanan-toast', message: __('Order updated successfully.'));
    }

    public function confirmDelete(int $id): void
    {
        $this->authorizeManage();
        $this->confirmingDeleteId = $id;
    }

    public function delete(): void
    {
        $this->authorizeManage();
        if ($this->confirmingDeleteId) {
            Pesanan::where('id', $this->confirmingDeleteId)->delete();
            $this->confirmingDeleteId = null;

            $this->dispatch('modal-close', name: 'confirm-delete-pesanan');
            $this->dispatch('modal-close', name: 'confirm-delete-pesanan-desktop');
            $this->dispatch('pesanan-toast', message: __('Order deleted successfully.'));
        }
    }

    protected function resetCreateForm(): void
    {
        $this->form = [
            'meja_id' => null,
            'kode_pesanan' => '',
            'customer_name' => '',
            'customer_note' => '',
            'subtotal' => 0,
            'discount_total' => 0,
            'tax_total' => 0,
            'total_harga' => 0,
            'status' => 'menunggu',
            'metode_pembayaran' => null,
            'dibayar' => null,
            'kembalian' => null,
            'kasir_id' => null,
            'chef_id' => null,
            'diskon_id' => null,
            'pajak_id' => null,
        ];

        $this->orderItems = [];
    }

    protected function authorizeManage(): void
    {
        abort_unless(auth()->check() && auth()->user()->can('pesanan.manage'), 403);
    }

    public function addItem(): void
    {
        $this->orderItems[] = [
            'id' => null,
            'menu_id' => '',
            'qty' => 1,
            'harga' => 0,
            'subtotal' => 0,
            'addon_ids' => [],
            'catatan' => '',
        ];
    }

    public function removeItem(int $index): void
    {
        if (!isset($this->orderItems[$index])) {
            return;
        }

        unset($this->orderItems[$index]);
        $this->orderItems = array_values($this->orderItems);

        $this->recalculateTotals();
    }

    public function updatedOrderItems($value, $name): void
    {
        if (preg_match('/^(?:orderItems\\.)?(\\d+)\\.(menu_id|qty|addon_ids)$/', (string) $name, $matches)) {
            $index = (int) $matches[1];
            $field = $matches[2];

            if ($field === 'menu_id') {
                $menuId = (int) ($this->orderItems[$index]['menu_id'] ?? 0);
                $this->orderItems[$index]['addon_ids'] = [];
                if ($menuId > 0) {
                    $menu = Menu::find($menuId);
                    if ($menu) {
                        $this->orderItems[$index]['harga'] = (float) $menu->harga;
                    }
                }
            }

            $this->recalculateItemSubtotal($index);
        }
    }

    public function updatedForm($value, $name): void
    {
        if (in_array($name, ['discount_total', 'tax_total'], true)) {
            $this->recalculateTotals();
        }
    }

    protected function recalculateItemSubtotal(int $index): void
    {
        if (!isset($this->orderItems[$index])) {
            return;
        }

        $qty = (int) ($this->orderItems[$index]['qty'] ?? 0);
        $menuId = (int) ($this->orderItems[$index]['menu_id'] ?? 0);

        $harga = 0.0;
        if ($menuId > 0) {
            $menu = Menu::query()->select(['id', 'harga'])->find($menuId);
            $base = (float) ($menu?->harga ?? 0);

            $addonIds = array_values(array_filter(array_map('intval', (array) ($this->orderItems[$index]['addon_ids'] ?? []))));
            if (!empty($addonIds)) {
                $addonTotal = (float) Addon::query()
                    ->whereIn('id', $addonIds)
                    ->where('status', 'tersedia')
                    ->whereHas('menus', fn ($q) => $q->whereKey($menuId))
                    ->sum('harga');
                $harga = $base + $addonTotal;
            } else {
                $harga = $base;
            }
        }

        $this->orderItems[$index]['qty'] = $qty;
        $this->orderItems[$index]['harga'] = $harga;
        $this->orderItems[$index]['subtotal'] = $qty * $harga;

        $this->recalculateTotals();
    }

    protected function recalculateTotals(): void
    {
        $subtotal = 0;

        foreach ($this->orderItems as $item) {
            $subtotal += (float) ($item['subtotal'] ?? 0);
        }

        $discount = (float) ($this->form['discount_total'] ?? 0);
        $tax = (float) ($this->form['tax_total'] ?? 0);

        $this->form['subtotal'] = $subtotal;
        $this->form['total_harga'] = max($subtotal - $discount + $tax, 0);
    }

    protected function normalizeItemsForSave(array $items): array
    {
        $menuIds = collect($items)->pluck('menu_id')->filter()->unique()->values()->all();
        $menus = Menu::query()
            ->with(['addons' => fn ($q) => $q->where('status', 'tersedia')->orderBy('nama_addon')])
            ->whereIn('id', $menuIds)
            ->get()
            ->keyBy('id');

        $normalized = [];
        foreach ($items as $item) {
            $menuId = (int) ($item['menu_id'] ?? 0);
            $qty = (int) ($item['qty'] ?? 0);
            if ($menuId <= 0 || $qty <= 0 || !$menus->has($menuId)) {
                continue;
            }

            $menu = $menus[$menuId];
            $baseHarga = (float) $menu->harga;

            $addonIds = array_values(array_filter(array_map('intval', (array) ($item['addon_ids'] ?? []))));
            $allowedAddonIds = $menu->addons->pluck('id')->all();
            $addonIds = array_values(array_intersect($addonIds, $allowedAddonIds));

            $addonTotal = 0.0;
            if (!empty($addonIds)) {
                $addonTotal = (float) $menu->addons->whereIn('id', $addonIds)->sum('harga');
            }

            $harga = $baseHarga + $addonTotal;
            $normalized[] = [
                'id' => $item['id'] ?? null,
                'menu_id' => $menuId,
                'qty' => $qty,
                'harga' => $harga,
                'subtotal' => $qty * $harga,
                'addon_ids' => $addonIds,
                'catatan' => $item['catatan'] ?? null,
            ];
        }

        return $normalized;
    }

    protected function calculateTotals(array $items, ?int $diskonId, ?int $pajakId, $discountOverride, $taxOverride): array
    {
        $subtotal = 0.0;
        foreach ($items as $item) {
            $subtotal += (float) ($item['subtotal'] ?? 0);
        }

        $discountTotal = is_numeric($discountOverride) ? (float) $discountOverride : 0.0;
        $taxTotal = is_numeric($taxOverride) ? (float) $taxOverride : 0.0;

        if ($diskonId) {
            $diskon = Diskon::find($diskonId);
            if ($diskon && blank($discountOverride)) {
                $discountTotal = $this->computeDiscount($diskon, $subtotal);
            }
        }

        $baseAfterDiscount = max($subtotal - $discountTotal, 0);

        if ($pajakId) {
            $pajak = Pajak::find($pajakId);
            if ($pajak && blank($taxOverride)) {
                $taxTotal = $this->computeTax($pajak, $baseAfterDiscount);
            }
        }

        $total = max($subtotal - $discountTotal + $taxTotal, 0);

        return [
            'subtotal' => $subtotal,
            'discount_total' => max($discountTotal, 0),
            'tax_total' => max($taxTotal, 0),
            'total_harga' => $total,
        ];
    }

    protected function computeDiscount(Diskon $diskon, float $subtotal): float
    {
        if ($diskon->min_subtotal !== null && $subtotal < (float) $diskon->min_subtotal) {
            return 0.0;
        }

        if ($diskon->tipe === 'percent') {
            return max($subtotal * ((float) $diskon->nilai / 100), 0);
        }

        return max((float) $diskon->nilai, 0);
    }

    protected function computeTax(Pajak $pajak, float $base): float
    {
        return max($base * ((float) $pajak->persentase / 100), 0);
    }
}; ?>

<section class="w-full">
    @php
        $query = Pesanan::with(['meja', 'diskon', 'pajak', 'kasir']);

        if (!empty($search)) {
            $query->where(function ($sub) use ($search) {
                $sub->where('kode_pesanan', 'like', '%'.$search.'%')
                    ->orWhere('customer_name', 'like', '%'.$search.'%');
            });
        }

        if ($statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        if ($paymentFilter !== 'all') {
            if ($paymentFilter === 'none') {
                $query->where(function ($q) {
                    $q->whereNull('metode_pembayaran')->orWhere('metode_pembayaran', '');
                });
            } else {
                $query->where('metode_pembayaran', $paymentFilter);
            }
        }

        $items = $query->orderByDesc('waktu_pesan')->orderByDesc('id')->paginate(10);

        $totalCount      = Pesanan::count();
        $openCount       = Pesanan::whereIn('status', ['menunggu', 'diproses', 'siap'])->count();
        $completedCount  = Pesanan::where('status', 'selesai')->count();
        $cancelledCount  = Pesanan::where('status', 'batal')->count();

        $statusMeta = [
            'menunggu' => [
                'label' => __('Waiting'),
                'badge' => 'bg-amber-50 text-amber-700 ring-1 ring-amber-100 dark:bg-amber-900/40 dark:text-amber-200 dark:ring-amber-800/60',
                'dot'   => 'bg-amber-500',
            ],
            'diproses' => [
                'label' => __('In progress'),
                'badge' => 'bg-blue-50 text-blue-700 ring-1 ring-blue-100 dark:bg-blue-900/40 dark:text-blue-200 dark:ring-blue-800/60',
                'dot'   => 'bg-blue-500',
            ],
            'siap' => [
                'label' => __('Ready'),
                'badge' => 'bg-cyan-50 text-cyan-700 ring-1 ring-cyan-100 dark:bg-cyan-900/40 dark:text-cyan-200 dark:ring-cyan-800/60',
                'dot'   => 'bg-cyan-500',
            ],
            'selesai' => [
                'label' => __('Completed'),
                'badge' => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100 dark:bg-emerald-900/40 dark:text-emerald-200 dark:ring-emerald-800/60',
                'dot'   => 'bg-emerald-500',
            ],
            'batal' => [
                'label' => __('Cancelled'),
                'badge' => 'bg-red-50 text-red-700 ring-1 ring-red-100 dark:bg-red-900/40 dark:text-red-200 dark:ring-red-800/60',
                'dot'   => 'bg-red-500',
            ],
        ];

        $mejas   = Meja::orderBy('nomor_meja')->get();
        $diskons = Diskon::orderBy('kode')->get();
        $pajaks  = Pajak::orderBy('nama')->get();
        $users   = User::orderBy('name')->get();
        $menus   = Menu::query()
            ->with(['addons' => fn ($q) => $q->where('status', 'tersedia')->orderBy('nama_addon')])
            ->orderBy('nama_menu')
            ->get();
        $menusById = $menus->keyBy('id');

        $selectedPesanan = $items->firstWhere('id', $confirmingDeleteId);
        $editingPesanan = $items->firstWhere('id', $editingId);
    @endphp

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <flux:heading size="xl" level="1">{{ __('Orders') }}</flux:heading>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @can('pesanan.manage')
                    <flux:button icon="plus" variant="primary" class="btn-brand" wire:click="openCreateModal">
                        {{ __('Create') }}
                    </flux:button>
                @endcan
            </div>
        </div>

        <!-- Mobile summary chips -->
        <div class="block sm:hidden -mx-4 overflow-x-auto no-scrollbar">
            <div class="flex gap-2 px-4">
                <div class="whitespace-nowrap rounded-full border border-neutral-200/70 bg-white/85 px-3 py-2 text-[11px] shadow-sm dark:border-neutral-700/60 dark:bg-neutral-900/70">
                    <span class="inline-flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-neutral-500"></span>
                        <span class="text-neutral-600 dark:text-neutral-300">{{ __('Total') }}</span>
                        <span class="font-semibold text-neutral-900 dark:text-white">{{ $totalCount }}</span>
                    </span>
                </div>
                <div class="whitespace-nowrap rounded-full border border-neutral-200/70 bg-white/85 px-3 py-2 text-[11px] shadow-sm dark:border-neutral-700/60 dark:bg-neutral-900/70">
                    <span class="inline-flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                        <span class="text-neutral-600 dark:text-neutral-300">{{ __('Active') }}</span>
                        <span class="font-semibold text-neutral-900 dark:text-white">{{ $openCount }}</span>
                    </span>
                </div>
                <div class="whitespace-nowrap rounded-full border border-neutral-200/70 bg-white/85 px-3 py-2 text-[11px] shadow-sm dark:border-neutral-700/60 dark:bg-neutral-900/70">
                    <span class="inline-flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                        <span class="text-neutral-600 dark:text-neutral-300">{{ __('Completed') }}</span>
                        <span class="font-semibold text-neutral-900 dark:text-white">{{ $completedCount }}</span>
                    </span>
                </div>
                <div class="whitespace-nowrap rounded-full border border-neutral-200/70 bg-white/85 px-3 py-2 text-[11px] shadow-sm dark:border-neutral-700/60 dark:bg-neutral-900/70">
                    <span class="inline-flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-red-500"></span>
                        <span class="text-neutral-600 dark:text-neutral-300">{{ __('Cancelled') }}</span>
                        <span class="font-semibold text-neutral-900 dark:text-white">{{ $cancelledCount }}</span>
                    </span>
                </div>
            </div>
        </div>

        <!-- Desktop summary cards -->
        <div class="hidden sm:grid gap-2 sm:gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl sm:rounded-2xl border border-neutral-200/70 bg-white/85 p-3 sm:p-4 shadow-sm sm:shadow-lg shadow-neutral-200/40 dark:border-neutral-700/60 dark:bg-neutral-900/70 dark:shadow-black/30">
                <p class="text-[11px] sm:text-xs font-medium uppercase tracking-[0.18em] sm:tracking-[0.2em] text-neutral-400">{{ __('Total Orders') }}</p>
                <div class="mt-2 sm:mt-3 flex items-baseline gap-2">
                    <span class="text-2xl sm:text-3xl font-semibold text-neutral-900 dark:text-white">{{ $totalCount }}</span>
                    <span class="text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('records') }}</span>
                </div>
            </div>
            <div class="rounded-xl sm:rounded-2xl border border-neutral-200/70 bg-white/85 p-3 sm:p-4 shadow-sm sm:shadow-lg shadow-neutral-200/40 dark:border-neutral-700/60 dark:bg-neutral-900/70 dark:shadow-black/30">
                <div class="flex items-center gap-2 text-[11px] sm:text-xs font-medium uppercase tracking-[0.18em] sm:tracking-[0.2em] text-neutral-400">
                    <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                    <span>{{ __('Active Orders') }}</span>
                </div>
                <div class="mt-2 sm:mt-3 flex items-baseline gap-2">
                    <span class="text-2xl sm:text-3xl font-semibold text-neutral-900 dark:text-white">{{ $openCount }}</span>
                    <span class="text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('items') }}</span>
                </div>
                <p class="mt-1 text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('Waiting, in progress, and ready') }}</p>
            </div>
            <div class="rounded-xl sm:rounded-2xl border border-neutral-200/70 bg-white/85 p-3 sm:p-4 shadow-sm sm:shadow-lg shadow-neutral-200/40 dark:border-neutral-700/60 dark:bg-neutral-900/70 dark:shadow-black/30">
                <div class="flex items-center gap-2 text-[11px] sm:text-xs font-medium uppercase tracking-[0.18em] sm:tracking-[0.2em] text-neutral-400">
                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                    <span>{{ __('Completed') }}</span>
                </div>
                <div class="mt-2 sm:mt-3 flex items-baseline gap-2">
                    <span class="text-2xl sm:text-3xl font-semibold text-neutral-900 dark:text-white">{{ $completedCount }}</span>
                    <span class="text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('items') }}</span>
                </div>
                <p class="mt-1 text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('Already paid and closed') }}</p>
            </div>
            <div class="rounded-xl sm:rounded-2xl border border-neutral-200/70 bg-white/85 p-3 sm:p-4 shadow-sm sm:shadow-lg shadow-neutral-200/40 dark:border-neutral-700/60 dark:bg-neutral-900/70 dark:shadow-black/30">
                <div class="flex items-center gap-2 text-[11px] sm:text-xs font-medium uppercase tracking-[0.18em] sm:tracking-[0.2em] text-neutral-400">
                    <span class="h-2 w-2 rounded-full bg-red-500"></span>
                    <span>{{ __('Cancelled') }}</span>
                </div>
                <div class="mt-2 sm:mt-3 flex items-baseline gap-2">
                    <span class="text-2xl sm:text-3xl font-semibold text-neutral-900 dark:text-white">{{ $cancelledCount }}</span>
                    <span class="text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('items') }}</span>
                </div>
                <p class="mt-1 text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('Void orders for history only') }}</p>
            </div>
        </div>

        <!-- filters -->
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex-1 min-w-0">
                <flux:input
                    wire:model.live.debounce.800ms="search"
                    :placeholder="__('Search order code or customer')"
                />
            </div>
            <div class="flex items-center gap-2 min-w-0 sm:justify-end">
                <flux:select
                    wire:model.live="statusFilter"
                    class="flex-1 min-w-0 sm:flex-none sm:w-44 rounded-full border-neutral-200 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900 focus:outline-hidden focus:ring-2 focus:ring-[color:var(--brand-accent)] focus:ring-offset-2 focus:ring-offset-[color:var(--brand-accent-foreground)]"
                >
                    <option value="all">{{ __('All Status') }}</option>
                    <option value="menunggu">{{ __('Waiting') }}</option>
                    <option value="diproses">{{ __('In progress') }}</option>
                    <option value="siap">{{ __('Ready') }}</option>
                    <option value="selesai">{{ __('Completed') }}</option>
                    <option value="batal">{{ __('Cancelled') }}</option>
                </flux:select>

                <flux:select
                    wire:model.live="paymentFilter"
                    class="flex-1 min-w-0 sm:flex-none sm:w-44 rounded-full border-neutral-200 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900 focus:outline-hidden focus:ring-2 focus:ring-[color:var(--brand-accent)] focus:ring-offset-2 focus:ring-offset-[color:var(--brand-accent-foreground)]"
                >
                    <option value="all">{{ __('All Payments') }}</option>
                    <option value="tunai">{{ __('Cash') }}</option>
                    <option value="transfer">{{ __('Bank Transfer') }}</option>
                    <option value="qris">{{ __('QRIS') }}</option>
                    <option value="none">{{ __('Unpaid') }}</option>
                </flux:select>

                <flux:button
                    size="sm"
                    variant="ghost"
                    class="btn-ghost-accent whitespace-nowrap shrink-0"
                    wire:click="$set('search','');$set('statusFilter','all');$set('paymentFilter','all')"
                >
                    {{ __('Clear') }}
                </flux:button>
            </div>
        </div>

        <!-- Mobile cards -->
        <div class="block sm:hidden">
            <div class="mt-2 grid gap-3">
                @forelse ($items as $item)
                    @php($status = $item->status)
                    @php($meta = $statusMeta[$status] ?? null)
                    <div class="rounded-2xl border border-neutral-200/80 bg-white p-4 shadow-sm dark:border-neutral-800/70 dark:bg-neutral-900">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="font-mono text-sm font-semibold text-neutral-900 dark:text-white">
                                    {{ $item->kode_pesanan }}
                                </div>
                                <div class="mt-0.5 text-[11px] text-neutral-500 dark:text-neutral-400">
                                    #{{ $item->id }}
                                </div>
                            </div>
                            <div>
                                <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-[11px] font-semibold {{ $meta['badge'] ?? 'bg-neutral-100 text-neutral-700 ring-1 ring-neutral-200 dark:bg-neutral-800 dark:text-neutral-200 dark:ring-neutral-700' }}">
                                    <span class="h-2 w-2 rounded-full {{ $meta['dot'] ?? 'bg-neutral-400' }}"></span>
                                    {{ $meta['label'] ?? ucfirst($status) }}
                                </span>
                            </div>
                        </div>

                        <div class="mt-3 grid gap-2 text-xs text-neutral-600 dark:text-neutral-300">
                            <div class="flex justify-between gap-2">
                                <span>{{ __('Table') }}</span>
                                <span class="font-medium text-neutral-900 dark:text-white">
                                    {{ optional($item->meja)->nomor_meja ?? '-' }}
                                </span>
                            </div>
                            <div class="flex justify-between gap-2">
                                <span>{{ __('Customer') }}</span>
                                <span class="text-right">
                                    <span class="font-medium text-neutral-900 dark:text-white">
                                        {{ $item->customer_name ?: __('Guest') }}
                                    </span>
                                    @if ($item->customer_note)
                                        <span class="block text-[11px] text-neutral-500 dark:text-neutral-400 line-clamp-2">
                                            {{ $item->customer_note }}
                                        </span>
                                    @endif
                                </span>
                            </div>
                            <div class="flex justify-between gap-2">
                                <span>{{ __('Total') }}</span>
                                <span class="font-semibold text-neutral-900 dark:text-white">
                                    Rp {{ number_format((float) $item->total_harga, 0, ',', '.') }}
                                </span>
                            </div>
                            <div class="flex justify-between gap-2">
                                <span>{{ __('Payment') }}</span>
                                <span class="text-right">
                                    @if ($item->metode_pembayaran === 'tunai')
                                        {{ __('Cash') }}
                                    @elseif ($item->metode_pembayaran === 'transfer')
                                        {{ __('Bank Transfer') }}
                                    @elseif ($item->metode_pembayaran === 'qris')
                                        {{ __('QRIS') }}
                                    @else
                                        <span class="text-neutral-500">{{ __('Unpaid') }}</span>
                                    @endif
                                    @if ($item->kasir)
                                        <span class="block text-[11px] text-neutral-500 dark:text-neutral-400">
                                            {{ __('Cashier') }}: {{ $item->kasir->name }}
                                        </span>
                                    @endif
                                </span>
                            </div>
                            <div class="flex justify-between gap-2">
                                <span>{{ __('Order Time') }}</span>
                                <span class="text-right">
                                    {{ optional($item->waktu_pesan)->format('d M Y H:i') ?? '-' }}
                                </span>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            @php($canPrintReceipt = $item->status === 'selesai' && !blank($item->metode_pembayaran))

                            @can('pembayaran.access')
                                @if ($canPrintReceipt)
                                    <flux:link class="flex-1" :href="route('pembayaran.receipt', $item)" target="_blank" rel="noopener">
                                        <flux:button
                                            size="sm"
                                            icon="printer"
                                            variant="ghost"
                                            class="w-full btn-ghost-accent"
                                        >
                                            {{ __('Receipt') }}
                                        </flux:button>
                                    </flux:link>
                                @else
                                    <flux:button
                                        size="sm"
                                        icon="printer"
                                        variant="ghost"
                                        class="flex-1 w-full btn-disabled-muted"
                                        disabled
                                        title="{{ __('Receipt is available after the order is completed and paid.') }}"
                                    >
                                        {{ __('Receipt') }}
                                    </flux:button>
                                @endif
                            @endcan

                            @can('pesanan.manage')
                                <flux:button
                                    size="sm"
                                    icon="pencil-square"
                                    variant="primary"
                                    class="flex-1 btn-accent"
                                    wire:click="openEditModal({{ $item->id }})"
                                >
                                    {{ __('Edit') }}
                                </flux:button>
                                <flux:modal.trigger name="confirm-delete-pesanan" class="flex-1">
                                    <flux:button
                                        size="sm"
                                        variant="danger"
                                        class="w-full"
                                        wire:click="confirmDelete({{ $item->id }})"
                                    >
                                        {{ __('Delete') }}
                                    </flux:button>
                                </flux:modal.trigger>
                            @endcan
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-neutral-200/80 bg-white p-6 text-center text-sm text-neutral-500 dark:border-neutral-800/70 dark:bg-neutral-900 dark:text-neutral-400">
                        {{ __('No orders found.') }}
                    </div>
                @endforelse
            </div>
            <div class="mt-4">
                {{ $items->links() }}
            </div>
        </div>

        <!-- Desktop table -->
        <div class="hidden sm:block rounded-3xl border border-neutral-200/80 bg-gradient-to-b from-white/95 via-white/90 to-white/70 shadow-2xl shadow-neutral-200/60 backdrop-blur-xl dark:border-neutral-800/80 dark:from-neutral-950/80 dark:via-neutral-950/60 dark:to-neutral-950/40 dark:shadow-black/30">
            <div class="overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm text-left">
                        <thead>
                            <tr>
                                <th class="px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">
                                    {{ __('Order Code') }}
                                </th>
                                <th class="px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">
                                    {{ __('Table') }}
                                </th>
                                <th class="px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">
                                    {{ __('Customer') }}
                                </th>
                                <th class="px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">
                                    {{ __('Status') }}
                                </th>
                                <th class="px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">
                                    {{ __('Total') }}
                                </th>
                                <th class="hidden md:table-cell px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">
                                    {{ __('Payment') }}
                                </th>
                                <th class="hidden lg:table-cell px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">
                                    {{ __('Order Time') }}
                                </th>
                                <th class="px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">
                                    {{ __('Actions') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100/80 text-neutral-700 dark:divide-neutral-900/40 dark:text-neutral-200">
                            @forelse ($items as $item)
                                @php($status = $item->status)
                                @php($meta = $statusMeta[$status] ?? null)
                                <tr class="group transition hover:bg-white/70 focus-within:bg-white/90 dark:hover:bg-neutral-900/40 dark:focus-within:bg-neutral-900/50">
                                    <td class="px-6 py-4 align-middle">
                                        <div class="flex flex-col">
                                            <span class="font-mono text-sm font-semibold text-neutral-900 dark:text-white">
                                                {{ $item->kode_pesanan }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 align-middle">
                                        <span class="text-sm text-neutral-900 dark:text-white">
                                            {{ optional($item->meja)->nomor_meja ?? '-' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 align-middle">
                                        <div class="flex min-w-0 flex-col">
                                            <span class="truncate text-sm text-neutral-900 dark:text-white">
                                                {{ $item->customer_name ?: __('Guest') }}
                                            </span>
                                            @if ($item->customer_note)
                                                <span class="mt-0.5 line-clamp-1 text-[11px] text-neutral-500 dark:text-neutral-400">
                                                    {{ $item->customer_note }}
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 align-middle">
                                        <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-[11px] font-semibold {{ $meta['badge'] ?? 'bg-neutral-100 text-neutral-700 ring-1 ring-neutral-200 dark:bg-neutral-800 dark:text-neutral-200 dark:ring-neutral-700' }}">
                                            <span class="h-2 w-2 rounded-full {{ $meta['dot'] ?? 'bg-neutral-400' }}"></span>
                                            {{ $meta['label'] ?? ucfirst($status) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 align-middle text-neutral-800 dark:text-neutral-100">
                                        <span class="font-semibold">
                                            Rp {{ number_format((float) $item->total_harga, 0, ',', '.') }}
                                        </span>
                                    </td>
                                    <td class="hidden md:table-cell px-6 py-4 align-middle">
                                        <div class="flex flex-col text-sm text-neutral-900 dark:text-white">
                                            @if ($item->metode_pembayaran === 'tunai')
                                                <span>{{ __('Cash') }}</span>
                                            @elseif ($item->metode_pembayaran === 'transfer')
                                                <span>{{ __('Bank Transfer') }}</span>
                                            @elseif ($item->metode_pembayaran === 'qris')
                                                <span>{{ __('QRIS') }}</span>
                                            @else
                                                <span class="text-neutral-500 dark:text-neutral-400">{{ __('Unpaid') }}</span>
                                            @endif
                                            @if ($item->kasir)
                                                <span class="text-[11px] text-neutral-500 dark:text-neutral-400">
                                                    {{ __('Cashier') }}: {{ $item->kasir->name }}
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="hidden lg:table-cell px-6 py-4 align-middle">
                                        <span class="text-xs text-neutral-800 dark:text-neutral-200">
                                            {{ optional($item->waktu_pesan)->format('d M Y H:i') ?? '-' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 align-middle">
                                        <div class="flex flex-wrap items-center gap-2">
                                            @php($canPrintReceipt = $item->status === 'selesai' && !blank($item->metode_pembayaran))

                                            @can('pembayaran.access')
                                                @if ($canPrintReceipt)
                                                    <flux:link :href="route('pembayaran.receipt', $item)" target="_blank" rel="noopener">
                                                        <flux:button
                                                            size="sm"
                                                            icon="printer"
                                                            variant="ghost"
                                                            class="btn-ghost-accent rounded-2xl shadow-sm transition"
                                                        >
                                                            {{ __('Receipt') }}
                                                        </flux:button>
                                                    </flux:link>
                                                @else
                                                    <flux:button
                                                        size="sm"
                                                        icon="printer"
                                                        variant="ghost"
                                                        class="btn-disabled-muted rounded-2xl shadow-sm transition"
                                                        disabled
                                                        title="{{ __('Receipt is available after the order is completed and paid.') }}"
                                                    >
                                                        {{ __('Receipt') }}
                                                    </flux:button>
                                                @endif
                                            @endcan

                                            @can('pesanan.manage')
                                                <flux:button
                                                    size="sm"
                                                    icon="pencil-square"
                                                    variant="primary"
                                                    class="btn-accent rounded-2xl shadow-sm transition"
                                                    wire:click="openEditModal({{ $item->id }})"
                                                >
                                                    {{ __('Edit') }}
                                                </flux:button>
                                                <flux:modal.trigger name="confirm-delete-pesanan-desktop">
                                                    <flux:button
                                                        size="sm"
                                                        variant="danger"
                                                        class="rounded-2xl shadow-sm transition"
                                                        wire:click="confirmDelete({{ $item->id }})"
                                                    >
                                                        {{ __('Delete') }}
                                                    </flux:button>
                                                </flux:modal.trigger>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="px-6 py-8 text-center text-sm text-neutral-500 dark:text-neutral-400" colspan="8">
                                        <div class="flex flex-col items-center gap-3">
                                            <div class="h-12 w-12 rounded-full bg-neutral-100 text-neutral-400 dark:bg-neutral-900/60 dark:text-neutral-500">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-full w-full p-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 4.5h9m-9 6h9m-9 6h9" />
                                                </svg>
                                            </div>
                                            <p>{{ __('No orders found.') }}</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="px-4 py-3">
                {{ $items->links() }}
            </div>
        </div>

        <!-- Create order modal -->
        <flux:modal name="create-pesanan" focusable class="mx-4 w-[calc(100%-2rem)] sm:mx-auto sm:max-w-4xl md:max-w-3xl lg:max-w-4xl">
            <div class="flex flex-col max-h-[85dvh] overflow-y-auto no-scrollbar md:max-h-none md:overflow-visible">
                <div class="sticky top-0 z-0 -mx-4 flex items-start justify-between gap-2 border-b border-neutral-200 bg-white/85 px-4 py-3 pr-12 backdrop-blur dark:border-neutral-700 dark:bg-neutral-900/70">
                    <div>
                        <flux:heading size="lg">{{ __('Create Order') }}</flux:heading>
                        <flux:subheading>{{ __('Fill the details below to add a new order.') }}</flux:subheading>
                    </div>
                </div>

                <form id="create-pesanan-form" wire:submit.prevent="save" class="flex-1 space-y-6 px-1 py-4 pb-[calc(env(safe-area-inset-bottom)+0.75rem)] md:pb-0">
                    <div class="grid gap-6 md:grid-cols-2">
                        <div class="space-y-4">
                            <flux:select wire:model.defer="form.meja_id" :label="__('Table')" required>
                                <option value="">{{ __('Select') }}</option>
                                @foreach ($mejas as $meja)
                                    <option value="{{ $meja->id }}">{{ $meja->nomor_meja }}</option>
                                @endforeach
                            </flux:select>

                            <flux:input
                                wire:model.defer="form.kode_pesanan"
                                :label="__('Order Code')"
                                maxlength="20"
                                placeholder="{{ __('Optional') }}"
                            />

                            <flux:input
                                wire:model.defer="form.customer_name"
                                :label="__('Customer Name')"
                                maxlength="100"
                                placeholder="{{ __('Guest') }}"
                            />

                            <flux:textarea
                                wire:model.defer="form.customer_note"
                                rows="3"
                                :label="__('Customer Note')"
                                placeholder="{{ __('Optional') }}"
                            ></flux:textarea>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <flux:select wire:model.defer="form.diskon_id" :label="__('Discount')">
                                    <option value="">{{ __('None') }}</option>
                                    @foreach ($diskons as $diskon)
                                        <option value="{{ $diskon->id }}">{{ $diskon->kode }}</option>
                                    @endforeach
                                </flux:select>

                                <flux:select wire:model.defer="form.pajak_id" :label="__('Tax')">
                                    <option value="">{{ __('None') }}</option>
                                    @foreach ($pajaks as $pajak)
                                        <option value="{{ $pajak->id }}">{{ $pajak->nama }} ({{ $pajak->persentase }}%)</option>
                                    @endforeach
                                </flux:select>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <flux:input
                                    wire:model.defer="form.subtotal"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    :label="__('Subtotal')"
                                    readonly
                                />
                                <flux:input
                                    wire:model.defer="form.discount_total"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    :label="__('Discount Total')"
                                />
                                <flux:input
                                    wire:model.defer="form.tax_total"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    :label="__('Tax Total')"
                                />
                                <flux:input
                                    wire:model.defer="form.total_harga"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    :label="__('Grand Total')"
                                    readonly
                                />
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <flux:select wire:model.defer="form.chef_id" :label="__('Chef')">
                                    <option value="">{{ __('Select') }}</option>
                                    @foreach ($users as $user)
                                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                                    @endforeach
                                </flux:select>
                            </div>

                            <div class="rounded-2xl border border-neutral-200/70 bg-white p-3 text-xs text-neutral-500 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-400">
                                <flux:heading size="sm">{{ __('Tips') }}</flux:heading>
                                <ul class="mt-2 list-disc space-y-1 pl-4">
                                    <li>{{ __('Leave the order code empty to auto-generate it.') }}</li>
                                    <li>{{ __('Add at least one item. Totals will be calculated automatically.') }}</li>
                                    <li>{{ __('Payments are handled from the Payments menu.') }}</li>
                                </ul>
                            </div>

                            <div class="hidden md:flex items-center justify-end gap-3 pt-2">
                                <flux:modal.close>
                                    <flux:button type="button" variant="ghost" class="btn-ghost-accent">{{ __('Cancel') }}</flux:button>
                                </flux:modal.close>
                                <flux:button type="submit" form="create-pesanan-form" variant="primary" icon="plus" class="btn-brand">{{ __('Create') }}</flux:button>
                            </div>
                        </div>
                        <div class="md:col-span-2">
                            <div class="mt-2 rounded-2xl border border-neutral-200/70 bg-white p-3 text-xs text-neutral-600 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300 space-y-2">
                                <div class="flex items-center justify-between gap-2">
                                    <flux:heading size="sm">{{ __('Order Items') }}</flux:heading>
                                    <flux:button type="button" size="xs" icon="plus" variant="ghost" class="btn-ghost-accent" wire:click="addItem">
                                        {{ __('Add Item') }}
                                    </flux:button>
                                </div>

                                @if (empty($orderItems))
                                    <p class="text-xs text-neutral-500 dark:text-neutral-400">
                                        {{ __('No items for this order yet.') }}
                                    </p>
                                @else
                                    <div class="sm:hidden space-y-3">
                                        @foreach ($orderItems as $index => $item)
                                            <div wire:key="create-order-item-mobile-{{ $index }}" class="rounded-xl border border-neutral-200/70 bg-white p-3 shadow-sm dark:border-neutral-800/70 dark:bg-neutral-900/60">
                                                <div class="space-y-3">
                                                    <flux:select wire:model.live="orderItems.{{ $index }}.menu_id" size="sm" class="w-full">
                                                        <option value="">{{ __('Select') }}</option>
                                                        @foreach ($menus as $menu)
                                                            <option value="{{ $menu->id }}">{{ $menu->nama_menu }}</option>
                                                        @endforeach
                                                    </flux:select>

                                                    @php($selectedMenu = $menusById[(int) ($item['menu_id'] ?? 0)] ?? null)
                                                    @if ($selectedMenu && $selectedMenu->addons->isNotEmpty())
                                                        <flux:checkbox.group
                                                            wire:model.live="orderItems.{{ $index }}.addon_ids"
                                                            variant="pills"
                                                            :label="__('Add-ons (optional)')"
                                                        >
                                                            @foreach ($selectedMenu->addons as $addon)
                                                                <flux:checkbox
                                                                    variant="pills"
                                                                    value="{{ $addon->id }}"
                                                                    :label="$addon->nama_addon . ' (+Rp ' . number_format((float) $addon->harga, 0, ',', '.') . ')'"
                                                                />
                                                            @endforeach
                                                        </flux:checkbox.group>
                                                    @endif

                                                    <div class="grid grid-cols-2 gap-2">
                                                        <flux:input wire:model.live.debounce.250ms="orderItems.{{ $index }}.qty" type="number" min="1" size="sm" :label="__('Qty')" class="w-full" />
                                                        <flux:input wire:model="orderItems.{{ $index }}.harga" type="number" step="0.01" min="0" size="sm" :label="__('Price')" class="w-full" readonly />
                                                    </div>

                                                    <div class="flex items-center justify-between rounded-lg bg-neutral-50 px-3 py-2 text-xs dark:bg-neutral-900/40">
                                                        <span class="text-neutral-500 dark:text-neutral-400">{{ __('Subtotal') }}</span>
                                                        <span class="font-semibold text-neutral-900 dark:text-white">Rp {{ number_format((float) ($item['subtotal'] ?? 0), 0, ',', '.') }}</span>
                                                    </div>

                                                    <flux:input wire:model.defer="orderItems.{{ $index }}.catatan" size="sm" :label="__('Note')" class="w-full" placeholder="{{ __('Optional') }}" />

                                                    <div class="flex justify-end">
                                                        <flux:button type="button" size="xs" variant="ghost" icon="trash" class="btn-ghost-danger" wire:click="removeItem({{ $index }})">
                                                            {{ __('Remove') }}
                                                        </flux:button>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>

                                    <div class="hidden sm:block overflow-hidden rounded-xl bg-neutral-50/60 dark:bg-neutral-900/40">
                                        <div class="max-h-60 overflow-y-auto overflow-x-auto">
                                            <table class="min-w-full text-[11px] table-fixed">
                                                <thead class="bg-neutral-100/70 dark:bg-neutral-900/70">
                                                    <tr>
                                                        <th class="w-4/12 px-2 py-1 text-left font-semibold uppercase tracking-[0.16em] text-neutral-500 dark:text-neutral-400">
                                                            {{ __('Menu') }}
                                                        </th>
                                                        <th class="w-1/12 px-2 py-1 text-center font-semibold uppercase tracking-[0.16em] text-neutral-500 dark:text-neutral-400">
                                                            {{ __('Qty') }}
                                                        </th>
                                                        <th class="w-2/12 px-2 py-1 text-right font-semibold uppercase tracking-[0.16em] text-neutral-500 dark:text-neutral-400">
                                                            {{ __('Price') }}
                                                        </th>
                                                        <th class="w-2/12 px-2 py-1 text-right font-semibold uppercase tracking-[0.16em] text-neutral-500 dark:text-neutral-400">
                                                            {{ __('Subtotal') }}
                                                        </th>
                                                        <th class="w-2/12 px-2 py-1 text-left font-semibold uppercase tracking-[0.16em] text-neutral-500 dark:text-neutral-400">
                                                            {{ __('Note') }}
                                                        </th>
                                                        <th class="w-1/12 px-2 py-1"></th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-neutral-200/60 dark:divide-neutral-800/60">
                                                    @foreach ($orderItems as $index => $item)
                                                        <tr class="align-top" wire:key="create-order-item-{{ $index }}">
	                                                            <td class="px-2 py-2">
	                                                                <flux:select
	                                                                    wire:model.live="orderItems.{{ $index }}.menu_id"
	                                                                    size="sm"
	                                                                    class="w-full"
	                                                                >
                                                                    <option value="">{{ __('Select') }}</option>
                                                                    @foreach ($menus as $menu)
                                                                        <option value="{{ $menu->id }}">{{ $menu->nama_menu }}</option>
                                                                    @endforeach
                                                                </flux:select>

                                                                @php($selectedMenu = $menusById[(int) ($item['menu_id'] ?? 0)] ?? null)
                                                                @if ($selectedMenu && $selectedMenu->addons->isNotEmpty())
                                                                    <flux:checkbox.group
                                                                        wire:model.live="orderItems.{{ $index }}.addon_ids"
                                                                        variant="pills"
                                                                        class="mt-1"
                                                                    >
                                                                        @foreach ($selectedMenu->addons as $addon)
                                                                            <flux:checkbox
                                                                                variant="pills"
                                                                                value="{{ $addon->id }}"
                                                                                :label="$addon->nama_addon . ' (+Rp ' . number_format((float) $addon->harga, 0, ',', '.') . ')'"
                                                                            />
                                                                        @endforeach
                                                                    </flux:checkbox.group>
                                                                @endif
                                                            </td>
	                                                            <td class="px-2 py-2">
	                                                                <flux:input
	                                                                    wire:model.live.debounce.250ms="orderItems.{{ $index }}.qty"
	                                                                    type="number"
	                                                                    min="1"
	                                                                    size="sm"
	                                                                    class="w-full text-center"
	                                                                />
                                                            </td>
                                                            <td class="px-2 py-2">
                                                                <flux:input
                                                                    wire:model="orderItems.{{ $index }}.harga"
                                                                    type="number"
                                                                    step="0.01"
                                                                    min="0"
                                                                    size="sm"
                                                                    class="w-full text-right"
                                                                    readonly
                                                                />
                                                            </td>
                                                            <td class="px-2 py-2">
                                                                <flux:input
                                                                    wire:model="orderItems.{{ $index }}.subtotal"
                                                                    type="number"
                                                                    step="0.01"
                                                                    min="0"
                                                                    size="sm"
                                                                    class="w-full text-right"
                                                                    readonly
                                                                />
                                                            </td>
                                                            <td class="px-2 py-2">
                                                                <flux:input
                                                                    wire:model.defer="orderItems.{{ $index }}.catatan"
                                                                    size="sm"
                                                                    class="w-full"
                                                                    placeholder="{{ __('Optional') }}"
                                                                />
                                                            </td>
                                                            <td class="px-2 py-2 text-right">
                                                                <flux:button
                                                                    type="button"
                                                                    size="xs"
                                                                    variant="ghost"
                                                                    icon="trash"
                                                                    class="btn-ghost-danger"
                                                                    wire:click="removeItem({{ $index }})"
                                                                >
                                                                    {{ __('Remove') }}
                                                                </flux:button>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </form>

                <div class="sticky bottom-0 z-10 -mx-4 md:hidden border-t border-neutral-200 bg-white/90 px-4 py-3 pb-[max(env(safe-area-inset-bottom),0.75rem)] backdrop-blur dark:border-neutral-700 dark:bg-neutral-900/80">
                    <div class="grid grid-cols-2 gap-2">
                        <flux:modal.close>
                            <flux:button type="button" variant="ghost" class="btn-ghost-accent w-full">{{ __('Cancel') }}</flux:button>
                        </flux:modal.close>
                        <flux:button type="submit" form="create-pesanan-form" variant="primary" icon="plus" class="btn-brand w-full">{{ __('Create') }}</flux:button>
                    </div>
                </div>
            </div>
        </flux:modal>

        <!-- Edit order modal -->
        <flux:modal name="edit-pesanan" focusable class="mx-4 w-[calc(100%-2rem)] sm:mx-auto sm:max-w-4xl md:max-w-3xl lg:max-w-4xl">
 	            <div class="flex flex-col max-h-[85dvh] overflow-y-auto no-scrollbar md:max-h-none md:overflow-visible">
	                <div class="sticky top-0 z-0 -mx-4 flex items-start justify-between gap-2 border-b border-neutral-200 bg-white/85 px-4 py-3 pr-12 backdrop-blur dark:border-neutral-700 dark:bg-neutral-900/70">
 	                    <div>
 	                        <flux:heading size="lg">{{ __('Edit Order') }}</flux:heading>
 	                        <flux:subheading>{{ __('Update the details for this order.') }}</flux:subheading>
 	                    </div>
                </div>

                <form id="edit-pesanan-form" wire:submit.prevent="update" class="flex-1 space-y-6 px-1 py-4 pb-[calc(env(safe-area-inset-bottom)+0.75rem)] md:pb-0">
                    <div class="grid gap-6 md:grid-cols-2">
                        <div class="space-y-4">
                            <flux:select wire:model.defer="form.meja_id" :label="__('Table')" required>
                                <option value="">{{ __('Select') }}</option>
                                @foreach ($mejas as $meja)
                                    <option value="{{ $meja->id }}">{{ $meja->nomor_meja }}</option>
                                @endforeach
                            </flux:select>

                            <flux:input
                                wire:model.defer="form.kode_pesanan"
                                :label="__('Order Code')"
                                maxlength="20"
                                placeholder="{{ __('Optional') }}"
                            />

                            <flux:input
                                wire:model.defer="form.customer_name"
                                :label="__('Customer Name')"
                                maxlength="100"
                                placeholder="{{ __('Guest') }}"
                            />

                            <flux:textarea
                                wire:model.defer="form.customer_note"
                                rows="3"
                                :label="__('Customer Note')"
                                placeholder="{{ __('Optional') }}"
                            ></flux:textarea>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <flux:select wire:model.defer="form.status" :label="__('Status')" required>
                                    <option value="menunggu">{{ __('Waiting') }}</option>
                                    <option value="diproses">{{ __('In progress') }}</option>
                                    <option value="siap">{{ __('Ready') }}</option>
                                    <option value="selesai">{{ __('Completed') }}</option>
                                    <option value="batal">{{ __('Cancelled') }}</option>
                                </flux:select>

                                <flux:select wire:model.defer="form.metode_pembayaran" :label="__('Payment Method')">
                                    <option value="">{{ __('Select') }}</option>
                                    <option value="tunai">{{ __('Cash') }}</option>
                                    <option value="transfer">{{ __('Bank Transfer') }}</option>
                                    <option value="qris">{{ __('QRIS') }}</option>
                                </flux:select>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <flux:input
                                    wire:model.defer="form.subtotal"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    :label="__('Subtotal')"
                                    readonly
                                />
                                <flux:input
                                    wire:model.defer="form.discount_total"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    :label="__('Discount Total')"
                                />
                                <flux:input
                                    wire:model.defer="form.tax_total"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    :label="__('Tax Total')"
                                />
                                <flux:input
                                    wire:model.defer="form.total_harga"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    :label="__('Grand Total')"
                                    readonly
                                />
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <flux:input
                                    wire:model.defer="form.dibayar"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    :label="__('Paid Amount')"
                                />
                                <flux:input
                                    wire:model.defer="form.kembalian"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    :label="__('Change')"
                                    readonly
                                />
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <flux:select wire:model.defer="form.diskon_id" :label="__('Discount')">
                                    <option value="">{{ __('None') }}</option>
                                    @foreach ($diskons as $diskon)
                                        <option value="{{ $diskon->id }}">{{ $diskon->kode }}</option>
                                    @endforeach
                                </flux:select>

                                <flux:select wire:model.defer="form.pajak_id" :label="__('Tax')">
                                    <option value="">{{ __('None') }}</option>
                                    @foreach ($pajaks as $pajak)
                                        <option value="{{ $pajak->id }}">{{ $pajak->nama }} ({{ $pajak->persentase }}%)</option>
                                    @endforeach
                                </flux:select>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <flux:select wire:model.defer="form.kasir_id" :label="__('Cashier')">
                                    <option value="">{{ __('Select') }}</option>
                                    @foreach ($users as $user)
                                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                                    @endforeach
                                </flux:select>

                                <flux:select wire:model.defer="form.chef_id" :label="__('Chef')">
                                    <option value="">{{ __('Select') }}</option>
                                    @foreach ($users as $user)
                                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                                    @endforeach
                                </flux:select>
                            </div>

                            <div class="rounded-2xl border border-neutral-200/70 bg-white p-3 text-xs text-neutral-500 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-400">
                                <flux:heading size="sm">{{ __('Order Info') }}</flux:heading>
                                <p class="mt-1">
                                    {{ __('Adjust status and payment carefully. Completed or cancelled orders will have their completion time recorded.') }}
                                </p>
                            </div>
                        </div>

                        <div class="md:col-span-2">
                            <div class="mt-2 rounded-2xl border border-neutral-200/70 bg-white p-3 text-xs text-neutral-600 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300 space-y-2">
                                <div class="flex items-center justify-between gap-2">
                                    <flux:heading size="sm">{{ __('Order Items') }}</flux:heading>
                                    <flux:button type="button" size="xs" icon="plus" variant="ghost" class="btn-ghost-accent" wire:click="addItem">
                                        {{ __('Add Item') }}
                                    </flux:button>
                                </div>

                                @if (empty($orderItems))
                                    <p class="text-xs text-neutral-500 dark:text-neutral-400">
                                        {{ __('No items for this order yet.') }}
                                    </p>
                                @else
                                    <div class="sm:hidden space-y-3">
                                        @foreach ($orderItems as $index => $item)
                                            <div wire:key="edit-order-item-mobile-{{ $index }}" class="rounded-xl border border-neutral-200/70 bg-white p-3 shadow-sm dark:border-neutral-800/70 dark:bg-neutral-900/60">
                                                <div class="space-y-3">
                                                    <flux:select wire:model.live="orderItems.{{ $index }}.menu_id" size="sm" class="w-full">
                                                        <option value="">{{ __('Select') }}</option>
                                                        @foreach ($menus as $menu)
                                                            <option value="{{ $menu->id }}">{{ $menu->nama_menu }}</option>
                                                        @endforeach
                                                    </flux:select>

                                                    @php($selectedMenu = $menusById[(int) ($item['menu_id'] ?? 0)] ?? null)
                                                    @if ($selectedMenu && $selectedMenu->addons->isNotEmpty())
                                                        <div data-flux-field>
                                                            <label data-flux-label>{{ __('Add-ons (optional)') }}</label>
                                                            <select
                                                                multiple
                                                                size="4"
                                                                wire:model.live="orderItems.{{ $index }}.addon_ids"
                                                                class="mt-1 w-full rounded-lg border border-zinc-200 border-b-zinc-300/80 bg-white px-3 py-2 text-xs text-zinc-700 shadow-xs focus:outline-none focus:ring-2 focus:ring-[color:var(--brand-accent)] dark:border-white/10 dark:bg-white/10 dark:text-zinc-300"
                                                            >
                                                                @foreach ($selectedMenu->addons as $addon)
                                                                    <option value="{{ $addon->id }}">{{ $addon->nama_addon }} (+Rp {{ number_format((float) $addon->harga, 0, ',', '.') }})</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                    @endif

                                                    <div class="grid grid-cols-2 gap-2">
                                                        <flux:input wire:model.live.debounce.250ms="orderItems.{{ $index }}.qty" type="number" min="1" size="sm" :label="__('Qty')" class="w-full" />
                                                        <flux:input wire:model="orderItems.{{ $index }}.harga" type="number" step="0.01" min="0" size="sm" :label="__('Price')" class="w-full" readonly />
                                                    </div>

                                                    <div class="flex items-center justify-between rounded-lg bg-neutral-50 px-3 py-2 text-xs dark:bg-neutral-900/40">
                                                        <span class="text-neutral-500 dark:text-neutral-400">{{ __('Subtotal') }}</span>
                                                        <span class="font-semibold text-neutral-900 dark:text-white">Rp {{ number_format((float) ($item['subtotal'] ?? 0), 0, ',', '.') }}</span>
                                                    </div>

                                                    <flux:input wire:model.defer="orderItems.{{ $index }}.catatan" size="sm" :label="__('Note')" class="w-full" placeholder="{{ __('Optional') }}" />

                                                    <div class="flex justify-end">
                                                        <flux:button type="button" size="xs" variant="ghost" icon="trash" class="btn-ghost-danger" wire:click="removeItem({{ $index }})">
                                                            {{ __('Remove') }}
                                                        </flux:button>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>

                                    <div class="hidden sm:block overflow-hidden rounded-xl bg-neutral-50/60 dark:bg-neutral-900/40">
                                        <div class="max-h-60 overflow-y-auto overflow-x-auto">
                                            <table class="min-w-full text-[11px] table-fixed">
                                                <thead class="bg-neutral-100/70 dark:bg-neutral-900/70">
                                                    <tr>
                                                        <th class="w-4/12 px-2 py-1 text-left font-semibold uppercase tracking-[0.16em] text-neutral-500 dark:text-neutral-400">
                                                            {{ __('Menu') }}
                                                        </th>
                                                        <th class="w-1/12 px-2 py-1 text-center font-semibold uppercase tracking-[0.16em] text-neutral-500 dark:text-neutral-400">
                                                            {{ __('Qty') }}
                                                        </th>
                                                        <th class="w-2/12 px-2 py-1 text-right font-semibold uppercase tracking-[0.16em] text-neutral-500 dark:text-neutral-400">
                                                            {{ __('Price') }}
                                                        </th>
                                                        <th class="w-2/12 px-2 py-1 text-right font-semibold uppercase tracking-[0.16em] text-neutral-500 dark:text-neutral-400">
                                                            {{ __('Subtotal') }}
                                                        </th>
                                                        <th class="w-2/12 px-2 py-1 text-left font-semibold uppercase tracking-[0.16em] text-neutral-500 dark:text-neutral-400">
                                                            {{ __('Note') }}
                                                        </th>
                                                        <th class="w-1/12 px-2 py-1"></th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-neutral-100/80 dark:divide-neutral-800/80">
                                                    @foreach ($orderItems as $index => $item)
                                                        <tr wire:key="edit-order-item-{{ $index }}">
                                                            <td class="px-2 py-1 align-middle">
                                                                <select
                                                                    wire:model="orderItems.{{ $index }}.menu_id"
                                                                    class="h-8 w-full rounded-md border border-neutral-300 bg-white px-2 text-[11px] shadow-sm focus:outline-none focus:ring-2 focus:ring-neutral-300 dark:border-neutral-700 dark:bg-neutral-900 dark:text-white"
                                                                >
                                                                    <option value="">{{ __('Select') }}</option>
                                                                    @foreach ($menus as $menu)
                                                                        <option value="{{ $menu->id }}">{{ $menu->nama_menu }}</option>
                                                                    @endforeach
                                                                </select>

                                                                @php($selectedMenu = $menusById[(int) ($item['menu_id'] ?? 0)] ?? null)
                                                                @if ($selectedMenu && $selectedMenu->addons->isNotEmpty())
                                                                    <flux:checkbox.group
                                                                        wire:model.live="orderItems.{{ $index }}.addon_ids"
                                                                        variant="pills"
                                                                        class="mt-2"
                                                                    >
                                                                        @foreach ($selectedMenu->addons as $addon)
                                                                            <flux:checkbox
                                                                                variant="pills"
                                                                                value="{{ $addon->id }}"
                                                                                :label="$addon->nama_addon . ' (+Rp ' . number_format((float) $addon->harga, 0, ',', '.') . ')'"
                                                                            />
                                                                        @endforeach
                                                                    </flux:checkbox.group>
                                                                @endif
                                                            </td>
                                                            <td class="px-2 py-1 align-middle">
                                                                <input
                                                                    type="number"
                                                                    min="1"
                                                                    wire:model="orderItems.{{ $index }}.qty"
                                                                    class="h-8 w-full rounded-md border border-neutral-300 bg-white px-2 text-right text-[11px] shadow-sm focus:outline-none focus:ring-2 focus:ring-neutral-300 dark:border-neutral-700 dark:bg-neutral-900 dark:text-white"
                                                                />
                                                            </td>
                                                            <td class="px-2 py-1 align-middle">
                                                                <input
                                                                    type="number"
                                                                    min="0"
                                                                    step="0.01"
                                                                    wire:model.defer="orderItems.{{ $index }}.harga"
                                                                    class="h-8 w-full rounded-md border border-neutral-300 bg-white px-2 text-right text-[11px] shadow-sm focus:outline-none focus:ring-2 focus:ring-neutral-300 dark:border-neutral-700 dark:bg-neutral-900 dark:text-white"
                                                                    readonly
                                                                />
                                                            </td>
                                                            <td class="px-2 py-1 align-middle text-right">
                                                                Rp {{ number_format((float) ($item['subtotal'] ?? 0), 0, ',', '.') }}
                                                            </td>
                                                            <td class="px-2 py-1 align-middle">
                                                                <input
                                                                    type="text"
                                                                    wire:model.defer="orderItems.{{ $index }}.catatan"
                                                                    class="h-8 w-full rounded-md border border-neutral-300 bg-white px-2 text-[11px] shadow-sm focus:outline-none focus:ring-2 focus:ring-neutral-300 dark:border-neutral-700 dark:bg-neutral-900 dark:text-white"
                                                                    placeholder="{{ __('Optional') }}"
                                                                />
                                                            </td>
                                                            <td class="px-2 py-1 align-middle text-right">
                                                                <flux:button
                                                                    type="button"
                                                                    size="xs"
                                                                    variant="ghost"
                                                                    icon="trash"
                                                                    class="btn-ghost-danger"
                                                                    wire:click="removeItem({{ $index }})"
                                                                >
                                                                    {{ __('Remove') }}
                                                                </flux:button>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </form>

                <div class="hidden md:block sticky bottom-0 z-10 -mx-4 border-t border-neutral-200 bg-white/90 px-4 py-3 backdrop-blur dark:border-neutral-700 dark:bg-neutral-900/80">
                    <div class="flex items-center justify-end gap-3">
                        <flux:modal.close>
                            <flux:button type="button" variant="ghost" class="btn-ghost-accent">{{ __('Cancel') }}</flux:button>
                        </flux:modal.close>
                        <flux:button type="submit" form="edit-pesanan-form" variant="primary" icon="check" class="btn-brand">{{ __('Update') }}</flux:button>
                    </div>
                </div>

                <div class="sticky bottom-0 z-10 -mx-4 md:hidden border-t border-neutral-200 bg-white/90 px-4 py-3 pb-[max(env(safe-area-inset-bottom),0.75rem)] backdrop-blur dark:border-neutral-700 dark:bg-neutral-900/80">
                    <div class="grid grid-cols-2 gap-2">
                        <flux:modal.close>
                            <flux:button type="button" variant="ghost" class="btn-ghost-accent w-full">{{ __('Cancel') }}</flux:button>
                        </flux:modal.close>
                        <flux:button type="submit" form="edit-pesanan-form" variant="primary" icon="check" class="btn-brand w-full">{{ __('Update') }}</flux:button>
                    </div>
                </div>
            </div>
        </flux:modal>

        <!-- Delete confirm modal - mobile flyout -->
        <flux:modal
            name="confirm-delete-pesanan"
            focusable
            variant="flyout"
            position="bottom"
            class="rounded-t-3xl sm:rounded-xl"
        >
            <div class="space-y-4 p-2 max-h-[60dvh] overflow-y-auto">
                <div class="flex items-center justify-center">
                    <div class="mx-auto mb-1 h-1.5 w-12 rounded-full bg-neutral-300 dark:bg-neutral-700"></div>
                </div>

                <div class="space-y-2">
                    <flux:heading size="lg">{{ __('Delete this order?') }}</flux:heading>
                    <flux:subheading>{{ __('This action cannot be undone.') }}</flux:subheading>
                </div>

                @if ($selectedPesanan)
                    <div class="rounded-2xl border border-red-200/60 bg-red-50/60 p-4 text-sm text-red-800 dark:border-red-800/60 dark:bg-red-900/20 dark:text-red-200">
                        <p class="font-semibold">
                            {{ $selectedPesanan->kode_pesanan }} <span class="text-xs text-red-700/80 dark:text-red-200/80">#{{ $selectedPesanan->id }}</span>
                        </p>
                        <p class="text-xs opacity-80">
                            {{ __('Total') }}: Rp {{ number_format((float) $selectedPesanan->total_harga, 0, ',', '.') }}
                        </p>
                    </div>
                @endif

                <div class="sticky bottom-0 -mx-2 mt-2 flex items-center justify-end gap-2 border-t border-neutral-200 bg-white/85 px-2 py-2 pb-[max(env(safe-area-inset-bottom),0.75rem)] backdrop-blur dark:border-neutral-700 dark:bg-neutral-900/70">
                    <flux:modal.close>
                        <flux:button variant="filled" wire:click="$set('confirmingDeleteId', null)">
                            {{ __('Cancel') }}
                        </flux:button>
                    </flux:modal.close>
                    <flux:button variant="danger" wire:click="delete">
                        {{ __('Yes, delete') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>

        <!-- Delete confirm modal - desktop centered -->
        <flux:modal
            name="confirm-delete-pesanan-desktop"
            focusable
            class="mx-4 max-w-full sm:mx-auto sm:max-w-lg"
        >
            <div class="space-y-4 p-2">
                <div class="space-y-2">
                    <flux:heading size="lg">{{ __('Delete this order?') }}</flux:heading>
                    <flux:subheading>{{ __('This action cannot be undone.') }}</flux:subheading>
                </div>

                @if ($selectedPesanan)
                    <div class="rounded-2xl border border-red-200/60 bg-red-50/60 p-4 text-sm text-red-800 dark:border-red-800/60 dark:bg-red-900/20 dark:text-red-200">
                        <p class="font-semibold">
                            {{ $selectedPesanan->kode_pesanan }} <span class="text-xs text-red-700/80 dark:text-red-200/80">#{{ $selectedPesanan->id }}</span>
                        </p>
                        <p class="text-xs opacity-80">
                            {{ __('Total') }}: Rp {{ number_format((float) $selectedPesanan->total_harga, 0, ',', '.') }}
                        </p>
                    </div>
                @endif

                <div class="mt-2 flex items-center justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="filled" wire:click="$set('confirmingDeleteId', null)">
                            {{ __('Cancel') }}
                        </flux:button>
                    </flux:modal.close>
                    <flux:button variant="danger" wire:click="delete">
                        {{ __('Yes, delete') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>

        <!-- Toast -->
        <div
            x-data="{
                show: false,
                message: '',
                timeout: null,
                handle(event) {
                    this.message = event.detail?.message || '{{ __('Order updated successfully.') }}';
                    this.show = true;
                    clearTimeout(this.timeout);
                    this.timeout = setTimeout(() => this.show = false, 3500);
                }
            }"
            x-on:pesanan-toast.window="handle($event)"
            class="pointer-events-none fixed inset-x-0 top-6 flex justify-center px-4"
        >
            <div
                x-show="show"
                x-transition:enter="transform ease-out duration-200"
                x-transition:enter-start="-translate-y-3 opacity-0"
                x-transition:enter-end="translate-y-0 opacity-100"
                x-transition:leave="transform ease-in duration-200"
                x-transition:leave-start="translate-y-0 opacity-100"
                x-transition:leave-end="-translate-y-3 opacity-0"
                class="pointer-events-auto rounded-2xl toast-brand px-4 py-3 text-sm"
            >
                <div class="flex items-center gap-2">
                    <flux:icon icon="check-circle" />
                    <span x-text="message"></span>
                </div>
            </div>
        </div>
    </div>
</section>
