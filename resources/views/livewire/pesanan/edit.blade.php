<?php

use App\Models\Diskon;
use App\Models\Meja;
use App\Models\Pajak;
use App\Models\Pesanan;
use App\Models\PesananDetail;
use App\Models\Menu;
use App\Models\Addon;
use App\Models\User;
use App\Services\TableWaitingListService;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Component;

new class extends Component {
    public Pesanan $pesanan;

    public array $form = [
        'meja_id' => null,
        'kode_pesanan' => '',
        'customer_name' => '',
        'customer_note' => '',
        'jumlah_orang' => 1,
        'subtotal' => '',
        'discount_total' => '',
        'tax_total' => '',
        'total_harga' => '',
        'status' => 'menunggu',
        'metode_pembayaran' => '',
        'dibayar' => '',
        'kembalian' => '',
        'kasir_id' => '',
        'chef_id' => '',
        'diskon_id' => '',
        'pajak_id' => '',
    ];

    public array $items = [];

    public function mount(Pesanan $pesanan): void
    {
        $this->pesanan = $pesanan->load(['details.menu', 'details.addons']);

        $this->form = [
            'meja_id' => $pesanan->meja_id,
            'kode_pesanan' => $pesanan->kode_pesanan,
            'customer_name' => $pesanan->customer_name,
            'customer_note' => $pesanan->customer_note,
            'jumlah_orang' => $pesanan->jumlah_orang,
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

        $this->items = $pesanan->details->map(function (PesananDetail $detail) {
            return [
                'id' => $detail->id,
                'menu_id' => $detail->menu_id,
                'qty' => $detail->qty,
                'harga' => (float) $detail->harga,
                'subtotal' => (float) $detail->subtotal,
                'addon_ids' => $detail->addons->pluck('id')->all(),
            ];
        })->toArray();

        $this->recalculateTotals();
    }

    public function addItem(): void
    {
        $this->items[] = [
            'id' => null,
            'menu_id' => '',
            'qty' => 1,
            'harga' => 0,
            'subtotal' => 0,
            'addon_ids' => [],
        ];
    }

    public function removeItem(int $index): void
    {
        if (!isset($this->items[$index])) {
            return;
        }

        unset($this->items[$index]);
        $this->items = array_values($this->items);

        $this->recalculateTotals();
    }

    public function updatedItems($value, $name): void
    {
        if (preg_match('/^(?:items\\.)?(\\d+)\\.(menu_id|qty|addon_ids)$/', (string) $name, $matches)) {
            $index = (int) $matches[1];
            $field = $matches[2];

            if ($field === 'menu_id') {
                $menuId = (int) ($this->items[$index]['menu_id'] ?? 0);
                $this->items[$index]['addon_ids'] = [];
                if ($menuId > 0) {
                    $menu = Menu::find($menuId);
                    if ($menu) {
                        $this->items[$index]['harga'] = (float) $menu->harga;
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
        if (!isset($this->items[$index])) {
            return;
        }

        $qty = (int) ($this->items[$index]['qty'] ?? 0);
        $menuId = (int) ($this->items[$index]['menu_id'] ?? 0);

        $harga = 0.0;
        if ($menuId > 0) {
            $menu = Menu::query()->select(['id', 'harga'])->find($menuId);
            $base = (float) ($menu?->harga ?? 0);

            $addonIds = array_values(array_filter(array_map('intval', (array) ($this->items[$index]['addon_ids'] ?? []))));
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

        $this->items[$index]['qty'] = $qty;
        $this->items[$index]['harga'] = $harga;
        $this->items[$index]['subtotal'] = $qty * $harga;

        $this->recalculateTotals();
    }

    protected function recalculateTotals(): void
    {
        $subtotal = 0;

        foreach ($this->items as $item) {
            $subtotal += (float) ($item['subtotal'] ?? 0);
        }

        $discount = (float) ($this->form['discount_total'] ?? 0);
        $tax = (float) ($this->form['tax_total'] ?? 0);

        $this->form['subtotal'] = $subtotal;
        $this->form['total_harga'] = max($subtotal - $discount + $tax, 0);
    }

    public function update(): void
    {
        $data = array_merge($this->form, ['items' => $this->items]);
        foreach (['customer_name', 'customer_note', 'discount_total', 'tax_total', 'metode_pembayaran', 'dibayar', 'kasir_id', 'chef_id', 'diskon_id', 'pajak_id'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === '') {
                $data[$field] = null;
            }
        }

        $validated = validator($data, [
            'meja_id' => ['required', 'exists:mejas,id'],
            'kode_pesanan' => ['nullable', 'string', 'max:20', 'unique:pesanans,kode_pesanan,' . $this->pesanan->id],
            'customer_name' => ['nullable', 'string', 'max:100'],
            'customer_note' => ['nullable', 'string', 'max:255'],
            'jumlah_orang' => ['required', 'integer', 'min:1', 'max:99'],
            'discount_total' => ['nullable', 'numeric', 'min:0'],
            'tax_total' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'in:booking,menunggu,sedang_diubah,diproses,siap,selesai,batal'],
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
        ])->validate();

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
                session()->flash('pesanan_toast', __('Payment method is required to complete an order.'));
                return;
            }
        } else {
            if (!blank($method)) {
                session()->flash('pesanan_toast', __('Paid orders must be marked as completed.'));
                return;
            }
        }

        if ($status === 'selesai') {
            $total = (float) $totals['total_harga'];
            if (in_array($method, ['transfer', 'qris'], true)) {
                $validated['dibayar'] = $total;
                $validated['kembalian'] = 0;
            } else {
                $dibayar = (float) ($validated['dibayar'] ?? 0);
                if ($dibayar < $total) {
                    session()->flash('pesanan_toast', __('Paid amount is insufficient.'));
                    return;
                }
                $validated['dibayar'] = $dibayar;
                $validated['kembalian'] = max($dibayar - $total, 0);
            }

            if (!$this->pesanan->waktu_selesai) {
                $validated['waktu_selesai'] = now();
            }

            if (empty($validated['kasir_id']) && auth()->check()) {
                $validated['kasir_id'] = auth()->id();
            }
        } else {
            $validated['metode_pembayaran'] = null;
            $validated['dibayar'] = null;
            $validated['kembalian'] = null;
        }

        $previousMejaId = (int) $this->pesanan->meja_id;

        DB::transaction(function () use ($validated) {
            $items = $this->normalizeItemsForSave($validated['items']);
            $totals = $this->calculateTotals(
                $items,
                $validated['diskon_id'] ?? null,
                $validated['pajak_id'] ?? null,
                $validated['discount_total'] ?? null,
                $validated['tax_total'] ?? null,
            );

            $pesananData = collect($validated)->except('items', 'subtotal', 'total_harga')->toArray();
            $pesananData['subtotal'] = $totals['subtotal'];
            $pesananData['discount_total'] = $totals['discount_total'];
            $pesananData['tax_total'] = $totals['tax_total'];
            $pesananData['total_harga'] = $totals['total_harga'];

            $this->pesanan->update($pesananData);

            $allAddonIds = collect($items)->pluck('addon_ids')->flatten()->filter()->unique()->values()->all();
            $addons = Addon::query()
                ->select(['id', 'harga'])
                ->whereIn('id', $allAddonIds)
                ->get()
                ->keyBy('id');

            $existingDetails = $this->pesanan->details()->get()->keyBy('id');
            $keptIds = [];

            foreach ($items as $item) {
                $payload = [
                    'menu_id' => $item['menu_id'],
                    'qty' => $item['qty'],
                    'harga' => $item['harga'],
                    'subtotal' => $item['subtotal'],
                ];

                if (!empty($item['id']) && $existingDetails->has($item['id'])) {
                    $detail = $existingDetails[$item['id']];
                    $detail->update($payload);
                } else {
                    $detail = $this->pesanan->details()->create($payload);
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
                $this->pesanan->details()->whereNotIn('id', $keptIds)->delete();
            } else {
                $this->pesanan->details()->delete();
            }
        });

        $waitingListService = app(TableWaitingListService::class);
        $waitingListService->syncMejaStatus($previousMejaId);
        $waitingListService->syncMejaStatus((int) $validated['meja_id']);
        $waitingListService->forgetKitchenCache();

        session()->flash('pesanan_toast', __('Order updated successfully.'));
        $this->redirectRoute('pesanan.index', navigate: true);
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

<section class="w-full space-y-6">
    @php
        $mejas = Meja::query()
            ->select(['id', 'nomor_meja', 'status'])
            ->where(function ($q) use ($pesanan) {
                $q->where('status', '!=', 'nonaktif')
                    ->orWhere('id', $pesanan->meja_id);
            })
            ->orderBy('nomor_meja')
            ->get();
        $diskons = Diskon::query()->select(['id', 'kode'])->orderBy('kode')->get();
        $pajaks = Pajak::query()->select(['id', 'nama', 'persentase'])->orderBy('nama')->get();
        $users = User::query()->select(['id', 'name'])->orderBy('name')->get();
        $menus = Menu::query()
            ->with(['addons' => fn ($q) => $q->where('status', 'tersedia')->orderBy('nama_addon')])
            ->orderBy('nama_menu')
            ->get();
        $menusById = $menus->keyBy('id');
    @endphp

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="space-y-1">
            <flux:heading size="xl" level="1">{{ __('Edit Order') }}</flux:heading>
            <flux:subheading>{{ __('Update order details, items, payment, and kitchen status.') }}</flux:subheading>
        </div>
        <flux:link :href="route('pesanan.index', [], false)" wire:navigate>
            <flux:button variant="ghost" icon="arrow-left" class="btn-ghost-accent">{{ __('Back') }}</flux:button>
        </flux:link>
    </div>

    <div class="rounded-2xl border border-neutral-200/80 bg-white p-4 shadow-sm dark:border-neutral-800/70 dark:bg-neutral-900 sm:p-5">
        <form id="edit-pesanan-form" wire:submit.prevent="update" class="grid gap-6 md:grid-cols-2">
            <div class="space-y-4">
                <flux:select wire:model="form.meja_id" :label="__('Table')" required>
                    <option value="">{{ __('Select') }}</option>
                    @foreach ($mejas as $meja)
                        <option value="{{ $meja->id }}">{{ $meja->nomor_meja }}</option>
                    @endforeach
                </flux:select>

                <flux:input
                    wire:model="form.kode_pesanan"
                    :label="__('Order Code')"
                    maxlength="20"
                    placeholder="{{ __('Optional') }}"
                />

                <flux:input
                    wire:model="form.customer_name"
                    :label="__('Customer Name')"
                    maxlength="100"
                    placeholder="{{ __('Guest') }}"
                />

                <flux:input
                    wire:model="form.jumlah_orang"
                    type="number"
                    min="1"
                    max="99"
                    :label="__('Guests')"
                />

                <div data-flux-field>
                    <label data-flux-label>{{ __('Customer Note') }}</label>
                    <textarea
                        wire:model="form.customer_note"
                        rows="3"
                        class="w-full min-h-24 rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-neutral-300 dark:border-neutral-700 dark:bg-neutral-900 dark:text-white"
                        placeholder="{{ __('Optional') }}"
                    ></textarea>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:select wire:model="form.status" :label="__('Status')" required>
                        <option value="menunggu">{{ __('Waiting') }}</option>
                        <option value="sedang_diubah">{{ __('Editing') }}</option>
                        <option value="booking">{{ __('Waiting List') }}</option>
                        <option value="diproses">{{ __('In progress') }}</option>
                        <option value="siap">{{ __('Ready') }}</option>
                        <option value="selesai">{{ __('Completed') }}</option>
                        <option value="batal">{{ __('Cancelled') }}</option>
                    </flux:select>

                    <flux:select wire:model="form.metode_pembayaran" :label="__('Payment Method')">
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
                        wire:model="form.subtotal"
                        type="number"
                        step="0.01"
                        min="0"
                        :label="__('Subtotal')"
                        readonly
                    />
                    <flux:input
                        wire:model="form.discount_total"
                        type="number"
                        step="0.01"
                        min="0"
                        :label="__('Discount Total')"
                    />
                    <flux:input
                        wire:model="form.tax_total"
                        type="number"
                        step="0.01"
                        min="0"
                        :label="__('Tax Total')"
                    />
                    <flux:input
                        wire:model="form.total_harga"
                        type="number"
                        step="0.01"
                        min="0"
                        :label="__('Grand Total')"
                        readonly
                    />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input
                        wire:model="form.dibayar"
                        type="number"
                        step="0.01"
                        min="0"
                        :label="__('Paid Amount')"
                    />
                    <flux:input
                        wire:model="form.kembalian"
                        type="number"
                        step="0.01"
                        min="0"
                        :label="__('Change')"
                        readonly
                    />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:select wire:model="form.diskon_id" :label="__('Discount')">
                        <option value="">{{ __('None') }}</option>
                        @foreach ($diskons as $diskon)
                            <option value="{{ $diskon->id }}">{{ $diskon->kode }}</option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model="form.pajak_id" :label="__('Tax')">
                        <option value="">{{ __('None') }}</option>
                        @foreach ($pajaks as $pajak)
                            <option value="{{ $pajak->id }}">{{ $pajak->nama }} ({{ $pajak->persentase }}%)</option>
                        @endforeach
                    </flux:select>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:select wire:model="form.kasir_id" :label="__('Cashier')">
                        <option value="">{{ __('Select') }}</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model="form.chef_id" :label="__('Chef')">
                        <option value="">{{ __('Select') }}</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                        @endforeach
                    </flux:select>
                </div>

                <div class="text-xs text-neutral-500 dark:text-neutral-400">
                    @if ($pesanan->waktu_pesan)
                        <div>
                            <span class="font-medium">{{ __('Order Time') }}:</span>
                            <span>{{ $pesanan->waktu_pesan->format('d M Y H:i') }}</span>
                        </div>
                    @endif
                    @if ($pesanan->waktu_selesai)
                        <div>
                            <span class="font-medium">{{ __('Completion Time') }}:</span>
                            <span>{{ $pesanan->waktu_selesai->format('d M Y H:i') }}</span>
                        </div>
                    @endif
                </div>
            </div>

        </form>

        <div class="mt-4 space-y-3">
            <div class="flex items-center justify-between gap-3">
                <flux:heading size="md">{{ __('Order Items') }}</flux:heading>
                <flux:button type="button" size="sm" icon="plus" variant="ghost" class="btn-ghost-accent" wire:click="addItem">
                    {{ __('Add Item') }}
                </flux:button>
            </div>

            @if (empty($items))
                <p class="text-sm text-neutral-500 dark:text-neutral-400">
                    {{ __('No items for this order yet.') }}
                </p>
            @else
                <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-700">
                    <table class="min-w-full text-sm">
                        <thead class="bg-neutral-50/80 dark:bg-neutral-900/60">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-neutral-500 dark:text-neutral-400">
                                    {{ __('Menu') }}
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-[0.16em] text-neutral-500 dark:text-neutral-400">
                                    {{ __('Qty') }}
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-[0.16em] text-neutral-500 dark:text-neutral-400">
                                    {{ __('Price') }}
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-[0.16em] text-neutral-500 dark:text-neutral-400">
                                    {{ __('Subtotal') }}
                                </th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
	                        <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
	                            @foreach ($items as $index => $item)
	                                <tr wire:key="edit-page-item-{{ $index }}">
	                                    <td class="px-4 py-3 align-top">
	                                        <flux:select
	                                            wire:model.live="items.{{ $index }}.menu_id"
	                                            :label="null"
	                                            class="w-full"
	                                        >
	                                            <option value="">{{ __('Select') }}</option>
	                                            @foreach ($menus as $menu)
	                                                <option value="{{ $menu->id }}">{{ $menu->nama_menu }}</option>
	                                            @endforeach
	                                        </flux:select>

	                                        @php($selectedMenu = $menusById[(int) ($item['menu_id'] ?? 0)] ?? null)
	                                        @if ($selectedMenu && $selectedMenu->addons->isNotEmpty())
	                                            <div class="mt-2">
	                                                <flux:checkbox.group
	                                                    wire:model.live="items.{{ $index }}.addon_ids"
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
	                                            </div>
	                                        @endif
	                                    </td>
	                                    <td class="px-4 py-3 align-top">
	                                        <flux:input
	                                            wire:model.live.debounce.250ms="items.{{ $index }}.qty"
	                                            type="number"
	                                            min="1"
	                                            class="w-full text-right"
	                                        />
	                                    </td>
	                                    <td class="px-4 py-3 align-top">
	                                        <flux:input
	                                            wire:model.live.debounce.250ms="items.{{ $index }}.harga"
	                                            type="number"
	                                            step="0.01"
	                                            min="0"
	                                            class="w-full text-right"
                                        />
                                    </td>
                                    <td class="px-4 py-3 align-top text-right align-middle">
                                        Rp {{ number_format((float) ($item['subtotal'] ?? 0), 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-3 align-top text-right">
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
            @endif
        </div>
    </div>

    <div class="sticky bottom-0 z-20 -mx-4 border-t border-neutral-200 bg-white/90 px-4 pt-3 pb-[calc(env(safe-area-inset-bottom)+0.75rem)] shadow-[0_-12px_30px_rgba(15,23,42,0.08)] backdrop-blur dark:border-neutral-800 dark:bg-neutral-950/80 dark:shadow-black/20 sm:static sm:mx-0 sm:flex sm:justify-end sm:border-0 sm:bg-transparent sm:px-0 sm:pt-0 sm:pb-0 sm:shadow-none sm:backdrop-blur-none">
        <div class="grid grid-cols-2 gap-2 sm:flex sm:justify-end">
            <flux:link :href="route('pesanan.index', [], false)" wire:navigate>
                <flux:button type="button" variant="ghost" class="btn-ghost-accent w-full justify-center sm:w-auto">{{ __('Cancel') }}</flux:button>
            </flux:link>
            <flux:button type="submit" form="edit-pesanan-form" variant="primary" icon="check" class="btn-brand w-full justify-center sm:w-auto">{{ __('Update') }}</flux:button>
        </div>
    </div>
</section>
