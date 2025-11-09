<?php

use App\Models\KategoriMenu;
use Livewire\Volt\Component;

new class extends Component {
    public array $form = [
        'nama_kategori' => '',
        'deskripsi' => '',
    ];

    public function save(): void
    {
        $validated = validator($this->form, [
            'nama_kategori' => ['required', 'string', 'max:100'],
            'deskripsi' => ['nullable', 'string'],
        ])->validate();

        KategoriMenu::create($validated);

        session()->flash('kategori_toast', __('Category created successfully.'));
        $this->redirectRoute('kategori.index', navigate: true);
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <flux:heading size="xl" level="1">{{ __('Create Category') }}</flux:heading>
        <flux:link :href="route('kategori.index')" wire:navigate>
            <flux:button variant="ghost" icon="arrow-left" class="btn-ghost-accent">{{ __('Back') }}</flux:button>
        </flux:link>
    </div>

    <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
        <form wire:submit="save" class="grid gap-6 md:grid-cols-2">
            <div class="md:col-span-1">
                <flux:input wire:model="form.nama_kategori" :label="__('Name')" required maxlength="100" />
            </div>
            <div class="md:col-span-2">
                <div data-flux-field>
                    <label data-flux-label>{{ __('Description') }}</label>
                    <textarea wire:model="form.deskripsi" class="w-full min-h-28 rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-neutral-300 dark:border-neutral-700 dark:bg-neutral-900 dark:text-white"></textarea>
                </div>
            </div>

            <div class="md:col-span-2 flex items-center gap-3">
                <flux:button type="submit" variant="primary" class="btn-brand">{{ __('Create') }}</flux:button>
                <flux:link :href="route('kategori.index')" wire:navigate>
                    <flux:button type="button" variant="ghost" class="btn-ghost-accent">{{ __('Cancel') }}</flux:button>
                </flux:link>
            </div>
        </form>
    </div>
</section>

