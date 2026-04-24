<?php

use App\Models\Pajak;
use Illuminate\Support\Facades\Cache;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public ?int $confirmingDeleteId = null;
    public string $search = '';
    public string $statusFilter = 'all';

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => 'all'],
        'page' => ['except' => 1],
    ];

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingStatusFilter(): void { $this->resetPage(); }

    public function confirmDelete(int $id): void
    {
        $this->authorizeManage();
        $this->confirmingDeleteId = $id;
    }

    public function toggleActive(int $id): void
    {
        $this->authorizeManage();

        $pajak = Pajak::query()->select(['id', 'is_active'])->findOrFail($id);
        $pajak->forceFill(['is_active' => !$pajak->is_active])->save();

        Cache::forget('admin:pajak:stats:v1');

        $this->dispatch('pajak-toast', message: $pajak->is_active
            ? __('Tax activated successfully.')
            : __('Tax deactivated successfully.'));
    }

    public function delete(): void
    {
        $this->authorizeManage();
        if ($this->confirmingDeleteId) {
            $isUsedInOrders = \App\Models\Pesanan::query()
                ->where('pajak_id', $this->confirmingDeleteId)
                ->exists();

            if ($isUsedInOrders) {
                $this->dispatch('modal-close', name: 'confirm-delete-pajak');
                $this->dispatch('modal-close', name: 'confirm-delete-pajak-desktop');
                $this->dispatch('pajak-toast', message: __('This tax cannot be deleted because it is already used in orders.'));
                $this->confirmingDeleteId = null;
                return;
            }

            Pajak::where('id', $this->confirmingDeleteId)->delete();
            Cache::forget('admin:pajak:stats:v1');
            $this->confirmingDeleteId = null;
            $this->dispatch('modal-close', name: 'confirm-delete-pajak');
            $this->dispatch('modal-close', name: 'confirm-delete-pajak-desktop');
            $this->dispatch('pajak-toast', message: __('Tax deleted successfully.'));
        }
    }

    protected function authorizeManage(): void
    {
        abort_unless(auth()->check() && auth()->user()->can('pajak.manage'), 403);
    }
}; ?>

