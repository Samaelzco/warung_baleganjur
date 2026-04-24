<?php

use App\Models\Pesanan;
use App\Services\TableWaitingListService;
use Illuminate\Support\Facades\Cache;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $search = '';
    public string $tableFilter = 'all';

    protected $queryString = [
        'search' => ['except' => ''],
        'tableFilter' => ['except' => 'all'],
        'page' => ['except' => 1],
    ];

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingTableFilter(): void { $this->resetPage(); }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->tableFilter = 'all';
        $this->resetPage();
    }

    public function activate(int $id): void
    {
        $this->authorizeManage();

        $waitingList = Pesanan::query()
            ->with('meja:id,nomor_meja,kapasitas,status')
            ->whereKey($id)
            ->where('status', 'booking')
            ->firstOrFail();

        $service = app(TableWaitingListService::class);
        if (!$waitingList->meja || !$service->hasCapacity($waitingList->meja, max((int) $waitingList->jumlah_orang, 1))) {
            $this->dispatch('waiting-list-toast', message: __('Table capacity is not available yet.'));
            return;
        }

        $waitingList->forceFill(['status' => 'menunggu'])->save();
        $service->syncMejaStatus($waitingList->meja);
        $service->forgetKitchenCache();
        Cache::forget('waiting-list:list:stats:v1');

        $this->dispatch('waiting-list-toast', message: __('Waiting list activated.'));
    }

    public function cancel(int $id): void
    {
        $this->authorizeManage();

        $waitingList = Pesanan::query()
            ->whereKey($id)
            ->where('status', 'booking')
            ->firstOrFail();

        $waitingList->forceFill(['status' => 'batal'])->save();
        Cache::forget('waiting-list:list:stats:v1');

        $this->dispatch('waiting-list-toast', message: __('Waiting list cancelled.'));
    }

    public function activateNext(int $mejaId): void
    {
        $this->authorizeManage();

        $count = app(TableWaitingListService::class)->activateNextWaitingLists($mejaId);
        if ($count > 0) {
            Cache::forget('waiting-list:list:stats:v1');
        }
        $this->dispatch('waiting-list-toast', message: $count > 0 ? __('Next waiting list activated.') : __('No waiting list could be activated.'));
    }

    protected function authorizeManage(): void
    {
        abort_unless(auth()->check() && auth()->user()->can('waiting-list.manage'), 403);
    }
}; ?>

