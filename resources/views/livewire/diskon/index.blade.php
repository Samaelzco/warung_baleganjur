<?php

use App\Models\Diskon;
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

        $diskon = Diskon::query()->select(['id', 'is_active'])->findOrFail($id);
        $diskon->forceFill(['is_active' => !$diskon->is_active])->save();

        Cache::forget('admin:diskon:stats:v1');

        $this->dispatch('diskon-toast', message: $diskon->is_active
            ? __('Discount activated successfully.')
            : __('Discount deactivated successfully.'));
    }

    public function delete(): void
    {
        $this->authorizeManage();
        if ($this->confirmingDeleteId) {
            $isUsedInOrders = \App\Models\Pesanan::query()
                ->where('diskon_id', $this->confirmingDeleteId)
                ->exists();

            if ($isUsedInOrders) {
                $this->dispatch('modal-close', name: 'confirm-delete-diskon');
                $this->dispatch('modal-close', name: 'confirm-delete-diskon-desktop');
                $this->dispatch('diskon-toast', message: __('This discount cannot be deleted because it is already used in orders.'));
                $this->confirmingDeleteId = null;
                return;
            }

            Diskon::where('id', $this->confirmingDeleteId)->delete();
            Cache::forget('admin:diskon:stats:v1');
            $this->confirmingDeleteId = null;

            $this->dispatch('modal-close', name: 'confirm-delete-diskon');
            $this->dispatch('modal-close', name: 'confirm-delete-diskon-desktop');
            $this->dispatch('diskon-toast', message: __('Discount deleted successfully.'));
        }
    }

    protected function authorizeManage(): void
    {
        abort_unless(auth()->check() && auth()->user()->can('diskon.manage'), 403);
    }
}; ?>

