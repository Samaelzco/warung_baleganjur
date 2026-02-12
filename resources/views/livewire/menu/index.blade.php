<?php

use App\Models\Menu;
use App\Models\KategoriMenu;
use App\Models\Addon;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;

new class extends Component {
    use WithPagination, WithFileUploads;

    public ?int $confirmingDeleteId = null;
    public ?int $editingId = null;
    public array $form = [];
    public $gambar = null;
    public string $search = '';
    public string $statusFilter = 'all';
    public ?int $kategoriFilter = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => 'all'],
        'kategoriFilter' => ['except' => null],
        'page' => ['except' => 1],
    ];

    public function mount(): void
    {
        $this->resetCreateForm();
    }

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingStatusFilter(): void { $this->resetPage(); }
    public function updatingKategoriFilter(): void { $this->resetPage(); }

    public function openCreateModal(): void
    {
        $this->authorizeManage();
        $this->resetCreateForm();
        $this->gambar = null;
        $this->dispatch('modal-show', name: 'create-menu');
    }

    public function openEditModal(int $id): void
    {
        $this->authorizeManage();
        $menu = Menu::with('addons')->findOrFail($id);
        $this->editingId = $menu->id;

        $this->form = [
            'nama_menu'   => $menu->nama_menu,
            'kategori_id' => $menu->kategori_id,
            'harga'       => $menu->harga,
            'status'      => $menu->status,
            'deskripsi'   => $menu->deskripsi,
            'addon_ids'   => $menu->addons->pluck('id')->all(),
        ];

        $this->gambar = null;
        $this->dispatch('modal-show', name: 'edit-menu');
    }

    public function save(): void
    {
        $this->authorizeManage();
        $data = [...$this->form, 'gambar' => $this->gambar];

        $validated = validator($data, [
            'nama_menu'   => ['required', 'string', 'max:150', 'unique:menus,nama_menu'],
            'kategori_id' => ['required', 'integer', 'exists:kategori_menus,id'],
            'harga'       => ['required', 'numeric', 'min:0'],
            'status'      => ['required', 'in:tersedia,habis'],
            'deskripsi'   => ['nullable', 'string'],
            'addon_ids'   => ['nullable', 'array'],
            'addon_ids.*' => ['integer', 'exists:addons,id'],
            'gambar'      => ['nullable', 'image', 'max:2048'],
        ])->validate();

        $addonIds = $validated['addon_ids'] ?? [];
        unset($validated['addon_ids']);

        if ($this->gambar) {
            $path = $this->gambar->store('menus', 'public');
            $validated['gambar'] = $path;
        } else {
            unset($validated['gambar']);
        }

        $menu = Menu::create($validated);
        $menu->addons()->sync($addonIds);

        $this->resetCreateForm();
        $this->dispatch('modal-close', name: 'create-menu');
        $this->dispatch('menu-toast', message: __('Menu created successfully.'));
    }

    public function update(): void
    {
        $this->authorizeManage();
        if (!$this->editingId) return;

        $data = [...$this->form, 'gambar' => $this->gambar];

        $validated = validator($data, [
            'nama_menu'   => ['required', 'string', 'max:150', 'unique:menus,nama_menu,' . $this->editingId],
            'kategori_id' => ['required', 'integer', 'exists:kategori_menus,id'],
            'harga'       => ['required', 'numeric', 'min:0'],
            'status'      => ['required', 'in:tersedia,habis'],
            'deskripsi'   => ['nullable', 'string'],
            'addon_ids'   => ['nullable', 'array'],
            'addon_ids.*' => ['integer', 'exists:addons,id'],
            'gambar'      => ['nullable', 'image', 'max:2048'],
        ])->validate();

        $addonIds = $validated['addon_ids'] ?? [];
        unset($validated['addon_ids']);

        $menu = Menu::findOrFail($this->editingId);

        if ($this->gambar) {
            if ($menu->gambar) {
                Storage::disk('public')->delete($menu->gambar);
            }
            $path = $this->gambar->store('menus', 'public');
            $validated['gambar'] = $path;
        } else {
            unset($validated['gambar']);
        }

        $menu->update($validated);
        $menu->addons()->sync($addonIds);

        $this->editingId = null;
        $this->dispatch('modal-close', name: 'edit-menu');
        $this->dispatch('menu-toast', message: __('Menu updated successfully.'));
    }

    public function confirmDelete(int $id): void
    {
        $this->authorizeManage();
        $this->confirmingDeleteId = $id;
    }

    public function delete(): void
    {
        $this->authorizeManage();
        if ($this->confirmingDeleteId) {
            $menu = Menu::find($this->confirmingDeleteId);
            if ($menu && $menu->gambar) {
                Storage::disk('public')->delete($menu->gambar);
            }

            Menu::where('id', $this->confirmingDeleteId)->delete();
            $this->confirmingDeleteId = null;
            $this->dispatch('modal-close', name: 'confirm-delete-menu');
            $this->dispatch('modal-close', name: 'confirm-delete-menu-desktop');
            $this->dispatch('menu-toast', message: __('Menu deleted successfully.'));
        }
    }

    protected function resetCreateForm(): void
    {
        $this->form = [
            'nama_menu'   => '',
            'kategori_id' => null,
            'harga'       => null,
            'status'      => 'tersedia',
            'deskripsi'   => '',
            'addon_ids'   => [],
        ];
    }

    protected function authorizeManage(): void
    {
        abort_unless(auth()->check() && auth()->user()->can('menu.manage'), 403);
    }
}; ?>

