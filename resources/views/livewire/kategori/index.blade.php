<?php

use App\Models\KategoriMenu;
use App\Models\Menu;
use Illuminate\Support\Facades\Cache;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public ?int $confirmingDeleteId = null;
    public string $search = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'page'   => ['except' => 1],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function confirmDelete(int $id): void
    {
        $this->authorizeManage();
        $this->confirmingDeleteId = $id;
    }

    public function toggleActive(int $id): void
    {
        $this->authorizeManage();

        $kategori = KategoriMenu::query()->findOrFail($id);
        $kategori->forceFill(['is_active' => !$kategori->is_active])->save();

        Cache::forget('customer:categories:v1');
        Cache::forget('customer:menus_available:v1');
        Cache::forget('admin:kategori:stats:v1');

        $this->dispatch('kategori-toast', message: $kategori->is_active
            ? __('Category activated successfully.')
            : __('Category deactivated successfully.'));
    }

    public function delete(): void
    {
        $this->authorizeManage();
        if ($this->confirmingDeleteId) {
            $isUsedByMenus = \App\Models\Menu::query()
                ->where('kategori_id', $this->confirmingDeleteId)
                ->exists();

            if ($isUsedByMenus) {
                $this->dispatch('modal-close', name: 'confirm-delete-kategori');
                $this->dispatch('modal-close', name: 'confirm-delete-kategori-desktop');
                $this->dispatch('kategori-toast', message: __('This category cannot be deleted because it is still used by menus.'));
                $this->confirmingDeleteId = null;
                return;
            }

            KategoriMenu::where('id', $this->confirmingDeleteId)->delete();
            Cache::forget('customer:categories:v1');
            Cache::forget('customer:menus_available:v1');
            Cache::forget('admin:kategori:stats:v1');
            $this->confirmingDeleteId = null;
            $this->dispatch('modal-close', name: 'confirm-delete-kategori');
            $this->dispatch('modal-close', name: 'confirm-delete-kategori-desktop');
            $this->dispatch('kategori-toast', message: __('Category deleted successfully.'));
        }
    }

    protected function authorizeManage(): void
    {
        abort_unless(auth()->check() && auth()->user()->can('kategori.manage'), 403);
    }
}; ?>

