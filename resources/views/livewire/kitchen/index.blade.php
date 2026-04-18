<?php

use App\Models\Pesanan;
use Illuminate\Support\Facades\Cache;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $search = '';
    public string $statusFilter = 'all';
    public ?int $lastActiveMaxId = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => 'all'],
        'page' => ['except' => 1],
    ];

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingStatusFilter(): void { $this->resetPage(); }

    public function mount(): void
    {
        $this->lastActiveMaxId = $this->currentActiveMaxId();
    }

    public function pollKitchen(): void
    {
        $current = $this->currentActiveMaxId();

        if ($this->lastActiveMaxId === null) {
            $this->lastActiveMaxId = $current;
            $this->dispatch('kitchen-poll-tick');
            return;
        }

        if ($current !== null && $current > $this->lastActiveMaxId) {
            $this->lastActiveMaxId = $current;
            $this->dispatch('kitchen-new-order', id: $current);
        }

        // Always tick so the browser can resync UI after Livewire DOM morphs.
        $this->dispatch('kitchen-poll-tick');
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->statusFilter = 'all';
        $this->resetPage();
    }

    protected function currentActiveMaxId(): ?int
    {
        $max = Pesanan::query()
            ->whereIn('status', ['menunggu', 'diproses', 'siap'])
            ->max('id');

        return $max ? (int) $max : null;
    }

    public function setStatus(int $id, string $toStatus): void
    {
        $this->authorizeManage();

        $toStatus = strtolower(trim($toStatus));
        if (!in_array($toStatus, ['diproses', 'siap'], true)) {
            return;
        }

        $pesanan = Pesanan::query()->select(['id', 'status'])->whereKey($id)->firstOrFail();

        $allowedTargets = match ($pesanan->status) {
            'menunggu' => ['diproses'],
            'diproses' => ['siap'],
            'siap' => ['diproses'],
            default => [],
        };

        if (!in_array($toStatus, $allowedTargets, true)) {
            return;
        }

        $pesanan->update(['status' => $toStatus]);
        Cache::forget('kitchen:status_counts');
    }

    protected function authorizeManage(): void
    {
        abort_unless(auth()->check() && auth()->user()->can('kitchen.manage'), 403);
    }
}; ?>