<section class="w-full">
    @php
        $query = Menu::query()->with('kategori');

        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('nama_menu', 'like', "%{$search}%")
                  ->orWhere('deskripsi', 'like', "%{$search}%");
            });
        }
        if ($statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }
        if (!empty($kategoriFilter)) {
            $query->where('kategori_id', $kategoriFilter);
        }

        $items = $query->orderBy('nama_menu')->paginate(10);

        $totalCount     = Menu::count();
        $availableCount = Menu::where('status', 'tersedia')->count();
        $outCount       = Menu::where('status', 'habis')->count();
        $kategories     = KategoriMenu::orderBy('nama_kategori')->get();
        $addons         = Addon::orderBy('nama_addon')->get();
        $kategoriCount  = $kategories->count();

        $statusMeta = [
            'all' => [
                'label' => __('All'),
                'count' => $totalCount,
                'dot'   => 'bg-neutral-500',
                'hint'  => __('All menu items'),
            ],
            'tersedia' => [
                'label' => __('Available'),
                'count' => $availableCount,
                'dot'   => 'bg-emerald-500',
                'hint'  => __('Currently can be ordered'),
            ],
            'habis' => [
                'label' => __('Out of stock'),
                'count' => $outCount,
                'dot'   => 'bg-red-500',
                'hint'  => __('Temporarily unavailable'),
            ],
        ];
    @endphp

    <div class="space-y-6">
        <!-- header -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <flux:heading size="xl" level="1">{{ __('Menus') }}</flux:heading>
            <div class="flex flex-wrap items-center gap-2">
                @can('menu.manage')
                    <flux:button icon="plus" variant="primary" class="btn-brand" wire:click="openCreateModal">{{ __('Create') }}</flux:button>
                @endcan
            </div>
        </div>

        <!-- mobile chips -->
        <div class="block sm:hidden -mx-4 overflow-x-auto no-scrollbar">
            <div class="flex gap-2 px-4">
                @foreach($statusMeta as $meta)
                    <div class="whitespace-nowrap rounded-full border border-neutral-200/70 bg-white/85 px-3 py-2 text-[11px] shadow-sm dark:border-neutral-700/60 dark:bg-neutral-900/70">
                        <span class="inline-flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full {{ $meta['dot'] }}"></span>
                            <span class="text-neutral-600 dark:text-neutral-300">{{ $meta['label'] }}</span>
                            <span class="font-semibold text-neutral-900 dark:text-white">{{ $meta['count'] }}</span>
                        </span>
                    </div>
                @endforeach
                <div class="whitespace-nowrap rounded-full border border-neutral-200/70 bg-white/85 px-3 py-2 text-[11px] shadow-sm dark:border-neutral-700/60 dark:bg-neutral-900/70">
                    <span class="inline-flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-sky-500"></span>
                        <span class="text-neutral-600 dark:text-neutral-300">{{ __('Categories') }}</span>
                        <span class="font-semibold text-neutral-900 dark:text-white">{{ $kategoriCount }}</span>
                    </span>
                </div>
            </div>
        </div>

        <!-- summary desktop -->
        <div class="hidden sm:grid gap-2 sm:gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl sm:rounded-2xl border border-neutral-200/70 bg-white/85 p-3 sm:p-4 shadow-sm sm:shadow-lg shadow-neutral-200/40 dark:border-neutral-700/60 dark:bg-neutral-900/70 dark:shadow-black/30">
                <p class="text-[11px] sm:text-xs font-medium uppercase tracking-[0.18em] sm:tracking-[0.2em] text-neutral-400">{{ __('Total Menus') }}</p>
                <div class="mt-2 sm:mt-3 flex items-baseline gap-2">
                    <span class="text-2xl sm:text-3xl font-semibold text-neutral-900 dark:text-white">{{ $totalCount }}</span>
                    <span class="text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('records') }}</span>
                </div>
            </div>
            <div class="rounded-xl sm:rounded-2xl border border-neutral-200/70 bg-white/85 p-3 sm:p-4 shadow-sm sm:shadow-lg shadow-neutral-200/40 dark:border-neutral-700/60 dark:bg-neutral-900/70 dark:shadow-black/30">
                <div class="flex items-center gap-2 text-[11px] sm:text-xs font-medium uppercase tracking-[0.18em] sm:tracking-[0.2em] text-neutral-400">
                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                    <span>{{ __('Available') }}</span>
                </div>
                <div class="mt-2 sm:mt-3 flex items-baseline gap-2">
                    <span class="text-2xl sm:text-3xl font-semibold text-neutral-900 dark:text-white">{{ $availableCount }}</span>
                    <span class="text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('items') }}</span>
                </div>
                <p class="mt-1 text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('Ready to be ordered') }}</p>
            </div>
            <div class="rounded-xl sm:rounded-2xl border border-neutral-200/70 bg-white/85 p-3 sm:p-4 shadow-sm sm:shadow-lg shadow-neutral-200/40 dark:border-neutral-700/60 dark:bg-neutral-900/70 dark:shadow-black/30">
                <div class="flex items-center gap-2 text-[11px] sm:text-xs font-medium uppercase tracking-[0.18em] sm:tracking-[0.2em] text-neutral-400">
                    <span class="h-2 w-2 rounded-full bg-red-500"></span>
                    <span>{{ __('Out of stock') }}</span>
                </div>
                <div class="mt-2 sm:mt-3 flex items-baseline gap-2">
                    <span class="text-2xl sm:text-3xl font-semibold text-neutral-900 dark:text-white">{{ $outCount }}</span>
                    <span class="text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('items') }}</span>
                </div>
                <p class="mt-1 text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('Restock or mark unavailable') }}</p>
            </div>
            <div class="rounded-xl sm:rounded-2xl border border-neutral-200/70 bg-white/85 p-3 sm:p-4 shadow-sm sm:shadow-lg shadow-neutral-200/40 dark:border-neutral-700/60 dark:bg-neutral-900/70 dark:shadow-black/30">
                <p class="text-[11px] sm:text-xs font-medium uppercase tracking-[0.18em] sm:tracking-[0.2em] text-neutral-400">{{ __('Categories') }}</p>
                <div class="mt-2 sm:mt-3 flex items-baseline gap-2">
                    <span class="text-2xl sm:text-3xl font-semibold text-neutral-900 dark:text-white">{{ $kategoriCount }}</span>
                    <span class="text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('linked') }}</span>
                </div>
            </div>
        </div>

        <!-- filters -->
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex-1 min-w-0">
                <flux:input
                    wire:model.live.debounce.800ms="search"
                    :placeholder="__('Search name or description')"
                />
            </div>
            <div class="flex items-center gap-2 min-w-0 sm:justify-end">
                <flux:select
                    wire:model.live="statusFilter"
                    class="flex-1 min-w-0 sm:flex-none sm:w-44 rounded-full border-neutral-200 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900 focus:outline-hidden focus:ring-2 focus:ring-[color:var(--brand-accent)] focus:ring-offset-2 focus:ring-offset-[color:var(--brand-accent-foreground)]"
                >
                    <option value="all">{{ __('All Status') }}</option>
                    <option value="tersedia">{{ __('Available') }}</option>
                    <option value="habis">{{ __('Out of stock') }}</option>
                </flux:select>
                <flux:select
                    wire:model.live="kategoriFilter"
                    class="flex-1 min-w-0 sm:flex-none sm:w-56 rounded-full border-neutral-200 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900 focus:outline-hidden focus:ring-2 focus:ring-[color:var(--brand-accent)] focus:ring-offset-2 focus:ring-offset-[color:var(--brand-accent-foreground)]"
                >
                    <option value="">{{ __('All Categories') }}</option>
                    @foreach($kategories as $kat)
                        <option value="{{ $kat->id }}">{{ $kat->nama_kategori }}</option>
                    @endforeach
                </flux:select>
                <flux:button
                    size="sm"
                    variant="ghost"
                    class="btn-ghost-accent whitespace-nowrap shrink-0"
                    wire:click="$set('search','');$set('statusFilter','all');$set('kategoriFilter', null)"
                >
                    {{ __('Clear') }}
                </flux:button>
            </div>
        </div>

        <!-- mobile cards -->
        <div class="block sm:hidden">
            <div class="grid gap-3">
                @forelse($items as $m)
                    <div class="rounded-2xl border border-neutral-200/80 bg-white p-4 shadow-sm dark:border-neutral-800/70 dark:bg-neutral-900">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-3">
                                @if ($m->gambar)
                                    <img src="{{ Storage::url($m->gambar) }}" alt="img" class="h-14 w-14 rounded object-cover border" />
                                @else
                                    <div class="h-14 w-14 rounded bg-neutral-100 dark:bg-neutral-800"></div>
                                @endif
                                <div>
                                    <div class="text-base font-semibold text-neutral-900 dark:text-white">{{ $m->nama_menu }}</div>
                                    <div class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">
                                        {{ $m->kategori?->nama_kategori ?? __('No category') }}
                                    </div>
                                    <div class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">{{ __('Created at') }} {{ $m->created_at?->format('d M Y') }}</div>
                                </div>
                            </div>
                            <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-[11px] font-semibold
                                {{ $m->status === 'tersedia'
                                    ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100 dark:bg-emerald-900/40 dark:text-emerald-200 dark:ring-emerald-800/60'
                                    : 'bg-red-50 text-red-700 ring-1 ring-red-100 dark:bg-red-900/40 dark:text-red-200 dark:ring-red-800/60' }}">
                                <span class="h-2 w-2 rounded-full {{ $m->status === 'tersedia' ? 'bg-emerald-500' : 'bg-red-500' }}"></span>
                                {{ $m->status === 'tersedia' ? __('Available') : __('Out of stock') }}
                            </span>
                        </div>

                        <div class="mt-3 text-sm text-neutral-700 dark:text-neutral-300">
                            Rp {{ number_format($m->harga, 0, ',', '.') }}
                        </div>

                        @if($m->deskripsi)
                            <div class="mt-2 text-xs text-neutral-500 dark:text-neutral-400">
                                {{ \Illuminate\Support\Str::limit($m->deskripsi, 120) }}
                            </div>
                        @endif

                        <div class="mt-4 flex items-center gap-2">
                            @can('menu.manage')
                                <flux:button size="sm" icon="pencil-square" variant="primary" class="flex-1 btn-accent" wire:click="openEditModal({{ $m->id }})">{{ __('Edit') }}</flux:button>
                                <flux:modal.trigger name="confirm-delete-menu" class="flex-1">
                                    <flux:button size="sm" icon="trash" variant="danger" class="w-full" wire:click="confirmDelete({{ $m->id }})">{{ __('Delete') }}</flux:button>
                                </flux:modal.trigger>
                            @endcan
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-neutral-200/80 bg-white p-6 text-center text-sm text-neutral-500 dark:border-neutral-800/70 dark:bg-neutral-900 dark:text-neutral-400">
                        {{ __('No data') }}
                    </div>
                @endforelse
            </div>
            <div class="mt-4">
                {{ $items->links() }}
            </div>
        </div>

        <!-- desktop table -->
        <div class="hidden sm:block rounded-3xl border border-neutral-200/80 bg-gradient-to-b from-white/95 via-white/90 to-white/70 shadow-2xl shadow-neutral-200/60 backdrop-blur-xl dark:border-neutral-800/80 dark:from-neutral-950/80 dark:via-neutral-950/60 dark:to-neutral-950/40 dark:shadow-black/30">
            <div class="overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm text-left">
                        <thead>
                            <tr>
                                <th class="hidden md:table-cell px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">{{ __('ID') }}</th>
                                <th class="px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">{{ __('Image') }}</th>
                                <th class="px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">{{ __('Name') }}</th>
                                <th class="hidden lg:table-cell px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">{{ __('Category') }}</th>
                                <th class="px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">{{ __('Price') }}</th>
                                <th class="px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">{{ __('Status') }}</th>
                                <th class="px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100/80 text-neutral-700 dark:divide-neutral-900/40 dark:text-neutral-200">
                            @forelse($items as $m)
                                <tr class="group transition hover:bg-white/70 focus-within:bg-white/90 dark:hover:bg-neutral-900/40 dark:focus-within:bg-neutral-900/50">
                                    <td class="hidden md:table-cell px-6 py-4 align-middle">
                                        <span class="inline-flex items-center rounded-full bg-neutral-900/5 px-3 py-1 text-xs font-semibold text-neutral-500 dark:bg-white/5 dark:text-neutral-300">
                                            #{{ str_pad($m->id, 3, '0', STR_PAD_LEFT) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 align-middle">
                                        @if($m->gambar)
                                            <img src="{{ Storage::url($m->gambar) }}" alt="img" class="h-12 w-12 rounded-xl object-cover border border-white/70 dark:border-neutral-800" />
                                        @else
                                            <div class="h-12 w-12 rounded-xl bg-neutral-100 dark:bg-neutral-800"></div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 align-middle">
                                        <div class="flex flex-col gap-1">
                                            <span class="text-base font-semibold text-neutral-900 dark:text-white">{{ $m->nama_menu }}</span>
                                            @if($m->deskripsi)
                                                <span class="hidden sm:block text-xs text-neutral-500 dark:text-neutral-400">{{ \Illuminate\Support\Str::limit($m->deskripsi, 80) }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="hidden lg:table-cell px-6 py-4 align-middle text-neutral-600 dark:text-neutral-300">
                                        {{ $m->kategori?->nama_kategori ?? '—' }}
                                    </td>
                                    <td class="px-6 py-4 align-middle text-neutral-800 dark:text-neutral-100">
                                        Rp {{ number_format($m->harga, 0, ',', '.') }}
                                    </td>
                                    <td class="px-6 py-4 align-middle">
                                        <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold
                                            {{ $m->status === 'tersedia'
                                                ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100 dark:bg-emerald-900/40 dark:text-emerald-200 dark:ring-emerald-800/60'
                                                : 'bg-red-50 text-red-700 ring-1 ring-red-100 dark:bg-red-900/40 dark:text-red-200 dark:ring-red-800/60' }}">
                                            <span class="h-2 w-2 rounded-full {{ $m->status === 'tersedia' ? 'bg-emerald-500' : 'bg-red-500' }}"></span>
                                            {{ $m->status === 'tersedia' ? __('Available') : __('Out of stock') }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 align-middle">
                                        <div class="flex flex-wrap items-center gap-2">
                                            @can('menu.manage')
                                                <flux:button
                                                    size="sm"
                                                    icon="pencil-square"
                                                    variant="primary"
                                                    class="btn-accent rounded-2xl shadow-sm transition"
                                                    wire:click="openEditModal({{ $m->id }})"
                                                >
                                                    {{ __('Edit') }}
                                                </flux:button>
                                                <flux:modal.trigger name="confirm-delete-menu-desktop">
                                                    <flux:button
                                                        size="sm"
                                                        icon="trash"
                                                        variant="danger"
                                                        class="rounded-2xl shadow-sm transition"
                                                        wire:click="confirmDelete({{ $m->id }})"
                                                    >
                                                        {{ __('Delete') }}
                                                    </flux:button>
                                                </flux:modal.trigger>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="px-6 py-8 text-center text-sm text-neutral-500 dark:text-neutral-400" colspan="7">
                                        <div class="flex flex-col items-center gap-3">
                                            <div class="h-12 w-12 rounded-full bg-neutral-100 text-neutral-400 dark:bg-neutral-900/60 dark:text-neutral-500">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-full w-full p-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 4.5h9m-9 6h9m-9 6h9" />
                                                </svg>
                                            </div>
                                            <p>{{ __('No data') }}</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="px-4 py-3">
                {{ $items->links() }}
            </div>
        </div>

        <!-- create menu modal -->
        <flux:modal name="create-menu" focusable class="mx-4 w-[calc(100%-2rem)] sm:mx-auto sm:max-w-4xl md:max-w-3xl lg:max-w-4xl">
            <div class="flex flex-col max-h-[85dvh] overflow-y-auto no-scrollbar md:max-h-none md:overflow-visible">
                <div class="sticky top-0 z-0 -mx-4 flex items-start justify-between gap-2 border-b border-neutral-200 bg-white/85 px-4 py-3 pr-12 backdrop-blur dark:border-neutral-700 dark:bg-neutral-900/70">
                    <div>
                        <flux:heading size="lg">{{ __('Create Menu') }}</flux:heading>
                        <flux:subheading>{{ __('Fill the details below to add a new menu item.') }}</flux:subheading>
                    </div>
                </div>

                <form id="create-menu-form" wire:submit.prevent="save" class="flex-1 space-y-6 px-1 py-4 pb-[calc(env(safe-area-inset-bottom)+0.75rem)] md:pb-0">
                    <div class="grid gap-6 md:grid-cols-5">
                        <div class="space-y-4 md:col-span-3">
                            <flux:input wire:model.defer="form.nama_menu" :label="__('Menu Name')" required maxlength="150" />
                            @error('form.nama_menu')
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
                                    <flux:input wire:model.defer="form.harga" type="number" min="0" step="100" inputmode="numeric" placeholder="0" :label="__('Price (IDR)')" required />
                                    @error('form.harga')
                                        <p class="text-xs text-red-500 sm:col-span-2">{{ $message }}</p>
                                    @enderror

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
                        </div>

                        <div class="space-y-4 md:col-span-2 md:pl-2">
                            <div class="rounded-2xl border border-neutral-200/70 bg-white p-3 dark:border-neutral-700 dark:bg-neutral-900">
                                <flux:heading size="sm">{{ __('Image') }}</flux:heading>
                                <input type="file" wire:model="gambar" accept="image/*" class="mt-2 block w-full text-sm" />
                                @error('gambar')
                                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                @enderror
                                @if ($gambar)
                                    <img src="{{ $gambar->temporaryUrl() }}" alt="preview" class="mt-2 h-24 w-24 rounded object-cover border" />
                                @endif
                            </div>
                            <div class="rounded-2xl border border-neutral-200/70 bg-white p-3 dark:border-neutral-700 dark:bg-neutral-900">
                                <flux:heading size="sm">{{ __('Tips') }}</flux:heading>
                                <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                                    {{ __('Menus will appear on customer ordering page based on category and availability.') }}
                                </p>
                            </div>
                            <div class="hidden md:flex items-center justify-end gap-3 pt-2">
                                <flux:modal.close>
                                    <flux:button type="button" variant="ghost" class="btn-ghost-accent">{{ __('Cancel') }}</flux:button>
                                </flux:modal.close>
                                <flux:button type="submit" form="create-menu-form" variant="primary" icon="plus" class="btn-brand">{{ __('Create') }}</flux:button>
                            </div>
                        </div>
                    </div>
                </form>

                <div class="sticky bottom-0 z-10 -mx-4 md:hidden border-t border-neutral-200 bg-white/90 px-4 py-3 pb-[max(env(safe-area-inset-bottom),0.75rem)] backdrop-blur dark:border-neutral-700 dark:bg-neutral-900/80">
                    <div class="grid grid-cols-2 gap-2">
                        <flux:modal.close>
                            <flux:button type="button" variant="ghost" class="btn-ghost-accent w-full">{{ __('Cancel') }}</flux:button>
                        </flux:modal.close>
                        <flux:button type="submit" form="create-menu-form" variant="primary" icon="plus" class="btn-brand w-full">{{ __('Create') }}</flux:button>
                    </div>
                </div>
            </div>
        </flux:modal>

        <!-- edit menu modal -->
        <flux:modal name="edit-menu" focusable class="mx-4 w-[calc(100%-2rem)] sm:mx-auto sm:max-w-4xl md:max-w-3xl lg:max-w-4xl">
            <div class="flex flex-col max-h-[85dvh] overflow-y-auto no-scrollbar md:max-h-none md:overflow-visible">
                <div class="sticky top-0 z-0 -mx-4 flex items-start justify-between gap-2 border-b border-neutral-200 bg-white/85 px-4 py-3 pr-12 backdrop-blur dark:border-neutral-700 dark:bg-neutral-900/70">
                    <div>
                        <flux:heading size="lg">{{ __('Edit Menu') }}</flux:heading>
                        <flux:subheading>{{ __('Update the details for this menu item.') }}</flux:subheading>
                    </div>
                </div>

                <form id="edit-menu-form" wire:submit.prevent="update" class="flex-1 space-y-6 px-1 py-4 pb-[calc(env(safe-area-inset-bottom)+0.75rem)] md:pb-0">
                    <div class="grid gap-6 md:grid-cols-5">
                        <div class="space-y-4 md:col-span-3">
                            <flux:input wire:model.defer="form.nama_menu" :label="__('Menu Name')" required maxlength="150" />
                            @error('form.nama_menu')
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
                                <flux:input wire:model.defer="form.harga" type="number" min="0" step="100" inputmode="numeric" placeholder="0" :label="__('Price (IDR)')" required />
                                @error('form.harga')
                                    <p class="text-xs text-red-500 sm:col-span-2">{{ $message }}</p>
                                @enderror

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
                                <flux:textarea wire:model.defer="form.deskripsi" rows="4" :label="__('Description')" placeholder="{{ __('Optional') }}"></flux:textarea>
                                @error('form.deskripsi')
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
                                @php($editingMenu = $editingId ? \App\Models\Menu::find($editingId) : null)
                                <div class="mt-2">
                                    @if ($gambar)
                                        <img src="{{ $gambar->temporaryUrl() }}" alt="preview" class="h-24 w-24 rounded object-cover border" />
                                    @elseif ($editingMenu?->gambar)
                                        <img src="{{ Storage::url($editingMenu->gambar) }}" alt="current" class="h-24 w-24 rounded object-cover border" />
                                    @endif
                                </div>
                            </div>
                            <div class="rounded-2xl border border-neutral-200/70 bg-white p-3 dark:border-neutral-700 dark:bg-neutral-900">
                                <flux:heading size="sm">{{ __('Info') }}</flux:heading>
                                <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                                    {{ __('Updating this menu will reflect on customer ordering page.') }}
                                </p>
                            </div>
                            <div class="hidden md:flex items-center justify-end gap-3 pt-2">
                                <flux:modal.close>
                                    <flux:button type="button" variant="ghost" class="btn-ghost-accent">{{ __('Cancel') }}</flux:button>
                                </flux:modal.close>
                                <flux:button type="submit" form="edit-menu-form" variant="primary" icon="check" class="btn-brand">{{ __('Update') }}</flux:button>
                            </div>
                        </div>
                    </div>
                </form>

                <div class="sticky bottom-0 z-10 -mx-4 md:hidden border-t border-neutral-200 bg-white/90 px-4 py-3 pb-[max(env(safe-area-inset-bottom),0.75rem)] backdrop-blur dark:border-neutral-700 dark:bg-neutral-900/80">
                    <div class="grid grid-cols-2 gap-2">
                        <flux:modal.close>
                            <flux:button type="button" variant="ghost" class="btn-ghost-accent w-full">{{ __('Cancel') }}</flux:button>
                        </flux:modal.close>
                        <flux:button type="submit" form="edit-menu-form" variant="primary" icon="check" class="btn-brand w-full">{{ __('Update') }}</flux:button>
                    </div>
                </div>
            </div>
        </flux:modal>

        @php($selectedMenu = $items->firstWhere('id', $confirmingDeleteId))

        <!-- delete menu mobile -->
        <flux:modal name="confirm-delete-menu" focusable variant="flyout" position="bottom" class="rounded-t-3xl sm:rounded-xl">
            <div class="space-y-4 p-2 max-h-[60dvh] overflow-y-auto">
                <div class="flex items-center justify-center">
                    <div class="mx-auto mb-1 h-1.5 w-12 rounded-full bg-neutral-300 dark:bg-neutral-700"></div>
                </div>

                <div class="space-y-2">
                    <flux:heading size="lg">{{ __('Delete this menu?') }}</flux:heading>
                    <flux:subheading>
                        {{ __('This action cannot be undone. This record will be permanently deleted.') }}
                    </flux:subheading>
                </div>

                @if ($selectedMenu)
                    <div class="rounded-2xl border border-red-200/60 bg-red-50/60 p-4 text-sm text-red-800 dark:border-red-800/60 dark:bg-red-900/20 dark:text-red-200">
                        <p class="font-semibold">{{ $selectedMenu->nama_menu }}</p>
                        <p class="text-xs opacity-80">{{ $selectedMenu->kategori?->nama_kategori }}</p>
                    </div>
                @endif

                <div class="sticky bottom-0 -mx-2 mt-2 flex items-center justify-end gap-2 border-t border-neutral-200 bg-white/85 px-2 py-2 pb-[max(env(safe-area-inset-bottom),0.75rem)] backdrop-blur dark:border-neutral-700 dark:bg-neutral-900/70">
                    <flux:modal.close>
                        <flux:button variant="filled" wire:click="$set('confirmingDeleteId', null)">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button variant="danger" wire:click="delete">
                        {{ __('Yes, delete') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>

        <!-- delete menu desktop -->
        <flux:modal name="confirm-delete-menu-desktop" focusable class="mx-4 max-w-full sm:mx-auto sm:max-w-lg">
            <div class="space-y-4 p-2">
                <div class="space-y-2">
                    <flux:heading size="lg">{{ __('Delete this menu?') }}</flux:heading>
                    <flux:subheading>
                        {{ __('This action cannot be undone. This record will be permanently deleted.') }}
                    </flux:subheading>
                </div>

                @if ($selectedMenu)
                    <div class="rounded-2xl border border-red-200/60 bg-red-50/60 p-4 text-sm text-red-800 dark:border-red-800/60 dark:bg-red-900/20 dark:text-red-200">
                        <p class="font-semibold">{{ $selectedMenu->nama_menu }}</p>
                        <p class="text-xs opacity-80">{{ $selectedMenu->kategori?->nama_kategori }}</p>
                    </div>
                @endif

                <div class="mt-2 flex items-center justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="filled" wire:click="$set('confirmingDeleteId', null)">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button variant="danger" wire:click="delete">
                        {{ __('Yes, delete') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>

        <!-- toast -->
        <div
            x-data="{
                show: false,
                message: '',
                timeout: null,
                handle(event) {
                    this.message = event.detail?.message || '{{ __('Menu deleted successfully.') }}';
                    this.show = true;
                    clearTimeout(this.timeout);
                    this.timeout = setTimeout(() => this.show = false, 3500);
                }
            }"
            x-on:menu-toast.window="handle($event)"
            class="pointer-events-none fixed inset-x-0 top-6 flex justify-center px-4"
        >
            <div
                x-show="show"
                x-transition:enter="transform ease-out duration-200"
                x-transition:enter-start="-translate-y-3 opacity-0"
                x-transition:enter-end="translate-y-0 opacity-100"
                x-transition:leave="transform ease-in duration-200"
                x-transition:leave-start="translate-y-0 opacity-100"
                x-transition:leave-end="-translate-y-3 opacity-0"
                class="pointer-events-auto rounded-2xl toast-brand px-4 py-3 text-sm"
            >
                <div class="flex items-center gap-2">
                    <flux:icon icon="check-circle" />
                    <span x-text="message"></span>
                </div>
            </div>
        </div>
    </div>
</section>