<section class="w-full">
    @php
        $query = KategoriMenu::query()->select([
            'id',
            'nama_kategori',
            'nama_kategori_en',
            'is_active',
            'created_at',
        ]);

        if (!empty($search)) {
            $query->where(function ($sub) use ($search) {
                $sub
                    ->where('nama_kategori', 'like', '%'.$search.'%')
                    ->orWhere('nama_kategori_en', 'like', '%'.$search.'%');
            });
        }

        $items        = $query->orderBy('nama_kategori')->paginate(10);
        $stats = Cache::remember('admin:kategori:stats:v1', 10, fn () => [
            'total' => KategoriMenu::query()->count(),
            'active' => KategoriMenu::query()->where('is_active', true)->count(),
            'inactive' => KategoriMenu::query()->where('is_active', false)->count(),
            'linked' => Menu::query()->distinct('kategori_id')->count('kategori_id'),
        ]);
        $totalCount = (int) ($stats['total'] ?? 0);
        $activeCount = (int) ($stats['active'] ?? 0);
        $inactiveCount = (int) ($stats['inactive'] ?? 0);
        $linkedCount = (int) ($stats['linked'] ?? 0);
        $summaryMeta = [
            [
                'label' => __('Categories'),
                'count' => $totalCount,
                'dot'   => 'bg-neutral-500',
                'hint'  => __('All category records'),
            ],
            [
                'label' => __('Active'),
                'count' => $activeCount,
                'dot'   => 'bg-emerald-500',
                'hint'  => __('Shown in ordering flow'),
            ],
            [
                'label' => __('Inactive'),
                'count' => $inactiveCount,
                'dot'   => 'bg-amber-500',
                'hint'  => __('Hidden from ordering flow'),
            ],
            [
                'label' => __('Linked'),
                'count' => $linkedCount,
                'dot'   => 'bg-sky-500',
                'hint'  => __('Already used by menus'),
            ],
        ];
    @endphp

    <div class="space-y-6">
        <!-- Heading & actions -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <flux:heading size="xl" level="1">{{ __('Categories') }}</flux:heading>
            <div class="flex flex-wrap items-center gap-2">
                @can('kategori.manage')
                    <flux:link :href="route('kategori.create', [], false)" wire:navigate>
                        <flux:button icon="plus" variant="primary" class="btn-brand">{{ __('Create') }}</flux:button>
                    </flux:link>
                @endcan
            </div>
        </div>

        <!-- Mobile chips (match meja) -->
        <div class="block sm:hidden -mx-4 overflow-x-auto no-scrollbar">
            <div class="flex gap-2 px-4">
                @foreach($summaryMeta as $meta)
                    <div class="whitespace-nowrap rounded-full border border-neutral-200/70 bg-white/85 px-3 py-2 text-[11px] shadow-sm dark:border-neutral-700/60 dark:bg-neutral-900/70">
                        <span class="inline-flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full {{ $meta['dot'] }}"></span>
                            <span class="text-neutral-600 dark:text-neutral-300">{{ $meta['label'] }}</span>
                            <span class="font-semibold text-neutral-900 dark:text-white">{{ $meta['count'] }}</span>
                        </span>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Desktop summary cards (match meja) -->
        <div class="hidden sm:grid gap-2 sm:gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl sm:rounded-2xl border border-neutral-200/70 bg-white/85 p-3 sm:p-4 shadow-sm sm:shadow-lg shadow-neutral-200/40 dark:border-neutral-700/60 dark:bg-neutral-900/70 dark:shadow-black/30">
                <p class="text-[11px] sm:text-xs font-medium uppercase tracking-[0.18em] sm:tracking-[0.2em] text-neutral-400">{{ __('Total Categories') }}</p>
                <div class="mt-2 sm:mt-3 flex items-baseline gap-2">
                    <span class="text-2xl sm:text-3xl font-semibold text-neutral-900 dark:text-white">{{ $totalCount }}</span>
                    <span class="text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('records') }}</span>
                </div>
            </div>
            <div class="rounded-xl sm:rounded-2xl border border-neutral-200/70 bg-white/85 p-3 sm:p-4 shadow-sm sm:shadow-lg shadow-neutral-200/40 dark:border-neutral-700/60 dark:bg-neutral-900/70 dark:shadow-black/30">
                <div class="flex items-center gap-2 text-[11px] sm:text-xs font-medium uppercase tracking-[0.18em] sm:tracking-[0.2em] text-neutral-400">
                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                    <span>{{ __('Active') }}</span>
                </div>
                <div class="mt-2 sm:mt-3 flex items-baseline gap-2">
                    <span class="text-2xl sm:text-3xl font-semibold text-neutral-900 dark:text-white">{{ $activeCount }}</span>
                    <span class="text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('records') }}</span>
                </div>
                <p class="mt-1 text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('Shown in ordering flow') }}</p>
            </div>
            <div class="rounded-xl sm:rounded-2xl border border-neutral-200/70 bg-white/85 p-3 sm:p-4 shadow-sm sm:shadow-lg shadow-neutral-200/40 dark:border-neutral-700/60 dark:bg-neutral-900/70 dark:shadow-black/30">
                <div class="flex items-center gap-2 text-[11px] sm:text-xs font-medium uppercase tracking-[0.18em] sm:tracking-[0.2em] text-neutral-400">
                    <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                    <span>{{ __('Inactive') }}</span>
                </div>
                <div class="mt-2 sm:mt-3 flex items-baseline gap-2">
                    <span class="text-2xl sm:text-3xl font-semibold text-neutral-900 dark:text-white">{{ $inactiveCount }}</span>
                    <span class="text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('records') }}</span>
                </div>
                <p class="mt-1 text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('Hidden from ordering flow') }}</p>
            </div>
            <div class="rounded-xl sm:rounded-2xl border border-neutral-200/70 bg-white/85 p-3 sm:p-4 shadow-sm sm:shadow-lg shadow-neutral-200/40 dark:border-neutral-700/60 dark:bg-neutral-900/70 dark:shadow-black/30">
                <div class="flex items-center gap-2 text-[11px] sm:text-xs font-medium uppercase tracking-[0.18em] sm:tracking-[0.2em] text-neutral-400">
                    <span class="h-2 w-2 rounded-full bg-sky-500"></span>
                    <span>{{ __('Linked') }}</span>
                </div>
                <div class="mt-2 sm:mt-3 flex items-baseline gap-2">
                    <span class="text-2xl sm:text-3xl font-semibold text-neutral-900 dark:text-white">{{ $linkedCount }}</span>
                    <span class="text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('linked') }}</span>
                </div>
                <p class="mt-1 text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('Already used by menus') }}</p>
            </div>
        </div>

        <!-- Search (match menu filters) -->
        <div class="flex items-center gap-2 sm:justify-between">
            <div class="flex-1 min-w-0">
                <flux:input wire:model.live.debounce.800ms="search" :placeholder="__('Search name or description')" />
            </div>
            <flux:button
                size="sm"
                variant="ghost"
                class="btn-ghost-accent whitespace-nowrap shrink-0"
                wire:click="$wire.set('search','')"
            >
                {{ __('Clear') }}
            </flux:button>
        </div>

        <!-- Mobile cards (match meja style) -->
        <div class="block sm:hidden">
            <div class="grid gap-3">
                @forelse ($items as $k)
                    <div class="rounded-2xl border border-neutral-200/80 bg-white p-4 shadow-sm dark:border-neutral-800/70 dark:bg-neutral-900">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-base font-semibold text-neutral-900 dark:text-white">{{ $k->nama_kategori }}</div>
                                <div class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">{{ __('Created at') }} {{ $k->created_at?->format('d M Y') }}</div>
                            </div>
                            <span class="{{ $k->is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' }} inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-medium">
                                {{ $k->is_active ? __('Active') : __('Inactive') }}
                            </span>
                        </div>

                        <div class="mt-4 flex items-center gap-2">
                            @can('kategori.manage')
                                <flux:button
                                    size="sm"
                                    icon="{{ $k->is_active ? 'pause-circle' : 'check-circle' }}"
                                    variant="ghost"
                                    class="flex-1 btn-ghost-accent"
                                    wire:click="toggleActive({{ $k->id }})"
                                >
                                    {{ $k->is_active ? __('Deactivate') : __('Activate') }}
                                </flux:button>
                                <flux:link class="flex-1" :href="route('kategori.edit', $k, false)" wire:navigate>
                                    <flux:button size="sm" icon="pencil-square" variant="primary" class="w-full btn-accent">{{ __('Edit') }}</flux:button>
                                </flux:link>
                            @endcan
                        </div>

                        <div class="mt-2 flex items-center gap-2">
                            @can('kategori.manage')
                                <flux:modal.trigger name="confirm-delete-kategori" class="flex-1">
                                    <flux:button size="sm" icon="trash" variant="danger" class="w-full" wire:click="confirmDelete({{ $k->id }})">{{ __('Delete') }}</flux:button>
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
                @forelse ($items as $k)
                    <div class="flex h-full flex-col rounded-2xl border border-neutral-200/80 bg-white p-4 shadow-sm dark:border-neutral-800/70 dark:bg-neutral-900">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="truncate text-base font-semibold text-neutral-900 dark:text-white">{{ $k->nama_kategori }}</div>
                                <div class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">{{ __('Created at') }} {{ $k->created_at?->format('d M Y') }}</div>
                            </div>
                            <span class="{{ $k->is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' }} inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-medium">
                                {{ $k->is_active ? __('Active') : __('Inactive') }}
                            </span>
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-2">
                            @can('kategori.manage')
                                <flux:button
                                    size="sm"
                                    icon="{{ $k->is_active ? 'pause-circle' : 'check-circle' }}"
                                    variant="ghost"
                                    class="w-full btn-ghost-accent"
                                    wire:click="toggleActive({{ $k->id }})"
                                >
                                    {{ $k->is_active ? __('Deactivate') : __('Activate') }}
                                </flux:button>
                                <flux:link :href="route('kategori.edit', $k, false)" wire:navigate>
                                    <flux:button size="sm" icon="pencil-square" variant="primary" class="w-full btn-accent">{{ __('Edit') }}</flux:button>
                                </flux:link>
                            @endcan
                        </div>

                        <div class="mt-2">
                            @can('kategori.manage')
                                <flux:modal.trigger name="confirm-delete-kategori-desktop">
                                    <flux:button size="sm" icon="trash" variant="danger" class="w-full" wire:click="confirmDelete({{ $k->id }})">{{ __('Delete') }}</flux:button>
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

        <!-- Desktop table (match meja) -->
        <div class="hidden lg:block rounded-3xl border border-neutral-200/80 bg-gradient-to-b from-white/95 via-white/90 to-white/70 shadow-2xl shadow-neutral-200/60 backdrop-blur-xl dark:border-neutral-800/80 dark:from-neutral-950/80 dark:via-neutral-950/60 dark:to-neutral-950/40 dark:shadow-black/30">
            <div class="overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full table-fixed text-sm">
                        <thead>
                            <tr>
                                <th class="hidden sm:table-cell border-b border-r border-neutral-200/80 px-6 py-4 text-center text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:border-neutral-800/70 dark:text-neutral-400">{{ __('ID') }}</th>
                                <th class="border-b border-r border-neutral-200/80 px-6 py-4 text-center text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:border-neutral-800/70 dark:text-neutral-400">{{ __('Name') }}</th>
                                <th class="border-b border-r border-neutral-200/80 px-6 py-4 text-center text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:border-neutral-800/70 dark:text-neutral-400">{{ __('Status') }}</th>
                                <th class="hidden md:table-cell md:w-[220px] lg:w-[240px] border-b border-r border-neutral-200/80 px-6 py-4 text-center text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:border-neutral-800/70 dark:text-neutral-400">{{ __('Created') }}</th>
                                <th class="md:min-w-[280px] lg:min-w-0 border-b border-neutral-200/80 px-6 py-4 text-center text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:border-neutral-800/70 dark:text-neutral-400">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100/80 text-neutral-700 dark:divide-neutral-900/40 dark:text-neutral-200">
                            @forelse ($items as $k)
                                <tr class="group transition hover:bg-white/70 focus-within:bg-white/90 dark:hover:bg-neutral-900/40 dark:focus-within:bg-neutral-900/50">
                                    <td class="hidden sm:table-cell border-r border-neutral-200/80 px-6 py-4 align-middle text-center dark:border-neutral-800/70">
                                        <span class="inline-flex items-center rounded-full bg-neutral-900/5 px-3 py-1 text-xs font-semibold text-neutral-500 dark:bg-white/5 dark:text-neutral-300">
                                            #{{ str_pad($k->id, 3, '0', STR_PAD_LEFT) }}
                                        </span>
                                    </td>
                                    <td class="border-r border-neutral-200/80 px-6 py-4 align-middle text-center dark:border-neutral-800/70">
                                        <div class="flex flex-col items-center">
                                            <span class="text-base font-semibold text-neutral-900 dark:text-white">{{ $k->nama_kategori }}</span>
                                        </div>
                                    </td>
                                    <td class="border-r border-neutral-200/80 px-6 py-4 align-middle text-center dark:border-neutral-800/70">
                                        <span class="{{ $k->is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' }} inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-medium">
                                            {{ $k->is_active ? __('Active') : __('Inactive') }}
                                        </span>
                                    </td>
                                    <td class="hidden md:table-cell border-r border-neutral-200/80 px-6 py-4 align-middle text-center text-neutral-500 dark:border-neutral-800/70 dark:text-neutral-400">
                                        {{ $k->created_at?->format('d M Y') }}
                                    </td>
                                    <td class="px-6 py-4 align-middle text-center">
                                        <div class="mx-auto grid w-full max-w-[280px] grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                            @can('kategori.manage')
                                                <div class="contents">
                                                    <flux:button
                                                        size="sm"
                                                        icon="{{ $k->is_active ? 'pause-circle' : 'check-circle' }}"
                                                        variant="ghost"
                                                        class="btn-ghost-accent w-full rounded-2xl shadow-sm transition whitespace-nowrap justify-center"
                                                        wire:click="toggleActive({{ $k->id }})"
                                                        title="{{ $k->is_active ? __('Deactivate') : __('Activate') }}"
                                                    >
                                                        {{ $k->is_active ? __('Deactivate') : __('Activate') }}
                                                    </flux:button>
                                                    <flux:link :href="route('kategori.edit', $k, false)" wire:navigate>
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
                                                    <flux:modal.trigger name="confirm-delete-kategori-desktop">
                                                        <flux:button
                                                            size="sm"
                                                            icon="trash"
                                                            variant="danger"
                                                            class="w-full rounded-2xl shadow-sm transition whitespace-nowrap justify-center sm:col-span-2 lg:col-span-1"
                                                            wire:click="confirmDelete({{ $k->id }})"
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
                                    <td class="px-6 py-8 text-center text-sm text-neutral-500 dark:text-neutral-400" colspan="5">
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

        @php($selectedKategori = $items->firstWhere('id', $confirmingDeleteId))

        <!-- Mobile bottom-sheet delete (match meja) -->
        <flux:modal name="confirm-delete-kategori" focusable variant="flyout" position="bottom" :closable="false" class="rounded-t-3xl sm:rounded-xl">
            <div class="space-y-4 p-2 max-h-[60dvh] overflow-y-auto">
                <div class="flex items-center justify-center">
                    <div class="mx-auto mb-1 h-1.5 w-12 rounded-full bg-neutral-300 dark:bg-neutral-700"></div>
                </div>

                <div class="space-y-2">
                    <flux:heading size="lg">{{ __('Delete this category?') }}</flux:heading>
                    <flux:subheading>
                        {{ __('This action cannot be undone. Categories that are still used by menus cannot be deleted.') }}
                    </flux:subheading>
                </div>

                @if ($selectedKategori)
                    <div class="rounded-2xl border border-red-200/60 bg-red-50/60 p-4 text-sm text-red-800 dark:border-red-800/60 dark:bg-red-900/20 dark:text-red-200">
                        <p class="font-semibold">{{ $selectedKategori->nama_kategori }}</p>
                    </div>
                @endif

                <div class="sticky bottom-0 -mx-2 mt-2 flex items-center justify-end gap-2 border-t border-neutral-200 bg-white px-2 py-2 pb-[max(env(safe-area-inset-bottom),0.75rem)] dark:border-neutral-700 dark:bg-neutral-900">
                    <flux:modal.close>
                        <flux:button variant="filled" wire:click="$wire.set('confirmingDeleteId', null)">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button variant="danger" wire:click="delete">
                        {{ __('Yes, delete') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>

        <!-- Desktop delete modal -->
        <flux:modal name="confirm-delete-kategori-desktop" focusable class="mx-4 max-w-full sm:mx-auto sm:max-w-lg">
            <div class="space-y-4 p-2">
                <div class="space-y-2">
                    <flux:heading size="lg">{{ __('Delete this category?') }}</flux:heading>
                    <flux:subheading>
                        {{ __('This action cannot be undone. Categories that are still used by menus cannot be deleted.') }}
                    </flux:subheading>
                </div>

                @if ($selectedKategori)
                    <div class="rounded-2xl border border-red-200/60 bg-red-50/60 p-4 text-sm text-red-800 dark:border-red-800/60 dark:bg-red-900/20 dark:text-red-200">
                        <p class="font-semibold">{{ $selectedKategori->nama_kategori }}</p>
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

        <!-- Toast (match meja) -->
        <div
            x-data="{
                show: false,
                message: '',
                timeout: null,
                handle(event) {
                    this.message = event.detail?.message || '{{ __('Category updated successfully.') }}';
                    this.show = true;
                    clearTimeout(this.timeout);
                    this.timeout = setTimeout(() => this.show = false, 3500);
                }
            }"
            x-on:kategori-toast.window="handle($event)"
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
