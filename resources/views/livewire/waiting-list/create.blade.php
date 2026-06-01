<?php

use App\Models\Addon;
use App\Models\Meja;
use App\Models\Menu;
use App\Models\Pajak;
use App\Models\Pesanan;
use App\Services\OrderStatusService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Component;

new class extends Component {
    public array $form = [
        'meja_id' => '',
        'customer_name' => '',
        'customer_note' => '',
        'jumlah_orang' => 1,
        'subtotal' => 0,
        'tax_total' => 0,
        'total_harga' => 0,
    ];

    public array $orderItems = [];

    public function mount(): void
    {
        $this->addItem();
    }

    public function addItem(): void
    {
        $this->orderItems[] = [
            'menu_id' => '',
            'qty' => 1,
            'harga' => 0,
            'subtotal' => 0,
            'addon_ids' => [],
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
        if (!preg_match('/^(?:orderItems\\.)?(\\d+)\\.(menu_id|qty|addon_ids)$/', (string) $name, $matches)) {
            return;
        }

        $index = (int) $matches[1];
        $field = $matches[2];

        if ($field === 'menu_id') {
            $menuId = (int) ($this->orderItems[$index]['menu_id'] ?? 0);
            $this->orderItems[$index]['addon_ids'] = [];
            $this->orderItems[$index]['harga'] = 0;

            if ($menuId > 0) {
                $menu = Menu::query()->select(['id', 'harga'])->find($menuId);
                if ($menu) {
                    $this->orderItems[$index]['harga'] = (float) $menu->harga;
                }
            }
        }

        $this->recalculateItemSubtotal($index);
    }

    public function save(): void
    {
        $data = array_merge($this->form, ['items' => $this->orderItems]);
        foreach (['customer_name', 'customer_note'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === '') {
                $data[$field] = null;
            }
        }

        $validated = validator($data, [
            'meja_id' => ['required', 'exists:mejas,id'],
            'customer_name' => ['nullable', 'string', 'max:100'],
            'customer_note' => ['nullable', 'string', 'max:255'],
            'jumlah_orang' => ['required', 'integer', 'min:1', 'max:99'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.menu_id' => ['required', 'exists:menus,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.addon_ids' => ['nullable', 'array'],
            'items.*.addon_ids.*' => ['integer', 'exists:addons,id'],
        ])->validate();

        $items = $this->normalizeItemsForSave($validated['items']);
        if (empty($items)) {
            validator([], ['items' => ['required']])->validate();
        }

        $totals = $this->calculateTotals($items);
        $orderStatus = app(OrderStatusService::class);

        DB::transaction(function () use ($validated, $items, $totals, $orderStatus) {
            $pesanan = Pesanan::create([
                'meja_id' => $validated['meja_id'],
                'kode_pesanan' => 'WTL-' . now()->format('YmdHis'),
                'status_token' => $orderStatus->generateToken(),
                'customer_name' => blank($validated['customer_name'] ?? null) ? __('Guest') : $validated['customer_name'],
                'customer_note' => blank($validated['customer_note'] ?? null) ? null : $validated['customer_note'],
                'jumlah_orang' => $validated['jumlah_orang'],
                'subtotal' => $totals['subtotal'],
                'discount_total' => 0,
                'tax_total' => $totals['tax_total'],
                'total_harga' => $totals['total_harga'],
                'status' => 'booking',
                'metode_pembayaran' => null,
                'dibayar' => null,
                'kembalian' => null,
                'referensi_pembayaran' => null,
                'kasir_id' => null,
                'chef_id' => null,
                'diskon_id' => null,
                'pajak_id' => $totals['pajak_id'],
                'waktu_pesan' => now(),
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

        Cache::forget('waiting-list:list:stats:v1');

        session()->flash('waiting_list_toast', __('Waiting list created successfully.'));
        $this->redirectRoute('waiting-list.index', navigate: true);
    }

    protected function recalculateItemSubtotal(int $index): void
    {
        if (!isset($this->orderItems[$index])) {
            return;
        }

        $qty = max((int) ($this->orderItems[$index]['qty'] ?? 1), 1);
        $menuId = (int) ($this->orderItems[$index]['menu_id'] ?? 0);
        $harga = 0.0;

        if ($menuId > 0) {
            $menu = Menu::query()->select(['id', 'harga'])->find($menuId);
            $harga = (float) ($menu?->harga ?? 0);

            $addonIds = array_values(array_filter(array_map('intval', (array) ($this->orderItems[$index]['addon_ids'] ?? []))));
            if (!empty($addonIds)) {
                $harga += (float) Addon::query()
                    ->whereIn('id', $addonIds)
                    ->where('status', 'tersedia')
                    ->whereHas('menus', fn ($q) => $q->whereKey($menuId))
                    ->sum('harga');
            }
        }

        $this->orderItems[$index]['qty'] = $qty;
        $this->orderItems[$index]['harga'] = $harga;
        $this->orderItems[$index]['subtotal'] = $qty * $harga;
        $this->recalculateTotals();
    }

    protected function recalculateTotals(): void
    {
        $items = $this->normalizeItemsForSave($this->orderItems);
        $totals = $this->calculateTotals($items);

        $this->form['subtotal'] = $totals['subtotal'];
        $this->form['tax_total'] = $totals['tax_total'];
        $this->form['total_harga'] = $totals['total_harga'];
    }

    protected function normalizeItemsForSave(array $items): array
    {
        $menuIds = collect($items)->pluck('menu_id')->filter()->unique()->values()->all();
        $menus = Menu::query()
            ->select(['id', 'harga'])
            ->with([
                'addons' => fn ($q) => $q
                    ->select(['addons.id', 'harga', 'status'])
                    ->where('status', 'tersedia'),
            ])
            ->where('status', 'tersedia')
            ->whereIn('id', $menuIds)
            ->get()
            ->keyBy('id');

        $normalized = [];
        foreach ($items as $item) {
            $menuId = (int) ($item['menu_id'] ?? 0);
            $qty = max((int) ($item['qty'] ?? 0), 0);
            if ($menuId <= 0 || $qty <= 0 || !$menus->has($menuId)) {
                continue;
            }

            $menu = $menus[$menuId];
            $addonIds = array_values(array_filter(array_map('intval', (array) ($item['addon_ids'] ?? []))));
            $addonIds = array_values(array_intersect($addonIds, $menu->addons->pluck('id')->all()));
            $harga = (float) $menu->harga + (float) $menu->addons->whereIn('id', $addonIds)->sum('harga');

            $normalized[] = [
                'menu_id' => $menuId,
                'qty' => $qty,
                'harga' => $harga,
                'subtotal' => $qty * $harga,
                'addon_ids' => $addonIds,
            ];
        }

        return $normalized;
    }

    protected function calculateTotals(array $items): array
    {
        $subtotal = (float) collect($items)->sum('subtotal');
        $taxes = Pajak::query()->where('is_active', true)->get(['id', 'persentase']);
        $taxTotal = max($subtotal * (((float) $taxes->sum('persentase')) / 100), 0);

        return [
            'subtotal' => $subtotal,
            'tax_total' => $taxTotal,
            'total_harga' => max($subtotal + $taxTotal, 0),
            'pajak_id' => $taxes->count() === 1 ? (int) $taxes->first()->id : null,
        ];
    }
}; ?>

<section class="w-full space-y-6">
    @php
        $mejas = Meja::query()
            ->select(['id', 'nomor_meja', 'kapasitas'])
            ->where('status', '!=', 'nonaktif')
            ->orderBy('nomor_meja')
            ->get();
        $menus = Menu::query()
            ->select(['id', 'nama_menu', 'harga', 'status'])
            ->with([
                'addons' => fn ($q) => $q
                    ->select(['addons.id', 'nama_addon', 'harga', 'status'])
                    ->where('status', 'tersedia')
                    ->orderBy('nama_addon'),
            ])
            ->where('status', 'tersedia')
            ->orderBy('nama_menu')
            ->get();
        $menusById = $menus->keyBy('id');
    @endphp

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="space-y-1">
            <flux:heading size="xl" level="1">{{ __('Create Waiting List') }}</flux:heading>
            <flux:subheading>{{ __('Add a waiting list entry with pre-ordered items.') }}</flux:subheading>
        </div>
        <flux:link :href="route('waiting-list.index', [], false)" wire:navigate>
            <flux:button variant="ghost" icon="arrow-left" class="btn-ghost-accent">{{ __('Back') }}</flux:button>
        </flux:link>
    </div>

    <form wire:submit.prevent="save" class="space-y-6">
        <div class="rounded-2xl border border-neutral-200/80 bg-white p-4 shadow-sm dark:border-neutral-800/70 dark:bg-neutral-900 sm:p-5">
            <div class="grid gap-6 lg:grid-cols-5">
                <div class="space-y-4 lg:col-span-2">
                    <flux:select wire:model.defer="form.meja_id" :label="__('Table')" required>
                        <option value="">{{ __('Select') }}</option>
                        @foreach ($mejas as $meja)
                            <option value="{{ $meja->id }}">{{ __('Table') }} {{ $meja->nomor_meja }} - {{ __('Capacity') }} {{ (int) ($meja->kapasitas ?? 4) }}</option>
                        @endforeach
                    </flux:select>
                    @error('form.meja_id')
                        <p class="text-xs text-red-500">{{ $message }}</p>
                    @enderror

                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model.defer="form.customer_name" :label="__('Customer Name')" maxlength="100" placeholder="{{ __('Guest') }}" />
                        <flux:input wire:model.defer="form.jumlah_orang" type="number" min="1" max="99" :label="__('Guests')" required />
                    </div>
                    @error('form.jumlah_orang')
                        <p class="text-xs text-red-500">{{ $message }}</p>
                    @enderror

                    <flux:textarea wire:model.defer="form.customer_note" :label="__('Customer Note')" rows="3" maxlength="255" placeholder="{{ __('Optional') }}"></flux:textarea>

                    <div class="grid gap-4 sm:grid-cols-3 lg:grid-cols-1 xl:grid-cols-3">
                        <flux:input wire:model.defer="form.subtotal" type="number" step="0.01" min="0" :label="__('Subtotal')" readonly />
                        <flux:input wire:model.defer="form.tax_total" type="number" step="0.01" min="0" :label="__('Tax Total')" readonly />
                        <flux:input wire:model.defer="form.total_harga" type="number" step="0.01" min="0" :label="__('Grand Total')" readonly />
                    </div>
                </div>

                <div class="space-y-4 lg:col-span-3">
                    <div class="rounded-2xl border border-neutral-200/70 bg-white p-3 dark:border-neutral-700 dark:bg-neutral-900">
                        <div class="flex items-center justify-between gap-2">
                            <flux:heading size="sm">{{ __('Order Items') }}</flux:heading>
                            <flux:button type="button" size="xs" icon="plus" variant="ghost" class="btn-ghost-accent" wire:click="addItem">
                                {{ __('Add Item') }}
                            </flux:button>
                        </div>

                        @error('items')
                            <p class="mt-2 text-xs text-red-500">{{ $message }}</p>
                        @enderror

                        <div class="mt-3 space-y-3">
                            @foreach ($orderItems as $index => $item)
                                <div wire:key="create-waiting-list-page-item-{{ $index }}" class="rounded-xl border border-neutral-200/70 bg-neutral-50/60 p-3 dark:border-neutral-800 dark:bg-neutral-950/40">
                                    <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_96px_120px_auto] md:items-start">
                                        <div>
                                            <flux:select wire:model.live="orderItems.{{ $index }}.menu_id" :label="__('Menu')">
                                                <option value="">{{ __('Select') }}</option>
                                                @foreach ($menus as $menu)
                                                    <option value="{{ $menu->id }}">{{ $menu->nama_menu }}</option>
                                                @endforeach
                                            </flux:select>
                                            @error('items.' . $index . '.menu_id')
                                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                            @enderror

                                            @php($selectedMenu = $menusById[(int) ($item['menu_id'] ?? 0)] ?? null)
                                            @if ($selectedMenu && $selectedMenu->addons->isNotEmpty())
                                                <div class="mt-2">
                                                    <flux:checkbox.group wire:model.live="orderItems.{{ $index }}.addon_ids" variant="pills" :label="__('Add-ons (optional)')">
                                                        @foreach ($selectedMenu->addons as $addon)
                                                            <flux:checkbox variant="pills" value="{{ $addon->id }}" :label="$addon->nama_addon . ' (+Rp ' . number_format((float) $addon->harga, 0, ',', '.') . ')'" />
                                                        @endforeach
                                                    </flux:checkbox.group>
                                                </div>
                                            @endif
                                        </div>

                                        <flux:input wire:model.live.debounce.250ms="orderItems.{{ $index }}.qty" type="number" min="1" :label="__('Qty')" />
                                        <div class="text-sm md:pt-7">
                                            <div class="text-xs text-neutral-500 dark:text-neutral-400">{{ __('Subtotal') }}</div>
                                            <div class="font-semibold text-neutral-900 dark:text-white">Rp {{ number_format((float) ($item['subtotal'] ?? 0), 0, ',', '.') }}</div>
                                        </div>
                                        <div class="md:pt-6">
                                            <flux:button type="button" size="sm" variant="ghost" icon="trash" class="btn-ghost-danger w-full md:w-auto" wire:click="removeItem({{ $index }})">
                                                {{ __('Remove') }}
                                            </flux:button>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="sticky bottom-0 z-20 -mx-4 border-t border-neutral-200 bg-white/90 px-4 pt-3 pb-[calc(env(safe-area-inset-bottom)+0.75rem)] shadow-[0_-12px_30px_rgba(15,23,42,0.08)] backdrop-blur dark:border-neutral-800 dark:bg-neutral-950/80 dark:shadow-black/20 sm:static sm:mx-0 sm:flex sm:justify-end sm:border-0 sm:bg-transparent sm:px-0 sm:pt-0 sm:pb-0 sm:shadow-none sm:backdrop-blur-none">
            <div class="grid grid-cols-2 gap-2 sm:flex sm:justify-end">
                <flux:link :href="route('waiting-list.index', [], false)" wire:navigate>
                    <flux:button type="button" variant="ghost" class="btn-ghost-accent w-full justify-center sm:w-auto">{{ __('Cancel') }}</flux:button>
                </flux:link>
                <flux:button type="submit" variant="primary" icon="plus" class="btn-brand w-full justify-center sm:w-auto">{{ __('Create') }}</flux:button>
            </div>
        </div>
    </form>
</section>
