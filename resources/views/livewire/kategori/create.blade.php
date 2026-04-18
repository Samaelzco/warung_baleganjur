<?php

use App\Models\KategoriMenu;
use Illuminate\Support\Facades\Cache;
use Livewire\Volt\Component;

new class extends Component {
    public array $form = [
        'nama_kategori' => '',
        'nama_kategori_en' => '',
    ];

    public function save(): void
    {
        $validated = validator($this->form, [
            'nama_kategori' => ['required', 'string', 'max:100', 'unique:kategori_menus,nama_kategori'],
            'nama_kategori_en' => ['nullable', 'string', 'max:100'],
        ])->validate();

        KategoriMenu::create($validated);
        Cache::forget('customer:categories:v1');
        Cache::forget('customer:menus_available:v1');
        Cache::forget('admin:kategori:stats:v1');

        session()->flash('kategori_toast', __('Category created successfully.'));
        $this->redirectRoute('kategori.index', navigate: true);
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="space-y-1">
            <flux:heading size="xl" level="1">{{ __('Create Category') }}</flux:heading>
            <flux:subheading>{{ __('Fill the details below to add a new category.') }}</flux:subheading>
        </div>
        <flux:link :href="route('kategori.index', [], false)" wire:navigate>
            <flux:button variant="ghost" icon="arrow-left" class="btn-ghost-accent">{{ __('Back') }}</flux:button>
        </flux:link>
    </div>

    <form wire:submit.prevent="save" class="space-y-6">
        <div class="rounded-2xl border border-neutral-200/80 bg-white p-4 shadow-sm dark:border-neutral-800/70 dark:bg-neutral-900 sm:p-5">
            <div class="grid gap-6 md:grid-cols-5">
                <div class="space-y-4 md:col-span-3">
                    <flux:input wire:model.defer="form.nama_kategori" :label="__('Category Name')" required maxlength="100" help="{{ __('e.g. Makanan, Minuman, Snack') }}" />
                    @error('form.nama_kategori')
                        <p class="text-xs text-red-500">{{ $message }}</p>
                    @enderror

                    <flux:input wire:model.defer="form.nama_kategori_en" :label="__('Category Name (English)')" maxlength="100" placeholder="{{ __('Optional') }}" help="{{ __('e.g. Food, Drinks, Snack') }}" />
                    @error('form.nama_kategori_en')
                        <p class="text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <div class="space-y-4 md:col-span-2 md:pl-2">
                    <div class="rounded-2xl border border-neutral-200/70 bg-white p-3 dark:border-neutral-700 dark:bg-neutral-900">
                        <flux:heading size="sm">{{ __('Tips') }}</flux:heading>
                        <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                            {{ __('Use categories to group menu items in the customer QR page.') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="sticky bottom-0 z-20 -mx-4 border-t border-neutral-200 bg-white/90 px-4 pt-3 pb-[calc(env(safe-area-inset-bottom)+0.75rem)] shadow-[0_-12px_30px_rgba(15,23,42,0.08)] backdrop-blur dark:border-neutral-800 dark:bg-neutral-950/80 dark:shadow-black/20 sm:static sm:mx-0 sm:flex sm:justify-end sm:border-0 sm:bg-transparent sm:px-0 sm:pt-0 sm:pb-0 sm:shadow-none sm:backdrop-blur-none">
            <div class="grid grid-cols-2 gap-2 sm:flex sm:justify-end">
                <flux:link :href="route('kategori.index', [], false)" wire:navigate>
                    <flux:button type="button" variant="ghost" class="btn-ghost-accent w-full justify-center sm:w-auto">{{ __('Cancel') }}</flux:button>
                </flux:link>
                <flux:button type="submit" variant="primary" icon="plus" class="btn-brand w-full justify-center sm:w-auto">{{ __('Create') }}</flux:button>
            </div>
        </div>
    </form>
</section>
