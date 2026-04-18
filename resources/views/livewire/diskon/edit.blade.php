<?php

use App\Models\Diskon;
use Illuminate\Support\Facades\Cache;
use Livewire\Volt\Component;

new class extends Component {
    public Diskon $diskon;

    public array $form = [
        'kode' => '',
        'tipe' => 'percent',
        'nilai' => '',
        'min_subtotal' => '',
        'is_active' => true,
        'tanggal_mulai' => '',
        'tanggal_selesai' => '',
    ];

    public function mount(Diskon $diskon): void
    {
        $this->diskon = $diskon;
        $this->form = [
            'kode' => $diskon->kode,
            'tipe' => $diskon->tipe,
            'nilai' => $diskon->nilai,
            'min_subtotal' => $diskon->min_subtotal,
            'is_active' => (bool) $diskon->is_active,
            'tanggal_mulai' => optional($diskon->tanggal_mulai)?->format('Y-m-d'),
            'tanggal_selesai' => optional($diskon->tanggal_selesai)?->format('Y-m-d'),
        ];
    }

    public function update(): void
    {
        $validated = validator($this->form, [
            'kode' => ['required', 'string', 'max:50', 'unique:diskons,kode,' . $this->diskon->id],
            'tipe' => ['required', 'in:percent,nominal'],
            'nilai' => ['required', 'numeric', 'min:0'],
            'min_subtotal' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
        ])->validate();

        $validated['is_active'] = (bool) ($validated['is_active'] ?? false);

        $this->diskon->update($validated);
        Cache::forget('admin:diskon:stats:v1');

        session()->flash('diskon_toast', __('Discount updated successfully.'));
        $this->redirectRoute('diskon.index', navigate: true);
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="space-y-1">
            <flux:heading size="xl" level="1">{{ __('Edit Discount') }}</flux:heading>
            <flux:subheading>{{ __('Update the details for this discount code.') }}</flux:subheading>
        </div>
        <flux:link :href="route('diskon.index', [], false)" wire:navigate>
            <flux:button variant="ghost" icon="arrow-left" class="btn-ghost-accent">{{ __('Back') }}</flux:button>
        </flux:link>
    </div>

    <form wire:submit.prevent="update" class="space-y-6">
        <div class="rounded-2xl border border-neutral-200/80 bg-white p-4 shadow-sm dark:border-neutral-800/70 dark:bg-neutral-900 sm:p-5">
            <div class="grid gap-6 md:grid-cols-5">
                <div class="space-y-4 md:col-span-3">
                    <flux:input
                        wire:model.defer="form.kode"
                        :label="__('Code')"
                        required
                        maxlength="50"
                        help="{{ __('Suggestion: use uppercase, e.g. WEEKNIGHT10') }}"
                    />
                    @error('form.kode')
                        <p class="text-xs text-red-500">{{ $message }}</p>
                    @enderror

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div data-flux-field>
                            <flux:select wire:model.defer="form.tipe" :label="__('Type')">
                                <option value="percent">{{ __('Percent') }}</option>
                                <option value="nominal">{{ __('Nominal') }}</option>
                            </flux:select>
                            @error('form.tipe')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <flux:input
                                wire:model.defer="form.nilai"
                                type="number"
                                min="0"
                                step="0.01"
                                inputmode="numeric"
                                placeholder="0"
                                :label="__('Value')"
                                required
                                help="{{ __('Percent: 10 = 10%') }}"
                            />
                            @error('form.nilai')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <flux:input
                        wire:model.defer="form.min_subtotal"
                        type="number"
                        min="0"
                        step="100"
                        :label="__('Minimum Subtotal')"
                        help="{{ __('Optional. Leave empty for no minimum.') }}"
                    />
                    @error('form.min_subtotal')
                        <p class="text-xs text-red-500">{{ $message }}</p>
                    @enderror

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <flux:input wire:model.defer="form.tanggal_mulai" type="date" :label="__('Start Date')" />
                            @error('form.tanggal_mulai')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <flux:input wire:model.defer="form.tanggal_selesai" type="date" :label="__('End Date')" />
                            @error('form.tanggal_selesai')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <flux:checkbox wire:model.defer="form.is_active" :label="__('Active')" />
                    @error('form.is_active')
                        <p class="text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <div class="space-y-4 md:col-span-2 md:pl-2">
                    <div class="rounded-2xl border border-neutral-200/70 bg-white p-3 text-xs text-neutral-500 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-400">
                        <flux:heading size="sm">{{ __('Tips') }}</flux:heading>
                        <ul class="mt-2 list-disc space-y-1 pl-4">
                            <li>{{ __('Adjust the value and period carefully, especially for running promotions.') }}</li>
                            <li>{{ __('Deactivate discounts that should no longer be used instead of deleting them.') }}</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="sticky bottom-0 z-20 -mx-4 border-t border-neutral-200 bg-white/90 px-4 pt-3 pb-[calc(env(safe-area-inset-bottom)+0.75rem)] shadow-[0_-12px_30px_rgba(15,23,42,0.08)] backdrop-blur dark:border-neutral-800 dark:bg-neutral-950/80 dark:shadow-black/20 sm:static sm:mx-0 sm:flex sm:justify-end sm:border-0 sm:bg-transparent sm:px-0 sm:pt-0 sm:pb-0 sm:shadow-none sm:backdrop-blur-none">
            <div class="grid grid-cols-2 gap-2 sm:flex sm:justify-end">
                <flux:link :href="route('diskon.index', [], false)" wire:navigate>
                    <flux:button type="button" variant="ghost" class="btn-ghost-accent w-full justify-center sm:w-auto">{{ __('Cancel') }}</flux:button>
                </flux:link>
                <flux:button type="submit" variant="primary" icon="check" class="btn-brand w-full justify-center sm:w-auto">{{ __('Update') }}</flux:button>
            </div>
        </div>
    </form>
</section>
