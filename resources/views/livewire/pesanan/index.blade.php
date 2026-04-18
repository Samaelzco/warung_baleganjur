<?php

use App\Models\Pesanan;
use App\Services\TableWaitingListService;
use Illuminate\Support\Facades\Cache;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public ?int $confirmingDeleteId = null;
    public string $search = '';
    public string $statusFilter = 'all';
    public string $paymentFilter = 'all';

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => 'all'],
        'paymentFilter' => ['except' => 'all'],
        'page' => ['except' => 1],
    ];

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingStatusFilter(): void { $this->resetPage(); }
    public function updatingPaymentFilter(): void { $this->resetPage(); }

    public function confirmDelete(int $id): void
    {
        $this->authorizeManage();
        $this->confirmingDeleteId = $id;
    }

    public function delete(): void
    {
        $this->authorizeManage();
        if ($this->confirmingDeleteId) {
            $pesanan = Pesanan::query()->select(['id', 'meja_id'])->find($this->confirmingDeleteId);
            $mejaId = $pesanan?->meja_id;
            $pesanan?->delete();
            if ($mejaId) {
                $waitingListService = app(TableWaitingListService::class);
                $waitingListService->activateNextWaitingLists((int) $mejaId);
                $waitingListService->forgetKitchenCache();
            }
            $this->confirmingDeleteId = null;

            $this->dispatch('modal-close', name: 'confirm-delete-pesanan');
            $this->dispatch('modal-close', name: 'confirm-delete-pesanan-desktop');
            $this->dispatch('pesanan-toast', message: __('Order deleted successfully.'));
        }
    }

    protected function authorizeManage(): void
    {
        abort_unless(auth()->check() && auth()->user()->can('pesanan.manage'), 403);
    }
}; ?>

