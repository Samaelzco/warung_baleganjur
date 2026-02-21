<?php

use App\Models\Menu;
use App\Models\KategoriMenu;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;
    public Menu $menu;

    public array $form = [
        'kategori_id' => null,
        'nama_menu' => '',
        'deskripsi' => '',
        'harga' => '',
        'status' => 'tersedia',
    ];
    public $gambar = null;

    public function mount(Menu $menu): void
    {
        $this->menu = $menu;
        $this->form = [
            'kategori_id' => $menu->kategori_id,
            'nama_menu' => $menu->nama_menu,
            'deskripsi' => $menu->deskripsi,
            'harga' => $menu->harga,
            'status' => $menu->status,
        ];
    }

    public function update(): void
    {
        $data = [...$this->form, 'gambar' => $this->gambar];
        $validated = validator($data, [
            'kategori_id' => ['required', 'exists:kategori_menus,id'],
            'nama_menu' => ['required', 'string', 'max:100'],
            'deskripsi' => ['nullable', 'string'],
            'harga' => ['required', 'numeric', 'min:0'],
            'gambar' => ['nullable', 'image', 'max:2048'],
            'status' => ['required', 'in:tersedia,habis'],
        ])->validate();

        if ($this->gambar) {
            if ($this->menu->gambar) {
                Storage::disk('public')->delete($this->menu->gambar);
            }
            $path = $this->gambar->store('menus', 'public');
            $validated['gambar'] = $path;
        } else {
            unset($validated['gambar']);
        }

        $this->menu->update($validated);

        session()->flash('menu_toast', __('Menu updated successfully.'));
        $this->redirectRoute('menu.index', navigate: true);
    }
}; ?>

<section class="w-full space-y-6">
    @php $kategories = KategoriMenu::query()->select(['id', 'nama_kategori'])->orderBy('nama_kategori')->get(); @endphp

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <flux:heading size="xl" level="1">{{ __('Edit Menu') }}</flux:heading>
        <flux:link :href="route('menu.index')" wire:navigate>
            <flux:button variant="ghost" icon="arrow-left" class="btn-ghost-accent">{{ __('Back') }}</flux:button>
        </flux:link>
    </div>

    <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
        <form wire:submit="update" class="grid gap-6 md:grid-cols-2">
            <div class="md:col-span-1">
                <flux:select wire:model="form.kategori_id" :label="__('Category')" required>
                    @foreach ($kategories as $kat)
                        <option value="{{ $kat->id }}">{{ $kat->nama_kategori }}</option>
                    @endforeach
                </flux:select>
            </div>
            <div class="md:col-span-1">
                <flux:input wire:model="form.nama_menu" :label="__('Name')" required maxlength="100" />
            </div>
            <div class="md:col-span-1">
                <flux:input type="number" step="0.01" min="0" wire:model="form.harga" :label="__('Price')" required />
            </div>
            <div class="md:col-span-1">
                <div data-flux-field>
                    <label data-flux-label>{{ __('Image') }}</label>
                    <input type="file" wire:model="gambar" accept="image/*" class="mt-2 block w-full text-sm" />
                    <div class="mt-2 flex items-center gap-3">
                        @if ($gambar)
                            <img src="{{ $gambar->temporaryUrl() }}" alt="preview" class="h-20 w-20 rounded object-cover border" />
                        @elseif ($menu->gambar)
                            <img src="{{ Storage::url($menu->gambar) }}" alt="current" class="h-20 w-20 rounded object-cover border" />
                        @endif
                    </div>
                </div>
            </div>
            <div class="md:col-span-2">
                <div data-flux-field>
                    <label data-flux-label>{{ __('Description') }}</label>
                    <textarea wire:model="form.deskripsi" rows="4" class="w-full min-h-28 rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-neutral-300 dark:border-neutral-700 dark:bg-neutral-900 dark:text-white"></textarea>
                </div>
            </div>
            <div class="md:col-span-1">
                <flux:select wire:model="form.status" :label="__('Status')">
                    <option value="tersedia">{{ __('Available') }}</option>
                    <option value="habis">{{ __('Out of stock') }}</option>
                </flux:select>
            </div>

            <div class="md:col-span-2 flex items-center gap-3">
                <flux:button type="submit" variant="primary" class="btn-brand">{{ __('Update') }}</flux:button>
                <flux:link :href="route('menu.index')" wire:navigate>
                    <flux:button type="button" variant="ghost" class="btn-ghost-accent">{{ __('Cancel') }}</flux:button>
                </flux:link>
            </div>
        </form>
    </div>
</section>