<section class="w-full">
    @php
        $query = Pesanan::query()
            ->select(['id', 'meja_id', 'kode_pesanan', 'customer_name', 'customer_note', 'jumlah_orang', 'subtotal', 'total_harga', 'waktu_pesan'])
            ->with([
                'meja:id,nomor_meja,kapasitas,status',
                'details:id,pesanan_id,menu_id,qty',
                'details.menu:id,nama_menu',
            ])
            ->where('status', 'booking');

        if ($search !== '') {
            $query->where(function ($sub) use ($search) {
                $sub->where('kode_pesanan', 'like', '%' . $search . '%')
                    ->orWhere('customer_name', 'like', '%' . $search . '%')
                    ->orWhereHas('meja', fn ($q) => $q->where('nomor_meja', 'like', '%' . $search . '%'));
            });
        }

        if ($tableFilter !== 'all') {
            $query->where('meja_id', (int) $tableFilter);
        }

        $items = $query->orderBy('waktu_pesan')->orderBy('id')->paginate(5);
        $mejas = \App\Models\Meja::query()->select(['id', 'nomor_meja', 'kapasitas'])->orderBy('nomor_meja')->get();
        $service = app(TableWaitingListService::class);

        $stats = Cache::remember('waiting-list:list:stats:v1', 10, fn () => [
            'waiting' => Pesanan::query()->where('status', 'booking')->count(),
            'guests' => (int) Pesanan::query()->where('status', 'booking')->sum('jumlah_orang'),
            'tables' => Pesanan::query()->where('status', 'booking')->distinct('meja_id')->count('meja_id'),
            'total' => (float) Pesanan::query()->where('status', 'booking')->sum('total_harga'),
        ]);

        $queuedCount = (int) ($stats['waiting'] ?? 0);
        $guestCount = (int) ($stats['guests'] ?? 0);
        $tableCount = (int) ($stats['tables'] ?? 0);
        $queuedTotal = (float) ($stats['total'] ?? 0);
    @endphp

    <div class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <flux:heading size="xl" level="1">{{ __('Waiting List') }}</flux:heading>
            <div class="flex flex-wrap items-center gap-2">
                <flux:link class="order-2 sm:order-1" :href="route('customer.waiting-list.index')" target="_blank">
                    <flux:button icon="arrow-top-right-on-square" variant="ghost" class="btn-ghost-accent">{{ __('Open Customer Page') }}</flux:button>
                </flux:link>
                @can('waiting-list.manage')
                    <flux:link class="order-1 sm:order-2" :href="route('waiting-list.create', [], false)" wire:navigate>
                        <flux:button icon="plus" variant="primary" class="btn-brand">{{ __('Create') }}</flux:button>
                    </flux:link>
                @endcan
            </div>
        </div>

        <!-- Mobile: horizontal scroll chips -->
        <div class="block sm:hidden -mx-4 overflow-x-auto no-scrollbar">
            <div class="flex gap-2 px-4">
                <div class="whitespace-nowrap rounded-full border border-neutral-200/70 bg-white/85 px-3 py-2 text-[11px] shadow-sm dark:border-neutral-700/60 dark:bg-neutral-900/70">
                    <span class="font-semibold text-neutral-900 dark:text-white">{{ $queuedCount }}</span>
                    <span class="text-neutral-500 dark:text-neutral-400">{{ __('Waiting') }}</span>
                </div>
                <div class="whitespace-nowrap rounded-full border border-neutral-200/70 bg-white/85 px-3 py-2 text-[11px] shadow-sm dark:border-neutral-700/60 dark:bg-neutral-900/70">
                    <span class="inline-flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                        <span class="text-neutral-600 dark:text-neutral-300">{{ __('Guests') }}</span>
                        <span class="font-semibold text-neutral-900 dark:text-white">{{ $guestCount }}</span>
                    </span>
                </div>
                <div class="whitespace-nowrap rounded-full border border-neutral-200/70 bg-white/85 px-3 py-2 text-[11px] shadow-sm dark:border-neutral-700/60 dark:bg-neutral-900/70">
                    <span class="inline-flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-sky-500"></span>
                        <span class="text-neutral-600 dark:text-neutral-300">{{ __('Tables') }}</span>
                        <span class="font-semibold text-neutral-900 dark:text-white">{{ $tableCount }}</span>
                    </span>
                </div>
            </div>
        </div>

        <!-- Tablet/Desktop: grid summary cards -->
        <div class="hidden sm:grid gap-2 sm:gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl sm:rounded-2xl border border-neutral-200/70 bg-white/85 p-3 sm:p-4 shadow-sm sm:shadow-lg shadow-neutral-200/40 dark:border-neutral-700/60 dark:bg-neutral-900/70 dark:shadow-black/30">
                <p class="text-[11px] sm:text-xs font-medium uppercase tracking-[0.18em] sm:tracking-[0.2em] text-neutral-400">{{ __('Waiting') }}</p>
                <div class="mt-2 sm:mt-3 flex items-baseline gap-2">
                    <span class="text-2xl sm:text-3xl font-semibold text-neutral-900 dark:text-white">{{ $queuedCount }}</span>
                    <span class="text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('orders') }}</span>
                </div>
            </div>
            <div class="rounded-xl sm:rounded-2xl border border-neutral-200/70 bg-white/85 p-3 sm:p-4 shadow-sm sm:shadow-lg shadow-neutral-200/40 dark:border-neutral-700/60 dark:bg-neutral-900/70 dark:shadow-black/30">
                <div class="flex items-center gap-2 text-[11px] sm:text-xs font-medium uppercase tracking-[0.18em] sm:tracking-[0.2em] text-neutral-400">
                    <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                    <span>{{ __('Guests') }}</span>
                </div>
                <div class="mt-2 sm:mt-3 flex items-baseline gap-2">
                    <span class="text-2xl sm:text-3xl font-semibold text-neutral-900 dark:text-white">{{ $guestCount }}</span>
                    <span class="text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('people') }}</span>
                </div>
                <p class="mt-1 text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('Waiting for available seats') }}</p>
            </div>
            <div class="rounded-xl sm:rounded-2xl border border-neutral-200/70 bg-white/85 p-3 sm:p-4 shadow-sm sm:shadow-lg shadow-neutral-200/40 dark:border-neutral-700/60 dark:bg-neutral-900/70 dark:shadow-black/30">
                <div class="flex items-center gap-2 text-[11px] sm:text-xs font-medium uppercase tracking-[0.18em] sm:tracking-[0.2em] text-neutral-400">
                    <span class="h-2 w-2 rounded-full bg-sky-500"></span>
                    <span>{{ __('Tables') }}</span>
                </div>
                <div class="mt-2 sm:mt-3 flex items-baseline gap-2">
                    <span class="text-2xl sm:text-3xl font-semibold text-neutral-900 dark:text-white">{{ $tableCount }}</span>
                    <span class="text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('tables') }}</span>
                </div>
                <p class="mt-1 text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('Tables with waiting list') }}</p>
            </div>
            <div class="rounded-xl sm:rounded-2xl border border-neutral-200/70 bg-white/85 p-3 sm:p-4 shadow-sm sm:shadow-lg shadow-neutral-200/40 dark:border-neutral-700/60 dark:bg-neutral-900/70 dark:shadow-black/30">
                <p class="text-[11px] sm:text-xs font-medium uppercase tracking-[0.18em] sm:tracking-[0.2em] text-neutral-400">{{ __('Potential Total') }}</p>
                <div class="mt-2 sm:mt-3 flex items-baseline gap-2">
                    <span class="text-2xl sm:text-3xl font-semibold text-neutral-900 dark:text-white">Rp {{ number_format($queuedTotal, 0, ',', '.') }}</span>
                </div>
            </div>
        </div>

        <!-- Search and filter -->
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex-1 min-w-0">
                <flux:input wire:model.live.debounce.1000ms="search" :placeholder="__('Search waiting list, customer, or table')" />
            </div>
            <div class="grid grid-cols-[1fr_auto] items-center gap-2 sm:flex sm:items-center sm:justify-end sm:gap-2">
                <flux:select
                    wire:model.live="tableFilter"
                    class="min-w-0 w-full sm:w-56 rounded-full border-neutral-200 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900 focus:outline-hidden focus:ring-2 focus:ring-[color:var(--brand-accent)] focus:ring-offset-2 focus:ring-offset-[color:var(--brand-accent-foreground)]"
                >
                    <option value="all">{{ __('All Tables') }}</option>
                    @foreach ($mejas as $m)
                        <option value="{{ $m->id }}">{{ __('Table') }} {{ $m->nomor_meja }}</option>
                    @endforeach
                </flux:select>
                <flux:button
                    size="sm"
                    variant="ghost"
                    class="btn-ghost-accent whitespace-nowrap justify-self-end"
                    wire:click="clearFilters"
                >
                    {{ __('Clear') }}
                </flux:button>
            </div>
        </div>

        <!-- Mobile cards -->
        <div class="block sm:hidden">
            <div class="grid gap-3">
                @forelse ($items as $item)
                    @php
                        $remaining = $item->meja ? $service->remainingSeats($item->meja) : 0;
                        $guests = max((int) $item->jumlah_orang, 1);
                        $canActivate = $item->meja && $remaining >= $guests;
                    @endphp
                    <div class="rounded-2xl border border-neutral-200/80 bg-white p-4 shadow-sm dark:border-neutral-800/70 dark:bg-neutral-900">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="truncate font-mono text-base font-semibold text-neutral-900 dark:text-white">{{ $item->kode_pesanan }}</div>
                                <div class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">{{ $item->waktu_pesan?->format('d M Y H:i') }}</div>
                            </div>
                            <span class="shrink-0 inline-flex items-center gap-2 rounded-full bg-amber-50 px-3 py-1 text-[11px] font-semibold text-amber-700 ring-1 ring-amber-100 dark:bg-amber-900/40 dark:text-amber-200 dark:ring-amber-800/60">
                                <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                                {{ __('Waiting') }}
                            </span>
                        </div>

                        <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
                            <div class="rounded-xl border border-neutral-200/70 bg-neutral-50/60 p-3 dark:border-neutral-800/70 dark:bg-neutral-950/30">
                                <p class="text-neutral-500 dark:text-neutral-400">{{ __('Customer') }}</p>
                                <p class="mt-1 truncate font-semibold text-neutral-900 dark:text-white">{{ $item->customer_name ?: __('Guest') }}</p>
                            </div>
                            <div class="rounded-xl border border-neutral-200/70 bg-neutral-50/60 p-3 dark:border-neutral-800/70 dark:bg-neutral-950/30">
                                <p class="text-neutral-500 dark:text-neutral-400">{{ __('Table') }}</p>
                                <p class="mt-1 font-semibold text-neutral-900 dark:text-white">{{ $item->meja ? __('Table') . ' ' . $item->meja->nomor_meja : '-' }}</p>
                            </div>
                            <div class="rounded-xl border border-neutral-200/70 bg-neutral-50/60 p-3 dark:border-neutral-800/70 dark:bg-neutral-950/30">
                                <p class="text-neutral-500 dark:text-neutral-400">{{ __('Guests') }}</p>
                                <p class="mt-1 font-semibold text-neutral-900 dark:text-white">{{ $guests }}</p>
                            </div>
                            <div class="rounded-xl border border-neutral-200/70 bg-neutral-50/60 p-3 dark:border-neutral-800/70 dark:bg-neutral-950/30">
                                <p class="text-neutral-500 dark:text-neutral-400">{{ __('Remaining') }}</p>
                                <p class="mt-1 font-semibold text-neutral-900 dark:text-white">{{ $remaining }} / {{ (int) ($item->meja?->kapasitas ?? 0) }}</p>
                            </div>
                        </div>

                        @if ($item->customer_note)
                            <div class="mt-3 text-xs text-neutral-500 dark:text-neutral-400">{{ \Illuminate\Support\Str::limit($item->customer_note, 120) }}</div>
                        @endif

                        <div class="mt-4 rounded-xl border border-neutral-200/70 bg-neutral-50/50 p-3 text-sm dark:border-neutral-800/70 dark:bg-neutral-950/30">
                            <div class="flex items-center justify-between gap-3 text-xs font-semibold tracking-wide text-neutral-500 dark:text-neutral-400">
                                <span>{{ __('Items') }}</span>
                                <span class="whitespace-nowrap">Rp {{ number_format((float) $item->total_harga, 0, ',', '.') }}</span>
                            </div>
                            <div class="mt-2 max-h-32 space-y-1 overflow-y-auto pr-2">
                                @foreach ($item->details as $detail)
                                    <div class="flex items-start justify-between gap-3 text-xs">
                                        <span class="min-w-0 truncate text-neutral-700 dark:text-neutral-200">{{ $detail->menu?->nama_menu ?? __('Menu') }}</span>
                                        <span class="shrink-0 font-semibold text-neutral-900 dark:text-white">x{{ (int) $detail->qty }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        @can('waiting-list.manage')
                            <div class="mt-4 grid grid-cols-2 gap-2">
                                <flux:button size="sm" icon="play" variant="primary" class="btn-brand w-full" wire:click="activate({{ $item->id }})" :disabled="!$canActivate">{{ __('Activate') }}</flux:button>
                                <flux:button size="sm" icon="x-mark" variant="danger" class="w-full" wire:click="cancel({{ $item->id }})">{{ __('Cancel') }}</flux:button>
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
                @forelse ($items as $item)
                    @php
                        $remaining = $item->meja ? $service->remainingSeats($item->meja) : 0;
                        $guests = max((int) $item->jumlah_orang, 1);
                        $canActivate = $item->meja && $remaining >= $guests;
                    @endphp
                    <div class="flex h-full flex-col rounded-2xl border border-neutral-200/80 bg-white p-4 shadow-sm dark:border-neutral-800/70 dark:bg-neutral-900">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="truncate font-mono text-base font-semibold text-neutral-900 dark:text-white">{{ $item->kode_pesanan }}</div>
                                <div class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">{{ $item->waktu_pesan?->format('d M Y H:i') }}</div>
                            </div>
                            <span class="shrink-0 inline-flex items-center gap-2 rounded-full bg-amber-50 px-3 py-1 text-[11px] font-semibold text-amber-700 ring-1 ring-amber-100 dark:bg-amber-900/40 dark:text-amber-200 dark:ring-amber-800/60">
                                <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                                {{ __('Waiting') }}
                            </span>
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-2 text-xs">
                            <div class="min-w-0">
                                <p class="text-neutral-500 dark:text-neutral-400">{{ __('Customer') }}</p>
                                <p class="mt-1 truncate font-semibold text-neutral-900 dark:text-white">{{ $item->customer_name ?: __('Guest') }}</p>
                            </div>
                            <div>
                                <p class="text-neutral-500 dark:text-neutral-400">{{ __('Table') }}</p>
                                <p class="mt-1 font-semibold text-neutral-900 dark:text-white">{{ $item->meja ? __('Table') . ' ' . $item->meja->nomor_meja : '-' }}</p>
                            </div>
                            <div>
                                <p class="text-neutral-500 dark:text-neutral-400">{{ __('Guests') }}</p>
                                <p class="mt-1 font-semibold text-neutral-900 dark:text-white">{{ $guests }}</p>
                            </div>
                            <div>
                                <p class="text-neutral-500 dark:text-neutral-400">{{ __('Remaining') }}</p>
                                <p class="mt-1 font-semibold text-neutral-900 dark:text-white">{{ $remaining }} / {{ (int) ($item->meja?->kapasitas ?? 0) }}</p>
                            </div>
                        </div>

                        <div class="mt-4 flex-1 rounded-xl border border-neutral-200/70 bg-neutral-50/50 p-3 text-sm dark:border-neutral-800/70 dark:bg-neutral-950/30">
                            <div class="flex items-center justify-between gap-3 text-xs font-semibold tracking-wide text-neutral-500 dark:text-neutral-400">
                                <span>{{ __('Items') }}</span>
                                <span class="whitespace-nowrap">Rp {{ number_format((float) $item->total_harga, 0, ',', '.') }}</span>
                            </div>
                            <div class="mt-2 max-h-32 space-y-1 overflow-y-auto pr-2">
                                @foreach ($item->details as $detail)
                                    <div class="flex items-start justify-between gap-3 text-xs">
                                        <span class="min-w-0 truncate text-neutral-700 dark:text-neutral-200">{{ $detail->menu?->nama_menu ?? __('Menu') }}</span>
                                        <span class="shrink-0 font-semibold text-neutral-900 dark:text-white">x{{ (int) $detail->qty }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        @can('waiting-list.manage')
                            <div class="mt-4 grid grid-cols-2 gap-2">
                                <flux:button size="sm" icon="play" variant="primary" class="btn-brand w-full" wire:click="activate({{ $item->id }})" :disabled="!$canActivate">{{ __('Activate') }}</flux:button>
                                <flux:button size="sm" icon="x-mark" variant="danger" class="w-full" wire:click="cancel({{ $item->id }})">{{ __('Cancel') }}</flux:button>
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
                <table class="min-w-full md:min-w-[1100px] lg:min-w-full text-sm text-left">
                    <thead>
                        <tr>
                            <th class="px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">{{ __('Waiting Code') }}</th>
                            <th class="px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">{{ __('Customer') }}</th>
                            <th class="px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">{{ __('Table') }}</th>
                            <th class="hidden xl:table-cell px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">{{ __('Items') }}</th>
                            <th class="px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">{{ __('Total') }}</th>
                            <th class="md:min-w-[260px] lg:min-w-0 px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100/80 text-neutral-700 dark:divide-neutral-900/40 dark:text-neutral-200">
                        @forelse ($items as $item)
                            @php
                                $remaining = $item->meja ? $service->remainingSeats($item->meja) : 0;
                                $guests = max((int) $item->jumlah_orang, 1);
                                $canActivate = $item->meja && $remaining >= $guests;
                            @endphp
                            <tr class="group transition hover:bg-white/70 focus-within:bg-white/90 dark:hover:bg-neutral-900/40 dark:focus-within:bg-neutral-900/50">
                                <td class="px-6 py-4 align-middle">
                                    <div class="inline-flex items-center gap-2 rounded-2xl border border-neutral-200/80 bg-neutral-50 px-3 py-2 font-mono text-xs tracking-wide text-neutral-600 dark:border-neutral-800/70 dark:bg-neutral-900/60 dark:text-neutral-300">
                                        <span>{{ $item->kode_pesanan }}</span>
                                    </div>
                                    <div class="mt-2 text-xs text-neutral-500 dark:text-neutral-400">{{ $item->waktu_pesan?->format('d M Y H:i') }}</div>
                                </td>
                                <td class="px-6 py-4 align-middle">
                                    <div class="flex flex-col">
                                        <span class="text-base font-semibold text-neutral-900 dark:text-white">{{ $item->customer_name ?: __('Guest') }}</span>
                                        <span class="text-xs text-neutral-500 dark:text-neutral-400">{{ __('Guests') }}: {{ $guests }}</span>
                                    </div>
                                    @if ($item->customer_note)
                                        <div class="mt-1 max-w-xs text-xs text-neutral-500 dark:text-neutral-400">{{ \Illuminate\Support\Str::limit($item->customer_note, 100) }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 align-middle">
                                    <div class="flex flex-col">
                                        <span class="text-base font-semibold text-neutral-900 dark:text-white">{{ __('Table') }} {{ $item->meja?->nomor_meja ?? '-' }}</span>
                                        <span class="text-xs text-neutral-500 dark:text-neutral-400">{{ __('Remaining') }} {{ $remaining }} / {{ (int) ($item->meja?->kapasitas ?? 0) }}</span>
                                    </div>
                                </td>
                                <td class="hidden xl:table-cell px-6 py-4 align-middle">
                                    <div class="space-y-1">
                                        @foreach ($item->details as $detail)
                                            <div class="flex max-w-xs items-start justify-between gap-3 text-xs text-neutral-600 dark:text-neutral-300">
                                                <span class="truncate">{{ $detail->menu?->nama_menu ?? __('Menu') }}</span>
                                                <span class="shrink-0 font-semibold text-neutral-900 dark:text-white">x{{ (int) $detail->qty }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="px-6 py-4 align-middle">
                                    <div class="font-semibold text-neutral-900 dark:text-white">Rp {{ number_format((float) $item->total_harga, 0, ',', '.') }}</div>
                                    <div class="mt-1 text-xs text-neutral-500 dark:text-neutral-400 xl:hidden">{{ (int) ($item->details?->sum('qty') ?? 0) }} {{ __('items') }}</div>
                                </td>
                                <td class="px-6 py-4 align-middle">
                                    <div class="flex flex-col items-start gap-2 lg:flex-row lg:flex-wrap lg:items-center">
                                        @can('waiting-list.manage')
                                            <flux:button
                                                size="sm"
                                                icon="play"
                                                variant="primary"
                                                class="btn-brand rounded-2xl shadow-sm transition whitespace-nowrap justify-center md:w-24 lg:w-auto"
                                                wire:click="activate({{ $item->id }})"
                                                :disabled="!$canActivate"
                                                title="{{ __('Activate') }}"
                                            >
                                                {{ __('Activate') }}
                                            </flux:button>
                                            <flux:button
                                                size="sm"
                                                icon="x-mark"
                                                variant="danger"
                                                class="rounded-2xl shadow-sm transition whitespace-nowrap justify-center md:w-24 lg:w-auto"
                                                wire:click="cancel({{ $item->id }})"
                                                title="{{ __('Cancel') }}"
                                            >
                                                {{ __('Cancel') }}
                                            </flux:button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-sm text-neutral-500 dark:text-neutral-400">
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

        <div
            x-data="{ show:false, message:'', timeout:null, handle(e){ this.message=e.detail?.message || ''; this.show=!!this.message; clearTimeout(this.timeout); this.timeout=setTimeout(()=>this.show=false,3500); } }"
            x-on:waiting-list-toast.window="handle($event)"
            class="pointer-events-none fixed inset-x-0 top-6 flex justify-center px-4"
        >
            <div x-show="show" class="pointer-events-auto rounded-2xl toast-brand px-4 py-3 text-sm">
                <span x-text="message"></span>
            </div>
        </div>
    </div>
</section>

@if (session('waiting_list_toast'))
    <script>
        window.addEventListener('load', () => {
            try {
                const message = @js(session('waiting_list_toast'));
                window.dispatchEvent(new CustomEvent('waiting-list-toast', { detail: { message } }));
            } catch (e) {}
        });
    </script>
@endif