<section class="w-full">
    @php
        $activeStatuses = ['menunggu', 'diproses', 'siap'];

        $detailGroupKey = function ($d) {
            $addonSig = ($d->addons ?? collect())
                ->pluck('id')
                ->map(fn ($v) => (int) $v)
                ->filter(fn ($v) => $v > 0)
                ->sort()
                ->values()
                ->implode(',');

            return (int) $d->menu_id . '|' . $addonSig;
        };

        $groupDetails = fn ($item) => ($item->details ?? collect())->groupBy($detailGroupKey);

        $orderFingerprint = function ($item) use ($groupDetails) {
            $groups = $groupDetails($item)
                ->map(function ($group) {
                    $first = $group->first();

                    $addonIds = $group
                        ->flatMap(fn ($d) => $d->addons?->pluck('id') ?? collect())
                        ->map(fn ($v) => (int) $v)
                        ->filter(fn ($v) => $v > 0)
                        ->unique()
                        ->sort()
                        ->values()
                        ->all();

                    return [
                        'menu_id' => (int) ($first?->menu_id ?? 0),
                        'addons' => $addonIds,
                        'qty' => (int) $group->sum('qty'),
                    ];
                })
                ->values()
                ->all();

            $payload = [
                'customer_name' => (string) ($item->customer_name ?? ''),
                'customer_note' => (string) ($item->customer_note ?? ''),
                'items' => $groups,
            ];

            return hash('sha1', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        };

        $query = Pesanan::query()
            ->select([
                'id',
                'meja_id',
                'kode_pesanan',
                'status',
                'customer_name',
                'customer_note',
                'waktu_pesan',
                'updated_at',
            ])
            ->with([
                'meja:id,nomor_meja',
                'details:id,pesanan_id,menu_id,qty',
                'details.menu:id,nama_menu',
                'details.addons:id,nama_addon',
            ])
            ->whereIn('status', $activeStatuses);

        if (!empty($search)) {
            $query->where(function ($sub) use ($search) {
                $sub->where('kode_pesanan', 'like', '%'.$search.'%')
                    ->orWhere('customer_name', 'like', '%'.$search.'%')
                    ->orWhereHas('meja', function ($q) use ($search) {
                        $q->where('nomor_meja', 'like', '%'.$search.'%');
                    });
            });
        }

        if ($statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        $items = $query
            ->orderBy('waktu_pesan')
            ->orderBy('id')
            ->paginate(10);

        $counts = Cache::remember('kitchen:status_counts', 10, function () use ($activeStatuses) {
            $rows = Pesanan::query()
                ->whereIn('status', $activeStatuses)
                ->select('status')
                ->selectRaw('count(*) as agg')
                ->groupBy('status')
                ->pluck('agg', 'status')
                ->all();

            $total = array_sum(array_map('intval', (array) $rows));

            return [
                'rows' => (array) $rows,
                'total' => (int) $total,
            ];
        });

        $statusCounts = (array) ($counts['rows'] ?? []);
        $totalCount = (int) ($counts['total'] ?? 0);
        $waitingCount = (int) ($statusCounts['menunggu'] ?? 0);
        $inProgressCount = (int) ($statusCounts['diproses'] ?? 0);
        $readyCount = (int) ($statusCounts['siap'] ?? 0);

        $statusMeta = [
            'all' => [
                'label' => __('All'),
                'count' => $totalCount,
                'dot'   => 'bg-neutral-500',
                'hint'  => __('All active kitchen orders'),
            ],
            'menunggu' => [
                'label' => __('Waiting'),
                'count' => $waitingCount,
                'badge' => 'bg-amber-50 text-amber-700 ring-1 ring-amber-100 dark:bg-amber-900/40 dark:text-amber-200 dark:ring-amber-800/60',
                'dot'   => 'bg-amber-500',
                'hint'  => __('Queued for preparation'),
            ],
            'diproses' => [
                'label' => __('In progress'),
                'count' => $inProgressCount,
                'badge' => 'bg-blue-50 text-blue-700 ring-1 ring-blue-100 dark:bg-blue-900/40 dark:text-blue-200 dark:ring-blue-800/60',
                'dot'   => 'bg-blue-500',
                'hint'  => __('Being prepared right now'),
            ],
            'siap' => [
                'label' => __('Ready'),
                'count' => $readyCount,
                'badge' => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100 dark:bg-emerald-900/40 dark:text-emerald-200 dark:ring-emerald-800/60',
                'dot'   => 'bg-emerald-500',
                'hint'  => __('Ready to be served'),
            ],
        ];
    @endphp

    <div class="space-y-6">
        <!-- header -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <flux:heading size="xl" level="1">{{ __('Kitchen') }}</flux:heading>
            <div
                x-data="kitchenSound({ src: '{{ asset('audio/discord-notification.mp3') }}' })"
                class="flex flex-wrap items-center gap-2"
            >
                <audio x-ref="audio" class="hidden" preload="auto"></audio>
                <template x-if="enabled">
                    <flux:button size="sm" variant="primary" class="btn-brand" type="button" x-on:click="toggle()">
                        <span class="inline-flex items-center gap-2">
                            <flux:icon icon="speaker-wave" />
                            <span>{{ __('Sound: On') }}</span>
                        </span>
                    </flux:button>
                </template>
                <template x-if="!enabled">
                    <flux:button size="sm" variant="ghost" class="btn-ghost-accent" type="button" x-on:click="toggle()">
                        <span class="inline-flex items-center gap-2">
                            <flux:icon icon="speaker-x-mark" />
                            <span>{{ __('Sound: Off') }}</span>
                        </span>
                    </flux:button>
                </template>
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
            </div>
        </div>

        <!-- summary desktop -->
        <div class="hidden sm:grid gap-2 sm:gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl sm:rounded-2xl border border-neutral-200/70 bg-white/85 p-3 sm:p-4 shadow-sm sm:shadow-lg shadow-neutral-200/40 dark:border-neutral-700/60 dark:bg-neutral-900/70 dark:shadow-black/30">
                <p class="text-[11px] sm:text-xs font-medium uppercase tracking-[0.18em] sm:tracking-[0.2em] text-neutral-400">{{ __('Active Orders') }}</p>
                <div class="mt-2 sm:mt-3 flex items-baseline gap-2">
                    <span class="text-2xl sm:text-3xl font-semibold text-neutral-900 dark:text-white">{{ $totalCount }}</span>
                    <span class="text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('items') }}</span>
                </div>
            </div>
            <div class="rounded-xl sm:rounded-2xl border border-neutral-200/70 bg-white/85 p-3 sm:p-4 shadow-sm sm:shadow-lg shadow-neutral-200/40 dark:border-neutral-700/60 dark:bg-neutral-900/70 dark:shadow-black/30">
                <div class="flex items-center gap-2 text-[11px] sm:text-xs font-medium uppercase tracking-[0.18em] sm:tracking-[0.2em] text-neutral-400">
                    <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                    <span>{{ __('Waiting') }}</span>
                </div>
                <div class="mt-2 sm:mt-3 flex items-baseline gap-2">
                    <span class="text-2xl sm:text-3xl font-semibold text-neutral-900 dark:text-white">{{ $waitingCount }}</span>
                    <span class="text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('orders') }}</span>
                </div>
                <p class="mt-1 text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('Queued for preparation') }}</p>
            </div>
            <div class="rounded-xl sm:rounded-2xl border border-neutral-200/70 bg-white/85 p-3 sm:p-4 shadow-sm sm:shadow-lg shadow-neutral-200/40 dark:border-neutral-700/60 dark:bg-neutral-900/70 dark:shadow-black/30">
                <div class="flex items-center gap-2 text-[11px] sm:text-xs font-medium uppercase tracking-[0.18em] sm:tracking-[0.2em] text-neutral-400">
                    <span class="h-2 w-2 rounded-full bg-blue-500"></span>
                    <span>{{ __('In progress') }}</span>
                </div>
                <div class="mt-2 sm:mt-3 flex items-baseline gap-2">
                    <span class="text-2xl sm:text-3xl font-semibold text-neutral-900 dark:text-white">{{ $inProgressCount }}</span>
                    <span class="text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('orders') }}</span>
                </div>
                <p class="mt-1 text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('Being prepared right now') }}</p>
            </div>
            <div class="rounded-xl sm:rounded-2xl border border-neutral-200/70 bg-white/85 p-3 sm:p-4 shadow-sm sm:shadow-lg shadow-neutral-200/40 dark:border-neutral-700/60 dark:bg-neutral-900/70 dark:shadow-black/30">
                <div class="flex items-center gap-2 text-[11px] sm:text-xs font-medium uppercase tracking-[0.18em] sm:tracking-[0.2em] text-neutral-400">
                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                    <span>{{ __('Ready') }}</span>
                </div>
                <div class="mt-2 sm:mt-3 flex items-baseline gap-2">
                    <span class="text-2xl sm:text-3xl font-semibold text-neutral-900 dark:text-white">{{ $readyCount }}</span>
                    <span class="text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('orders') }}</span>
                </div>
                <p class="mt-1 text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('Ready to be served') }}</p>
            </div>
        </div>

        <!-- filters -->
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex-1">
                <flux:input
                    wire:model.live.debounce.800ms="search"
                    :placeholder="__('Search order code, customer, or table')"
                />
            </div>
            <div class="flex items-center gap-2">
                <flux:select
                    wire:model.live="statusFilter"
                    class="rounded-full border-neutral-200 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900 focus:outline-hidden focus:ring-2 focus:ring-[color:var(--brand-accent)] focus:ring-offset-2 focus:ring-offset-[color:var(--brand-accent-foreground)]"
                >
                    <option value="all">{{ __('All Status') }}</option>
                    <option value="menunggu">{{ __('Waiting') }}</option>
                    <option value="diproses">{{ __('In progress') }}</option>
                    <option value="siap">{{ __('Ready') }}</option>
                </flux:select>
                <flux:button size="sm" variant="ghost" class="btn-ghost-accent" wire:click="clearFilters">{{ __('Clear') }}</flux:button>
            </div>
        </div>

        <div
            id="kitchen-orders"
            x-data="kitchenOrderChanges"
            data-new-order-label="{{ __('New order') }}"
            data-new-orders-label="{{ __('new orders') }}"
            data-order-updated-label="{{ __('Order updated') }}"
            data-orders-updated-label="{{ __('orders updated') }}"
            class="space-y-4"
        >
            <div
                x-data="{
                    active: !document.hidden,
                    modalOpen: false,
                    init() {
                        const sync = () => { this.active = !document.hidden }
                        document.addEventListener('visibilitychange', sync)
                        window.addEventListener('modal-show', () => { this.modalOpen = true })
                        window.addEventListener('modal-close', () => { this.modalOpen = false })
                        sync()
                    },
                }"
                x-init="init()"
                x-show="active && !modalOpen"
                wire:poll.visible.5s="pollKitchen"
                class="fixed left-0 top-0 h-1 w-1 opacity-0 pointer-events-none"
                aria-hidden="true"
            ></div>

        <!-- mobile cards -->
        <div class="block sm:hidden">
            <div class="grid gap-3">
            @forelse ($items as $item)
                @php
                    $meta = $statusMeta[$item->status] ?? null;
                    $fp = $orderFingerprint($item);
                @endphp
                <div
                    class="kitchen-order rounded-2xl border border-neutral-200/80 bg-white p-4 shadow-sm dark:border-neutral-800/70 dark:bg-neutral-900"
                    data-kitchen-order
                    data-order-id="{{ (int) $item->id }}"
                    data-order-fp="{{ $fp }}"
                    data-order-code="{{ $item->kode_pesanan }}"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <div class="h-14 w-14 rounded bg-neutral-100 dark:bg-neutral-800 flex items-center justify-center border border-white/70 dark:border-neutral-800">
                                <span class="text-xs font-semibold text-neutral-600 dark:text-neutral-300">
                                    {{ optional($item->meja)->nomor_meja ?? '-' }}
                                </span>
                            </div>
                            <div class="min-w-0">
                                <div class="truncate text-base font-semibold text-neutral-900 dark:text-white">{{ $item->kode_pesanan }}</div>
                                <div class="mt-1 flex flex-wrap items-center gap-2">
                                    <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-[11px] font-semibold {{ $meta['badge'] ?? 'bg-neutral-100 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-200' }}">
                                        <span class="h-2 w-2 rounded-full {{ $meta['dot'] ?? 'bg-neutral-400' }}"></span>
                                        {{ $meta['label'] ?? ucfirst($item->status) }}
                                    </span>
                                    <span wire:ignore class="kitchen-updated-badge inline-flex items-center gap-2 rounded-full px-3 py-1 text-[11px] font-semibold">
                                        <span class="h-2 w-2 rounded-full kitchen-updated-dot"></span>
                                        {{ __('Updated') }}
                                    </span>
                                </div>
                                <div class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">
                                    {{ __('Table') }} {{ optional($item->meja)->nomor_meja ?? '-' }} · {{ $item->customer_name ?: __('Guest') }}
                                </div>
                                @if ($item->waktu_pesan)
                                    <div class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">{{ __('Created at') }} {{ $item->waktu_pesan->format('d M Y H:i') }}</div>
                                @endif
                            </div>
                        </div>
                        <span class="shrink-0"></span>
                    </div>

                    @if ($item->customer_note)
                        <div class="mt-2 text-xs text-neutral-500 dark:text-neutral-400">
                            {{ \Illuminate\Support\Str::limit($item->customer_note, 120) }}
                        </div>
                    @endif

                    <div class="mt-4 rounded-xl border border-neutral-200/70 bg-neutral-50/50 p-3 text-sm dark:border-neutral-800/70 dark:bg-neutral-950/30">
                        <div class="flex items-center justify-between gap-3 text-xs font-semibold tracking-wide text-neutral-500 dark:text-neutral-400">
                            <span>{{ __('Items') }}</span>
                            <span class="whitespace-nowrap">{{ (int) ($item->details?->sum('qty') ?? 0) }} {{ __('items') }}</span>
                        </div>

                        <div class="mt-2 max-h-40 space-y-2 overflow-y-auto pr-2">
                            @php
                                $detailGroups = $groupDetails($item);
                            @endphp
                            @foreach ($detailGroups as $group)
                                @php
                                    $first = $group->first();
                                    $qtySum = (int) $group->sum('qty');
                                    $addonNames = $group->flatMap(fn ($d) => $d->addons?->pluck('nama_addon') ?? collect())->filter()->unique()->values();
                                @endphp

                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="font-medium text-neutral-900 dark:text-white">
                                            {{ optional($first?->menu)->nama_menu ?? __('Menu') }}
                                            @if ($addonNames->isNotEmpty())
                                                <div class="mt-0.5 text-[10px] text-neutral-500 dark:text-neutral-400">
                                                    + {{ $addonNames->join(', ') }}
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="whitespace-nowrap text-sm font-semibold text-neutral-900 dark:text-white">
                                        x{{ $qtySum }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    @can('kitchen.manage')
                        <div class="mt-4 flex items-center gap-2">
                            @if ($item->status === 'menunggu')
                                <flux:button size="sm" variant="primary" class="flex-1 btn-brand" wire:click="setStatus({{ $item->id }}, 'diproses')">{{ __('Start') }}</flux:button>
                            @elseif ($item->status === 'diproses')
                                <flux:button size="sm" variant="primary" class="flex-1 btn-brand" wire:click="setStatus({{ $item->id }}, 'siap')">{{ __('Mark Ready') }}</flux:button>
                            @elseif ($item->status === 'siap')
                                <flux:button size="sm" variant="ghost" class="flex-1 btn-ghost-accent" wire:click="setStatus({{ $item->id }}, 'diproses')">{{ __('Back') }}</flux:button>
                            @endif
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

        <!-- tablet cards -->
        <div class="hidden sm:block lg:hidden">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                @forelse ($items as $item)
                    @php
                        $meta = $statusMeta[$item->status] ?? null;
                        $fp = $orderFingerprint($item);
                    @endphp

                    <div
                        class="kitchen-order flex h-full flex-col rounded-2xl border border-neutral-200/80 bg-white p-4 shadow-sm dark:border-neutral-800/70 dark:bg-neutral-900"
                        data-kitchen-order
                        data-order-id="{{ (int) $item->id }}"
                        data-order-fp="{{ $fp }}"
                        data-order-code="{{ $item->kode_pesanan }}"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex min-w-0 items-center gap-3">
                                <div class="h-12 w-12 rounded bg-neutral-100 dark:bg-neutral-800 flex items-center justify-center border border-white/70 dark:border-neutral-800">
                                    <span class="text-xs font-semibold text-neutral-600 dark:text-neutral-300">
                                        {{ optional($item->meja)->nomor_meja ?? '-' }}
                                    </span>
                                </div>
                                <div class="min-w-0">
                                    <div class="truncate text-base font-semibold text-neutral-900 dark:text-white">{{ $item->kode_pesanan }}</div>
                                    <div class="mt-1 flex flex-wrap items-center gap-2">
                                        <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-[11px] font-semibold {{ $meta['badge'] ?? 'bg-neutral-100 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-200' }}">
                                            <span class="h-2 w-2 rounded-full {{ $meta['dot'] ?? 'bg-neutral-400' }}"></span>
                                            {{ $meta['label'] ?? ucfirst($item->status) }}
                                        </span>
                                        <span wire:ignore class="kitchen-updated-badge inline-flex items-center gap-2 rounded-full px-3 py-1 text-[11px] font-semibold">
                                            <span class="h-2 w-2 rounded-full kitchen-updated-dot"></span>
                                            {{ __('Updated') }}
                                        </span>
                                    </div>
                                    <div class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">
                                        {{ __('Table') }} {{ optional($item->meja)->nomor_meja ?? '-' }} · {{ $item->customer_name ?: __('Guest') }}
                                    </div>
                                    @if ($item->waktu_pesan)
                                        <div class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">{{ __('Created at') }} {{ $item->waktu_pesan->format('d M Y H:i') }}</div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        @if ($item->customer_note)
                            <div class="mt-3 text-xs text-neutral-500 dark:text-neutral-400">
                                {{ \Illuminate\Support\Str::limit($item->customer_note, 120) }}
                            </div>
                        @endif

                        <div class="mt-4 flex flex-1 flex-col rounded-xl border border-neutral-200/70 bg-neutral-50/50 p-3 text-sm dark:border-neutral-800/70 dark:bg-neutral-950/30">
                            <div class="flex items-center justify-between gap-3 text-xs font-semibold tracking-wide text-neutral-500 dark:text-neutral-400">
                                <span>{{ __('Items') }}</span>
                                <span class="whitespace-nowrap">{{ (int) ($item->details?->sum('qty') ?? 0) }} {{ __('items') }}</span>
                            </div>

                            <div class="mt-2 flex-1 space-y-2 overflow-y-auto pr-2">
                                @php
                                    $detailGroups = $groupDetails($item);
                                @endphp
                                @foreach ($detailGroups as $group)
                                    @php
                                        $first = $group->first();
                                        $qtySum = (int) $group->sum('qty');
                                        $addonNames = $group->flatMap(fn ($d) => $d->addons?->pluck('nama_addon') ?? collect())->filter()->unique()->values();
                                    @endphp

                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <div class="font-medium text-neutral-900 dark:text-white">
                                                {{ optional($first?->menu)->nama_menu ?? __('Menu') }}
                                                @if ($addonNames->isNotEmpty())
                                                    <div class="mt-0.5 text-[10px] text-neutral-500 dark:text-neutral-400">
                                                        + {{ $addonNames->join(', ') }}
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="whitespace-nowrap text-sm font-semibold text-neutral-900 dark:text-white">
                                            x{{ $qtySum }}
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        @can('kitchen.manage')
                            <div class="mt-4 flex items-center gap-2">
                                @if ($item->status === 'menunggu')
                                    <flux:button size="sm" variant="primary" class="flex-1 btn-brand" wire:click="setStatus({{ $item->id }}, 'diproses')">{{ __('Start') }}</flux:button>
                                @elseif ($item->status === 'diproses')
                                    <flux:button size="sm" variant="primary" class="flex-1 btn-brand" wire:click="setStatus({{ $item->id }}, 'siap')">{{ __('Mark Ready') }}</flux:button>
                                @elseif ($item->status === 'siap')
                                    <flux:button size="sm" variant="ghost" class="flex-1 btn-ghost-accent" wire:click="setStatus({{ $item->id }}, 'diproses')">{{ __('Back') }}</flux:button>
                                @endif
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

        <!-- desktop table -->
        <div class="hidden lg:block rounded-3xl border border-neutral-200/80 bg-gradient-to-b from-white/95 via-white/90 to-white/70 shadow-2xl shadow-neutral-200/60 backdrop-blur-xl dark:border-neutral-800/80 dark:from-neutral-950/80 dark:via-neutral-950/60 dark:to-neutral-950/40 dark:shadow-black/30">
            <div class="overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm text-left">
                        <thead>
                            <tr>
                                <th class="hidden md:table-cell px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">{{ __('ID') }}</th>
                                <th class="px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">{{ __('Table') }}</th>
                                <th class="px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">{{ __('Order') }}</th>
                                <th class="hidden lg:table-cell px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">{{ __('Customer') }}</th>
                                <th class="hidden xl:table-cell px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">{{ __('Items') }}</th>
                                <th class="px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">{{ __('Status') }}</th>
                                <th class="px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100/80 text-neutral-700 dark:divide-neutral-900/40 dark:text-neutral-200">
                            @forelse ($items as $item)
                                @php
                                    $meta = $statusMeta[$item->status] ?? null;
                                    $fp = $orderFingerprint($item);
                                @endphp
                                <tr
                                    class="kitchen-order group transition hover:bg-white/70 focus-within:bg-white/90 dark:hover:bg-neutral-900/40 dark:focus-within:bg-neutral-900/50"
                                    data-kitchen-order
                                    data-order-id="{{ (int) $item->id }}"
                                    data-order-fp="{{ $fp }}"
                                    data-order-code="{{ $item->kode_pesanan }}"
                                >
                                    <td class="hidden md:table-cell px-6 py-4 align-middle">
                                        <span class="inline-flex items-center rounded-full bg-neutral-900/5 px-3 py-1 text-xs font-semibold text-neutral-500 dark:bg-white/5 dark:text-neutral-300">
                                            #{{ str_pad((string) $item->id, 3, '0', STR_PAD_LEFT) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 align-middle">
                                        <div class="h-12 w-12 rounded-xl bg-neutral-100 dark:bg-neutral-800 flex items-center justify-center border border-white/70 dark:border-neutral-800">
                                            <span class="text-sm font-semibold text-neutral-700 dark:text-neutral-200">
                                                {{ optional($item->meja)->nomor_meja ?? '-' }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 align-middle">
                                        <div class="flex flex-col">
                                            <div class="flex items-center gap-2">
                                                <span class="text-base font-semibold text-neutral-900 dark:text-white">{{ $item->kode_pesanan }}</span>
                                                <span wire:ignore class="kitchen-updated-badge inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold">
                                                    <span class="h-2 w-2 rounded-full kitchen-updated-dot"></span>
                                                    {{ __('Updated') }}
                                                </span>
                                            </div>
                                            @if ($item->waktu_pesan)
                                                <span class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">{{ __('Created at') }} {{ $item->waktu_pesan->format('d M Y H:i') }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="hidden lg:table-cell px-6 py-4 align-middle text-neutral-600 dark:text-neutral-300">
                                        <div class="flex flex-col gap-1">
                                            <span class="font-medium text-neutral-900 dark:text-white">{{ $item->customer_name ?: __('Guest') }}</span>
                                            @if ($item->customer_note)
                                                <span class="text-xs text-neutral-500 dark:text-neutral-400 line-clamp-2">{{ $item->customer_note }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="hidden xl:table-cell px-6 py-4 align-middle">
                                        <div class="space-y-2">
                                            <div class="flex items-center justify-between gap-3 text-[11px] font-semibold tracking-wide text-neutral-500 dark:text-neutral-400">
                                                <span>{{ __('Items') }}</span>
                                                <span class="whitespace-nowrap">{{ (int) ($item->details?->sum('qty') ?? 0) }} {{ __('items') }}</span>
                                            </div>

                                            <div class="max-h-28 space-y-1 overflow-y-auto pr-2 text-sm">
                                                @php
                                                    $detailGroups = $groupDetails($item);
                                                @endphp
                                                @foreach ($detailGroups as $group)
                                                    @php
                                                        $first = $group->first();
                                                        $qtySum = (int) $group->sum('qty');
                                                        $addonNames = $group->flatMap(fn ($d) => $d->addons?->pluck('nama_addon') ?? collect())->filter()->unique()->values();
                                                    @endphp

                                                    <div class="flex items-start justify-between gap-3">
                                                        <span class="min-w-0 truncate text-neutral-900 dark:text-white">
                                                            {{ optional($first?->menu)->nama_menu ?? __('Menu') }}
                                                            @if ($addonNames->isNotEmpty())
                                                                <div class="mt-0.5 text-[10px] text-neutral-500 dark:text-neutral-400">
                                                                    + {{ $addonNames->join(', ') }}
                                                                </div>
                                                            @endif
                                                        </span>
                                                        <span class="whitespace-nowrap font-semibold text-neutral-900 dark:text-white">x{{ $qtySum }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 align-middle">
                                        <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold {{ $meta['badge'] ?? 'bg-neutral-100 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-200' }}">
                                            <span class="h-2 w-2 rounded-full {{ $meta['dot'] ?? 'bg-neutral-400' }}"></span>
                                            {{ $meta['label'] ?? ucfirst($item->status) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 align-middle">
                                        @can('kitchen.manage')
                                            <div class="flex flex-wrap items-center gap-2">
                                                @if ($item->status === 'menunggu')
                                                    <flux:button size="sm" variant="primary" class="btn-brand rounded-2xl shadow-sm transition" wire:click="setStatus({{ $item->id }}, 'diproses')">{{ __('Start') }}</flux:button>
                                                @elseif ($item->status === 'diproses')
                                                    <flux:button size="sm" variant="primary" class="btn-brand rounded-2xl shadow-sm transition" wire:click="setStatus({{ $item->id }}, 'siap')">{{ __('Mark Ready') }}</flux:button>
                                                @elseif ($item->status === 'siap')
                                                    <flux:button size="sm" variant="ghost" class="btn-ghost-accent rounded-2xl shadow-sm transition" wire:click="setStatus({{ $item->id }}, 'diproses')">{{ __('Back') }}</flux:button>
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-xs text-neutral-500 dark:text-neutral-400">{{ __('No access') }}</span>
                                        @endcan
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
        </div>

        <!-- toast -->
        <div
            x-data="{
                show: false,
                message: '',
                timeout: null,
                handle(event) {
                    this.message = event.detail?.message || '{{ __('Order status updated.') }}';
                    this.show = true;
                    clearTimeout(this.timeout);
                    this.timeout = setTimeout(() => this.show = false, 3500);
                }
            }"
            x-on:kitchen-toast.window="handle($event)"
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