<section class="w-full">
    @php
        $query = Diskon::query()->select([
            'id',
            'kode',
            'tipe',
            'nilai',
            'min_subtotal',
            'tanggal_mulai',
            'tanggal_selesai',
            'is_active',
        ]);

        if (!empty($search)) {
            $query->where('kode', 'like', "%{$search}%");
        }

        if ($statusFilter === 'active') {
            $query->where('is_active', true);
        } elseif ($statusFilter === 'inactive') {
            $query->where('is_active', false);
        }

        $items = $query->orderBy('kode')->paginate(10);

        $stats = Cache::remember('admin:diskon:stats:v1', 10, fn () => [
            'total'    => Diskon::query()->count(),
            'active'   => Diskon::query()->where('is_active', true)->count(),
            'inactive' => Diskon::query()->where('is_active', false)->count(),
            'percent'  => Diskon::query()->where('tipe', 'percent')->count(),
            'nominal'  => Diskon::query()->where('tipe', 'nominal')->count(),
        ]);

        $totalCount    = (int) ($stats['total'] ?? 0);
        $activeCount   = (int) ($stats['active'] ?? 0);
        $inactiveCount = (int) ($stats['inactive'] ?? 0);
        $percentCount  = (int) ($stats['percent'] ?? 0);
        $nominalCount  = (int) ($stats['nominal'] ?? 0);
    @endphp

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <flux:heading size="xl" level="1">{{ __('Discounts') }}</flux:heading>
                
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @can('diskon.manage')
                    <flux:link :href="route('diskon.create', [], false)" wire:navigate>
                        <flux:button variant="primary" icon="plus" class="btn-brand">{{ __('Create') }}</flux:button>
                    </flux:link>
                @endcan
            </div>
        </div>

        <!-- Mobile summary chips -->
        <div class="block sm:hidden -mx-4 overflow-x-auto no-scrollbar">
            <div class="flex gap-2 px-4">
                <div class="whitespace-nowrap rounded-full border border-neutral-200/70 bg-white/85 px-3 py-2 text-[11px] shadow-sm dark:border-neutral-700/60 dark:bg-neutral-900/70">
                    <span class="inline-flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-neutral-500"></span>
                        <span class="text-neutral-600 dark:text-neutral-300">{{ __('Total') }}</span>
                        <span class="font-semibold text-neutral-900 dark:text-white">{{ $totalCount }}</span>
                    </span>
                </div>
                <div class="whitespace-nowrap rounded-full border border-neutral-200/70 bg-white/85 px-3 py-2 text-[11px] shadow-sm dark:border-neutral-700/60 dark:bg-neutral-900/70">
                    <span class="inline-flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                        <span class="text-neutral-600 dark:text-neutral-300">{{ __('Active') }}</span>
                        <span class="font-semibold text-neutral-900 dark:text-white">{{ $activeCount }}</span>
                    </span>
                </div>
                <div class="whitespace-nowrap rounded-full border border-neutral-200/70 bg-white/85 px-3 py-2 text-[11px] shadow-sm dark:border-neutral-700/60 dark:bg-neutral-900/70">
                    <span class="inline-flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                        <span class="text-neutral-600 dark:text-neutral-300">{{ __('Inactive') }}</span>
                        <span class="font-semibold text-neutral-900 dark:text-white">{{ $inactiveCount }}</span>
                    </span>
                </div>
            </div>
        </div>

        <!-- Desktop summary cards (match meja / pajak) -->
        <div class="hidden sm:grid gap-2 sm:gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl sm:rounded-2xl border border-neutral-200/70 bg-white/85 p-3 sm:p-4 shadow-sm sm:shadow-lg shadow-neutral-200/40 dark:border-neutral-700/60 dark:bg-neutral-900/70 dark:shadow-black/30">
                <p class="text-[11px] sm:text-xs font-medium uppercase tracking-[0.18em] sm:tracking-[0.2em] text-neutral-400">{{ __('Total Discounts') }}</p>
                <div class="mt-2 sm:mt-3 flex items-baseline gap-2">
                    <span class="text-2xl sm:text-3xl font-semibold text-neutral-900 dark:text-white">{{ $totalCount }}</span>
                    <span class="text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('codes') }}</span>
                </div>
            </div>
            <div class="rounded-xl sm:rounded-2xl border border-neutral-200/70 bg-white/85 p-3 sm:p-4 shadow-sm sm:shadow-lg shadow-neutral-200/40 dark:border-neutral-700/60 dark:bg-neutral-900/70 dark:shadow-black/30">
                <div class="flex items-center gap-2 text-[11px] sm:text-xs font-medium uppercase tracking-[0.18em] sm:tracking-[0.2em] text-neutral-400">
                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                    <span>{{ __('Active') }}</span>
                </div>
                <div class="mt-2 sm:mt-3 flex items-baseline gap-2">
                    <span class="text-2xl sm:text-3xl font-semibold text-neutral-900 dark:text-white">{{ $activeCount }}</span>
                    <span class="text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('codes') }}</span>
                </div>
                <p class="mt-1 text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('Currently available for customers') }}</p>
            </div>
            <div class="rounded-xl sm:rounded-2xl border border-neutral-200/70 bg-white/85 p-3 sm:p-4 shadow-sm sm:shadow-lg shadow-neutral-200/40 dark:border-neutral-700/60 dark:bg-neutral-900/70 dark:shadow-black/30">
                <div class="flex items-center gap-2 text-[11px] sm:text-xs font-medium uppercase tracking-[0.18em] sm:tracking-[0.2em] text-neutral-400">
                    <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                    <span>{{ __('Inactive') }}</span>
                </div>
                <div class="mt-2 sm:mt-3 flex items-baseline gap-2">
                    <span class="text-2xl sm:text-3xl font-semibold text-neutral-900 dark:text-white">{{ $inactiveCount }}</span>
                    <span class="text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('codes') }}</span>
                </div>
                <p class="mt-1 text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('Kept for history but not usable') }}</p>
            </div>
            
        </div>

        <!-- Search & filter -->
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex-1 min-w-0">
                <flux:input
                    wire:model.live.debounce.1000ms="search"
                    :placeholder="__('Search discount code')"
                />
            </div>
            <div class="flex items-center gap-2 min-w-0">
                <flux:select
                    wire:model.live="statusFilter"
                    class="flex-1 sm:flex-none sm:w-56 rounded-full border-neutral-200 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900 focus:outline-hidden focus:ring-2 focus:ring-[color:var(--brand-accent)] focus:ring-offset-2 focus:ring-offset-[color:var(--brand-accent-foreground)]"
                >
                    <option value="all">{{ __('All Status') }}</option>
                    <option value="active">{{ __('Active') }}</option>
                    <option value="inactive">{{ __('Inactive') }}</option>
                </flux:select>
                <flux:button
                    size="sm"
                    variant="ghost"
                    class="btn-ghost-accent"
                    wire:click="$wire.set('search','');$wire.set('statusFilter','all')"
                >
                    {{ __('Clear') }}
                </flux:button>
            </div>
        </div>

        <!-- Mobile cards -->
        <div class="block sm:hidden">
            <div class="mt-2 grid gap-3">
                @forelse($items as $d)
                    <div class="rounded-2xl border border-neutral-200/80 bg-white p-4 shadow-sm dark:border-neutral-800/70 dark:bg-neutral-900">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="font-mono text-sm font-semibold text-neutral-900 dark:text-white">
                                    {{ $d->kode }}
                                </div>
                                <div class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">
                                    @if($d->tipe === 'percent')
                                        -{{ number_format($d->nilai, 2) }}%
                                    @else
                                        -Rp {{ number_format($d->nilai, 0, ',', '.') }}
                                    @endif
                                </div>
                            </div>
                            <div>
                                @if($d->is_active)
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

                        <div class="mt-3 space-y-1 text-xs text-neutral-500 dark:text-neutral-400">
                            @if($d->min_subtotal !== null)
                                <p>{{ __('Min. subtotal') }}: Rp {{ number_format($d->min_subtotal, 0, ',', '.') }}</p>
                            @endif
                            @php
                                $start = $d->tanggal_mulai;
                                $end   = $d->tanggal_selesai;
                            @endphp
                            <p>
                                {{ __('Validity') }}:
                                @if($start && $end)
                                    {{ $start->format('d M Y') }} - {{ $end->format('d M Y') }}
                                @elseif($start)
                                    {{ __('From') }} {{ $start->format('d M Y') }}
                                @elseif($end)
                                    {{ __('Until') }} {{ $end->format('d M Y') }}
                                @else
                                    {{ __('No date limit') }}
                                @endif
                            </p>
                        </div>

                        <div class="mt-4 flex items-center gap-2">
                            @can('diskon.manage')
                                <flux:button
                                    size="sm"
                                    icon="{{ $d->is_active ? 'pause-circle' : 'check-circle' }}"
                                    variant="ghost"
                                    class="btn-ghost-accent flex-1 rounded-2xl shadow-sm transition whitespace-nowrap justify-center"
                                    wire:click="toggleActive({{ $d->id }})"
                                >
                                    {{ $d->is_active ? __('Deactivate') : __('Activate') }}
                                </flux:button>
                                <flux:link class="flex-1" :href="route('diskon.edit', $d, false)" wire:navigate>
                                    <flux:button size="sm" icon="pencil-square" variant="primary" class="btn-accent w-full rounded-2xl shadow-sm transition whitespace-nowrap justify-center">{{ __('Edit') }}</flux:button>
                                </flux:link>
                            @endcan
                        </div>

                        <div class="mt-2">
                            @can('diskon.manage')
                                <flux:modal.trigger name="confirm-delete-diskon" class="w-full">
                                    <flux:button
                                        size="sm"
                                        icon="trash"
                                        variant="danger"
                                        class="w-full rounded-2xl shadow-sm transition whitespace-nowrap justify-center"
                                        wire:click="confirmDelete({{ $d->id }})"
                                    >
                                        {{ __('Delete') }}
                                    </flux:button>
                                </flux:modal.trigger>
                            @endcan
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-neutral-200/80 bg-white p-6 text-center text-sm text-neutral-500 dark:border-neutral-800/70 dark:bg-neutral-900 dark:text-neutral-400">
                        <div class="flex flex-col items-center gap-3">
                            <div class="h-12 w-12 rounded-full bg-neutral-100 text-neutral-400 dark:bg-neutral-900/60 dark:text-neutral-500">
                                <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-full w-full p-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 4.5h9m-9 6h9m-9 6h9" />
                                </svg>
                            </div>
                            <p>{{ __('No data') }}</p>
                        </div>
                    </div>
                @endforelse
            </div>
            <div class="mt-4">
                {{ $items->links() }}
            </div>
        </div>

        <!-- Tablet cards -->
        <div class="hidden sm:block lg:hidden">
            <div class="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-2">
                @forelse($items as $d)
                    <div class="flex h-full flex-col rounded-2xl border border-neutral-200/80 bg-white p-4 shadow-sm dark:border-neutral-800/70 dark:bg-neutral-900">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="truncate font-mono text-sm font-semibold text-neutral-900 dark:text-white">
                                    {{ $d->kode }}
                                </div>
                                <div class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">
                                    @if($d->tipe === 'percent')
                                        -{{ number_format($d->nilai, 2) }}%
                                    @else
                                        -Rp {{ number_format($d->nilai, 0, ',', '.') }}
                                    @endif
                                </div>
                            </div>
                            <div class="shrink-0">
                                @if($d->is_active)
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

                        <div class="mt-3 space-y-1 text-xs text-neutral-500 dark:text-neutral-400">
                            @if($d->min_subtotal !== null)
                                <p>{{ __('Min. subtotal') }}: Rp {{ number_format($d->min_subtotal, 0, ',', '.') }}</p>
                            @endif
                            @php
                                $start = $d->tanggal_mulai;
                                $end   = $d->tanggal_selesai;
                            @endphp
                            <p>
                                {{ __('Validity') }}:
                                @if($start && $end)
                                    {{ $start->format('d M Y') }} - {{ $end->format('d M Y') }}
                                @elseif($start)
                                    {{ __('From') }} {{ $start->format('d M Y') }}
                                @elseif($end)
                                    {{ __('Until') }} {{ $end->format('d M Y') }}
                                @else
                                    {{ __('No date limit') }}
                                @endif
                            </p>
                        </div>

                        @can('diskon.manage')
                            <div class="mt-4 grid grid-cols-2 gap-2">
                                <flux:button
                                    size="sm"
                                    icon="{{ $d->is_active ? 'pause-circle' : 'check-circle' }}"
                                    variant="ghost"
                                    class="btn-ghost-accent w-full rounded-2xl shadow-sm transition whitespace-nowrap justify-center"
                                    wire:click="toggleActive({{ $d->id }})"
                                >
                                    {{ $d->is_active ? __('Deactivate') : __('Activate') }}
                                </flux:button>
                                <flux:link :href="route('diskon.edit', $d, false)" wire:navigate>
                                    <flux:button size="sm" icon="pencil-square" variant="primary" class="btn-accent w-full rounded-2xl shadow-sm transition whitespace-nowrap justify-center">{{ __('Edit') }}</flux:button>
                                </flux:link>
                            </div>

                            <div class="mt-2">
                                <flux:modal.trigger name="confirm-delete-diskon-desktop">
                                    <flux:button
                                        size="sm"
                                        icon="trash"
                                        variant="danger"
                                        class="w-full rounded-2xl shadow-sm transition whitespace-nowrap justify-center"
                                        wire:click="confirmDelete({{ $d->id }})"
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
        <div class="mt-2 hidden lg:block rounded-3xl border border-neutral-200/80 bg-gradient-to-b from-white/95 via-white/90 to-white/70 shadow-2xl shadow-neutral-200/60 backdrop-blur-xl dark:border-neutral-800/80 dark:from-neutral-950/80 dark:via-neutral-950/60 dark:to-neutral-950/40 dark:shadow-black/30">
            <div class="overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full table-fixed text-sm">
                        <thead>
                            <tr>
                                <th class="hidden sm:table-cell border-b border-r border-neutral-200/80 px-6 py-4 text-center text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:border-neutral-800/70 dark:text-neutral-400">{{ __('ID') }}</th>
                                <th class="border-b border-r border-neutral-200/80 px-6 py-4 text-center text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:border-neutral-800/70 dark:text-neutral-400">{{ __('Code') }}</th>
                                <th class="border-b border-r border-neutral-200/80 px-6 py-4 text-center text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:border-neutral-800/70 dark:text-neutral-400">{{ __('Value') }}</th>
                                <th class="hidden md:table-cell border-b border-r border-neutral-200/80 px-6 py-4 text-center text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:border-neutral-800/70 dark:text-neutral-400">{{ __('Requirements') }}</th>
                                <th class="hidden lg:table-cell border-b border-r border-neutral-200/80 px-6 py-4 text-center text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:border-neutral-800/70 dark:text-neutral-400">{{ __('Period') }}</th>
                                <th class="border-b border-r border-neutral-200/80 px-6 py-4 text-center text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:border-neutral-800/70 dark:text-neutral-400">{{ __('Status') }}</th>
                                <th class="md:min-w-[280px] lg:min-w-0 border-b border-neutral-200/80 px-6 py-4 text-center text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:border-neutral-800/70 dark:text-neutral-400">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100/80 text-neutral-700 dark:divide-neutral-900/40 dark:text-neutral-200">
                            @forelse($items as $d)
                                <tr class="group transition hover:bg-white/70 focus-within:bg-white/90 dark:hover:bg-neutral-900/40 dark:focus-within:bg-neutral-900/50">
                                    <td class="hidden sm:table-cell border-r border-neutral-200/80 px-6 py-4 align-middle text-center dark:border-neutral-800/70">
                                        <span class="inline-flex items-center rounded-full bg-neutral-900/5 px-3 py-1 text-xs font-semibold text-neutral-500 dark:bg-white/5 dark:text-neutral-300">
                                            #{{ str_pad($d->id, 3, '0', STR_PAD_LEFT) }}
                                        </span>
                                    </td>
                                    <td class="border-r border-neutral-200/80 px-6 py-4 align-middle text-center dark:border-neutral-800/70">
                                        <div class="flex flex-col items-center">
                                            <span class="font-mono text-sm font-semibold text-neutral-900 dark:text-white">
                                                {{ $d->kode }}
                                            </span>
                                            <span class="hidden sm:block text-[11px] text-neutral-500 dark:text-neutral-400">
                                                {{ $d->tipe === 'percent' ? __('Percent') : __('Nominal') }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="border-r border-neutral-200/80 px-6 py-4 align-middle text-center dark:border-neutral-800/70">
                                        <span class="text-sm font-medium text-neutral-900 dark:text-neutral-50">
                                            @if($d->tipe === 'percent')
                                                -{{ number_format($d->nilai, 2) }}%
                                            @else
                                                -Rp {{ number_format($d->nilai, 0, ',', '.') }}
                                            @endif
                                        </span>
                                    </td>
                                    <td class="hidden md:table-cell border-r border-neutral-200/80 px-6 py-4 align-middle text-center dark:border-neutral-800/70">
                                        <span class="text-xs text-neutral-500 dark:text-neutral-400">
                                            @if($d->min_subtotal !== null)
                                                {{ __('Min. subtotal') }}: Rp {{ number_format($d->min_subtotal, 0, ',', '.') }}
                                            @else
                                                {{ __('No minimum subtotal') }}
                                            @endif
                                        </span>
                                    </td>
                                    <td class="hidden lg:table-cell border-r border-neutral-200/80 px-6 py-4 align-middle text-center dark:border-neutral-800/70">
                                        <span class="text-xs text-neutral-500 dark:text-neutral-400">
                                            @php
                                                $start = $d->tanggal_mulai;
                                                $end   = $d->tanggal_selesai;
                                            @endphp
                                            @if($start && $end)
                                                {{ $start->format('d M Y') }} - {{ $end->format('d M Y') }}
                                            @elseif($start)
                                                {{ __('From') }} {{ $start->format('d M Y') }}
                                            @elseif($end)
                                                {{ __('Until') }} {{ $end->format('d M Y') }}
                                            @else
                                                {{ __('No date limit') }}
                                            @endif
                                        </span>
                                    </td>
                                    <td class="border-r border-neutral-200/80 px-6 py-4 align-middle text-center dark:border-neutral-800/70">
                                        @if($d->is_active)
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
                                    </td>
                                    <td class="px-6 py-4 align-middle text-center">
                                        <div class="mx-auto grid w-full max-w-[420px] grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                            @can('diskon.manage')
                                                <div class="contents">
                                                    <flux:button
                                                        size="sm"
                                                        icon="{{ $d->is_active ? 'pause-circle' : 'check-circle' }}"
                                                        variant="ghost"
                                                        class="btn-ghost-accent w-full rounded-2xl shadow-sm transition whitespace-nowrap justify-center"
                                                        wire:click="toggleActive({{ $d->id }})"
                                                        title="{{ $d->is_active ? __('Deactivate') : __('Activate') }}"
                                                    >
                                                        {{ $d->is_active ? __('Deactivate') : __('Activate') }}
                                                    </flux:button>
                                                    <flux:link :href="route('diskon.edit', $d, false)" wire:navigate>
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
                                                    <flux:modal.trigger name="confirm-delete-diskon-desktop">
                                                        <flux:button
                                                            size="sm"
                                                            icon="trash"
                                                            variant="danger"
                                                            class="w-full rounded-2xl shadow-sm transition whitespace-nowrap justify-center sm:col-span-2 lg:col-span-1"
                                                            wire:click="confirmDelete({{ $d->id }})"
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

        @php($selectedDiskon = $items->firstWhere('id', $confirmingDeleteId))

        <!-- Delete confirmation - mobile -->
        <flux:modal name="confirm-delete-diskon" focusable variant="flyout" position="bottom" :closable="false" class="rounded-t-3xl sm:rounded-xl">
            <div class="space-y-4 p-2 max-h-[60dvh] overflow-y-auto">
                <div class="flex items-center justify-center">
                    <div class="mx-auto mb-1 h-1.5 w-12 rounded-full bg-neutral-300 dark:bg-neutral-700"></div>
                </div>

                <div class="space-y-2">
                    <flux:heading size="lg">{{ __('Delete this discount?') }}</flux:heading>
                    <flux:subheading>
                        {{ __('This action cannot be undone. Discounts that are already used in orders cannot be deleted.') }}
                    </flux:subheading>
                </div>

                @if ($selectedDiskon)
                    <div class="rounded-2xl border border-red-200/60 bg-red-50/60 p-4 text-sm text-red-800 dark:border-red-800/60 dark:bg-red-900/20 dark:text-red-200">
                        <p class="font-semibold">{{ $selectedDiskon->kode }}</p>
                        <p class="text-xs opacity-80">
                            @if($selectedDiskon->tipe === 'percent')
                                -{{ number_format($selectedDiskon->nilai, 2) }}%
                            @else
                                -Rp {{ number_format($selectedDiskon->nilai, 0, ',', '.') }}
                            @endif
                        </p>
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

        <!-- Delete confirmation - desktop -->
        <flux:modal name="confirm-delete-diskon-desktop" focusable class="mx-4 max-w-full sm:mx-auto sm:max-w-lg">
            <div class="space-y-4 p-2">
                <div class="space-y-2">
                    <flux:heading size="lg">{{ __('Delete this discount?') }}</flux:heading>
                    <flux:subheading>
                        {{ __('This action cannot be undone. Discounts that are already used in orders cannot be deleted.') }}
                    </flux:subheading>
                </div>

                @if ($selectedDiskon)
                    <div class="rounded-2xl border border-red-200/60 bg-red-50/60 p-4 text-sm text-red-800 dark:border-red-800/60 dark:bg-red-900/20 dark:text-red-200">
                        <p class="font-semibold">{{ $selectedDiskon->kode }}</p>
                        <p class="text-xs opacity-80">
                            @if($selectedDiskon->tipe === 'percent')
                                -{{ number_format($selectedDiskon->nilai, 2) }}%
                            @else
                                -Rp {{ number_format($selectedDiskon->nilai, 0, ',', '.') }}
                            @endif
                        </p>
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
                    this.message = event.detail?.message || '{{ __('Discount updated successfully.') }}';
                    this.show = true;
                    clearTimeout(this.timeout);
                    this.timeout = setTimeout(() => this.show = false, 3500);
                }
            }"
            x-on:diskon-toast.window="handle($event)"
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
