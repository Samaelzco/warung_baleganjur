<?php

use App\Models\Addon;
use App\Models\KategoriMenu;
use App\Models\Menu;
use App\Services\MenuImageService;
use Illuminate\Support\Facades\Cache;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public array $form = [
        'nama_menu' => '',
        'nama_menu_en' => '',
        'kategori_id' => null,
        'harga' => null,
        'status' => 'tersedia',
        'deskripsi' => '',
        'deskripsi_en' => '',
        'addon_ids' => [],
    ];

    public $gambar = null;

    public function save(): void
    {
        $data = [...$this->form, 'gambar' => $this->gambar];

        $validated = validator($data, [
            'nama_menu' => ['required', 'string', 'max:150', 'unique:menus,nama_menu'],
            'nama_menu_en' => ['nullable', 'string', 'max:150'],
            'kategori_id' => [
                'required',
                'integer',
                'exists:kategori_menus,id',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (!KategoriMenu::query()->whereKey($value)->where('is_active', true)->exists()) {
                        $fail(__('The selected category is inactive.'));
                    }
                },
            ],
            'harga' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:tersedia,habis'],
            'deskripsi' => ['nullable', 'string'],
            'deskripsi_en' => ['nullable', 'string'],
            'addon_ids' => ['nullable', 'array'],
            'addon_ids.*' => ['integer', 'exists:addons,id'],
            'gambar' => ['nullable', 'image', 'max:2048'],
        ])->validate();

        $addonIds = $validated['addon_ids'] ?? [];
        unset($validated['addon_ids']);

        if ($this->gambar) {
            $path = $this->gambar->store('menus', 'public');
            $validated['gambar'] = $path;
            MenuImageService::generateThumbnails($path);
        } else {
            unset($validated['gambar']);
        }

        $menu = Menu::create($validated);
        $menu->addons()->sync($addonIds);

        Cache::forget('customer:menus_available:v1');
        Cache::forget('customer:menus_orderable_display:v1');
        Cache::forever('customer:menu_version', ((int) Cache::get('customer:menu_version', 1)) + 1);
        Cache::forget('admin:menu:stats:v1');

        session()->flash('menu_toast', __('Menu created successfully.'));
        $this->redirectRoute('menu.index', navigate: true);
    }
}; ?>

