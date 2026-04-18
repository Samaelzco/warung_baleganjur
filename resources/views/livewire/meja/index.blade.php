<?php

use App\Models\Meja;
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

    public function confirmDelete(int $id): void
    {
        $this->authorizeManage();
        $this->confirmingDeleteId = $id;
    }

    public function delete(): void
    {
        $this->authorizeManage();
        if ($this->confirmingDeleteId) {
            Meja::where('id', $this->confirmingDeleteId)->delete();
            Cache::forget('admin:meja:stats:v1');
            $this->confirmingDeleteId = null;
            // Close both mobile (bottom sheet) and desktop (centered) delete modals
            $this->dispatch('modal-close', name: 'confirm-delete-meja');
            $this->dispatch('modal-close', name: 'confirm-delete-meja-desktop');
            $this->dispatch('meja-toast', message: __('Table deleted successfully.'));
        }
    }

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingStatusFilter(): void { $this->resetPage(); }

    protected function authorizeManage(): void
    {
        abort_unless(auth()->check() && auth()->user()->can('meja.manage'), 403);
    }

}; ?>

<section class="w-full">
    @php
        $query = Meja::query()->select([
            'id',
            'nomor_meja',
            'qr_token',
            'status',
            'kapasitas',
            'created_at',
            'updated_at',
        ]);
        if (!empty($search)) {
            $query->where(function ($sub) use ($search) {
                $sub->where('nomor_meja', 'like', '%'.$search.'%')
                    ->orWhere('qr_token', 'like', '%'.$search.'%');
            });
        }
        if ($statusFilter !== 'all' && !empty($statusFilter)) {
            $query->where('status', $statusFilter);
        }
        $items = $query->orderBy('nomor_meja')->paginate(5);
        $statusMeta = [
            'kosong' => [
                'label' => __('Empty'),
                'badge' => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100 dark:bg-emerald-900/40 dark:text-emerald-200 dark:ring-emerald-800/60',
                'dot' => 'bg-emerald-500',
                'hint' => __('Ready to welcome guests'),
            ],
            'terisi' => [
                'label' => __('Occupied'),
                'badge' => 'bg-amber-50 text-amber-700 ring-1 ring-amber-100 dark:bg-amber-900/40 dark:text-amber-200 dark:ring-amber-800/60',
                'dot' => 'bg-amber-500',
                'hint' => __('Currently in use'),
            ],
            'reservasi' => [
                'label' => __('Reserved'),
                'badge' => 'bg-purple-50 text-purple-700 ring-1 ring-purple-100 dark:bg-purple-900/40 dark:text-purple-200 dark:ring-purple-800/60',
                'dot' => 'bg-purple-500',
                'hint' => __('Booked in advance'),
            ],
        ];

        $stats = Cache::remember('admin:meja:stats:v1', 10, fn () => [
            'total' => Meja::query()->count(),
            'status_counts' => Meja::query()
                ->select('status')
                ->selectRaw('count(*) as agg')
                ->groupBy('status')
                ->pluck('agg', 'status')
                ->all(),
        ]);

        $statusCounts = collect($stats['status_counts'] ?? []);
        $totalCount = (int) ($stats['total'] ?? 0);
    @endphp

    <div class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <flux:heading size="xl" level="1">{{ __('Tables') }}</flux:heading>
            <div class="flex flex-wrap items-center gap-2">
                @can('meja.manage')
                    <flux:link class="order-1 sm:order-2" :href="route('meja.create', [], false)" wire:navigate>
                        <flux:button icon="plus" variant="primary" class="btn-brand">{{ __('Create') }}</flux:button>
                    </flux:link>
                @endcan
                <flux:link class="order-2 sm:order-1" :href="route('meja.print', [], false)" target="_blank">
                    <flux:button icon="printer" variant="ghost" class="btn-ghost-accent">{{ __('Print (PDF)') }}</flux:button>
                </flux:link>
            </div>
        </div>

        <!-- Mobile: horizontal scroll chips -->
        <div class="block sm:hidden -mx-4 overflow-x-auto no-scrollbar">
            <div class="flex gap-2 px-4">
                <div class="whitespace-nowrap rounded-full border border-neutral-200/70 bg-white/85 px-3 py-2 text-[11px] shadow-sm dark:border-neutral-700/60 dark:bg-neutral-900/70">
                    <span class="font-semibold text-neutral-900 dark:text-white">{{ $totalCount }}</span>
                    <span class="text-neutral-500 dark:text-neutral-400">{{ __('Tables') }}</span>
                </div>
                @foreach ($statusMeta as $key => $meta)
                    <div class="whitespace-nowrap rounded-full border border-neutral-200/70 bg-white/85 px-3 py-2 text-[11px] shadow-sm dark:border-neutral-700/60 dark:bg-neutral-900/70">
                        <span class="inline-flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full {{ $meta['dot'] }}"></span>
                            <span class="text-neutral-600 dark:text-neutral-300">{{ $meta['label'] }}</span>
                            <span class="font-semibold text-neutral-900 dark:text-white">{{ $statusCounts->get($key, 0) }}</span>
                        </span>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Tablet/Desktop: grid summary cards -->
        <div class="hidden sm:grid gap-2 sm:gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl sm:rounded-2xl border border-neutral-200/70 bg-white/85 p-3 sm:p-4 shadow-sm sm:shadow-lg shadow-neutral-200/40 dark:border-neutral-700/60 dark:bg-neutral-900/70 dark:shadow-black/30">
                <p class="text-[11px] sm:text-xs font-medium uppercase tracking-[0.18em] sm:tracking-[0.2em] text-neutral-400">{{ __('Total Tables') }}</p>
                <div class="mt-2 sm:mt-3 flex items-baseline gap-2">
                    <span class="text-2xl sm:text-3xl font-semibold text-neutral-900 dark:text-white">{{ $totalCount }}</span>
                    <span class="text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('records') }}</span>
                </div>
            </div>
            @foreach ($statusMeta as $key => $meta)
                <div class="rounded-xl sm:rounded-2xl border border-neutral-200/70 bg-white/85 p-3 sm:p-4 shadow-sm sm:shadow-lg shadow-neutral-200/40 dark:border-neutral-700/60 dark:bg-neutral-900/70 dark:shadow-black/30">
                    <div class="flex items-center gap-2 text-[11px] sm:text-xs font-medium uppercase tracking-[0.18em] sm:tracking-[0.2em] text-neutral-400">
                        <span class="h-2 w-2 rounded-full {{ $meta['dot'] }}"></span>
                        <span>{{ $meta['label'] }}</span>
                    </div>
                    <div class="mt-2 sm:mt-3 flex items-baseline gap-2">
                        <span class="text-2xl sm:text-3xl font-semibold text-neutral-900 dark:text-white">{{ $statusCounts->get($key, 0) }}</span>
                        <span class="text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('Tables') }}</span>
                    </div>
                    <p class="mt-1 text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ $meta['hint'] }}</p>
                </div>
            @endforeach
        </div>

        <!-- Search and filter -->
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex-1 min-w-0">
                <flux:input wire:model.live.debounce.1000ms="search" :placeholder="__('Search table number or token')" />
            </div>
            <div class="grid grid-cols-[1fr_auto] items-center gap-2 sm:flex sm:items-center sm:justify-end sm:gap-2">
                <flux:select
                    wire:model.live="statusFilter"
                    class="min-w-0 w-full sm:w-52 rounded-full border-neutral-200 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900 focus:outline-hidden focus:ring-2 focus:ring-[color:var(--brand-accent)] focus:ring-offset-2 focus:ring-offset-[color:var(--brand-accent-foreground)]"
                >
                    <option value="all">{{ __('All') }}</option>
                    <option value="kosong">{{ __('Empty') }}</option>
                    <option value="terisi">{{ __('Occupied') }}</option>
                    <option value="reservasi">{{ __('Reserved') }}</option>
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
                @forelse ($items as $m)
                    <div class="rounded-2xl border border-neutral-200/80 bg-white p-4 shadow-sm dark:border-neutral-800/70 dark:bg-neutral-900">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-base font-semibold text-neutral-900 dark:text-white">{{ __('Table') }} {{ $m->nomor_meja }}</div>
                                <div class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">{{ __('Created at') }} {{ $m->created_at?->format('d M Y') }}</div>
                            </div>
                            @php($status = $m->status)
                            <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-[11px] font-semibold {{ $statusMeta[$status]['badge'] ?? 'bg-neutral-100 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-200' }}">
                                <span class="h-2 w-2 rounded-full {{ $statusMeta[$status]['dot'] ?? 'bg-neutral-400' }}"></span>
                                {{ $statusMeta[$status]['label'] ?? ucfirst($status) }}
                            </span>
                        </div>

                        <div class="mt-3 flex items-center gap-3">
                            <div class="inline-flex items-center justify-center rounded-xl border border-neutral-200/70 bg-white p-1 shadow-sm dark:border-neutral-800/60 dark:bg-neutral-950">
                                <img alt="QR" class="h-24 w-24 rounded-lg border border-white/70 bg-white object-contain dark:border-neutral-800" src="{{ route('meja.qr', ['token' => $m->qr_token, 'size' => 192, 'format' => 'svg'], false) }}" />
                            </div>
                            <div class="text-xs text-neutral-500 dark:text-neutral-400">
                                <p class="font-medium text-neutral-800 dark:text-neutral-200">{{ __('Scan to order') }}</p>
                                <p>{{ __('Last updated') }} {{ $m->updated_at?->diffForHumans() }}</p>
                            </div>
                        </div>

                        <div class="mt-3 text-xs font-medium text-neutral-600 dark:text-neutral-300">
                            {{ __('Capacity') }}: <span class="font-semibold text-neutral-900 dark:text-white">{{ (int) ($m->kapasitas ?? 4) }}</span>
                        </div>

                        <div class="mt-3 inline-flex items-center gap-2 rounded-2xl border border-neutral-200/80 bg-neutral-50 px-3 py-2 font-mono text-xs tracking-wide text-neutral-600 dark:border-neutral-800/70 dark:bg-neutral-900/60 dark:text-neutral-300">
                            <span>{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::limit($m->qr_token, 12, '...')) }}</span>
                        </div>

                        <div class="mt-4 flex items-center gap-2">
                            <flux:link class="flex-1" :href="route('meja.print', ['ids' => $m->id], false)" target="_blank">
                                <flux:button size="sm" icon="printer" variant="ghost" class="w-full btn-ghost-accent">{{ __('Print (PDF)') }}</flux:button>
                            </flux:link>
                            @can('meja.manage')
                                <flux:link class="flex-1" :href="route('meja.edit', $m, false)" wire:navigate>
                                    <flux:button size="sm" icon="pencil-square" variant="primary" class="w-full btn-accent">{{ __('Edit') }}</flux:button>
                                </flux:link>
                                <flux:modal.trigger name="confirm-delete-meja" class="flex-1">
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

        <!-- Tablet cards -->
        <div class="hidden sm:block lg:hidden">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                @forelse ($items as $m)
                    @php($status = $m->status)
                    <div class="flex h-full flex-col rounded-2xl border border-neutral-200/80 bg-white p-4 shadow-sm dark:border-neutral-800/70 dark:bg-neutral-900">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="truncate text-base font-semibold text-neutral-900 dark:text-white">{{ __('Table') }} {{ $m->nomor_meja }}</div>
                                <div class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">{{ __('Created at') }} {{ $m->created_at?->format('d M Y') }}</div>
                            </div>
                            <span class="shrink-0 inline-flex items-center gap-2 rounded-full px-3 py-1 text-[11px] font-semibold {{ $statusMeta[$status]['badge'] ?? 'bg-neutral-100 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-200' }}">
                                <span class="h-2 w-2 rounded-full {{ $statusMeta[$status]['dot'] ?? 'bg-neutral-400' }}"></span>
                                {{ $statusMeta[$status]['label'] ?? ucfirst($status) }}
                            </span>
                        </div>

                        <div class="mt-4 flex items-center gap-3">
                            <div class="shrink-0 inline-flex items-center justify-center rounded-xl border border-neutral-200/70 bg-white p-1 shadow-sm dark:border-neutral-800/60 dark:bg-neutral-950">
                                <img alt="QR" class="h-20 w-20 rounded-lg border border-white/70 bg-white object-contain dark:border-neutral-800" src="{{ route('meja.qr', ['token' => $m->qr_token, 'size' => 192, 'format' => 'svg'], false) }}" />
                            </div>
                            <div class="min-w-0 text-xs text-neutral-500 dark:text-neutral-400">
                                <p class="font-medium text-neutral-800 dark:text-neutral-200">{{ __('Scan to order') }}</p>
                                <p class="truncate">{{ __('Last updated') }} {{ $m->updated_at?->diffForHumans() }}</p>
                            </div>
                        </div>

                        <div class="mt-3 text-xs font-medium text-neutral-600 dark:text-neutral-300">
                            {{ __('Capacity') }}: <span class="font-semibold text-neutral-900 dark:text-white">{{ (int) ($m->kapasitas ?? 4) }}</span>
                        </div>

                        <div class="mt-3 inline-flex items-center gap-2 rounded-2xl border border-neutral-200/80 bg-neutral-50 px-3 py-2 font-mono text-xs tracking-wide text-neutral-600 dark:border-neutral-800/70 dark:bg-neutral-900/60 dark:text-neutral-300">
                            <span>{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::limit($m->qr_token, 12, '...')) }}</span>
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-2">
                            <flux:link class="col-span-2" :href="route('meja.print', ['ids' => $m->id], false)" target="_blank">
                                <flux:button size="sm" icon="printer" variant="ghost" class="w-full btn-ghost-accent">{{ __('Print (PDF)') }}</flux:button>
                            </flux:link>
                            @can('meja.manage')
                                <flux:link :href="route('meja.edit', $m, false)" wire:navigate>
                                    <flux:button size="sm" icon="pencil-square" variant="primary" class="w-full btn-accent">{{ __('Edit') }}</flux:button>
                                </flux:link>
                                <flux:modal.trigger name="confirm-delete-meja-desktop">
                                    <flux:button size="sm" icon="trash" variant="danger" class="w-full" wire:click="confirmDelete({{ $m->id }})">{{ __('Delete') }}</flux:button>
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

        <!-- Desktop table -->
        <div class="hidden lg:block rounded-3xl border border-neutral-200/80 bg-gradient-to-b from-white/95 via-white/90 to-white/70 shadow-2xl shadow-neutral-200/60 backdrop-blur-xl dark:border-neutral-800/80 dark:from-neutral-950/80 dark:via-neutral-950/60 dark:to-neutral-950/40 dark:shadow-black/30">
            <div class="overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full md:min-w-[1100px] lg:min-w-full text-sm text-left">
                        <thead>
                        <tr>
                            <th class="hidden sm:table-cell px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">{{ __('ID') }}</th>
                            <th class="px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">{{ __('Table') }}</th>
                            <th class="px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">{{ __('Status') }}</th>
                            <th class="hidden md:table-cell md:min-w-[220px] lg:min-w-0 px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">{{ __('QR Code') }}</th>
                            <th class="hidden md:table-cell md:min-w-[200px] lg:min-w-0 px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">{{ __('QR Token') }}</th>
                            <th class="md:min-w-[280px] lg:min-w-0 px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">{{ __('Actions') }}</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100/80 text-neutral-700 dark:divide-neutral-900/40 dark:text-neutral-200">
                            @forelse($items as $m)
                                <tr class="group transition hover:bg-white/70 focus-within:bg-white/90 dark:hover:bg-neutral-900/40 dark:focus-within:bg-neutral-900/50">
                                    <td class="hidden sm:table-cell px-6 py-4 align-middle">
                                        <span class="inline-flex items-center rounded-full bg-neutral-900/5 px-3 py-1 text-xs font-semibold text-neutral-500 dark:bg-white/5 dark:text-neutral-300">
                                            #{{ str_pad($m->id, 3, '0', STR_PAD_LEFT) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 align-middle">
                                        <div class="flex flex-col">
                                            <span class="text-base font-semibold text-neutral-900 dark:text-white">{{ __('Table') }} {{ $m->nomor_meja }}</span>
                                            <span class="hidden sm:block text-xs text-neutral-500 dark:text-neutral-400">{{ __('Created at') }} {{ $m->created_at?->format('d M Y') }}</span>
                                        </div>
                                    </td>
                                     <td class="px-6 py-4 align-middle">
                                         @php($status = $m->status)
                                         <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold {{ $statusMeta[$status]['badge'] ?? 'bg-neutral-100 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-200' }}">
                                             <span class="h-2 w-2 rounded-full {{ $statusMeta[$status]['dot'] ?? 'bg-neutral-400' }}"></span>
                                             {{ $statusMeta[$status]['label'] ?? ucfirst($status) }}
                                         </span>
                                      </td>
                                    <td class="hidden md:table-cell px-6 py-4 align-middle">
                                        <div class="flex items-center gap-4">
                                            <div class="shrink-0 inline-flex items-center justify-center rounded-2xl border border-neutral-200/70 bg-white p-1 shadow-sm dark:border-neutral-800/60 dark:bg-neutral-950">
                                                <img alt="QR" class="h-16 w-16 lg:h-20 lg:w-20 rounded-xl border border-white/70 bg-white object-contain dark:border-neutral-800" src="{{ route('meja.qr', ['token' => $m->qr_token, 'size' => 192, 'format' => 'svg'], false) }}" />
                                            </div>
                                            <div class="hidden lg:block text-xs text-neutral-500 dark:text-neutral-400">
                                                <p class="font-medium text-neutral-800 dark:text-neutral-200">{{ __('Scan to order') }}</p>
                                                <p>{{ __('Last updated') }} {{ $m->updated_at?->diffForHumans() }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="hidden md:table-cell px-6 py-4 align-middle">
                                        <div class="inline-flex items-center gap-2 rounded-2xl border border-neutral-200/80 bg-neutral-50 px-3 py-2 font-mono text-xs tracking-wide text-neutral-600 dark:border-neutral-800/70 dark:bg-neutral-900/60 dark:text-neutral-300">
                                            <span>{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::limit($m->qr_token, 12, '...')) }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 align-middle">
                                        <div class="flex flex-col items-start gap-2 lg:flex-row lg:flex-wrap lg:items-center">
                                            <div class="flex flex-wrap items-center justify-start gap-2 lg:contents">
                                                <flux:link :href="route('meja.print', ['ids' => $m->id], false)" target="_blank">
                                                    <flux:button
                                                        size="sm"
                                                        icon="printer"
                                                        variant="ghost"
                                                        class="btn-ghost-accent rounded-2xl shadow-sm transition whitespace-nowrap justify-center md:w-24 lg:w-auto"
                                                        title="{{ __('Print (PDF)') }}"
                                                    >
                                                        <span class="hidden md:inline lg:hidden">{{ __('Print') }}</span>
                                                        <span class="md:hidden lg:inline">{{ __('Print (PDF)') }}</span>
                                                    </flux:button>
                                                </flux:link>
                                            </div>
                                            @can('meja.manage')
                                                <div class="flex flex-wrap justify-start gap-2 lg:contents">
                                                    <flux:link :href="route('meja.edit', $m, false)" wire:navigate>
                                                        <flux:button
                                                            size="sm"
                                                            icon="pencil-square"
                                                            variant="primary"
                                                            class="btn-accent rounded-2xl shadow-sm transition whitespace-nowrap justify-center md:w-24 lg:w-auto"
                                                            title="{{ __('Edit') }}"
                                                        >
                                                            {{ __('Edit') }}
                                                        </flux:button>
                                                    </flux:link>
                                                    <flux:modal.trigger name="confirm-delete-meja-desktop">
                                                        <flux:button
                                                            size="sm"
                                                            icon="trash"
                                                            variant="danger"
                                                            class="rounded-2xl shadow-sm transition whitespace-nowrap justify-center md:w-24 lg:w-auto"
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
                                    <td class="px-6 py-8 text-center text-sm text-neutral-500 dark:text-neutral-400" colspan="6">
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

        {{-- Detail modal (removed) --}}
        {{--
        <flux:modal name="detail-meja" focusable class="mx-4 w-[calc(100%-2rem)] sm:mx-auto sm:max-w-xl md:max-w-lg lg:max-w-xl">
            <div class="space-y-4 p-2">
                <div class="flex items-start justify-between gap-2">
                    <div class="space-y-1">
                        <flux:heading size="lg">{{ __('Table') }} {{ $viewing['nomor_meja'] ?? '-' }}</flux:heading>
                        <flux:subheading class="flex flex-wrap items-center gap-x-2 gap-y-1">
                            <span class="text-xs text-neutral-500 dark:text-neutral-400">
                                {{ __('Created') }}: {{ $viewing['created_at'] ?? '-' }}
                            </span>
                            <span class="text-xs text-neutral-500 dark:text-neutral-400">·</span>
                            <span class="text-xs text-neutral-500 dark:text-neutral-400">
                                {{ __('Updated') }} {{ $viewing['updated_human'] ?? '-' }}
                            </span>
                        </flux:subheading>
                    </div>
                </div>

                @php($vStatus = $viewing['status'] ?? null)
                @if ($vStatus)
                    <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold {{ $statusMeta[$vStatus]['badge'] ?? 'bg-neutral-100 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-200' }}">
                        <span class="h-2 w-2 rounded-full {{ $statusMeta[$vStatus]['dot'] ?? 'bg-neutral-400' }}"></span>
                        {{ $statusMeta[$vStatus]['label'] ?? ucfirst((string) $vStatus) }}
                    </span>
                @endif

                <div class="rounded-3xl border border-neutral-200/80 bg-white p-4 shadow-sm dark:border-neutral-800/70 dark:bg-neutral-900">
                    <div class="flex flex-col items-center gap-3">
                        @if (!empty($viewing['qr_token']))
                            <div class="inline-flex items-center justify-center rounded-2xl border border-neutral-200/70 bg-white p-2 shadow-sm dark:border-neutral-800/60 dark:bg-neutral-950">
                                <img
                                    alt="QR"
                                    class="h-48 w-48 rounded-xl border border-white/70 bg-white object-contain dark:border-neutral-800"
                                    src="{{ route('meja.qr', ['token' => $viewing['qr_token'], 'size' => 420, 'format' => 'svg'], false) }}"
                                />
                            </div>
                            <div class="w-full rounded-2xl border border-neutral-200/80 bg-neutral-50 px-3 py-2 font-mono text-xs tracking-wide text-neutral-700 dark:border-neutral-800/70 dark:bg-neutral-900/60 dark:text-neutral-200">
                                {{ $viewing['qr_token'] }}
                            </div>
                        @else
                            <div class="text-sm text-neutral-500 dark:text-neutral-400">{{ __('No QR token') }}</div>
                        @endif
                    </div>
                </div>

                @if (!empty($viewingId))
                    <div class="flex items-center justify-end gap-2">
                        <flux:link :href="route('meja.print', ['ids' => $viewingId], false)" target="_blank">
                            <flux:button icon="printer" variant="primary" class="btn-brand">
                                {{ __('Print (PDF)') }}
                            </flux:button>
                        </flux:link>
                    </div>
                @endif
            </div>
        </flux:modal>
        --}}
        @php($selectedMeja = $items->firstWhere('id', $confirmingDeleteId))

        <flux:modal name="confirm-delete-meja" focusable variant="flyout" position="bottom" :closable="false" class="rounded-t-3xl sm:rounded-xl">
            <div class="space-y-4 p-2 max-h-[60dvh] overflow-y-auto">
                <div class="flex items-center justify-center">
                    <div class="mx-auto mb-1 h-1.5 w-12 rounded-full bg-neutral-300 dark:bg-neutral-700"></div>
                </div>

                <div class="space-y-2">
                    <flux:heading size="lg">{{ __('Delete this table?') }}</flux:heading>
                    <flux:subheading>
                        {{ __('This action cannot be undone. Orders linked to this table will lose their QR link.') }}
                    </flux:subheading>
                </div>

                @if ($selectedMeja)
                    <div class="rounded-2xl border border-red-200/60 bg-red-50/60 p-4 text-sm text-red-800 dark:border-red-800/60 dark:bg-red-900/20 dark:text-red-200">
                        <p class="font-semibold">{{ __('Table') }} {{ $selectedMeja->nomor_meja }}</p>
                        <p class="text-xs opacity-80">{{ __('Created at') }} {{ $selectedMeja->created_at?->format('d M Y') }}</p>
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

        <!-- Desktop centered delete modal -->
        @php($selectedMeja = $items->firstWhere('id', $confirmingDeleteId))
        <flux:modal name="confirm-delete-meja-desktop" focusable class="mx-4 max-w-full sm:mx-auto sm:max-w-lg">
            <div class="space-y-4 p-2">
                <div class="space-y-2">
                    <flux:heading size="lg">{{ __('Delete this table?') }}</flux:heading>
                    <flux:subheading>
                        {{ __('This action cannot be undone. Orders linked to this table will lose their QR link.') }}
                    </flux:subheading>
                </div>

                @if ($selectedMeja)
                    <div class="rounded-2xl border border-red-200/60 bg-red-50/60 p-4 text-sm text-red-800 dark:border-red-800/60 dark:bg-red-900/20 dark:text-red-200">
                        <p class="font-semibold">{{ __('Table') }} {{ $selectedMeja->nomor_meja }}</p>
                        <p class="text-xs opacity-80">{{ __('Created at') }} {{ $selectedMeja->created_at?->format('d M Y') }}</p>
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

        

        <div
            x-data="{
                show: false,
                message: '',
                timeout: null,
                handle(event) {
                    this.message = event.detail?.message || '{{ __('Table deleted successfully.') }}';
                    this.show = true;
                    clearTimeout(this.timeout);
                    this.timeout = setTimeout(() => this.show = false, 3500);
                }
            }"
            x-on:meja-toast.window="handle($event)"
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

@if (session('meja_toast'))
    <script>
        window.addEventListener('load', () => {
            try {
                const message = @js(session('meja_toast'));
                window.dispatchEvent(new CustomEvent('meja-toast', { detail: { message } }));
            } catch (e) {}
        });
    </script>
@endif
