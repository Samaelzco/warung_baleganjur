<?php

use App\Models\Pesanan;
use App\Services\TableWaitingListService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Livewire\Volt\Component;
use Livewire\WithPagination;

	new class extends Component {
	    use WithPagination;

	    public ?int $payingId = null;
	    public ?array $payingOrder = null;
	    public array $payForm = [
	        'metode_pembayaran' => '',
	        'dibayar' => '',
	        'kembalian' => 0,
	        'referensi_pembayaran' => '',
	        'print_receipt' => true,
	    ];

    public string $search = '';
    public string $sort = 'oldest';

    protected $queryString = [
        'search' => ['except' => ''],
        'sort' => ['except' => 'oldest'],
        'page' => ['except' => 1],
    ];

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingSort(): void { $this->resetPage(); }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->sort = 'oldest';
        $this->resetPage();
    }

	    public function openPayModal(int $id): void
	    {
	        $this->authorizeManage();

	        $pesanan = Pesanan::query()
                ->select([
                    'id',
                    'meja_id',
                    'kode_pesanan',
                    'customer_name',
                    'customer_note',
                    'waktu_pesan',
                    'subtotal',
                    'discount_total',
                    'tax_total',
                    'total_harga',
                    'status',
                    'metode_pembayaran',
                ])
                ->with([
                    'meja:id,nomor_meja',
                    'details:id,pesanan_id,menu_id,qty,harga,subtotal',
                    'details.menu:id,nama_menu',
                    'details.addons:id,nama_addon',
                ])
	            ->whereKey($id)
            ->where('status', 'siap')
            ->where(function ($q) {
                $q->whereNull('metode_pembayaran')->orWhere('metode_pembayaran', '');
            })
            ->firstOrFail();

	        $this->payingId = $pesanan->id;
	        $this->payingOrder = [
	            'id' => $pesanan->id,
	            'kode_pesanan' => $pesanan->kode_pesanan,
	            'meja' => optional($pesanan->meja)->nomor_meja,
	            'customer_name' => $pesanan->customer_name,
	            'customer_note' => $pesanan->customer_note,
	            'waktu_pesan' => optional($pesanan->waktu_pesan)->format('Y-m-d H:i'),
	            'subtotal' => (float) $pesanan->subtotal,
	            'discount_total' => (float) $pesanan->discount_total,
	            'tax_total' => (float) $pesanan->tax_total,
	            'total_harga' => (float) $pesanan->total_harga,
	            'items' => $pesanan->details->map(function ($detail) {
	                return [
	                    'menu' => optional($detail->menu)->nama_menu,
	                    'qty' => (int) $detail->qty,
	                    'harga' => (float) $detail->harga,
	                    'subtotal' => (float) $detail->subtotal,
	                    'addons' => $detail->addons?->pluck('nama_addon')->all() ?? [],
	                ];
	            })->toArray(),
	        ];

	        $this->payForm = [
	            'metode_pembayaran' => '',
	            'dibayar' => (string) ((float) $pesanan->total_harga),
	            'kembalian' => 0,
	            'referensi_pembayaran' => '',
	            'print_receipt' => true,
	        ];

	        $this->dispatch('modal-show', name: 'pay-pesanan');
	    }

    public function updatedPayForm($value, $name): void
    {
        if (!in_array($name, ['metode_pembayaran', 'dibayar'], true)) {
            return;
        }

        $this->recalculateChange();
    }

	    protected function recalculateChange(): void
	    {
	        if (!$this->payingId) {
	            return;
	        }

	        $method = (string) ($this->payForm['metode_pembayaran'] ?? '');
	        $total = (float) ($this->payingOrder['total_harga'] ?? 0);
	        if ($total <= 0) {
	            $pesanan = Pesanan::query()->select(['id', 'total_harga'])->find($this->payingId);
	            if (!$pesanan) {
	                return;
	            }
	            $total = (float) $pesanan->total_harga;
	        }

	        if (in_array($method, ['transfer', 'qris'], true)) {
	            $this->payForm['dibayar'] = (string) $total;
	            $this->payForm['kembalian'] = 0;
	            return;
        }

        $dibayar = (float) ($this->payForm['dibayar'] ?? 0);
        $this->payForm['kembalian'] = max($dibayar - $total, 0);
    }

	    public function confirmPayment(): void
	    {
	        $this->authorizeManage();
	        if (!$this->payingId) {
	            return;
	        }

	        $validated = validator($this->payForm, [
	            'metode_pembayaran' => ['required', 'in:tunai,transfer,qris'],
	            'dibayar' => ['required', 'numeric', 'min:0'],
	            'referensi_pembayaran' => ['nullable', 'string', 'max:100'],
	            'print_receipt' => ['nullable'],
	        ])->validate();

	        $receiptId = $this->payingId;
	        $shouldPrint = (bool) ($validated['print_receipt'] ?? false);
            $saved = false;
            $mejaId = null;

	        DB::transaction(function () use ($validated, &$saved, &$mejaId) {
	            $pesanan = Pesanan::query()
	                ->whereKey($this->payingId)
                    ->select(['id', 'meja_id', 'status', 'metode_pembayaran', 'total_harga', 'waktu_selesai'])
	                ->lockForUpdate()
                ->firstOrFail();
            $mejaId = (int) $pesanan->meja_id;

            if ($pesanan->status !== 'siap') {
                $this->dispatch('pembayaran-toast', message: __('This order is not ready anymore.'));
                return;
            }

            if (!blank($pesanan->metode_pembayaran)) {
                $this->dispatch('pembayaran-toast', message: __('This order is already paid.'));
                return;
            }

            $method = $validated['metode_pembayaran'];
            $total = (float) $pesanan->total_harga;

            $dibayar = (float) $validated['dibayar'];
            $kembalian = 0.0;

            if (in_array($method, ['transfer', 'qris'], true)) {
                $dibayar = $total;
                $kembalian = 0.0;
            } else {
                if ($dibayar < $total) {
                    $this->dispatch('pembayaran-toast', message: __('Paid amount is insufficient.'));
                    return;
                }
                $kembalian = max($dibayar - $total, 0);
            }

	            $pesanan->update([
	                'metode_pembayaran' => $method,
	                'dibayar' => $dibayar,
	                'kembalian' => $kembalian,
	                'referensi_pembayaran' => blank($validated['referensi_pembayaran'] ?? null) ? null : $validated['referensi_pembayaran'],
	                'kasir_id' => auth()->id(),
	                'status' => 'selesai',
	                'waktu_selesai' => $pesanan->waktu_selesai ?? now(),
	            ]);

                $saved = true;
	        });

            if (!$saved) {
                return;
            }

	        $this->payingId = null;
	        $this->payingOrder = null;
	        $this->dispatch('modal-close', name: 'pay-pesanan');
	        $this->dispatch('pembayaran-toast', message: __('Payment saved.'));
            $this->dispatch('payment-success');

            if ($mejaId) {
                $waitingListService = app(TableWaitingListService::class);
                $waitingListService->activateNextWaitingLists($mejaId);
                $waitingListService->forgetKitchenCache();
            }

	        if ($shouldPrint && $receiptId) {
	            $this->dispatch('receipt-print', url: route('pembayaran.receipt', $receiptId) . '?print=1');
	        }
	    }

    protected function authorizeManage(): void
    {
        abort_unless(auth()->check() && auth()->user()->can('pembayaran.manage'), 403);
    }
}; ?>