<section class="w-full">
    @php
        $query = Pajak::query()->select([
            'id',
            'nama',
            'persentase',
            'is_active',
        ]);

        if (!empty($search)) {
            $query->where('nama', 'like', '%'.$search.'%');
        }

        if ($statusFilter === 'active') {
            $query->where('is_active', true);
        } elseif ($statusFilter === 'inactive') {
            $query->where('is_active', false);
        }

        $items = $query->orderBy('nama')->paginate(10);

        $stats = Cache::remember('admin:pajak:stats:v1', 10, fn () => [
            'total'    => Pajak::query()->count(),
            'active'   => Pajak::query()->where('is_active', true)->count(),
            'inactive' => Pajak::query()->where('is_active', false)->count(),
            'avg'      => round(Pajak::query()->avg('persentase') ?? 0, 2),
        ]);

        $totalCount    = (int) ($stats['total'] ?? 0);
        $activeCount   = (int) ($stats['active'] ?? 0);
        $inactiveCount = (int) ($stats['inactive'] ?? 0);
        $averageRate   = (float) ($stats['avg'] ?? 0);

        $statusMeta = [
            'active' => [
                'label' => __('Active'),
                'dot'   => 'bg-emerald-500',
                'hint'  => __('Currently used in calculations'),
            ],
            'inactive' => [
                'label' => __('Inactive'),
                'dot'   => 'bg-amber-500',
                'hint'  => __('Kept for history, not applied'),
            ],
        ];
    @endphp

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <flux:heading size="xl" level="1">{{ __('Taxes') }}</flux:heading>
            <div class="flex flex-wrap items-center gap-2">
                @can('pajak.manage')
                    <flux:link :href="route('pajak.create', [], false)" wire:navigate>
                        <flux:button icon="plus" variant="primary" class="btn-brand">{{ __('Create') }}</flux:button>
                    </flux:link>
                @endcan
            </div>
        </div>

        <!-- Mobile chips -->
        <div class="block sm:hidden -mx-4 overflow-x-auto no-scrollbar">
            <div class="flex gap-2 px-4">
                <div class="whitespace-nowrap rounded-full border border-neutral-200/70 bg-white/85 px-3 py-2 text-[11px] shadow-sm dark:border-neutral-700/60 dark:bg-neutral-900/70">
                    <span class="font-semibold text-neutral-900 dark:text-white">{{ $totalCount }}</span>
                    <span class="ml-1 text-neutral-500 dark:text-neutral-400">{{ __('Taxes') }}</span>
                </div>
                <div class="whitespace-nowrap rounded-full border border-neutral-200/70 bg-white/85 px-3 py-2 text-[11px] shadow-sm dark:border-neutral-700/60 dark:bg-neutral-900/70">
                    <span class="inline-flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full {{ $statusMeta['active']['dot'] }}"></span>
                        <span class="text-neutral-600 dark:text-neutral-300">{{ $statusMeta['active']['label'] }}</span>
                        <span class="font-semibold text-neutral-900 dark:text-white">{{ $activeCount }}</span>
                    </span>
                </div>
                <div class="whitespace-nowrap rounded-full border border-neutral-200/70 bg-white/85 px-3 py-2 text-[11px] shadow-sm dark:border-neutral-700/60 dark:bg-neutral-900/70">
                    <span class="inline-flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full {{ $statusMeta['inactive']['dot'] }}"></span>
                        <span class="text-neutral-600 dark:text-neutral-300">{{ $statusMeta['inactive']['label'] }}</span>
                        <span class="font-semibold text-neutral-900 dark:text-white">{{ $inactiveCount }}</span>
                    </span>
                </div>
            </div>
        </div>

        <!-- Desktop summary cards (match meja style) -->
        <div class="hidden sm:grid gap-2 sm:gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl sm:rounded-2xl border border-neutral-200/70 bg-white/85 p-3 sm:p-4 shadow-sm sm:shadow-lg shadow-neutral-200/40 dark:border-neutral-700/60 dark:bg-neutral-900/70 dark:shadow-black/30">
                <p class="text-[11px] sm:text-xs font-medium uppercase tracking-[0.18em] sm:tracking-[0.2em] text-neutral-400">{{ __('Total Taxes') }}</p>
                <div class="mt-2 sm:mt-3 flex items-baseline gap-2">
                    <span class="text-2xl sm:text-3xl font-semibold text-neutral-900 dark:text-white">{{ $totalCount }}</span>
                    <span class="text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('records') }}</span>
                </div>
            </div>

            <div class="rounded-xl sm:rounded-2xl border border-neutral-200/70 bg-white/85 p-3 sm:p-4 shadow-sm sm:shadow-lg shadow-neutral-200/40 dark:border-neutral-700/60 dark:bg-neutral-900/70 dark:shadow-black/30">
                <div class="flex items-center gap-2 text-[11px] sm:text-xs font-medium uppercase tracking-[0.18em] sm:tracking-[0.2em] text-neutral-400">
                    <span class="h-2 w-2 rounded-full {{ $statusMeta['active']['dot'] }}"></span>
                    <span>{{ $statusMeta['active']['label'] }}</span>
                </div>
                <div class="mt-2 sm:mt-3 flex items-baseline gap-2">
                    <span class="text-2xl sm:text-3xl font-semibold text-neutral-900 dark:text-white">{{ $activeCount }}</span>
                    <span class="text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('items') }}</span>
                </div>
                <p class="mt-1 text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ $statusMeta['active']['hint'] }}</p>
            </div>

            <div class="rounded-xl sm:rounded-2xl border border-neutral-200/70 bg-white/85 p-3 sm:p-4 shadow-sm sm:shadow-lg shadow-neutral-200/40 dark:border-neutral-700/60 dark:bg-neutral-900/70 dark:shadow-black/30">
                <div class="flex items-center gap-2 text-[11px] sm:text-xs font-medium uppercase tracking-[0.18em] sm:tracking-[0.2em] text-neutral-400">
                    <span class="h-2 w-2 rounded-full {{ $statusMeta['inactive']['dot'] }}"></span>
                    <span>{{ $statusMeta['inactive']['label'] }}</span>
                </div>
                <div class="mt-2 sm:mt-3 flex items-baseline gap-2">
                    <span class="text-2xl sm:text-3xl font-semibold text-neutral-900 dark:text-white">{{ $inactiveCount }}</span>
                    <span class="text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('items') }}</span>
                </div>
                <p class="mt-1 text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ $statusMeta['inactive']['hint'] }}</p>
            </div>

            <div class="rounded-xl sm:rounded-2xl border border-neutral-200/70 bg-white/85 p-3 sm:p-4 shadow-sm sm:shadow-lg shadow-neutral-200/40 dark:border-neutral-700/60 dark:bg-neutral-900/70 dark:shadow-black/30">
                <p class="text-[11px] sm:text-xs font-medium uppercase tracking-[0.18em] sm:tracking-[0.2em] text-neutral-400">{{ __('Average Rate') }}</p>
                <div class="mt-2 sm:mt-3 flex items-baseline gap-2">
                    <span class="text-2xl sm:text-3xl font-semibold text-neutral-900 dark:text-white">{{ $averageRate }}%</span>
                    <span class="text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('across all taxes') }}</span>
                </div>
            </div>
        </div>

        <!-- Search & filter -->
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex-1 min-w-0">
                <flux:input
                    wire:model.live.debounce.1000ms="search"
                    :placeholder="__('Search tax name')"
                />
            </div>
            <div class="grid grid-cols-[1fr_auto] items-center gap-2 sm:flex sm:items-center sm:justify-end sm:gap-2">
                <flux:select
                    wire:model.live="statusFilter"
                    class="min-w-0 w-full sm:w-44 rounded-full border-neutral-200 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900 focus:outline-hidden focus:ring-2 focus:ring-[color:var(--brand-accent)] focus:ring-offset-2 focus:ring-offset-[color:var(--brand-accent-foreground)]"
                >
                    <option value="all">{{ __('All') }}</option>
                    <option value="active">{{ __('Active') }}</option>
                    <option value="inactive">{{ __('Inactive') }}</option>
                </flux:select>
                <flux:button
                    size="sm"
                    variant="ghost"
                    class="btn-ghost-accent whitespace-nowrap justify-self-end"
                    wire:click="$wire.set('search','');$wire.set('statusFilter','all')"
                >
                    {{ __('Clear') }}
                </flux:button>
            </div>
        </div>

        <!-- Mobile cards -->
        <div class="block sm:hidden">
            <div class="grid gap-3">
                @forelse($items as $p)
                    <div class="rounded-2xl border border-neutral-200/80 bg-white p-4 shadow-sm dark:border-neutral-800/70 dark:bg-neutral-900">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-base font-semibold text-neutral-900 dark:text-white">
                                    {{ $p->nama }}
                                </div>
                                <div class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">
                                    {{ __('Rate') }}: {{ number_format($p->persentase, 2) }}%
                                </div>
                            </div>
                            <div>
                                @if($p->is_active)
                                    <span class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-[11px] font-semibold text-emerald-700 ring-1 ring-emerald-100 dark:bg-emerald-900/40 dark:text-emerald-100 dark:ring-emerald-800/60">
                                        <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                        {{ __('Active') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-2 rounded-full bg-neutral-100 px-3 py-1 text-[11px] font-semibold text-neutral-600 ring-1 ring-neutral-200 dark:bg-neutral-800/60 dark:text-neutral-200 dark:ring-neutral-700">
                                        <span class="h-2 w-2 rounded-full bg-neutral-400"></span>
                                        {{ __('Inactive') }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        @can('pajak.manage')
                            <div class="mt-4 flex items-center gap-2">
                                <flux:button
                                    size="sm"
                                    icon="{{ $p->is_active ? 'pause-circle' : 'check-circle' }}"
                                    variant="ghost"
                                    class="flex-1 btn-ghost-accent"
                                    wire:click="toggleActive({{ $p->id }})"
                                >
                                    {{ $p->is_active ? __('Deactivate') : __('Activate') }}
                                </flux:button>
                                <flux:link class="flex-1" :href="route('pajak.edit', $p, false)" wire:navigate>
                                    <flux:button size="sm" icon="pencil-square" variant="primary" class="w-full btn-accent">{{ __('Edit') }}</flux:button>
                                </flux:link>
                            </div>
                            <div class="mt-2">
                                <flux:modal.trigger name="confirm-delete-pajak" class="w-full">
                                    <flux:button
                                        size="sm"
                                        icon="trash"
                                        variant="danger"
                                        class="w-full btn-ghost-danger"
                                        wire:click="confirmDelete({{ $p->id }})"
                                    >
                                        {{ __('Delete') }}
                                    </flux:button>
                                </flux:modal.trigger>
                            </div>
                        @endcan
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
                @forelse($items as $p)
                    <div class="flex h-full flex-col rounded-2xl border border-neutral-200/80 bg-white p-4 shadow-sm dark:border-neutral-800/70 dark:bg-neutral-900">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="truncate text-base font-semibold text-neutral-900 dark:text-white">
                                    {{ $p->nama }}
                                </div>
                                <div class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">
                                    {{ __('Rate') }}: {{ number_format($p->persentase, 2) }}%
                                </div>
                            </div>
                            <div class="shrink-0">
                                @if($p->is_active)
                                    <span class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-[11px] font-semibold text-emerald-700 ring-1 ring-emerald-100 dark:bg-emerald-900/40 dark:text-emerald-100 dark:ring-emerald-800/60">
                                        <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                        {{ __('Active') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-2 rounded-full bg-neutral-100 px-3 py-1 text-[11px] font-semibold text-neutral-600 ring-1 ring-neutral-200 dark:bg-neutral-800/60 dark:text-neutral-200 dark:ring-neutral-700">
                                        <span class="h-2 w-2 rounded-full bg-neutral-400"></span>
                                        {{ __('Inactive') }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        @can('pajak.manage')
                            <div class="mt-4 grid grid-cols-2 gap-2">
                                <flux:button
                                    size="sm"
                                    icon="{{ $p->is_active ? 'pause-circle' : 'check-circle' }}"
                                    variant="ghost"
                                    class="w-full btn-ghost-accent"
                                    wire:click="toggleActive({{ $p->id }})"
                                >
                                    {{ $p->is_active ? __('Deactivate') : __('Activate') }}
                                </flux:button>
                                <flux:link :href="route('pajak.edit', $p, false)" wire:navigate>
                                    <flux:button size="sm" icon="pencil-square" variant="primary" class="w-full btn-accent">{{ __('Edit') }}</flux:button>
                                </flux:link>
                            </div>
                            <div class="mt-2">
                                <flux:modal.trigger name="confirm-delete-pajak-desktop">
                                    <flux:button
                                        size="sm"
                                        icon="trash"
                                        variant="danger"
                                        class="w-full btn-ghost-danger"
                                        wire:click="confirmDelete({{ $p->id }})"
                                    >
                                        {{ __('Delete') }}
                                    </flux:button>
                                </flux:modal.trigger>
                            </div>
                        @endcan
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

        <!-- Desktop table -->
        <div class="hidden lg:block rounded-3xl border border-neutral-200/80 bg-gradient-to-b from-white/95 via-white/90 to-white/70 shadow-2xl shadow-neutral-200/60 backdrop-blur-xl dark:border-neutral-800/80 dark:from-neutral-950/80 dark:via-neutral-950/60 dark:to-neutral-950/40 dark:shadow-black/30">
            <div class="overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm text-left">
                        <thead>
                            <tr>
                                <th class="hidden md:table-cell px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">{{ __('ID') }}</th>
                                <th class="px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">{{ __('Name') }}</th>
                                <th class="px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">{{ __('Rate') }}</th>
                                <th class="px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">{{ __('Status') }}</th>
                                <th class="px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100/80 text-neutral-700 dark:divide-neutral-900/40 dark:text-neutral-200">
                            @forelse($items as $p)
                                <tr class="group transition hover:bg-white/70 focus-within:bg-white/90 dark:hover:bg-neutral-900/40 dark:focus-within:bg-neutral-900/50">
                                    <td class="hidden md:table-cell px-6 py-4 align-middle">
                                        <span class="inline-flex items-center rounded-full bg-neutral-900/5 px-3 py-1 text-xs font-semibold text-neutral-500 dark:bg-white/5 dark:text-neutral-300">
                                            #{{ str_pad($p->id, 3, '0', STR_PAD_LEFT) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 align-middle">
                                        <div class="flex flex-col">
                                            <span class="text-base font-semibold text-neutral-900 dark:text-white">{{ $p->nama }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 align-middle">
                                        {{ number_format($p->persentase, 2) }}%
                                    </td>
                                    <td class="px-6 py-4 align-middle">
                                        @if($p->is_active)
                                            <span class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-100 dark:bg-emerald-900/40 dark:text-emerald-100 dark:ring-emerald-800/60">
                                                <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                                {{ __('Active') }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-2 rounded-full bg-neutral-100 px-3 py-1 text-xs font-semibold text-neutral-600 ring-1 ring-neutral-200 dark:bg-neutral-800/60 dark:text-neutral-200 dark:ring-neutral-700">
                                                <span class="h-2 w-2 rounded-full bg-neutral-400"></span>
                                                {{ __('Inactive') }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 align-middle">
                                        <div class="flex flex-wrap items-center gap-2">
                                            @can('pajak.manage')
                                                <flux:button
                                                    size="sm"
                                                    icon="{{ $p->is_active ? 'pause-circle' : 'check-circle' }}"
                                                    variant="ghost"
                                                    class="btn-ghost-accent rounded-2xl shadow-sm transition whitespace-nowrap justify-center md:w-24 lg:w-auto"
                                                    wire:click="toggleActive({{ $p->id }})"
                                                    title="{{ $p->is_active ? __('Deactivate') : __('Activate') }}"
                                                >
                                                    {{ $p->is_active ? __('Deactivate') : __('Activate') }}
                                                </flux:button>
                                                <flux:link :href="route('pajak.edit', $p, false)" wire:navigate>
                                                    <flux:button
                                                        size="sm"
                                                        icon="pencil-square"
                                                        variant="primary"
                                                        class="btn-accent rounded-2xl shadow-sm transition"
                                                    >
                                                        {{ __('Edit') }}
                                                    </flux:button>
                                                </flux:link>
                                                <flux:modal.trigger name="confirm-delete-pajak-desktop">
                                                    <flux:button
                                                        size="sm"
                                                        icon="trash"
                                                        variant="danger"
                                                        class="rounded-2xl shadow-sm transition"
                                                        wire:click="confirmDelete({{ $p->id }})"
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

        @php($selectedPajak = $items->firstWhere('id', $confirmingDeleteId))

        <!-- Delete pajak mobile (flyout bottom) -->
        <flux:modal name="confirm-delete-pajak" focusable variant="flyout" position="bottom" :closable="false" class="rounded-t-3xl sm:rounded-xl">
            <div class="space-y-4 p-2 max-h-[60dvh] overflow-y-auto">
                <div class="flex items-center justify-center">
                    <div class="mx-auto mb-1 h-1.5 w-12 rounded-full bg-neutral-300 dark:bg-neutral-700"></div>
                </div>

                <div class="space-y-2">
                    <flux:heading size="lg">{{ __('Delete this tax?') }}</flux:heading>
                    <flux:subheading>
                        {{ __('This action cannot be undone. Taxes that are already used in orders cannot be deleted.') }}
                    </flux:subheading>
                </div>

                @if ($selectedPajak)
                    <div class="rounded-2xl border border-red-200/60 bg-red-50/60 p-4 text-sm text-red-800 dark:border-red-800/60 dark:bg-red-900/20 dark:text-red-200">
                        <p class="font-semibold">{{ $selectedPajak->nama }}</p>
                        <p class="text-xs opacity-80">{{ __('Rate') }} {{ number_format($selectedPajak->persentase, 2) }}%</p>
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

        <!-- Delete pajak desktop (centered) -->
        @php($selectedPajak = $items->firstWhere('id', $confirmingDeleteId))
        <flux:modal name="confirm-delete-pajak-desktop" focusable class="mx-4 max-w-full sm:mx-auto sm:max-w-lg">
            <div class="space-y-4 p-2">
                <div class="space-y-2">
                    <flux:heading size="lg">{{ __('Delete this tax?') }}</flux:heading>
                    <flux:subheading>
                        {{ __('This action cannot be undone. Taxes that are already used in orders cannot be deleted.') }}
                    </flux:subheading>
                </div>

                @if ($selectedPajak)
                    <div class="rounded-2xl border border-red-200/60 bg-red-50/60 p-4 text-sm text-red-800 dark:border-red-800/60 dark:bg-red-900/20 dark:text-red-200">
                        <p class="font-semibold">{{ $selectedPajak->nama }}</p>
                        <p class="text-xs opacity-80">{{ __('Rate') }} {{ number_format($selectedPajak->persentase, 2) }}%</p>
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

        <!-- Toast -->
        <div
            x-data="{
                show: false,
                message: '',
                timeout: null,
                handle(event) {
                    this.message = event.detail?.message || '{{ __('Tax deleted successfully.') }}';
                    this.show = true;
                    clearTimeout(this.timeout);
                    this.timeout = setTimeout(() => this.show = false, 3500);
                }
            }"
            x-on:pajak-toast.window="handle($event)"
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

@if (session('pajak_toast'))
    <script>
        window.addEventListener('load', () => {
            try {
                const message = @js(session('pajak_toast'));
                window.dispatchEvent(new CustomEvent('pajak-toast', { detail: { message } }));
            } catch (e) {}
        });
    </script>
@endif