<section class="w-full">
    @php
        $query = Pesanan::query()
            ->select([
                'id',
                'meja_id',
                'kasir_id',
                'kode_pesanan',
                'customer_name',
                'customer_note',
                'jumlah_orang',
                'status',
                'metode_pembayaran',
                'total_harga',
                'waktu_pesan',
            ])
            ->with([
                'meja' => fn ($q) => $q->select(['id', 'nomor_meja']),
                'kasir' => fn ($q) => $q->select(['id', 'name']),
            ]);

        if (!empty($search)) {
            $query->where(function ($sub) use ($search) {
                $sub->where('kode_pesanan', 'like', '%'.$search.'%')
                    ->orWhere('customer_name', 'like', '%'.$search.'%');
            });
        }

        if ($statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        if ($paymentFilter !== 'all') {
            if ($paymentFilter === 'none') {
                $query->where(function ($q) {
                    $q->whereNull('metode_pembayaran')->orWhere('metode_pembayaran', '');
                });
            } else {
                $query->where('metode_pembayaran', $paymentFilter);
            }
        }

        $items = $query->orderByDesc('waktu_pesan')->orderByDesc('id')->paginate(10);

        $stats = Cache::remember('admin:pesanan:stats:v1', 10, fn () => [
            'total'     => Pesanan::query()->count(),
            'open'      => Pesanan::query()->whereIn('status', ['menunggu', 'diproses', 'siap'])->count(),
            'completed' => Pesanan::query()->where('status', 'selesai')->count(),
            'cancelled' => Pesanan::query()->where('status', 'batal')->count(),
        ]);

        $totalCount      = (int) ($stats['total'] ?? 0);
        $openCount       = (int) ($stats['open'] ?? 0);
        $completedCount  = (int) ($stats['completed'] ?? 0);
        $cancelledCount  = (int) ($stats['cancelled'] ?? 0);

        $statusMeta = [
            'booking' => [
                'label' => __('Waiting List'),
                'badge' => 'bg-purple-50 text-purple-700 ring-1 ring-purple-100 dark:bg-purple-900/40 dark:text-purple-200 dark:ring-purple-800/60',
                'dot'   => 'bg-purple-500',
            ],
            'menunggu' => [
                'label' => __('Waiting'),
                'badge' => 'bg-amber-50 text-amber-700 ring-1 ring-amber-100 dark:bg-amber-900/40 dark:text-amber-200 dark:ring-amber-800/60',
                'dot'   => 'bg-amber-500',
            ],
            'diproses' => [
                'label' => __('In progress'),
                'badge' => 'bg-blue-50 text-blue-700 ring-1 ring-blue-100 dark:bg-blue-900/40 dark:text-blue-200 dark:ring-blue-800/60',
                'dot'   => 'bg-blue-500',
            ],
            'siap' => [
                'label' => __('Ready'),
                'badge' => 'bg-cyan-50 text-cyan-700 ring-1 ring-cyan-100 dark:bg-cyan-900/40 dark:text-cyan-200 dark:ring-cyan-800/60',
                'dot'   => 'bg-cyan-500',
            ],
            'selesai' => [
                'label' => __('Completed'),
                'badge' => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100 dark:bg-emerald-900/40 dark:text-emerald-200 dark:ring-emerald-800/60',
                'dot'   => 'bg-emerald-500',
            ],
            'batal' => [
                'label' => __('Cancelled'),
                'badge' => 'bg-red-50 text-red-700 ring-1 ring-red-100 dark:bg-red-900/40 dark:text-red-200 dark:ring-red-800/60',
                'dot'   => 'bg-red-500',
            ],
        ];

        $selectedPesanan = $items->firstWhere('id', $confirmingDeleteId);
    @endphp

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <flux:heading size="xl" level="1">{{ __('Orders') }}</flux:heading>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @can('pesanan.manage')
                    <flux:link :href="route('pesanan.create', [], false)" wire:navigate>
                        <flux:button icon="plus" variant="primary" class="btn-brand">{{ __('Create') }}</flux:button>
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
                        <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                        <span class="text-neutral-600 dark:text-neutral-300">{{ __('Active') }}</span>
                        <span class="font-semibold text-neutral-900 dark:text-white">{{ $openCount }}</span>
                    </span>
                </div>
                <div class="whitespace-nowrap rounded-full border border-neutral-200/70 bg-white/85 px-3 py-2 text-[11px] shadow-sm dark:border-neutral-700/60 dark:bg-neutral-900/70">
                    <span class="inline-flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                        <span class="text-neutral-600 dark:text-neutral-300">{{ __('Completed') }}</span>
                        <span class="font-semibold text-neutral-900 dark:text-white">{{ $completedCount }}</span>
                    </span>
                </div>
                <div class="whitespace-nowrap rounded-full border border-neutral-200/70 bg-white/85 px-3 py-2 text-[11px] shadow-sm dark:border-neutral-700/60 dark:bg-neutral-900/70">
                    <span class="inline-flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-red-500"></span>
                        <span class="text-neutral-600 dark:text-neutral-300">{{ __('Cancelled') }}</span>
                        <span class="font-semibold text-neutral-900 dark:text-white">{{ $cancelledCount }}</span>
                    </span>
                </div>
            </div>
        </div>

        <!-- Desktop summary cards -->
        <div class="hidden sm:grid gap-2 sm:gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl sm:rounded-2xl border border-neutral-200/70 bg-white/85 p-3 sm:p-4 shadow-sm sm:shadow-lg shadow-neutral-200/40 dark:border-neutral-700/60 dark:bg-neutral-900/70 dark:shadow-black/30">
                <p class="text-[11px] sm:text-xs font-medium uppercase tracking-[0.18em] sm:tracking-[0.2em] text-neutral-400">{{ __('Total Orders') }}</p>
                <div class="mt-2 sm:mt-3 flex items-baseline gap-2">
                    <span class="text-2xl sm:text-3xl font-semibold text-neutral-900 dark:text-white">{{ $totalCount }}</span>
                    <span class="text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('records') }}</span>
                </div>
            </div>
            <div class="rounded-xl sm:rounded-2xl border border-neutral-200/70 bg-white/85 p-3 sm:p-4 shadow-sm sm:shadow-lg shadow-neutral-200/40 dark:border-neutral-700/60 dark:bg-neutral-900/70 dark:shadow-black/30">
                <div class="flex items-center gap-2 text-[11px] sm:text-xs font-medium uppercase tracking-[0.18em] sm:tracking-[0.2em] text-neutral-400">
                    <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                    <span>{{ __('Active Orders') }}</span>
                </div>
                <div class="mt-2 sm:mt-3 flex items-baseline gap-2">
                    <span class="text-2xl sm:text-3xl font-semibold text-neutral-900 dark:text-white">{{ $openCount }}</span>
                    <span class="text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('items') }}</span>
                </div>
                <p class="mt-1 text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('Waiting, in progress, and ready') }}</p>
            </div>
            <div class="rounded-xl sm:rounded-2xl border border-neutral-200/70 bg-white/85 p-3 sm:p-4 shadow-sm sm:shadow-lg shadow-neutral-200/40 dark:border-neutral-700/60 dark:bg-neutral-900/70 dark:shadow-black/30">
                <div class="flex items-center gap-2 text-[11px] sm:text-xs font-medium uppercase tracking-[0.18em] sm:tracking-[0.2em] text-neutral-400">
                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                    <span>{{ __('Completed') }}</span>
                </div>
                <div class="mt-2 sm:mt-3 flex items-baseline gap-2">
                    <span class="text-2xl sm:text-3xl font-semibold text-neutral-900 dark:text-white">{{ $completedCount }}</span>
                    <span class="text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('items') }}</span>
                </div>
                <p class="mt-1 text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('Already paid and closed') }}</p>
            </div>
            <div class="rounded-xl sm:rounded-2xl border border-neutral-200/70 bg-white/85 p-3 sm:p-4 shadow-sm sm:shadow-lg shadow-neutral-200/40 dark:border-neutral-700/60 dark:bg-neutral-900/70 dark:shadow-black/30">
                <div class="flex items-center gap-2 text-[11px] sm:text-xs font-medium uppercase tracking-[0.18em] sm:tracking-[0.2em] text-neutral-400">
                    <span class="h-2 w-2 rounded-full bg-red-500"></span>
                    <span>{{ __('Cancelled') }}</span>
                </div>
                <div class="mt-2 sm:mt-3 flex items-baseline gap-2">
                    <span class="text-2xl sm:text-3xl font-semibold text-neutral-900 dark:text-white">{{ $cancelledCount }}</span>
                    <span class="text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('items') }}</span>
                </div>
                <p class="mt-1 text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('Void orders for history only') }}</p>
            </div>
        </div>

        <!-- filters -->
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex-1 min-w-0">
                <flux:input
                    wire:model.live.debounce.800ms="search"
                    :placeholder="__('Search order code or customer')"
                />
            </div>
            <div class="flex items-center gap-2 min-w-0 sm:justify-end">
                <flux:select
                    wire:model.live="statusFilter"
                    class="flex-1 min-w-0 sm:flex-none sm:w-44 rounded-full border-neutral-200 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900 focus:outline-hidden focus:ring-2 focus:ring-[color:var(--brand-accent)] focus:ring-offset-2 focus:ring-offset-[color:var(--brand-accent-foreground)]"
                >
                    <option value="all">{{ __('All Status') }}</option>
                    <option value="booking">{{ __('Waiting List') }}</option>
                    <option value="menunggu">{{ __('Waiting') }}</option>
                    <option value="diproses">{{ __('In progress') }}</option>
                    <option value="siap">{{ __('Ready') }}</option>
                    <option value="selesai">{{ __('Completed') }}</option>
                    <option value="batal">{{ __('Cancelled') }}</option>
                </flux:select>

                <flux:select
                    wire:model.live="paymentFilter"
                    class="flex-1 min-w-0 sm:flex-none sm:w-44 rounded-full border-neutral-200 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900 focus:outline-hidden focus:ring-2 focus:ring-[color:var(--brand-accent)] focus:ring-offset-2 focus:ring-offset-[color:var(--brand-accent-foreground)]"
                >
                    <option value="all">{{ __('All Payments') }}</option>
                    <option value="tunai">{{ __('Cash') }}</option>
                    <option value="transfer">{{ __('Bank Transfer') }}</option>
                    <option value="qris">{{ __('QRIS') }}</option>
                    <option value="none">{{ __('Unpaid') }}</option>
                </flux:select>

                <flux:button
                    size="sm"
                    variant="ghost"
                    class="btn-ghost-accent whitespace-nowrap shrink-0"
                    wire:click="$wire.set('search','');$wire.set('statusFilter','all');$wire.set('paymentFilter','all')"
                >
                    {{ __('Clear') }}
                </flux:button>
            </div>
        </div>

        <!-- Mobile cards -->
        <div class="block sm:hidden">
            <div class="mt-2 grid gap-3">
                @forelse ($items as $item)
                    @php($status = $item->status)
                    @php($meta = $statusMeta[$status] ?? null)
                    <div class="rounded-2xl border border-neutral-200/80 bg-white p-4 shadow-sm dark:border-neutral-800/70 dark:bg-neutral-900">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="font-mono text-sm font-semibold text-neutral-900 dark:text-white">
                                    {{ $item->kode_pesanan }}
                                </div>
                                <div class="mt-0.5 text-[11px] text-neutral-500 dark:text-neutral-400">
                                    #{{ $item->id }}
                                </div>
                            </div>
                            <div>
                                <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-[11px] font-semibold {{ $meta['badge'] ?? 'bg-neutral-100 text-neutral-700 ring-1 ring-neutral-200 dark:bg-neutral-800 dark:text-neutral-200 dark:ring-neutral-700' }}">
                                    <span class="h-2 w-2 rounded-full {{ $meta['dot'] ?? 'bg-neutral-400' }}"></span>
                                    {{ $meta['label'] ?? ucfirst($status) }}
                                </span>
                            </div>
                        </div>

                        <div class="mt-3 grid gap-2 text-xs text-neutral-600 dark:text-neutral-300">
                            <div class="flex justify-between gap-2">
                                <span>{{ __('Table') }}</span>
                                <span class="font-medium text-neutral-900 dark:text-white">
                                    {{ optional($item->meja)->nomor_meja ?? '-' }}
                                </span>
                            </div>
                            <div class="flex justify-between gap-2">
                                <span>{{ __('Customer') }}</span>
                                <span class="text-right">
                                    <span class="font-medium text-neutral-900 dark:text-white">
                                        {{ $item->customer_name ?: __('Guest') }}
                                    </span>
                                    @if ($item->customer_note)
                                        <span class="block text-[11px] text-neutral-500 dark:text-neutral-400 line-clamp-2">
                                            {{ $item->customer_note }}
                                        </span>
                                    @endif
                                </span>
                            </div>
                            <div class="flex justify-between gap-2">
                                <span>{{ __('Total') }}</span>
                                <span class="font-semibold text-neutral-900 dark:text-white">
                                    Rp {{ number_format((float) $item->total_harga, 0, ',', '.') }}
                                </span>
                            </div>
                            <div class="flex justify-between gap-2">
                                <span>{{ __('Payment') }}</span>
                                <span class="text-right">
                                    @if ($item->metode_pembayaran === 'tunai')
                                        {{ __('Cash') }}
                                    @elseif ($item->metode_pembayaran === 'transfer')
                                        {{ __('Bank Transfer') }}
                                    @elseif ($item->metode_pembayaran === 'qris')
                                        {{ __('QRIS') }}
                                    @else
                                        <span class="text-neutral-500">{{ __('Unpaid') }}</span>
                                    @endif
                                    @if ($item->kasir)
                                        <span class="block text-[11px] text-neutral-500 dark:text-neutral-400">
                                            {{ __('Cashier') }}: {{ $item->kasir->name }}
                                        </span>
                                    @endif
                                </span>
                            </div>
                            <div class="flex justify-between gap-2">
                                <span>{{ __('Order Time') }}</span>
                                <span class="text-right">
                                    {{ optional($item->waktu_pesan)->format('d M Y H:i') ?? '-' }}
                                </span>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            @php($canPrintReceipt = $item->status === 'selesai' && !blank($item->metode_pembayaran))

                            @can('pembayaran.access')
                                @if ($canPrintReceipt)
                                    <flux:link class="flex-1" :href="route('pembayaran.receipt', $item) . '?print=1'" target="_blank" rel="noopener">
                                        <flux:button
                                            size="sm"
                                            icon="printer"
                                            variant="ghost"
                                            class="w-full btn-ghost-accent"
                                        >
                                            {{ __('Receipt') }}
                                        </flux:button>
                                    </flux:link>
                                @else
                                    <flux:button
                                        size="sm"
                                        icon="printer"
                                        variant="ghost"
                                        class="flex-1 w-full btn-disabled-muted"
                                        disabled
                                        title="{{ __('Receipt is available after the order is completed and paid.') }}"
                                    >
                                        {{ __('Receipt') }}
                                    </flux:button>
                                @endif
                            @endcan

                            @can('pesanan.manage')
                                <flux:link class="flex-1" :href="route('pesanan.edit', $item, false)" wire:navigate>
                                    <flux:button size="sm" icon="pencil-square" variant="primary" class="w-full btn-accent">{{ __('Edit') }}</flux:button>
                                </flux:link>
                                <flux:modal.trigger name="confirm-delete-pesanan" class="flex-1">
                                    <flux:button
                                        size="sm"
                                        variant="danger"
                                        class="w-full"
                                        wire:click="confirmDelete({{ $item->id }})"
                                    >
                                        {{ __('Delete') }}
                                    </flux:button>
                                </flux:modal.trigger>
                            @endcan
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-neutral-200/80 bg-white p-6 text-center text-sm text-neutral-500 dark:border-neutral-800/70 dark:bg-neutral-900 dark:text-neutral-400">
                        {{ __('No orders found.') }}
                    </div>
                @endforelse
            </div>
            <div class="mt-4">
                {{ $items->links() }}
            </div>
        </div>

        <!-- Desktop table -->
        <div class="hidden sm:block rounded-3xl border border-neutral-200/80 bg-gradient-to-b from-white/95 via-white/90 to-white/70 shadow-2xl shadow-neutral-200/60 backdrop-blur-xl dark:border-neutral-800/80 dark:from-neutral-950/80 dark:via-neutral-950/60 dark:to-neutral-950/40 dark:shadow-black/30">
            <div class="overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm text-left">
                        <thead>
                            <tr>
                                <th class="px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">
                                    {{ __('Order Code') }}
                                </th>
                                <th class="px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">
                                    {{ __('Table') }}
                                </th>
                                <th class="px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">
                                    {{ __('Customer') }}
                                </th>
                                <th class="px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">
                                    {{ __('Status') }}
                                </th>
                                <th class="px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">
                                    {{ __('Total') }}
                                </th>
                                <th class="hidden md:table-cell px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">
                                    {{ __('Payment') }}
                                </th>
                                <th class="hidden lg:table-cell px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">
                                    {{ __('Order Time') }}
                                </th>
                                <th class="px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">
                                    {{ __('Actions') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100/80 text-neutral-700 dark:divide-neutral-900/40 dark:text-neutral-200">
                            @forelse ($items as $item)
                                @php($status = $item->status)
                                @php($meta = $statusMeta[$status] ?? null)
                                <tr class="group transition hover:bg-white/70 focus-within:bg-white/90 dark:hover:bg-neutral-900/40 dark:focus-within:bg-neutral-900/50">
                                    <td class="px-6 py-4 align-middle">
                                        <div class="flex flex-col">
                                            <span class="font-mono text-sm font-semibold text-neutral-900 dark:text-white">
                                                {{ $item->kode_pesanan }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 align-middle">
                                        <span class="text-sm text-neutral-900 dark:text-white">
                                            {{ optional($item->meja)->nomor_meja ?? '-' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 align-middle">
                                        <div class="flex min-w-0 flex-col">
                                            <span class="truncate text-sm text-neutral-900 dark:text-white">
                                                {{ $item->customer_name ?: __('Guest') }}
                                            </span>
                                            @if ($item->customer_note)
                                                <span class="mt-0.5 line-clamp-1 text-[11px] text-neutral-500 dark:text-neutral-400">
                                                    {{ $item->customer_note }}
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 align-middle">
                                        <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-[11px] font-semibold {{ $meta['badge'] ?? 'bg-neutral-100 text-neutral-700 ring-1 ring-neutral-200 dark:bg-neutral-800 dark:text-neutral-200 dark:ring-neutral-700' }}">
                                            <span class="h-2 w-2 rounded-full {{ $meta['dot'] ?? 'bg-neutral-400' }}"></span>
                                            {{ $meta['label'] ?? ucfirst($status) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 align-middle text-neutral-800 dark:text-neutral-100">
                                        <span class="font-semibold">
                                            Rp {{ number_format((float) $item->total_harga, 0, ',', '.') }}
                                        </span>
                                    </td>
                                    <td class="hidden md:table-cell px-6 py-4 align-middle">
                                        <div class="flex flex-col text-sm text-neutral-900 dark:text-white">
                                            @if ($item->metode_pembayaran === 'tunai')
                                                <span>{{ __('Cash') }}</span>
                                            @elseif ($item->metode_pembayaran === 'transfer')
                                                <span>{{ __('Bank Transfer') }}</span>
                                            @elseif ($item->metode_pembayaran === 'qris')
                                                <span>{{ __('QRIS') }}</span>
                                            @else
                                                <span class="text-neutral-500 dark:text-neutral-400">{{ __('Unpaid') }}</span>
                                            @endif
                                            @if ($item->kasir)
                                                <span class="text-[11px] text-neutral-500 dark:text-neutral-400">
                                                    {{ __('Cashier') }}: {{ $item->kasir->name }}
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="hidden lg:table-cell px-6 py-4 align-middle">
                                        <span class="text-xs text-neutral-800 dark:text-neutral-200">
                                            {{ optional($item->waktu_pesan)->format('d M Y H:i') ?? '-' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 align-middle">
                                        <div class="flex flex-wrap items-center gap-2">
                                            @php($canPrintReceipt = $item->status === 'selesai' && !blank($item->metode_pembayaran))

                                            @can('pembayaran.access')
                                                @if ($canPrintReceipt)
                                                    <flux:link :href="route('pembayaran.receipt', $item) . '?print=1'" target="_blank" rel="noopener">
                                                        <flux:button
                                                            size="sm"
                                                            icon="printer"
                                                            variant="ghost"
                                                            class="btn-ghost-accent rounded-2xl shadow-sm transition"
                                                        >
                                                            {{ __('Receipt') }}
                                                        </flux:button>
                                                    </flux:link>
                                                @else
                                                    <flux:button
                                                        size="sm"
                                                        icon="printer"
                                                        variant="ghost"
                                                        class="btn-disabled-muted rounded-2xl shadow-sm transition"
                                                        disabled
                                                        title="{{ __('Receipt is available after the order is completed and paid.') }}"
                                                    >
                                                        {{ __('Receipt') }}
                                                    </flux:button>
                                                @endif
                                            @endcan

                                            @can('pesanan.manage')
                                                <flux:link :href="route('pesanan.edit', $item, false)" wire:navigate>
                                                    <flux:button
                                                        size="sm"
                                                        icon="pencil-square"
                                                        variant="primary"
                                                        class="btn-accent rounded-2xl shadow-sm transition"
                                                    >
                                                        {{ __('Edit') }}
                                                    </flux:button>
                                                </flux:link>
                                                <flux:modal.trigger name="confirm-delete-pesanan-desktop">
                                                    <flux:button
                                                        size="sm"
                                                        variant="danger"
                                                        class="rounded-2xl shadow-sm transition"
                                                        wire:click="confirmDelete({{ $item->id }})"
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
                                    <td class="px-6 py-8 text-center text-sm text-neutral-500 dark:text-neutral-400" colspan="8">
                                        <div class="flex flex-col items-center gap-3">
                                            <div class="h-12 w-12 rounded-full bg-neutral-100 text-neutral-400 dark:bg-neutral-900/60 dark:text-neutral-500">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-full w-full p-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 4.5h9m-9 6h9m-9 6h9" />
                                                </svg>
                                            </div>
                                            <p>{{ __('No orders found.') }}</p>
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

        <!-- Delete confirm modal - mobile flyout -->
        <flux:modal
            name="confirm-delete-pesanan"
            focusable
            variant="flyout"
            position="bottom"
            :closable="false"
            class="rounded-t-3xl sm:rounded-xl"
        >
            <div class="space-y-4 p-2 max-h-[60dvh] overflow-y-auto">
                <div class="flex items-center justify-center">
                    <div class="mx-auto mb-1 h-1.5 w-12 rounded-full bg-neutral-300 dark:bg-neutral-700"></div>
                </div>

                <div class="space-y-2">
                    <flux:heading size="lg">{{ __('Delete this order?') }}</flux:heading>
                    <flux:subheading>{{ __('This action cannot be undone. This record will be permanently deleted.') }}</flux:subheading>
                </div>

                @if ($selectedPesanan)
                    <div class="rounded-2xl border border-red-200/60 bg-red-50/60 p-4 text-sm text-red-800 dark:border-red-800/60 dark:bg-red-900/20 dark:text-red-200">
                        <p class="font-semibold">
                            {{ $selectedPesanan->kode_pesanan }} <span class="text-xs text-red-700/80 dark:text-red-200/80">#{{ $selectedPesanan->id }}</span>
                        </p>
                        <p class="text-xs opacity-80">
                            {{ __('Total') }}: Rp {{ number_format((float) $selectedPesanan->total_harga, 0, ',', '.') }}
                        </p>
                    </div>
                @endif

                <div class="sticky bottom-0 -mx-2 mt-2 flex items-center justify-end gap-2 border-t border-neutral-200 bg-white/85 px-2 py-2 pb-[max(env(safe-area-inset-bottom),0.75rem)] backdrop-blur dark:border-neutral-700 dark:bg-neutral-900/70">
                    <flux:modal.close>
                        <flux:button variant="filled" wire:click="$wire.set('confirmingDeleteId', null)">
                            {{ __('Cancel') }}
                        </flux:button>
                    </flux:modal.close>
                    <flux:button variant="danger" wire:click="delete">
                        {{ __('Yes, delete') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>

        <!-- Delete confirm modal - desktop centered -->
        <flux:modal
            name="confirm-delete-pesanan-desktop"
            focusable
            class="mx-4 max-w-full sm:mx-auto sm:max-w-lg"
        >
            <div class="space-y-4 p-2">
                <div class="space-y-2">
                    <flux:heading size="lg">{{ __('Delete this order?') }}</flux:heading>
                    <flux:subheading>{{ __('This action cannot be undone. This record will be permanently deleted.') }}</flux:subheading>
                </div>

                @if ($selectedPesanan)
                    <div class="rounded-2xl border border-red-200/60 bg-red-50/60 p-4 text-sm text-red-800 dark:border-red-800/60 dark:bg-red-900/20 dark:text-red-200">
                        <p class="font-semibold">
                            {{ $selectedPesanan->kode_pesanan }} <span class="text-xs text-red-700/80 dark:text-red-200/80">#{{ $selectedPesanan->id }}</span>
                        </p>
                        <p class="text-xs opacity-80">
                            {{ __('Total') }}: Rp {{ number_format((float) $selectedPesanan->total_harga, 0, ',', '.') }}
                        </p>
                    </div>
                @endif

                <div class="mt-2 flex items-center justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="filled" wire:click="$wire.set('confirmingDeleteId', null)">
                            {{ __('Cancel') }}
                        </flux:button>
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
                    this.message = event.detail?.message || '{{ __('Order updated successfully.') }}';
                    this.show = true;
                    clearTimeout(this.timeout);
                    this.timeout = setTimeout(() => this.show = false, 3500);
                }
            }"
            x-on:pesanan-toast.window="handle($event)"
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