<section class="w-full">
    @php
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

	        $query = Pesanan::query()
                ->select([
                    'id',
                    'meja_id',
                    'kode_pesanan',
                    'customer_name',
                    'customer_note',
                    'waktu_pesan',
                    'total_harga',
                ])
                ->with([
                    'meja:id,nomor_meja',
                    'details:id,pesanan_id,menu_id,qty',
                    'details.menu:id,nama_menu',
                    'details.addons:id,nama_addon',
                ])
	            ->where('status', 'siap')
            ->where(function ($q) {
                $q->whereNull('metode_pembayaran')->orWhere('metode_pembayaran', '');
            });

        if (!empty($search)) {
            $query->where(function ($sub) use ($search) {
                $sub->where('kode_pesanan', 'like', '%'.$search.'%')
                    ->orWhere('customer_name', 'like', '%'.$search.'%')
                    ->orWhereHas('meja', function ($q) use ($search) {
                        $q->where('nomor_meja', 'like', '%'.$search.'%');
                    });
            });
        }

        if ($sort === 'newest') {
            $query->orderByDesc('waktu_pesan')->orderByDesc('id');
        } else {
            $query->orderBy('waktu_pesan')->orderBy('id');
        }

        $items = $query->paginate(10);

        $today = now()->toDateString();
        $stats = Cache::remember('payments:stats:' . $today, 10, function () use ($today) {
            $readyAgg = Pesanan::query()
                ->selectRaw("
                    sum(case when status = 'siap' then 1 else 0 end) as ready,
                    sum(case when status = 'siap' and (metode_pembayaran is null or metode_pembayaran = '') then 1 else 0 end) as ready_unpaid
                ")
                ->first();

            $paidAgg = Pesanan::query()
                ->where('status', 'selesai')
                ->whereNotNull('metode_pembayaran')
                ->whereDate('waktu_selesai', $today)
                ->selectRaw('count(*) as cnt, coalesce(sum(total_harga), 0) as total')
                ->first();

            return [
                'ready_unpaid' => (int) ($readyAgg->ready_unpaid ?? 0),
                'ready' => (int) ($readyAgg->ready ?? 0),
                'paid_today_count' => (int) ($paidAgg->cnt ?? 0),
                'paid_today_total' => (float) ($paidAgg->total ?? 0),
            ];
        });

        $totalReadyUnpaid = (int) ($stats['ready_unpaid'] ?? 0);
        $totalReady = (int) ($stats['ready'] ?? 0);
        $paidTodayCount = (int) ($stats['paid_today_count'] ?? 0);
        $paidTodayTotal = (float) ($stats['paid_today_total'] ?? 0);

        $statusMeta = [
            'ready_unpaid' => [
                'label' => __('Ready & unpaid'),
                'count' => $totalReadyUnpaid,
                'dot'   => 'bg-emerald-500',
                'hint'  => __('Waiting for cashier payment'),
            ],
            'ready' => [
                'label' => __('Ready'),
                'count' => $totalReady,
                'dot'   => 'bg-cyan-500',
                'hint'  => __('Ready to be paid or served'),
            ],
            'paid_today' => [
                'label' => __('Paid today'),
                'count' => $paidTodayCount,
                'dot'   => 'bg-purple-500',
                'hint'  => __('Completed payments today'),
            ],
        ];
    @endphp

    <div class="space-y-6">
        <!-- header -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <flux:heading size="xl" level="1">{{ __('Payments') }}</flux:heading>
            <div
                x-data="paymentSound({ src: '{{ asset('audio/google-pay-success.mp3') }}' })"
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
                <div class="whitespace-nowrap rounded-full border border-neutral-200/70 bg-white/85 px-3 py-2 text-[11px] shadow-sm dark:border-neutral-700/60 dark:bg-neutral-900/70">
                    <span class="inline-flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-sky-500"></span>
                        <span class="text-neutral-600 dark:text-neutral-300">{{ __('Revenue today') }}</span>
                        <span class="font-semibold text-neutral-900 dark:text-white">Rp {{ number_format($paidTodayTotal, 0, ',', '.') }}</span>
                    </span>
                </div>
            </div>
        </div>

        <!-- summary desktop -->
        <div class="hidden sm:grid gap-2 sm:gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl sm:rounded-2xl border border-neutral-200/70 bg-white/85 p-3 sm:p-4 shadow-sm sm:shadow-lg shadow-neutral-200/40 dark:border-neutral-700/60 dark:bg-neutral-900/70 dark:shadow-black/30">
                <p class="text-[11px] sm:text-xs font-medium uppercase tracking-[0.18em] sm:tracking-[0.2em] text-neutral-400">{{ __('Ready & unpaid') }}</p>
                <div class="mt-2 sm:mt-3 flex items-baseline gap-2">
                    <span class="text-2xl sm:text-3xl font-semibold text-neutral-900 dark:text-white">{{ $totalReadyUnpaid }}</span>
                    <span class="text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('orders') }}</span>
                </div>
            </div>
            <div class="rounded-xl sm:rounded-2xl border border-neutral-200/70 bg-white/85 p-3 sm:p-4 shadow-sm sm:shadow-lg shadow-neutral-200/40 dark:border-neutral-700/60 dark:bg-neutral-900/70 dark:shadow-black/30">
                <div class="flex items-center gap-2 text-[11px] sm:text-xs font-medium uppercase tracking-[0.18em] sm:tracking-[0.2em] text-neutral-400">
                    <span class="h-2 w-2 rounded-full bg-cyan-500"></span>
                    <span>{{ __('Ready') }}</span>
                </div>
                <div class="mt-2 sm:mt-3 flex items-baseline gap-2">
                    <span class="text-2xl sm:text-3xl font-semibold text-neutral-900 dark:text-white">{{ $totalReady }}</span>
                    <span class="text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('orders') }}</span>
                </div>
                <p class="mt-1 text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('Kitchen has marked ready') }}</p>
            </div>
            <div class="rounded-xl sm:rounded-2xl border border-neutral-200/70 bg-white/85 p-3 sm:p-4 shadow-sm sm:shadow-lg shadow-neutral-200/40 dark:border-neutral-700/60 dark:bg-neutral-900/70 dark:shadow-black/30">
                <div class="flex items-center gap-2 text-[11px] sm:text-xs font-medium uppercase tracking-[0.18em] sm:tracking-[0.2em] text-neutral-400">
                    <span class="h-2 w-2 rounded-full bg-purple-500"></span>
                    <span>{{ __('Paid today') }}</span>
                </div>
                <div class="mt-2 sm:mt-3 flex items-baseline gap-2">
                    <span class="text-2xl sm:text-3xl font-semibold text-neutral-900 dark:text-white">{{ $paidTodayCount }}</span>
                    <span class="text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('orders') }}</span>
                </div>
                <p class="mt-1 text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('Completed payments today') }}</p>
            </div>
            <div class="rounded-xl sm:rounded-2xl border border-neutral-200/70 bg-white/85 p-3 sm:p-4 shadow-sm sm:shadow-lg shadow-neutral-200/40 dark:border-neutral-700/60 dark:bg-neutral-900/70 dark:shadow-black/30">
                <p class="text-[11px] sm:text-xs font-medium uppercase tracking-[0.18em] sm:tracking-[0.2em] text-neutral-400">{{ __('Revenue today') }}</p>
                <div class="mt-2 sm:mt-3 flex items-baseline gap-2">
                    <span class="text-2xl sm:text-3xl font-semibold text-neutral-900 dark:text-white">Rp {{ number_format($paidTodayTotal, 0, ',', '.') }}</span>
                </div>
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
                    wire:model.live="sort"
                    class="rounded-full border-neutral-200 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900 focus:outline-hidden focus:ring-2 focus:ring-[color:var(--brand-accent)] focus:ring-offset-2 focus:ring-offset-[color:var(--brand-accent-foreground)]"
                >
                    <option value="oldest">{{ __('Oldest') }}</option>
                    <option value="newest">{{ __('Newest') }}</option>
                </flux:select>
                <flux:button
                    size="sm"
                    variant="ghost"
                    class="btn-ghost-accent"
                    wire:click="clearFilters"
                >
                    {{ __('Clear') }}
                </flux:button>
            </div>
        </div>

        <div class="space-y-4">
            @if(!$payingId)
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
                    wire:poll.visible.5s
                    class="fixed left-0 top-0 h-1 w-1 opacity-0 pointer-events-none"
                    aria-hidden="true"
                ></div>
            @endif
        <!-- Mobile cards -->
        <div class="block sm:hidden">
            <div class="grid gap-3">
                @forelse ($items as $item)
                    <div class="rounded-2xl border border-neutral-200/80 bg-white p-4 shadow-sm dark:border-neutral-800/70 dark:bg-neutral-900">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <div class="h-14 w-14 rounded bg-neutral-100 dark:bg-neutral-800 flex items-center justify-center">
                                    <span class="text-xs font-semibold text-neutral-500 dark:text-neutral-300">{{ __('Pay') }}</span>
                                </div>
                                <div class="min-w-0">
                                    <div class="font-mono text-sm font-semibold text-neutral-900 dark:text-white">{{ $item->kode_pesanan }}</div>
                                    <div class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">
                                        {{ __('Table') }} {{ optional($item->meja)->nomor_meja ?? '-' }} · {{ $item->customer_name ?: __('Guest') }}
                                    </div>
                                    @if ($item->waktu_pesan)
                                        <div class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">{{ __('Order Time') }} {{ $item->waktu_pesan->format('d M Y H:i') }}</div>
                                    @endif
                                </div>
                            </div>
                            <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-[11px] font-semibold bg-cyan-50 text-cyan-700 ring-1 ring-cyan-100 dark:bg-cyan-900/40 dark:text-cyan-200 dark:ring-cyan-800/60">
                                <span class="h-2 w-2 rounded-full bg-cyan-500"></span>
                                {{ __('Ready') }}
                            </span>
                        </div>

                        <div class="mt-3 text-right text-sm font-semibold text-neutral-900 dark:text-white">
                            Rp {{ number_format((float) $item->total_harga, 0, ',', '.') }}
                        </div>

                        <div class="mt-3 rounded-xl border border-neutral-200/70 bg-neutral-50/50 p-3 text-sm dark:border-neutral-800/70 dark:bg-neutral-950/30">
                            <div class="space-y-2">
                                @foreach ($item->details as $detail)
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <div class="font-medium text-neutral-900 dark:text-white">{{ optional($detail->menu)->nama_menu ?? __('Menu') }}</div>
                                            @if ($detail->addons->isNotEmpty())
                                                <div class="mt-0.5 text-xs text-neutral-600 dark:text-neutral-300">
                                                    <span class="text-neutral-500 dark:text-neutral-400">{{ __('Add-ons') }}:</span>
                                                    {{ $detail->addons->pluck('nama_addon')->join(', ') }}
                                                </div>
                                            @endif
                                        </div>
                                        <div class="whitespace-nowrap text-sm font-semibold text-neutral-900 dark:text-white">x{{ (int) $detail->qty }}</div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        @can('pembayaran.manage')
                            <div class="mt-4 flex items-center gap-2">
                                <flux:button size="sm" variant="primary" class="flex-1 btn-brand" wire:click="openPayModal({{ $item->id }})">{{ __('Pay') }}</flux:button>
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
                    <div class="flex h-full flex-col rounded-2xl border border-neutral-200/80 bg-white p-4 shadow-sm dark:border-neutral-800/70 dark:bg-neutral-900">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex min-w-0 items-center gap-3">
                                <div class="h-12 w-12 rounded bg-neutral-100 dark:bg-neutral-800 flex items-center justify-center">
                                    <span class="text-xs font-semibold text-neutral-500 dark:text-neutral-300">{{ __('Pay') }}</span>
                                </div>
                                <div class="min-w-0">
                                    <div class="truncate font-mono text-sm font-semibold text-neutral-900 dark:text-white">{{ $item->kode_pesanan }}</div>
                                    <div class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">
                                        {{ __('Table') }} {{ optional($item->meja)->nomor_meja ?? '-' }} · {{ $item->customer_name ?: __('Guest') }}
                                    </div>
                                    @if ($item->waktu_pesan)
                                        <div class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">{{ __('Order Time') }} {{ $item->waktu_pesan->format('d M Y H:i') }}</div>
                                    @endif
                                </div>
                            </div>
                            <span class="shrink-0 inline-flex items-center gap-2 rounded-full px-3 py-1 text-[11px] font-semibold bg-cyan-50 text-cyan-700 ring-1 ring-cyan-100 dark:bg-cyan-900/40 dark:text-cyan-200 dark:ring-cyan-800/60">
                                <span class="h-2 w-2 rounded-full bg-cyan-500"></span>
                                {{ __('Ready') }}
                            </span>
                        </div>

                        <div class="mt-3 text-right text-sm font-semibold text-neutral-900 dark:text-white">
                            Rp {{ number_format((float) $item->total_harga, 0, ',', '.') }}
                        </div>

                        <div class="mt-3 flex flex-1 flex-col rounded-xl border border-neutral-200/70 bg-neutral-50/50 p-3 text-sm dark:border-neutral-800/70 dark:bg-neutral-950/30">
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
                                        <div class="whitespace-nowrap text-sm font-semibold text-neutral-900 dark:text-white">x{{ $qtySum }}</div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        @can('pembayaran.manage')
                            <div class="mt-4 flex items-center gap-2">
                                <flux:button size="sm" variant="primary" class="flex-1 btn-brand" wire:click="openPayModal({{ $item->id }})">{{ __('Pay') }}</flux:button>
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
                    <table class="min-w-full table-fixed text-sm">
                        <thead>
                            <tr>
                                <th class="hidden md:table-cell md:w-[10%] border-b border-r border-neutral-200/80 px-6 py-4 text-center text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:border-neutral-800/70 dark:text-neutral-400">{{ __('ID') }}</th>
                                <th class="w-[22%] border-b border-r border-neutral-200/80 px-6 py-4 text-center text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:border-neutral-800/70 dark:text-neutral-400">{{ __('Order') }}</th>
                                <th class="hidden md:table-cell md:w-[26%] border-b border-r border-neutral-200/80 px-6 py-4 text-center text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:border-neutral-800/70 dark:text-neutral-400">{{ __('Items') }}</th>
                                <th class="hidden md:table-cell md:w-[12%] border-b border-r border-neutral-200/80 px-6 py-4 text-center text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:border-neutral-800/70 dark:text-neutral-400">{{ __('Table') }}</th>
                                <th class="w-[12%] border-b border-r border-neutral-200/80 px-6 py-4 text-center text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:border-neutral-800/70 dark:text-neutral-400">{{ __('Total') }}</th>
                                <th class="w-[12%] border-b border-r border-neutral-200/80 px-6 py-4 text-center text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:border-neutral-800/70 dark:text-neutral-400">{{ __('Status') }}</th>
                                <th class="w-[18%] border-b border-neutral-200/80 px-6 py-4 text-center text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:border-neutral-800/70 dark:text-neutral-400">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100/80 text-neutral-700 dark:divide-neutral-900/40 dark:text-neutral-200">
                            @forelse ($items as $item)
                                <tr class="group transition hover:bg-white/70 focus-within:bg-white/90 dark:hover:bg-neutral-900/40 dark:focus-within:bg-neutral-900/50">
                                    <td class="hidden md:table-cell border-r border-neutral-200/80 px-6 py-4 align-middle text-center dark:border-neutral-800/70">
                                        <span class="inline-flex items-center rounded-full bg-neutral-900/5 px-3 py-1 text-xs font-semibold text-neutral-500 dark:bg-white/5 dark:text-neutral-300">
                                            #{{ str_pad((string) $item->id, 3, '0', STR_PAD_LEFT) }}
                                        </span>
                                    </td>
                                    <td class="border-r border-neutral-200/80 px-6 py-4 align-middle text-center dark:border-neutral-800/70">
                                        <div class="flex flex-col items-center">
                                            <span class="font-mono text-sm font-semibold text-neutral-900 dark:text-white">{{ $item->kode_pesanan }}</span>
                                            <span class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                                                {{ $item->customer_name ?: __('Guest') }}
                                                @if ($item->waktu_pesan)
                                                    · {{ $item->waktu_pesan->format('d M Y H:i') }}
                                                @endif
                                            </span>
                                        </div>
                                    </td>
                                    <td class="hidden md:table-cell border-r border-neutral-200/80 px-6 py-4 align-middle text-center dark:border-neutral-800/70">
                                        @php
                                            $detailGroups = $groupDetails($item);
                                        @endphp
                                        <div class="max-h-28 space-y-1 overflow-y-auto pr-2 text-sm">
                                            @foreach ($detailGroups as $group)
                                                @php
                                                    $first = $group->first();
                                                    $qtySum = (int) $group->sum('qty');
                                                    $addonNames = $group->flatMap(fn ($d) => $d->addons?->pluck('nama_addon') ?? collect())->filter()->unique()->values();
                                                @endphp
                                                <div class="flex items-start justify-between gap-3">
                                                    <div class="min-w-0 text-left">
                                                        <span class="block truncate text-neutral-900 dark:text-white">
                                                            {{ optional($first?->menu)->nama_menu ?? __('Menu') }}
                                                        </span>
                                                        @if ($addonNames->isNotEmpty())
                                                            <span class="text-xs text-neutral-500 dark:text-neutral-400">
                                                                (+{{ $addonNames->join(', ') }})
                                                            </span>
                                                        @endif
                                                    </div>
                                                    <span class="whitespace-nowrap font-semibold text-neutral-900 dark:text-white">x{{ $qtySum }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="hidden md:table-cell border-r border-neutral-200/80 px-6 py-4 align-middle text-center text-neutral-600 dark:border-neutral-800/70 dark:text-neutral-300">
                                        {{ optional($item->meja)->nomor_meja ?? '—' }}
                                    </td>
                                    <td class="border-r border-neutral-200/80 px-6 py-4 align-middle text-center dark:border-neutral-800/70">
                                        <span class="font-semibold text-neutral-900 dark:text-white">Rp {{ number_format((float) $item->total_harga, 0, ',', '.') }}</span>
                                    </td>
                                    <td class="border-r border-neutral-200/80 px-6 py-4 align-middle text-center dark:border-neutral-800/70">
                                        <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold bg-cyan-50 text-cyan-700 ring-1 ring-cyan-100 dark:bg-cyan-900/40 dark:text-cyan-200 dark:ring-cyan-800/60">
                                            <span class="h-2 w-2 rounded-full bg-cyan-500"></span>
                                            {{ __('Ready') }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 align-middle text-center">
                                        @can('pembayaran.manage')
                                            <div class="mx-auto flex w-full max-w-[220px] justify-center">
                                                <flux:button size="sm" variant="primary" class="btn-brand w-full rounded-2xl shadow-sm transition justify-center" wire:click="openPayModal({{ $item->id }})">{{ __('Pay') }}</flux:button>
                                            </div>
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

	        <!-- Pay modal -->
	        <flux:modal name="pay-pesanan" focusable class="mx-4 max-w-full sm:mx-auto sm:max-w-2xl">
	            <div class="flex flex-col max-h-[85dvh] overflow-y-auto no-scrollbar md:max-h-none md:overflow-visible">
                <div class="sticky top-0 z-0 -mx-4 flex items-start justify-between gap-2 border-b border-neutral-200 bg-white/85 px-4 py-3 pr-12 backdrop-blur dark:border-neutral-700 dark:bg-neutral-900/70">
                    <div>
                        <flux:heading size="lg">{{ __('Payment') }}</flux:heading>
                        <flux:subheading>{{ __('Confirm payment for this order.') }}</flux:subheading>
                    </div>
                </div>

	                <form id="pay-pesanan-form" wire:submit.prevent="confirmPayment" class="flex-1 space-y-6 px-1 py-4 pb-[calc(env(safe-area-inset-bottom)+0.75rem)] md:pb-0">
	                    @php($isNonCash = in_array(($payForm['metode_pembayaran'] ?? ''), ['transfer', 'qris'], true))

	                    @if (!empty($payingOrder))
	                        <div class="rounded-2xl border border-neutral-200/70 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
	                            <div class="flex items-start justify-between gap-3">
	                                <div>
	                                    <div class="text-[11px] font-medium uppercase tracking-[0.2em] text-neutral-400">{{ __('Order') }}</div>
	                                    <div class="mt-1 font-mono text-sm font-semibold text-neutral-900 dark:text-white">{{ $payingOrder['kode_pesanan'] }}</div>
	                                    <div class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
	                                        {{ __('Table') }}: <span class="font-medium text-neutral-700 dark:text-neutral-200">{{ $payingOrder['meja'] ?? '-' }}</span>
	                                        <span class="mx-2 text-neutral-300 dark:text-neutral-600">•</span>
	                                        {{ __('Time') }}: <span class="font-medium text-neutral-700 dark:text-neutral-200">{{ $payingOrder['waktu_pesan'] ?? '-' }}</span>
	                                    </div>
	                                </div>
	                                <div class="text-right">
	                                    <div class="text-[11px] font-medium uppercase tracking-[0.2em] text-neutral-400">{{ __('Grand Total') }}</div>
	                                    <div class="mt-1 text-lg font-semibold text-neutral-900 dark:text-white">
	                                        Rp {{ number_format((float) ($payingOrder['total_harga'] ?? 0), 0, ',', '.') }}
	                                    </div>
	                                </div>
	                            </div>

	                            <div class="mt-3 grid gap-2 sm:grid-cols-2 text-xs text-neutral-600 dark:text-neutral-300">
	                                <div>
	                                    <span class="text-neutral-500 dark:text-neutral-400">{{ __('Customer') }}:</span>
	                                    <span class="font-medium text-neutral-800 dark:text-neutral-100">{{ $payingOrder['customer_name'] ?? '-' }}</span>
	                                </div>
	                                <div>
	                                    <span class="text-neutral-500 dark:text-neutral-400">{{ __('Cashier') }}:</span>
	                                    <span class="font-medium text-neutral-800 dark:text-neutral-100">{{ auth()->user()?->name ?? '-' }}</span>
	                                </div>
	                            </div>

	                            @if (!empty($payingOrder['items']))
	                                <div class="mt-4 overflow-hidden rounded-xl border border-neutral-200/70 bg-neutral-50/60 dark:border-neutral-800/70 dark:bg-neutral-900/40">
	                                    <div class="max-h-48 overflow-y-auto overflow-x-auto">
	                                        <table class="min-w-full text-[11px]">
	                                            <thead class="bg-neutral-100/70 dark:bg-neutral-900/70">
	                                                <tr>
	                                                    <th class="px-3 py-2 text-left font-semibold uppercase tracking-[0.16em] text-neutral-500 dark:text-neutral-400">{{ __('Item') }}</th>
	                                                    <th class="px-3 py-2 text-right font-semibold uppercase tracking-[0.16em] text-neutral-500 dark:text-neutral-400">{{ __('Qty') }}</th>
	                                                    <th class="px-3 py-2 text-right font-semibold uppercase tracking-[0.16em] text-neutral-500 dark:text-neutral-400">{{ __('Subtotal') }}</th>
	                                                </tr>
	                                            </thead>
	                                            <tbody class="divide-y divide-neutral-200/60 dark:divide-neutral-800/60">
	                                                @foreach ($payingOrder['items'] as $detail)
	                                                    <tr>
	                                                        <td class="px-3 py-2">
	                                                            <div class="font-medium text-neutral-900 dark:text-white">{{ $detail['menu'] ?? __('Menu') }}</div>
	                                                            @if (!empty($detail['addons']))
	                                                                <div class="mt-0.5 text-[10px] text-neutral-500 dark:text-neutral-400">
	                                                                    + {{ collect($detail['addons'])->filter()->join(', ') }}
	                                                                </div>
	                                                            @endif
	                                                        </td>
	                                                        <td class="px-3 py-2 text-right text-neutral-700 dark:text-neutral-200">{{ (int) ($detail['qty'] ?? 0) }}</td>
	                                                        <td class="px-3 py-2 text-right font-medium text-neutral-900 dark:text-white">
	                                                            Rp {{ number_format((float) ($detail['subtotal'] ?? 0), 0, ',', '.') }}
	                                                        </td>
	                                                    </tr>
	                                                @endforeach
	                                            </tbody>
	                                        </table>
	                                    </div>
	                                </div>
	                            @endif
	                        </div>
	                    @endif

		                    <div class="grid gap-4 sm:grid-cols-2">
		                        <div class="space-y-4">
		                            <flux:select wire:model.live="payForm.metode_pembayaran" :label="__('Payment Method')" required class="w-full">
		                                <option value="">{{ __('Select') }}</option>
		                                <option value="tunai">{{ __('Cash') }}</option>
		                                <option value="transfer">{{ __('Bank Transfer') }}</option>
		                                <option value="qris">{{ __('QRIS') }}</option>
		                            </flux:select>

		                            @if ($isNonCash)
		                                <flux:input
		                                    wire:model.live="payForm.dibayar"
		                                    type="number"
		                                    step="0.01"
		                                    min="0"
		                                    :label="__('Paid Amount')"
		                                    required
		                                    class="w-full"
		                                    readonly
		                                />
		                            @else
		                                <flux:input
		                                    wire:model.live.debounce.250ms="payForm.dibayar"
		                                    type="number"
		                                    step="0.01"
		                                    min="0"
		                                    :label="__('Cash Given')"
		                                    required
		                                    class="w-full"
		                                />
		                            @endif

		                            @if ($isNonCash)
		                                <flux:input
		                                    wire:model.live.debounce.300ms="payForm.referensi_pembayaran"
		                                    type="text"
		                                    :label="__('Reference No. (optional)')"
		                                    maxlength="100"
		                                    placeholder="{{ __('e.g. transfer/QRIS reference') }}"
		                                    class="w-full"
		                                />
		                            @endif
		                        </div>

		                        <div class="space-y-4">
		                            <flux:input
		                                wire:model.defer="payForm.kembalian"
		                                type="number"
		                                step="0.01"
		                                min="0"
		                                :label="__('Change')"
		                                class="w-full"
		                                readonly
		                            />

		                            <div class="rounded-2xl border border-neutral-200/70 bg-white p-3 text-xs text-neutral-600 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300">
		                                <div class="flex items-start justify-between gap-3">
		                                    <div>
		                                        <flux:heading size="sm">{{ __('Tips') }}</flux:heading>
		                                        <ul class="mt-2 list-disc space-y-1 pl-4">
		                                            <li>{{ __('For cash, paid amount must be equal or greater than the grand total.') }}</li>
		                                            <li>{{ __('For transfer/QRIS, paid amount will be set to the grand total automatically.') }}</li>
		                                        </ul>
		                                    </div>
		                                    <label class="flex items-center gap-2 text-[11px]">
		                                        <input type="checkbox" class="rounded border-neutral-300 text-[color:var(--brand-accent)] focus:ring-[color:var(--brand-accent)] dark:border-neutral-700" wire:model.live="payForm.print_receipt" />
		                                        <span class="text-neutral-700 dark:text-neutral-200">{{ __('Print receipt') }}</span>
		                                    </label>
		                                </div>
		                            </div>
		                        </div>
		                    </div>

	                    <div class="hidden md:flex items-center justify-end gap-3 pt-2">
	                        <flux:modal.close>
                            <flux:button type="button" variant="ghost" class="btn-ghost-accent">{{ __('Cancel') }}</flux:button>
                        </flux:modal.close>
	                        <flux:button
	                            type="submit"
	                            form="pay-pesanan-form"
	                            variant="primary"
	                            icon="check"
	                            class="btn-brand"
	                            x-on:click="if ($wire.get('payForm.print_receipt')) { window.__receiptWindow = window.open('about:blank', '_blank'); }"
	                        >
	                            {{ $payForm['print_receipt'] ? __('Save & Print') : __('Save') }}
	                        </flux:button>
	                    </div>
	                </form>

                <div class="sticky bottom-0 z-10 -mx-4 md:hidden border-t border-neutral-200 bg-white/90 px-4 py-3 backdrop-blur dark:border-neutral-700 dark:bg-neutral-900/70">
	                    <div class="flex items-center gap-2">
	                        <flux:modal.close class="flex-1">
	                            <flux:button type="button" variant="ghost" class="w-full btn-ghost-accent">{{ __('Cancel') }}</flux:button>
	                        </flux:modal.close>
	                        <flux:button
	                            type="submit"
	                            form="pay-pesanan-form"
	                            variant="primary"
	                            class="flex-1 btn-brand"
	                            x-on:click="if ($wire.get('payForm.print_receipt')) { window.__receiptWindow = window.open('about:blank', '_blank'); }"
	                        >
	                            {{ $payForm['print_receipt'] ? __('Save & Print') : __('Save') }}
	                        </flux:button>
	                    </div>
	                </div>
	            </div>
	        </flux:modal>

	        <!-- Toast -->
	        <div
	            x-data
	            x-on:receipt-print.window="
	                const url = $event.detail?.url;
	                if (!url) return;
	                const existing = window.__receiptWindow && !window.__receiptWindow.closed ? window.__receiptWindow : null;
	                const win = existing || window.open(url, '_blank', 'noopener,noreferrer');
	                if (win) {
	                    try { win.location = url; } catch (e) {}
	                }
	                window.__receiptWindow = null;
	            "
	        ></div>

	        <div
	            wire:ignore
	            x-data="{
	                show: false,
	                message: '',
	                defaultMessage: @js(__('Payment saved.')),
	                timeout: null,
	                handle(event) {
	                    this.message = event.detail?.message || this.defaultMessage;
	                    this.show = true;
	                    clearTimeout(this.timeout);
	                    this.timeout = setTimeout(() => this.show = false, 3500);
	                }
	            }"
	            x-on:pembayaran-toast.window="handle($event)"
	            class="pointer-events-none fixed inset-x-0 top-6 flex justify-center px-4"
	        >
	            <div
	                x-cloak
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
