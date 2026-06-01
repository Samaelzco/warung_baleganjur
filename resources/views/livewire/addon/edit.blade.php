<?php

use App\Models\Addon;
use Illuminate\Support\Facades\Cache;
use Livewire\Volt\Component;

new class extends Component
{
    public Addon $addon;

    public array $form = [
        'nama_addon' => '',
        'nama_addon_en' => '',
        'harga' => null,
        'status' => 'tersedia',
    ];

    public function mount(Addon $addon): void
    {
        $this->addon = $addon;
        $this->form = [
            'nama_addon' => $addon->nama_addon,
            'nama_addon_en' => $addon->nama_addon_en,
            'harga' => $addon->harga,
            'status' => $addon->status,
        ];
    }

    public function update(): void
    {
        $validated = validator($this->form, [
            'nama_addon' => ['required', 'string', 'max:100', 'unique:addons,nama_addon,'.$this->addon->id],
            'nama_addon_en' => ['nullable', 'string', 'max:100'],
            'harga' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:tersedia,habis'],
        ])->validate();

        $this->addon->update($validated);
        Cache::forget('customer:menus_available:v1');
        Cache::forget('customer:menus_orderable_display:v1');
        Cache::forever('customer:menu_version', ((int) Cache::get('customer:menu_version', 1)) + 1);
        Cache::forget('admin:addon:stats:v1');

        session()->flash('addon_toast', __('Add-on updated successfully.'));
        $this->redirectRoute('addon.index', navigate: true);
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="space-y-1">
            <flux:heading size="xl" level="1">{{ __('Edit Add-on') }}</flux:heading>
            <flux:subheading>{{ __('Update the details for this add-on.') }}</flux:subheading>
        </div>
        <flux:link :href="route('addon.index', [], false)" wire:navigate>
            <flux:button variant="ghost" icon="arrow-left" class="btn-ghost-accent">{{ __('Back') }}</flux:button>
        </flux:link>
    </div>

    <form wire:submit.prevent="update" class="space-y-6">
        <div class="rounded-2xl border border-neutral-200/80 bg-white p-4 shadow-sm dark:border-neutral-800/70 dark:bg-neutral-900 sm:p-5">
            <div class="grid gap-6 md:grid-cols-5">
                <div class="space-y-4 md:col-span-3">
                    <flux:input
                        wire:model.defer="form.nama_addon"
                        :label="__('Add-on Name')"
                        required
                        maxlength="100"
                    />
                    @error('form.nama_addon')
                        <p class="text-xs text-red-500">{{ $message }}</p>
                    @enderror

                    <flux:input
                        wire:model.defer="form.nama_addon_en"
                        :label="__('Add-on Name (English)')"
                        maxlength="100"
                        placeholder="{{ __('Optional') }}"
                    />
                    @error('form.nama_addon_en')
                        <p class="text-xs text-red-500">{{ $message }}</p>
                    @enderror

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-rupiah-input model="form.harga" :label="__('Price (IDR)')" required />
                            @error('form.harga')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div data-flux-field>
                            <flux:select wire:model.defer="form.status" :label="__('Status')">
                                <option value="tersedia">{{ __('Available') }}</option>
                                <option value="habis">{{ __('Out of stock') }}</option>
                            </flux:select>
                            @error('form.status')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="space-y-4 md:col-span-2 md:pl-2">
                    <div class="rounded-2xl border border-neutral-200/70 bg-white p-3 text-xs text-neutral-500 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-400">
                        <flux:heading size="sm">{{ __('Tips') }}</flux:heading>
                        <ul class="mt-2 list-disc space-y-1 pl-4">
                            <li>{{ __('If a menu already uses this add-on, changing the price will apply to new orders.') }}</li>
                            <li>{{ __('Past orders keep a snapshot of add-on prices.') }}</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="sticky bottom-0 z-20 -mx-4 border-t border-neutral-200 bg-white/90 px-4 pt-3 pb-[calc(env(safe-area-inset-bottom)+0.75rem)] shadow-[0_-12px_30px_rgba(15,23,42,0.08)] backdrop-blur dark:border-neutral-800 dark:bg-neutral-950/80 dark:shadow-black/20 sm:static sm:mx-0 sm:flex sm:justify-end sm:border-0 sm:bg-transparent sm:px-0 sm:pt-0 sm:pb-0 sm:shadow-none sm:backdrop-blur-none">
            <div class="grid grid-cols-2 gap-2 sm:flex sm:justify-end">
                <flux:link :href="route('addon.index', [], false)" wire:navigate>
                    <flux:button type="button" variant="ghost" class="btn-ghost-accent w-full justify-center sm:w-auto">{{ __('Cancel') }}</flux:button>
                </flux:link>
                <flux:button type="submit" variant="primary" icon="check" class="btn-brand w-full justify-center sm:w-auto">{{ __('Update') }}</flux:button>
            </div>
        </div>
    </form>
</section>
