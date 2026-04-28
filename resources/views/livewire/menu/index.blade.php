<?php

use App\Models\Menu;
use App\Models\KategoriMenu;
use App\Services\MenuImageService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public ?int $confirmingDeleteId = null;
    public string $search = '';
    public string $statusFilter = 'all';
    public ?int $kategoriFilter = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => 'all'],
        'kategoriFilter' => ['except' => null],
        'page' => ['except' => 1],
    ];

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingStatusFilter(): void { $this->resetPage(); }
    public function updatingKategoriFilter(): void { $this->resetPage(); }

    public function confirmDelete(int $id): void
    {
        $this->authorizeManage();
        $this->confirmingDeleteId = $id;
    }

    public function toggleStatus(int $id): void
    {
        $this->authorizeManage();

        $menu = Menu::query()->select(['id', 'status'])->findOrFail($id);
        $nextStatus = $menu->status === 'tersedia' ? 'habis' : 'tersedia';

        $menu->forceFill(['status' => $nextStatus])->save();

        Cache::forget('customer:menus_available:v1');
        Cache::forget('customer:menus_orderable_display:v1');
        Cache::forever('customer:menu_version', ((int) Cache::get('customer:menu_version', 1)) + 1);
        Cache::forget('admin:menu:stats:v1');

        $this->dispatch('menu-toast', message: $nextStatus === 'habis'
            ? __('Menu marked as out of stock.')
            : __('Menu marked as available.'));
    }

    public function delete(): void
    {
        $this->authorizeManage();
        if ($this->confirmingDeleteId) {
            $isUsedInOrders = \App\Models\PesananDetail::query()
                ->where('menu_id', $this->confirmingDeleteId)
                ->exists();

            if ($isUsedInOrders) {
                $this->dispatch('modal-close', name: 'confirm-delete-menu');
                $this->dispatch('modal-close', name: 'confirm-delete-menu-desktop');
                $this->dispatch('menu-toast', message: __('This menu cannot be deleted because it is already used in orders.'));
                $this->confirmingDeleteId = null;
                return;
            }

            $menu = Menu::query()->select(['id', 'gambar'])->find($this->confirmingDeleteId);
            if ($menu && $menu->gambar) {
                Storage::disk('public')->delete($menu->gambar);
                MenuImageService::deleteThumbnails($menu->gambar);
            }

            Menu::where('id', $this->confirmingDeleteId)->delete();
            Cache::forget('customer:menus_available:v1');
            Cache::forget('customer:menus_orderable_display:v1');
            Cache::forever('customer:menu_version', ((int) Cache::get('customer:menu_version', 1)) + 1);
            Cache::forget('admin:menu:stats:v1');
            $this->confirmingDeleteId = null;
            $this->dispatch('modal-close', name: 'confirm-delete-menu');
            $this->dispatch('modal-close', name: 'confirm-delete-menu-desktop');
            $this->dispatch('menu-toast', message: __('Menu deleted successfully.'));
        }
    }

    protected function authorizeManage(): void
    {
        abort_unless(auth()->check() && auth()->user()->can('menu.manage'), 403);
    }
}; ?>