<section class="w-full space-y-6">
    @php
        $kategories = KategoriMenu::query()->select(['id', 'nama_kategori'])->where('is_active', true)->orderBy('nama_kategori')->get();
        $addons = Addon::query()->select(['id', 'nama_addon', 'harga', 'status'])->orderBy('nama_addon')->get();
    @endphp

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="space-y-1">
            <flux:heading size="xl" level="1">{{ __('Create Menu') }}</flux:heading>
            <flux:subheading>{{ __('Fill the details below to add a new menu item.') }}</flux:subheading>
        </div>
        <flux:link :href="route('menu.index', [], false)" wire:navigate>
            <flux:button variant="ghost" icon="arrow-left" class="btn-ghost-accent">{{ __('Back') }}</flux:button>
        </flux:link>
    </div>

    <form wire:submit.prevent="save" class="space-y-6">
        <div class="rounded-2xl border border-neutral-200/80 bg-white p-4 shadow-sm dark:border-neutral-800/70 dark:bg-neutral-900 sm:p-5">
            <div class="grid gap-6 md:grid-cols-5">
                <div class="space-y-4 md:col-span-3">
                    <flux:input wire:model.defer="form.nama_menu" :label="__('Menu Name')" required maxlength="150" />
                    @error('form.nama_menu')
                        <p class="text-xs text-red-500">{{ $message }}</p>
                    @enderror

                    <flux:input wire:model.defer="form.nama_menu_en" :label="__('Menu Name (English)')" maxlength="150" placeholder="{{ __('Optional') }}" />
                    @error('form.nama_menu_en')
                        <p class="text-xs text-red-500">{{ $message }}</p>
                    @enderror

                    <div data-flux-field>
                        <flux:select wire:model.defer="form.kategori_id" :label="__('Category')">
                            <option value="">{{ __('Select category') }}</option>
                            @foreach($kategories as $kat)
                                <option value="{{ $kat->id }}">{{ $kat->nama_kategori }}</option>
                            @endforeach
                        </flux:select>
                        @error('form.kategori_id')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <flux:input wire:model.defer="form.harga" type="number" min="0" step="100" inputmode="numeric" placeholder="0" :label="__('Price (IDR)')" required />
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

                    <flux:checkbox.group wire:model.defer="form.addon_ids" variant="pills" :label="__('Add-ons (optional)')">
                        @forelse ($addons as $addon)
                            <flux:checkbox
                                variant="pills"
                                value="{{ $addon->id }}"
                                :label="$addon->nama_addon . ' (+Rp ' . number_format((float) $addon->harga, 0, ',', '.') . ')' . ($addon->status === 'habis' ? ' · ' . __('Out of stock') : '')"
                            />
                        @empty
                            <div class="text-xs text-neutral-500 dark:text-neutral-400">{{ __('No add-ons yet.') }}</div>
                        @endforelse
                        @error('form.addon_ids')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">{{ __('Selected add-ons will be selectable when ordering this menu.') }}</p>
                    </flux:checkbox.group>

                    <div>
                        <flux:textarea wire:model.defer="form.deskripsi" rows="4" :label="__('Description')" placeholder="{{ __('Optional, short description of this menu') }}"></flux:textarea>
                        @error('form.deskripsi')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <flux:textarea wire:model.defer="form.deskripsi_en" rows="4" :label="__('Description (English)')" placeholder="{{ __('Optional') }}"></flux:textarea>
                        @error('form.deskripsi_en')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="space-y-4 md:col-span-2 md:pl-2">
                    <div class="rounded-2xl border border-neutral-200/70 bg-white p-3 dark:border-neutral-700 dark:bg-neutral-900">
                        <flux:heading size="sm">{{ __('Image') }}</flux:heading>
                        <input type="file" wire:model="gambar" accept="image/*" class="mt-2 block w-full text-sm" />
                        @error('gambar')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                        @if ($gambar)
                            <img src="{{ $gambar->temporaryUrl() }}" alt="preview" class="mt-3 h-24 w-24 rounded-xl border object-cover" />
                        @endif
                    </div>

                    <div class="rounded-2xl border border-neutral-200/70 bg-white p-3 dark:border-neutral-700 dark:bg-neutral-900">
                        <flux:heading size="sm">{{ __('Tips') }}</flux:heading>
                        <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                            {{ __('Menus will appear on customer ordering page based on category and availability.') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="sticky bottom-0 z-20 -mx-4 border-t border-neutral-200 bg-white/90 px-4 pt-3 pb-[calc(env(safe-area-inset-bottom)+0.75rem)] shadow-[0_-12px_30px_rgba(15,23,42,0.08)] backdrop-blur dark:border-neutral-800 dark:bg-neutral-950/80 dark:shadow-black/20 sm:static sm:mx-0 sm:flex sm:justify-end sm:border-0 sm:bg-transparent sm:px-0 sm:pt-0 sm:pb-0 sm:shadow-none sm:backdrop-blur-none">
            <div class="grid grid-cols-2 gap-2 sm:flex sm:justify-end">
                <flux:link :href="route('menu.index', [], false)" wire:navigate>
                    <flux:button type="button" variant="ghost" class="btn-ghost-accent w-full justify-center sm:w-auto">{{ __('Cancel') }}</flux:button>
                </flux:link>
                <flux:button type="submit" variant="primary" icon="plus" class="btn-brand w-full justify-center sm:w-auto">{{ __('Create') }}</flux:button>
            </div>
        </div>
    </form>
</section>
