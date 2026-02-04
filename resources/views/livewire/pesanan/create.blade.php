<?php

use App\Models\Diskon;
use App\Models\Meja;
use App\Models\Pajak;
use App\Models\Pesanan;
use App\Models\User;
use Livewire\Volt\Component;

new class extends Component {
    public array $form = [
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

    public function save(): void
    {
        $data = $this->form;
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
        ])->validate();

        if (empty($validated['kode_pesanan'])) {
            $validated['kode_pesanan'] = 'ORD-' . now()->format('YmdHis');
        }

        $validated['customer_name'] = blank($validated['customer_name'] ?? null) ? __('Guest') : $validated['customer_name'];
        $validated['waktu_pesan'] = now();
        $validated['status'] = 'menunggu';
        $validated['subtotal'] = 0;
        $validated['total_harga'] = 0;
        $validated['metode_pembayaran'] = null;
        $validated['dibayar'] = null;
        $validated['kembalian'] = null;
        $validated['kasir_id'] = null;

        Pesanan::create($validated);

        session()->flash('pesanan_toast', __('Order created successfully.'));
        $this->redirectRoute('pesanan.index', navigate: true);
    }
}; ?>

<section class="w-full space-y-6">
    @php
        $mejas = Meja::orderBy('nomor_meja')->get();
        $diskons = Diskon::orderBy('kode')->get();
        $pajaks = Pajak::orderBy('nama')->get();
        $users = User::orderBy('name')->get();
    @endphp

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <flux:heading size="xl" level="1">{{ __('Create Order') }}</flux:heading>
        <flux:link :href="route('pesanan.index')" wire:navigate>
            <flux:button variant="ghost" icon="arrow-left" class="btn-ghost-accent">{{ __('Back') }}</flux:button>
        </flux:link>
    </div>

    <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
        <form wire:submit="save" class="grid gap-6 md:grid-cols-2">
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
                    <flux:select wire:model="form.status" :label="__('Status')">
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
            </div>

            <div class="md:col-span-2 flex items-center gap-3">
                <flux:button type="submit" variant="primary" class="btn-brand">{{ __('Create') }}</flux:button>
                <flux:link :href="route('pesanan.index')" wire:navigate>
                    <flux:button type="button" variant="ghost" class="btn-ghost-accent">{{ __('Cancel') }}</flux:button>
                </flux:link>
            </div>
        </form>
    </div>
</section>