<section class="w-full">
    @php
        $query = Menu::query()
            ->select([
                'id',
                'kategori_id',
                'nama_menu',
                'harga',
                'status',
                'deskripsi',
                'gambar',
                'created_at',
                'updated_at',
            ])
            ->with([
                'kategori' => fn ($q) => $q->select(['id', 'nama_kategori']),
            ]);

        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('nama_menu', 'like', "%{$search}%")
                  ->orWhere('nama_menu_en', 'like', "%{$search}%")
                  ->orWhere('deskripsi', 'like', "%{$search}%")
                  ->orWhere('deskripsi_en', 'like', "%{$search}%");
            });
        }
        if ($statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }
        if (!empty($kategoriFilter)) {
            $query->where('kategori_id', $kategoriFilter);
        }

        $items = $query->orderBy('nama_menu')->paginate(10);

        $imageMeta = function (?string $img, int $sizePx): ?array {
            $img = trim((string) $img);
            if ($img === '') {
                return null;
            }

            $isExternal = \Illuminate\Support\Str::startsWith($img, ['http://', 'https://', '/']);
            $src = $isExternal ? $img : \Illuminate\Support\Facades\Storage::url($img);

            if ($isExternal) {
                return ['src' => $src, 'srcset' => null, 'sizes' => null, 'size' => $sizePx];
            }

            $thumbs = MenuImageService::thumbnailPaths($img, [160, 320, 480, 640]);
            $thumb160 = \Illuminate\Support\Facades\Storage::url($thumbs[160] ?? '');
            $thumb320 = \Illuminate\Support\Facades\Storage::url($thumbs[320] ?? '');
            $thumb480 = \Illuminate\Support\Facades\Storage::url($thumbs[480] ?? '');
            $thumb640 = \Illuminate\Support\Facades\Storage::url($thumbs[640] ?? '');

            return [
                'src' => $src,
                'srcset' => trim($thumb160 . ' 160w, ' . $thumb320 . ' 320w, ' . $thumb480 . ' 480w, ' . $thumb640 . ' 640w'),
                'sizes' => $sizePx . 'px',
                'size' => $sizePx,
            ];
        };

        $stats = Cache::remember('admin:menu:stats:v1', 10, fn () => [
            'total'     => Menu::query()->count(),
            'available' => Menu::query()->where('status', 'tersedia')->count(),
            'out'       => Menu::query()->where('status', 'habis')->count(),
        ]);

        $totalCount     = (int) ($stats['total'] ?? 0);
        $availableCount = (int) ($stats['available'] ?? 0);
        $outCount       = (int) ($stats['out'] ?? 0);
        $kategories     = KategoriMenu::query()->select(['id', 'nama_kategori'])->orderBy('nama_kategori')->get();
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
                    <flux:link :href="route('menu.create', [], false)" wire:navigate>
                        <flux:button icon="plus" variant="primary" class="btn-brand">{{ __('Create') }}</flux:button>
                    </flux:link>
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
                    wire:click="$wire.set('search','');$wire.set('statusFilter','all');$wire.set('kategoriFilter', null)"
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
                                        @php($im = $imageMeta($m->gambar, 56))
                                        <img
                                            src="{{ $im['src'] }}"
                                            @if (!empty($im['srcset']))
                                                srcset="{{ $im['srcset'] }}"
                                                sizes="{{ $im['sizes'] }}"
                                            @endif
                                            alt="{{ $m->nama_menu }}"
                                            class="h-14 w-14 rounded object-cover border"
                                            width="56"
                                            height="56"
                                            loading="lazy"
                                            decoding="async"
                                            fetchpriority="low"
                                        />
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
                                <flux:button
                                    size="sm"
                                    icon="{{ $m->status === 'tersedia' ? 'pause-circle' : 'check-circle' }}"
                                    variant="ghost"
                                    class="btn-ghost-accent flex-1 rounded-2xl shadow-sm transition whitespace-nowrap justify-center"
                                    wire:click="toggleStatus({{ $m->id }})"
                                >
                                    {{ $m->status === 'tersedia' ? __('Out of stock') : __('Available') }}
                                </flux:button>
                                <flux:link class="flex-1" :href="route('menu.edit', $m, false)" wire:navigate>
                                    <flux:button size="sm" icon="pencil-square" variant="primary" class="btn-accent w-full rounded-2xl shadow-sm transition whitespace-nowrap justify-center">{{ __('Edit') }}</flux:button>
                                </flux:link>
                            @endcan
                        </div>

                        <div class="mt-2 flex items-center gap-2">
                            @can('menu.manage')
                                <flux:modal.trigger name="confirm-delete-menu" class="w-full">
                                    <flux:button size="sm" icon="trash" variant="danger" class="w-full rounded-2xl shadow-sm transition whitespace-nowrap justify-center" wire:click="confirmDelete({{ $m->id }})">{{ __('Delete') }}</flux:button>
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

        <!-- Tablet cards -->
        <div class="hidden sm:block lg:hidden">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                @forelse($items as $m)
                    <div class="flex h-full flex-col rounded-2xl border border-neutral-200/80 bg-white p-4 shadow-sm dark:border-neutral-800/70 dark:bg-neutral-900">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex min-w-0 items-center gap-3">
                                @if ($m->gambar)
                                    @php($im = $imageMeta($m->gambar, 48))
                                    <img
                                        src="{{ $im['src'] }}"
                                        @if (!empty($im['srcset']))
                                            srcset="{{ $im['srcset'] }}"
                                            sizes="{{ $im['sizes'] }}"
                                        @endif
                                        alt="{{ $m->nama_menu }}"
                                        class="h-12 w-12 rounded-xl object-cover border border-white/70 dark:border-neutral-800"
                                        width="48"
                                        height="48"
                                        loading="lazy"
                                        decoding="async"
                                        fetchpriority="low"
                                    />
                                @else
                                    <div class="h-12 w-12 rounded-xl bg-neutral-100 dark:bg-neutral-800"></div>
                                @endif
                                <div class="min-w-0">
                                    <div class="truncate text-base font-semibold text-neutral-900 dark:text-white">{{ $m->nama_menu }}</div>
                                    <div class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">
                                        {{ $m->kategori?->nama_kategori ?? __('No category') }}
                                    </div>
                                    <div class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">{{ __('Created at') }} {{ $m->created_at?->format('d M Y') }}</div>
                                </div>
                            </div>
                            <span class="shrink-0 inline-flex items-center gap-2 rounded-full px-3 py-1 text-[11px] font-semibold
                                {{ $m->status === 'tersedia'
                                    ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100 dark:bg-emerald-900/40 dark:text-emerald-200 dark:ring-emerald-800/60'
                                    : 'bg-red-50 text-red-700 ring-1 ring-red-100 dark:bg-red-900/40 dark:text-red-200 dark:ring-red-800/60' }}">
                                <span class="h-2 w-2 rounded-full {{ $m->status === 'tersedia' ? 'bg-emerald-500' : 'bg-red-500' }}"></span>
                                {{ $m->status === 'tersedia' ? __('Available') : __('Out of stock') }}
                            </span>
                        </div>

                        <div class="mt-3 text-right text-sm font-semibold text-neutral-900 dark:text-white">
                            Rp {{ number_format($m->harga, 0, ',', '.') }}
                        </div>

                        @if($m->deskripsi)
                            <div class="mt-2 text-xs text-neutral-500 dark:text-neutral-400">
                                {{ \Illuminate\Support\Str::limit($m->deskripsi, 140) }}
                            </div>
                        @endif

                        <div class="mt-4 grid grid-cols-2 gap-2">
                            @can('menu.manage')
                                <flux:button
                                    size="sm"
                                    icon="{{ $m->status === 'tersedia' ? 'pause-circle' : 'check-circle' }}"
                                    variant="ghost"
                                    class="btn-ghost-accent w-full rounded-2xl shadow-sm transition whitespace-nowrap justify-center"
                                    wire:click="toggleStatus({{ $m->id }})"
                                >
                                    {{ $m->status === 'tersedia' ? __('Out of stock') : __('Available') }}
                                </flux:button>
                                <flux:link :href="route('menu.edit', $m, false)" wire:navigate>
                                    <flux:button size="sm" icon="pencil-square" variant="primary" class="btn-accent w-full rounded-2xl shadow-sm transition whitespace-nowrap justify-center">{{ __('Edit') }}</flux:button>
                                </flux:link>
                            @endcan
                        </div>

                        <div class="mt-2">
                            @can('menu.manage')
                                <flux:modal.trigger name="confirm-delete-menu-desktop">
                                    <flux:button size="sm" icon="trash" variant="danger" class="w-full rounded-2xl shadow-sm transition whitespace-nowrap justify-center" wire:click="confirmDelete({{ $m->id }})">{{ __('Delete') }}</flux:button>
                                </flux:modal.trigger>
                            @endcan
                        </div>
                    </div>
                @empty
                    <div class="sm:col-span-2 rounded-2xl border border-neutral-200/80 bg-white p-6 text-center text-sm text-neutral-500 dark:border-neutral-800/70 dark:bg-neutral-900 dark:text-neutral-400">
                        {{ __('No data') }}
                    </div>
                @endforelse
            </div>
            <div class="mt-4">
                {{ $items->links() }}
            </div>
        </div>

        <!-- desktop table -->
        <div class="hidden lg:block rounded-3xl border border-neutral-200/80 bg-gradient-to-b from-white/95 via-white/90 to-white/70 shadow-2xl shadow-neutral-200/60 backdrop-blur-xl dark:border-neutral-800/80 dark:from-neutral-950/80 dark:via-neutral-950/60 dark:to-neutral-950/40 dark:shadow-black/30">
            <div class="overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full table-fixed text-sm">
                        <thead>
                            <tr>
                                <th class="hidden sm:table-cell border-b border-r border-neutral-200/80 px-6 py-4 text-center text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:border-neutral-800/70 dark:text-neutral-400">{{ __('ID') }}</th>
                                <th class="border-b border-r border-neutral-200/80 px-6 py-4 text-center text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:border-neutral-800/70 dark:text-neutral-400">{{ __('Image') }}</th>
                                <th class="border-b border-r border-neutral-200/80 px-6 py-4 text-center text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:border-neutral-800/70 dark:text-neutral-400">{{ __('Name') }}</th>
                                <th class="hidden lg:table-cell border-b border-r border-neutral-200/80 px-6 py-4 text-center text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:border-neutral-800/70 dark:text-neutral-400">{{ __('Category') }}</th>
                                <th class="border-b border-r border-neutral-200/80 px-6 py-4 text-center text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:border-neutral-800/70 dark:text-neutral-400">{{ __('Price') }}</th>
                                <th class="border-b border-r border-neutral-200/80 px-6 py-4 text-center text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:border-neutral-800/70 dark:text-neutral-400">{{ __('Status') }}</th>
                                <th class="md:min-w-[280px] lg:min-w-0 border-b border-neutral-200/80 px-6 py-4 text-center text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:border-neutral-800/70 dark:text-neutral-400">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100/80 text-neutral-700 dark:divide-neutral-900/40 dark:text-neutral-200">
                            @forelse($items as $m)
                                <tr class="group transition hover:bg-white/70 focus-within:bg-white/90 dark:hover:bg-neutral-900/40 dark:focus-within:bg-neutral-900/50">
                                    <td class="hidden sm:table-cell border-r border-neutral-200/80 px-6 py-4 align-middle text-center dark:border-neutral-800/70">
                                        <span class="inline-flex items-center rounded-full bg-neutral-900/5 px-3 py-1 text-xs font-semibold text-neutral-500 dark:bg-white/5 dark:text-neutral-300">
                                            #{{ str_pad($m->id, 3, '0', STR_PAD_LEFT) }}
                                        </span>
                                    </td>
                                    <td class="border-r border-neutral-200/80 px-6 py-4 align-middle text-center dark:border-neutral-800/70">
                                        <div class="flex justify-center">
                                            @if($m->gambar)
                                                @php($im = $imageMeta($m->gambar, 48))
                                                <img
                                                    src="{{ $im['src'] }}"
                                                    @if (!empty($im['srcset']))
                                                        srcset="{{ $im['srcset'] }}"
                                                        sizes="{{ $im['sizes'] }}"
                                                    @endif
                                                    alt="{{ $m->nama_menu }}"
                                                    class="h-12 w-12 rounded-xl object-cover border border-white/70 dark:border-neutral-800"
                                                    width="48"
                                                    height="48"
                                                    loading="lazy"
                                                    decoding="async"
                                                    fetchpriority="low"
                                                />
                                            @else
                                                <div class="h-12 w-12 rounded-xl bg-neutral-100 dark:bg-neutral-800"></div>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="border-r border-neutral-200/80 px-6 py-4 align-middle text-center dark:border-neutral-800/70">
                                        <div class="flex flex-col items-center gap-1">
                                            <span class="text-base font-semibold text-neutral-900 dark:text-white">{{ $m->nama_menu }}</span>
                                            @if($m->deskripsi)
                                                <span class="hidden sm:block text-xs text-neutral-500 dark:text-neutral-400">{{ \Illuminate\Support\Str::limit($m->deskripsi, 80) }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="hidden lg:table-cell border-r border-neutral-200/80 px-6 py-4 align-middle text-center text-neutral-600 dark:border-neutral-800/70 dark:text-neutral-300">
                                        {{ $m->kategori?->nama_kategori ?? '—' }}
                                    </td>
                                    <td class="border-r border-neutral-200/80 px-6 py-4 align-middle text-center text-neutral-800 dark:border-neutral-800/70 dark:text-neutral-100">
                                        Rp {{ number_format($m->harga, 0, ',', '.') }}
                                    </td>
                                    <td class="border-r border-neutral-200/80 px-6 py-4 align-middle text-center dark:border-neutral-800/70">
                                        <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold
                                            {{ $m->status === 'tersedia'
                                                ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100 dark:bg-emerald-900/40 dark:text-emerald-200 dark:ring-emerald-800/60'
                                                : 'bg-red-50 text-red-700 ring-1 ring-red-100 dark:bg-red-900/40 dark:text-red-200 dark:ring-red-800/60' }}">
                                            <span class="h-2 w-2 rounded-full {{ $m->status === 'tersedia' ? 'bg-emerald-500' : 'bg-red-500' }}"></span>
                                            {{ $m->status === 'tersedia' ? __('Available') : __('Out of stock') }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 align-middle text-center">
                                        <div class="mx-auto grid w-full max-w-[420px] grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                            @can('menu.manage')
                                                <div class="contents">
                                                    <flux:button
                                                        size="sm"
                                                        icon="{{ $m->status === 'tersedia' ? 'pause-circle' : 'check-circle' }}"
                                                        variant="ghost"
                                                        class="btn-ghost-accent w-full rounded-2xl shadow-sm transition whitespace-nowrap justify-center"
                                                        wire:click="toggleStatus({{ $m->id }})"
                                                        title="{{ $m->status === 'tersedia' ? __('Out of stock') : __('Available') }}"
                                                    >
                                                        {{ $m->status === 'tersedia' ? __('Out of stock') : __('Available') }}
                                                    </flux:button>
                                                    <flux:link :href="route('menu.edit', $m, false)" wire:navigate>
                                                        <flux:button
                                                            size="sm"
                                                            icon="pencil-square"
                                                            variant="primary"
                                                            class="btn-accent w-full rounded-2xl shadow-sm transition whitespace-nowrap justify-center"
                                                            title="{{ __('Edit') }}"
                                                        >
                                                            {{ __('Edit') }}
                                                        </flux:button>
                                                    </flux:link>
                                                    <flux:modal.trigger name="confirm-delete-menu-desktop">
                                                        <flux:button
                                                            size="sm"
                                                            icon="trash"
                                                            variant="danger"
                                                            class="w-full rounded-2xl shadow-sm transition whitespace-nowrap justify-center sm:col-span-2 lg:col-span-1"
                                                            wire:click="confirmDelete({{ $m->id }})"
                                                            title="{{ __('Delete') }}"
                                                        >
                                                            {{ __('Delete') }}
                                                        </flux:button>
                                                    </flux:modal.trigger>
                                                </div>
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

        @php($selectedMenu = $items->firstWhere('id', $confirmingDeleteId))

        <!-- delete menu mobile -->
        <flux:modal name="confirm-delete-menu" focusable variant="flyout" position="bottom" :closable="false" class="rounded-t-3xl sm:rounded-xl">
            <div class="space-y-4 p-2 max-h-[60dvh] overflow-y-auto">
                <div class="flex items-center justify-center">
                    <div class="mx-auto mb-1 h-1.5 w-12 rounded-full bg-neutral-300 dark:bg-neutral-700"></div>
                </div>

                <div class="space-y-2">
                    <flux:heading size="lg">{{ __('Delete this menu?') }}</flux:heading>
                    <flux:subheading>
                        {{ __('This action cannot be undone. Menus that are already used in orders cannot be deleted.') }}
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
                        <flux:button variant="filled" wire:click="$wire.set('confirmingDeleteId', null)">{{ __('Cancel') }}</flux:button>
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
                        {{ __('This action cannot be undone. Menus that are already used in orders cannot be deleted.') }}
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
                        <flux:button variant="filled" wire:click="$wire.set('confirmingDeleteId', null)">{{ __('Cancel') }}</flux:button>
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
