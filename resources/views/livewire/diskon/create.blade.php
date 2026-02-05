<?php

use App\Models\Diskon;
use Livewire\Volt\Component;

new class extends Component {
    public array $form = [
        'kode' => '',
        'tipe' => 'percent',
        'nilai' => '',
        'min_subtotal' => '',
        'is_active' => true,
        'tanggal_mulai' => '',
        'tanggal_selesai' => '',
    ];

    public function save(): void
    {
        $validated = validator($this->form, [
            'kode' => ['required', 'string', 'max:50', 'unique:diskons,kode'],
            'tipe' => ['required', 'in:percent,nominal'],
            'nilai' => ['required', 'numeric', 'min:0'],
            'min_subtotal' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
        ])->validate();

        $validated['is_active'] = (bool) ($validated['is_active'] ?? false);

        Diskon::create($validated);

        session()->flash('diskon_toast', __('Discount created successfully.'));
        $this->redirectRoute('diskon.index', navigate: true);
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <flux:heading size="xl" level="1">{{ __('Create Discount') }}</flux:heading>
        <flux:link :href="route('diskon.index')" wire:navigate>
            <flux:button variant="ghost" icon="arrow-left" class="btn-ghost-accent">{{ __('Back') }}</flux:button>
        </flux:link>
    </div>

    <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
        <form wire:submit="save" class="grid gap-6 md:grid-cols-2">
            <div class="md:col-span-1 space-y-4">
                <flux:input wire:model="form.kode" :label="__('Code')" required maxlength="50" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <div data-flux-field>
                        <label data-flux-label>{{ __('Type') }}</label>
                        <select
                            wire:model="form.tipe"
                            class="mt-1 w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-neutral-300 dark:border-neutral-700 dark:bg-neutral-900 dark:text-white"
                        >
                            <option value="percent">{{ __('Percent') }}</option>
                            <option value="nominal">{{ __('Nominal') }}</option>
                        </select>
                    </div>
        <flux:input wire:model="form.nilai" type="number" step="0.01" min="0" inputmode="numeric" placeholder="0" :label="__('Value')" required />
                </div>

                <flux:input
                    wire:model="form.min_subtotal"
                    type="number"
                    step="0.01"
                    min="0"
                    :label="__('Min. Subtotal')"
                    placeholder="{{ __('Optional') }}"
                />

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="form.tanggal_mulai" type="date" :label="__('Start Date')" />
                    <flux:input wire:model="form.tanggal_selesai" type="date" :label="__('End Date')" />
                </div>

                <flux:checkbox wire:model="form.is_active" :label="__('Active')" />
            </div>

            <div class="md:col-span-1 flex flex-col justify-between space-y-4">
                <div class="rounded-2xl border border-neutral-200/70 bg-white p-3 text-sm text-neutral-500 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-400">
                    <flux:heading size="sm">{{ __('Tips') }}</flux:heading>
                    <ul class="mt-2 list-disc space-y-1 pl-4 text-xs">
                        <li>{{ __('Use uppercase codes without spaces, e.g. "BALEGANJUR10".') }}</li>
                        <li>{{ __('Percent type uses the value as a percentage (e.g. 10 = 10%).') }}</li>
                        <li>{{ __('Nominal type uses the value as a fixed amount (e.g. 10000 = Rp 10.000).') }}</li>
                    </ul>
                </div>
            </div>

            <div class="md:col-span-2 flex items-center gap-3">
                <flux:button type="submit" variant="primary" class="btn-brand">{{ __('Create') }}</flux:button>
                <flux:link :href="route('diskon.index')" wire:navigate>
                    <flux:button type="button" variant="ghost" class="btn-ghost-accent">{{ __('Cancel') }}</flux:button>
                </flux:link>
            </div>
        </form>
    </div>
</section>
