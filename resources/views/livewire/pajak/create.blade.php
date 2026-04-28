<?php

use App\Models\Pajak;
use Illuminate\Support\Facades\Cache;
use Livewire\Volt\Component;

new class extends Component {
    public array $form = [
        'nama' => '',
        'persentase' => 10.00,
        'is_active' => true,
    ];

    public function save(): void
    {
        $validated = validator($this->form, [
            'nama' => ['required', 'string', 'max:100', 'unique:pajaks,nama'],
            'persentase' => ['required', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['boolean'],
        ])->validate();

        $validated['is_active'] = (bool) ($validated['is_active'] ?? false);
        $validated['user_id'] = auth()->id();

        Pajak::create($validated);
        Cache::forget('admin:pajak:stats:v1');

        session()->flash('pajak_toast', __('Tax created successfully.'));
        $this->redirectRoute('pajak.index', navigate: true);
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="space-y-1">
            <flux:heading size="xl" level="1">{{ __('Create Tax') }}</flux:heading>
            <flux:subheading>{{ __('Add a new tax configuration.') }}</flux:subheading>
        </div>
        <flux:link :href="route('pajak.index', [], false)" wire:navigate>
            <flux:button variant="ghost" icon="arrow-left" class="btn-ghost-accent">{{ __('Back') }}</flux:button>
        </flux:link>
    </div>

    <form wire:submit.prevent="save" class="space-y-6">
        <div class="rounded-2xl border border-neutral-200/80 bg-white p-4 shadow-sm dark:border-neutral-800/70 dark:bg-neutral-900 sm:p-5">
            <div class="grid gap-6 md:grid-cols-5">
                <div class="space-y-4 md:col-span-3">
                    <flux:input
                        wire:model.defer="form.nama"
                        :label="__('Name')"
                        required
                        maxlength="100"
                        help="{{ __('Use a clear tax name, e.g. Restaurant Tax 10%.') }}"
                    />
                    @error('form.nama')
                        <p class="text-xs text-red-500">{{ $message }}</p>
                    @enderror

                    <flux:input
                        wire:model.defer="form.persentase"
                        type="number"
                        step="0.01"
                        min="0"
                        max="100"
                        :label="__('Percentage (%)')"
                        required
                    />
                    @error('form.persentase')
                        <p class="text-xs text-red-500">{{ $message }}</p>
                    @enderror

                    <flux:checkbox wire:model.defer="form.is_active" :label="__('Active')" />
                    @error('form.is_active')
                        <p class="text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <div class="space-y-4 md:col-span-2 md:pl-2">
                    <div class="rounded-2xl border border-neutral-200/70 bg-white p-3 text-xs text-neutral-500 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-400">
                        <flux:heading size="sm">{{ __('Tips') }}</flux:heading>
                        <ul class="mt-2 list-disc space-y-1 pl-4">
                            <li>{{ __('Only one active tax will usually be applied in orders.') }}</li>
                            <li>{{ __('Use clear names, e.g. "Pajak Restoran 10%".') }}</li>
                            <li>{{ __('You can deactivate taxes without deleting them.') }}</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="sticky bottom-0 z-20 -mx-4 border-t border-neutral-200 bg-white/90 px-4 pt-3 pb-[calc(env(safe-area-inset-bottom)+0.75rem)] shadow-[0_-12px_30px_rgba(15,23,42,0.08)] backdrop-blur dark:border-neutral-800 dark:bg-neutral-950/80 dark:shadow-black/20 sm:static sm:mx-0 sm:flex sm:justify-end sm:border-0 sm:bg-transparent sm:px-0 sm:pt-0 sm:pb-0 sm:shadow-none sm:backdrop-blur-none">
            <div class="grid grid-cols-2 gap-2 sm:flex sm:justify-end">
                <flux:link :href="route('pajak.index', [], false)" wire:navigate>
                    <flux:button type="button" variant="ghost" class="btn-ghost-accent w-full justify-center sm:w-auto">{{ __('Cancel') }}</flux:button>
                </flux:link>
                <flux:button type="submit" variant="primary" icon="plus" class="btn-brand w-full justify-center sm:w-auto">{{ __('Create') }}</flux:button>
            </div>
        </div>
    </form>
</section>
