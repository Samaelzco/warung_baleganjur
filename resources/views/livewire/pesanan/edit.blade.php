<?php

use App\Models\Diskon;
use App\Models\Meja;
use App\Models\Pajak;
use App\Models\Pesanan;
use App\Models\PesananDetail;
use App\Models\Menu;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Component;

new class extends Component {
    public Pesanan $pesanan;

    public array $form = [
        'meja_id' => null,
        'kode_pesanan' => '',
        'customer_name' => '',
        'customer_note' => '',
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
        $this->pesanan = $pesanan->load(['details.menu']);

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

        $this->items = $pesanan->details->map(function (PesananDetail $detail) {
            return [
                'id' => $detail->id,
                'menu_id' => $detail->menu_id,
                'qty' => $detail->qty,
                'harga' => (float) $detail->harga,
                'subtotal' => (float) $detail->subtotal,
                'catatan' => $detail->catatan,
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
            'catatan' => '',
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
        if (preg_match('/^(\\d+)\\.(qty|harga)$/', (string) $name, $matches)) {
            $index = (int) $matches[1];
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
        $harga = (float) ($this->items[$index]['harga'] ?? 0);

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

        $validated = validator($data, [
            'meja_id' => ['required', 'exists:mejas,id'],
            'kode_pesanan' => ['nullable', 'string', 'max:20', 'unique:pesanans,kode_pesanan,' . $this->pesanan->id],
            'customer_name' => ['required', 'string', 'max:100'],
            'customer_note' => ['nullable', 'string', 'max:255'],
            'subtotal' => ['required', 'numeric', 'min:0'],
            'discount_total' => ['nullable', 'numeric', 'min:0'],
            'tax_total' => ['nullable', 'numeric', 'min:0'],
            'total_harga' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:menunggu,diproses,siap,selesai,batal'],
            'metode_pembayaran' => ['nullable', 'in:tunai,transfer'],
            'dibayar' => ['nullable', 'numeric', 'min:0'],
            'kembalian' => ['nullable', 'numeric', 'min:0'],
            'kasir_id' => ['nullable', 'exists:users,id'],
            'chef_id' => ['nullable', 'exists:users,id'],
            'diskon_id' => ['nullable', 'exists:diskons,id'],
            'pajak_id' => ['nullable', 'exists:pajaks,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.menu_id' => ['required', 'exists:menus,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.harga' => ['required', 'numeric', 'min:0'],
            'items.*.subtotal' => ['required', 'numeric', 'min:0'],
            'items.*.catatan' => ['nullable', 'string'],
        ])->validate();

        if (in_array($validated['status'], ['selesai', 'batal'], true)) {
            if (!$this->pesanan->waktu_selesai) {
                $validated['waktu_selesai'] = now();
            }

            if (empty($validated['kasir_id']) && auth()->check()) {
                $validated['kasir_id'] = auth()->id();
            }
        } else {
            $validated['waktu_selesai'] = null;
        }

        DB::transaction(function () use ($validated) {
            $pesananData = collect($validated)->except('items')->toArray();

            $this->pesanan->update($pesananData);

            $existingDetails = $this->pesanan->details()->get()->keyBy('id');
            $keptIds = [];

            foreach ($validated['items'] as $item) {
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
                    $detail = $this->pesanan->details()->create($payload);
                }

                $keptIds[] = $detail->id;
            }

            if (!empty($keptIds)) {
                $this->pesanan->details()->whereNotIn('id', $keptIds)->delete();
            } else {
                $this->pesanan->details()->delete();
            }
        });

        session()->flash('pesanan_toast', __('Order updated successfully.'));
        $this->redirectRoute('pesanan.index', navigate: true);
    }
}; ?>

<section class="w-full space-y-6">
    @php
        $mejas = Meja::orderBy('nomor_meja')->get();
        $diskons = Diskon::orderBy('kode')->get();
        $pajaks = Pajak::orderBy('nama')->get();
        $users = User::orderBy('name')->get();
        $menus = Menu::orderBy('nama_menu')->get();
    @endphp

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <flux:heading size="xl" level="1">{{ __('Edit Order') }}</flux:heading>
        <flux:link :href="route('pesanan.index')" wire:navigate>
            <flux:button variant="ghost" icon="arrow-left" class="btn-ghost-accent">{{ __('Back') }}</flux:button>
        </flux:link>
    </div>

    <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700 space-y-6">
        <form wire:submit="update" class="grid gap-6 md:grid-cols-2">
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
                    required
                    maxlength="100"
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
                        <option value="diproses">{{ __('In progress') }}</option>
                        <option value="siap">{{ __('Ready') }}</option>
                        <option value="selesai">{{ __('Completed') }}</option>
                        <option value="batal">{{ __('Cancelled') }}</option>
                    </flux:select>

                    <flux:select wire:model="form.metode_pembayaran" :label="__('Payment Method')">
                        <option value="">{{ __('Select') }}</option>
                        <option value="tunai">{{ __('Cash') }}</option>
                        <option value="transfer">{{ __('Bank Transfer') }}</option>
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
                        required
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
                        required
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

            <div class="md:col-span-2 flex items-center gap-3">
                <flux:button type="submit" variant="primary" class="btn-brand">{{ __('Update') }}</flux:button>
                <flux:link :href="route('pesanan.index')" wire:navigate>
                    <flux:button type="button" variant="ghost" class="btn-ghost-accent">{{ __('Cancel') }}</flux:button>
                </flux:link>
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
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-neutral-500 dark:text-neutral-400">
                                    {{ __('Note') }}
                                </th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                            @foreach ($items as $index => $item)
                                <tr>
                                    <td class="px-4 py-3 align-top">
                                        <flux:select
                                            wire:model="items.{{ $index }}.menu_id"
                                            :label="null"
                                            class="w-full"
                                        >
                                            <option value="">{{ __('Select') }}</option>
                                            @foreach ($menus as $menu)
                                                <option value="{{ $menu->id }}">{{ $menu->nama_menu }}</option>
                                            @endforeach
                                        </flux:select>
                                    </td>
                                    <td class="px-4 py-3 align-top">
                                        <flux:input
                                            wire:model="items.{{ $index }}.qty"
                                            type="number"
                                            min="1"
                                            class="w-full text-right"
                                        />
                                    </td>
                                    <td class="px-4 py-3 align-top">
                                        <flux:input
                                            wire:model="items.{{ $index }}.harga"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            class="w-full text-right"
                                        />
                                    </td>
                                    <td class="px-4 py-3 align-top text-right align-middle">
                                        Rp {{ number_format((float) ($item['subtotal'] ?? 0), 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-3 align-top">
                                        <textarea
                                            wire:model="items.{{ $index }}.catatan"
                                            rows="2"
                                            class="w-full rounded-md border border-neutral-300 bg-white px-2 py-1 text-xs shadow-sm focus:outline-none focus:ring-2 focus:ring-neutral-300 dark:border-neutral-700 dark:bg-neutral-900 dark:text-white"
                                            placeholder="{{ __('Optional') }}"
                                        ></textarea>
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
</section>
