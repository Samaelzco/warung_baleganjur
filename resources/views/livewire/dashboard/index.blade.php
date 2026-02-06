<?php

use App\Models\Pesanan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Component;

new class extends Component {
    public string $filterMode = 'range'; // range|month
    public ?string $startDate = null; // Y-m-d
    public ?string $endDate = null;   // Y-m-d

    public int $month;
    public int $year;

    public array $charts = [];
    public int $chartsRevision = 0;

    public function mount(): void
    {
        $today = now()->toDateString();
        $this->startDate = $today;
        $this->endDate = $today;

        $this->month = (int) now()->format('n');
        $this->year = (int) now()->format('Y');

        $this->refreshCharts();
    }

    public function updatedFilterMode(): void
    {
        $this->syncDatesFromMode();
        $this->refreshCharts();
    }

    public function updatedStartDate(): void { $this->filterMode = 'range'; $this->refreshCharts(); }
    public function updatedEndDate(): void { $this->filterMode = 'range'; $this->refreshCharts(); }
    public function updatedMonth(): void { $this->filterMode = 'month'; $this->syncDatesFromMode(); $this->refreshCharts(); }
    public function updatedYear(): void { $this->filterMode = 'month'; $this->syncDatesFromMode(); $this->refreshCharts(); }

    public function setPreset(string $preset): void
    {
        if ($preset === 'this_month') {
            $this->filterMode = 'month';
            $this->month = (int) now()->format('n');
            $this->year = (int) now()->format('Y');
            $this->syncDatesFromMode();
            $this->refreshCharts();
            return;
        }

        $this->filterMode = 'range';

        if ($preset === 'yesterday') {
            $d = now()->subDay()->toDateString();
            $this->startDate = $d;
            $this->endDate = $d;
        } elseif ($preset === 'today') {
            $d = now()->toDateString();
            $this->startDate = $d;
            $this->endDate = $d;
        } elseif ($preset === '7d') {
            $this->startDate = now()->subDays(6)->toDateString();
            $this->endDate = now()->toDateString();
        } elseif ($preset === '30d') {
            $this->startDate = now()->subDays(29)->toDateString();
            $this->endDate = now()->toDateString();
        }

        $this->refreshCharts();
    }

    protected function syncDatesFromMode(): void
    {
        if ($this->filterMode !== 'month') {
            return;
        }

        $base = Carbon::create($this->year, $this->month, 1);
        $this->startDate = $base->copy()->startOfMonth()->toDateString();
        $this->endDate = $base->copy()->endOfMonth()->toDateString();
    }

    protected function resolvedRange(): array
    {
        $start = Carbon::parse($this->startDate ?: now()->toDateString())->startOfDay();
        $end = Carbon::parse($this->endDate ?: now()->toDateString())->endOfDay();

        if ($end->lt($start)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        return [$start, $end];
    }

    protected function refreshCharts(): void
    {
        [$start, $end] = $this->resolvedRange();
        $isSingleDay = $start->toDateString() === $end->toDateString();

        $paidBase = Pesanan::query()
            ->where('status', 'selesai')
            ->whereNotNull('metode_pembayaran')
            ->whereBetween('waktu_selesai', [$start, $end]);

        if ($isSingleDay) {
            $rows = (clone $paidBase)
                ->selectRaw('HOUR(waktu_selesai) as h, COALESCE(SUM(total_harga),0) as total, COUNT(*) as cnt')
                ->groupBy('h')
                ->orderBy('h')
                ->get();

            $map = $rows->keyBy('h');
            $labels = [];
            $values = [];
            $counts = [];
            for ($h = 0; $h <= 23; $h++) {
                $labels[] = str_pad((string) $h, 2, '0', STR_PAD_LEFT) . ':00';
                $values[] = (float) ($map[$h]->total ?? 0);
                $counts[] = (int) ($map[$h]->cnt ?? 0);
            }
        } else {
            $rows = (clone $paidBase)
                ->selectRaw('DATE(waktu_selesai) as d, COALESCE(SUM(total_harga),0) as total, COUNT(*) as cnt')
                ->groupBy('d')
                ->orderBy('d')
                ->get();

            $map = $rows->keyBy('d');
            $labels = [];
            $values = [];
            $counts = [];

            $cursor = $start->copy()->startOfDay();
            $endDay = $end->copy()->startOfDay();
            while ($cursor->lte($endDay)) {
                $key = $cursor->toDateString();
                $labels[] = $cursor->format('d M');
                $values[] = (float) ($map[$key]->total ?? 0);
                $counts[] = (int) ($map[$key]->cnt ?? 0);
                $cursor->addDay();
            }
        }

        $byMethod = (clone $paidBase)
            ->selectRaw('metode_pembayaran as method, COALESCE(SUM(total_harga),0) as total, COUNT(*) as cnt')
            ->groupBy('method')
            ->get()
            ->keyBy('method');

        $methodLabels = [__('Cash'), __('QRIS'), __('Transfer')];
        $methodKeys = ['tunai', 'qris', 'transfer'];
        $methodTotals = [];
        $methodCounts = [];
        foreach ($methodKeys as $k) {
            $methodTotals[] = (float) ($byMethod[$k]->total ?? 0);
            $methodCounts[] = (int) ($byMethod[$k]->cnt ?? 0);
        }

        $this->charts = [
            'timeseries' => [
                'labels' => $labels,
                'revenue' => $values,
                'orders' => $counts,
                'mode' => $isSingleDay ? 'hour' : 'day',
            ],
            'payment' => [
                'labels' => $methodLabels,
                'totals' => $methodTotals,
                'counts' => $methodCounts,
            ],
        ];
        $this->chartsRevision++;
    }
}; ?>

@php
        [$start, $end] = $this->resolvedRange();

        $paidQuery = Pesanan::query()
            ->where('status', 'selesai')
            ->whereNotNull('metode_pembayaran')
            ->whereBetween('waktu_selesai', [$start, $end]);

        $paidCount = (int) (clone $paidQuery)->count();
        $revenue = (float) (clone $paidQuery)->sum('total_harga');
        $discountTotal = (float) (clone $paidQuery)->sum('discount_total');
        $taxTotal = (float) (clone $paidQuery)->sum('tax_total');
        $avgOrder = $paidCount > 0 ? ($revenue / $paidCount) : 0;

        $cashCount = (int) (clone $paidQuery)->where('metode_pembayaran', 'tunai')->count();
        $cashTotal = (float) (clone $paidQuery)->where('metode_pembayaran', 'tunai')->sum('total_harga');
        $cashIn = (float) (clone $paidQuery)->where('metode_pembayaran', 'tunai')->sum('dibayar');
        $changeOut = (float) (clone $paidQuery)->where('metode_pembayaran', 'tunai')->sum('kembalian');

        $nonCashCount = (int) (clone $paidQuery)->whereIn('metode_pembayaran', ['qris', 'transfer'])->count();
        $nonCashTotal = (float) (clone $paidQuery)->whereIn('metode_pembayaran', ['qris', 'transfer'])->sum('total_harga');

        $statusCounts = Pesanan::query()
            ->select('status', DB::raw('COUNT(*) as agg'))
            ->whereBetween('waktu_pesan', [$start, $end])
            ->groupBy('status')
            ->pluck('agg', 'status')
            ->all();

        $readyUnpaid = (int) Pesanan::query()
            ->where('status', 'siap')
            ->whereBetween('waktu_pesan', [$start, $end])
            ->where(function ($q) {
                $q->whereNull('metode_pembayaran')->orWhere('metode_pembayaran', '');
            })
            ->count();

        $monthOptions = collect(range(1, 12))
            ->mapWithKeys(fn ($m) => [$m => Carbon::create(null, $m, 1)->format('F')])
            ->all();
        $yearOptions = collect(range((int) now()->format('Y') - 5, (int) now()->format('Y') + 1))
            ->values()
            ->all();

        $topMenus = DB::table('pesanan_details')
            ->join('pesanans', 'pesanans.id', '=', 'pesanan_details.pesanan_id')
            ->join('menus', 'menus.id', '=', 'pesanan_details.menu_id')
            ->where('pesanans.status', 'selesai')
            ->whereNotNull('pesanans.metode_pembayaran')
            ->whereBetween('pesanans.waktu_selesai', [$start, $end])
            ->groupBy('menus.id', 'menus.nama_menu')
            ->selectRaw('menus.id, menus.nama_menu, COALESCE(SUM(pesanan_details.qty),0) as qty, COALESCE(SUM(pesanan_details.subtotal),0) as total')
            ->orderByDesc('qty')
            ->limit(10)
            ->get();

        $recent = Pesanan::query()
            ->with(['meja', 'kasir'])
            ->where('status', 'selesai')
            ->whereNotNull('metode_pembayaran')
            ->whereBetween('waktu_selesai', [$start, $end])
            ->orderByDesc('waktu_selesai')
            ->limit(10)
            ->get();
@endphp

<section class="w-full space-y-6">
    @php
        $summaryCard = 'rounded-xl sm:rounded-2xl border border-neutral-200/70 bg-white/85 p-3 sm:p-4 shadow-sm sm:shadow-lg shadow-neutral-200/40 dark:border-neutral-700/60 dark:bg-neutral-900/70 dark:shadow-black/30';
        $panelCard = 'rounded-3xl border border-neutral-200/80 bg-gradient-to-b from-white/95 via-white/90 to-white/70 shadow-2xl shadow-neutral-200/60 backdrop-blur-xl dark:border-neutral-800/80 dark:from-neutral-950/80 dark:via-neutral-950/60 dark:to-neutral-950/40 dark:shadow-black/30';
        $innerCard = 'rounded-2xl border border-neutral-200/70 bg-white/70 dark:border-neutral-800/60 dark:bg-neutral-900/40';
        $filterSelectClass = 'rounded-full border-neutral-200 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900 focus:outline-hidden focus:ring-2 focus:ring-[color:var(--brand-accent)] focus:ring-offset-2 focus:ring-offset-[color:var(--brand-accent-foreground)]';

        $isSameDay = $start->toDateString() === $end->toDateString();
        $isSameMonth = $start->format('Y-m') === $end->format('Y-m');
        if ($isSameDay) {
            $rangeLabel = $start->format('d M Y');
        } elseif ($isSameMonth) {
            $rangeLabel = $start->format('d') . '–' . $end->format('d M Y');
        } else {
            $rangeLabel = $start->format('d M Y') . ' — ' . $end->format('d M Y');
        }
    @endphp

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl" level="1">{{ __('Dashboard') }}</flux:heading>
            <flux:subheading>{{ $start->format('d M Y') }} — {{ $end->format('d M Y') }}</flux:subheading>
        </div>

        <div class="flex flex-wrap items-center justify-start gap-2 sm:justify-end">
            <flux:link
                :href="route('dashboard.report', ['start' => $start->toDateString(), 'end' => $end->toDateString()], false)"
                target="_blank"
            >
                <flux:button size="sm" variant="ghost" icon="printer" class="btn-ghost-accent rounded-full">
                    {{ __('Report PDF') }}
                </flux:button>
            </flux:link>

            <!-- Minimal date pill + popover -->
            <flux:dropdown position="bottom" align="start">
            <flux:button
                size="sm"
                variant="ghost"
                icon="calendar-days"
                class="btn-ghost-accent rounded-full"
            >
                {{ $rangeLabel }}
            </flux:button>

            <flux:menu
                keep-open
                class="w-[360px] max-w-[calc(100vw-2rem)]"
            >
                <div class="p-2 space-y-3">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <div class="text-sm font-semibold text-neutral-900 dark:text-white">{{ __('Date Filter') }}</div>
                            <div class="text-xs text-neutral-500 dark:text-neutral-400">{{ __('Updates instantly for all cards & charts.') }}</div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <flux:button
                            size="sm"
                            variant="ghost"
                            class="{{ $filterMode === 'range' ? 'btn-accent' : 'btn-ghost-accent' }} rounded-full w-full"
                            wire:click="$set('filterMode','range')"
                            wire:loading.attr="disabled"
                            wire:target="filterMode,startDate,endDate,month,year,setPreset"
                        >
                            {{ __('Range') }}
                        </flux:button>
                        <flux:button
                            size="sm"
                            variant="ghost"
                            class="{{ $filterMode === 'month' ? 'btn-accent' : 'btn-ghost-accent' }} rounded-full w-full"
                            wire:click="$set('filterMode','month')"
                            wire:loading.attr="disabled"
                            wire:target="filterMode,startDate,endDate,month,year,setPreset"
                        >
                            {{ __('Month') }}
                        </flux:button>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <flux:button size="sm" variant="ghost" class="btn-ghost-accent rounded-full" wire:click="setPreset('yesterday')" wire:loading.attr="disabled" wire:target="setPreset,startDate,endDate,month,year,filterMode">{{ __('Yesterday') }}</flux:button>
                        <flux:button size="sm" variant="ghost" class="btn-ghost-accent rounded-full" wire:click="setPreset('today')" wire:loading.attr="disabled" wire:target="setPreset,startDate,endDate,month,year,filterMode">{{ __('Today') }}</flux:button>
                        <flux:button size="sm" variant="ghost" class="btn-ghost-accent rounded-full" wire:click="setPreset('7d')" wire:loading.attr="disabled" wire:target="setPreset,startDate,endDate,month,year,filterMode">{{ __('7D') }}</flux:button>
                        <flux:button size="sm" variant="ghost" class="btn-ghost-accent rounded-full" wire:click="setPreset('this_month')" wire:loading.attr="disabled" wire:target="setPreset,startDate,endDate,month,year,filterMode">{{ __('This Month') }}</flux:button>
                    </div>

                    <div class="pt-1">
                        <div class="{{ $filterMode === 'month' ? '' : 'hidden' }}">
                            <div class="grid gap-2 sm:grid-cols-2">
                                <flux:select wire:model.live="month" :label="__('Month')" class="{{ $filterSelectClass }}" wire:loading.attr="disabled" wire:target="month,year,filterMode,setPreset">
                                    @foreach ($monthOptions as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </flux:select>
                                <flux:select wire:model.live="year" :label="__('Year')" class="{{ $filterSelectClass }}" wire:loading.attr="disabled" wire:target="month,year,filterMode,setPreset">
                                    @foreach ($yearOptions as $y)
                                        <option value="{{ $y }}">{{ $y }}</option>
                                    @endforeach
                                </flux:select>
                            </div>
                        </div>

                        <div class="{{ $filterMode === 'month' ? 'hidden' : '' }}">
                            <div class="grid gap-2 sm:grid-cols-2">
                                <flux:input wire:model.live="startDate" type="date" :label="__('Start')" wire:loading.attr="disabled" wire:target="startDate,endDate,filterMode,setPreset" />
                                <flux:input wire:model.live="endDate" type="date" :label="__('End')" wire:loading.attr="disabled" wire:target="startDate,endDate,filterMode,setPreset" />
                            </div>
                        </div>
                    </div>
                </div>
            </flux:menu>
        </flux:dropdown>
        </div>
    </div>

        <!-- Mobile KPI chips -->
        <div class="block sm:hidden -mx-4 overflow-x-auto no-scrollbar">
            <div class="flex gap-2 px-4">
                <div class="whitespace-nowrap rounded-full border border-neutral-200/70 bg-white/85 px-3 py-2 text-[11px] shadow-sm dark:border-neutral-700/60 dark:bg-neutral-900/70">
                    <span class="inline-flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-[color:var(--brand-accent)]"></span>
                        <span class="text-neutral-600 dark:text-neutral-300">{{ __('Revenue') }}</span>
                        <span class="font-semibold text-neutral-900 dark:text-white">Rp {{ number_format($revenue, 0, ',', '.') }}</span>
                    </span>
                </div>
                <div class="whitespace-nowrap rounded-full border border-neutral-200/70 bg-white/85 px-3 py-2 text-[11px] shadow-sm dark:border-neutral-700/60 dark:bg-neutral-900/70">
                    <span class="inline-flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-neutral-500"></span>
                        <span class="text-neutral-600 dark:text-neutral-300">{{ __('Orders') }}</span>
                        <span class="font-semibold text-neutral-900 dark:text-white">{{ $paidCount }}</span>
                    </span>
                </div>
                <div class="whitespace-nowrap rounded-full border border-neutral-200/70 bg-white/85 px-3 py-2 text-[11px] shadow-sm dark:border-neutral-700/60 dark:bg-neutral-900/70">
                    <span class="inline-flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                        <span class="text-neutral-600 dark:text-neutral-300">{{ __('Avg') }}</span>
                        <span class="font-semibold text-neutral-900 dark:text-white">Rp {{ number_format($avgOrder, 0, ',', '.') }}</span>
                    </span>
                </div>
                <div class="whitespace-nowrap rounded-full border border-neutral-200/70 bg-white/85 px-3 py-2 text-[11px] shadow-sm dark:border-neutral-700/60 dark:bg-neutral-900/70">
                    <span class="inline-flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                        <span class="text-neutral-600 dark:text-neutral-300">{{ __('Discount') }}</span>
                        <span class="font-semibold text-neutral-900 dark:text-white">Rp {{ number_format($discountTotal, 0, ',', '.') }}</span>
                    </span>
                </div>
                <div class="whitespace-nowrap rounded-full border border-neutral-200/70 bg-white/85 px-3 py-2 text-[11px] shadow-sm dark:border-neutral-700/60 dark:bg-neutral-900/70">
                    <span class="inline-flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-sky-500"></span>
                        <span class="text-neutral-600 dark:text-neutral-300">{{ __('Tax') }}</span>
                        <span class="font-semibold text-neutral-900 dark:text-white">Rp {{ number_format($taxTotal, 0, ',', '.') }}</span>
                    </span>
                </div>
            </div>
        </div>

        <!-- KPI cards -->
        <div class="hidden sm:grid gap-2 sm:gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5">
            <div class="{{ $summaryCard }}">
                <div class="flex items-center gap-2">
                    <span class="h-2 w-2 rounded-full bg-[color:var(--brand-accent)]"></span>
                    <p class="text-[11px] sm:text-xs font-medium uppercase tracking-[0.2em] text-neutral-400">{{ __('Revenue') }}</p>
                </div>
                <div class="mt-3 text-2xl font-semibold text-neutral-900 dark:text-white">Rp {{ number_format($revenue, 0, ',', '.') }}</div>
                <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">{{ __('Paid orders') }}: {{ $paidCount }}</p>
            </div>
            <div class="{{ $summaryCard }}">
                <div class="flex items-center gap-2">
                    <span class="h-2 w-2 rounded-full bg-neutral-500"></span>
                    <p class="text-[11px] sm:text-xs font-medium uppercase tracking-[0.2em] text-neutral-400">{{ __('Orders') }}</p>
                </div>
                <div class="mt-3 text-2xl font-semibold text-neutral-900 dark:text-white">{{ $paidCount }}</div>
                <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">{{ __('Ready unpaid') }}: {{ $readyUnpaid }}</p>
            </div>
            <div class="{{ $summaryCard }}">
                <div class="flex items-center gap-2">
                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                    <p class="text-[11px] sm:text-xs font-medium uppercase tracking-[0.2em] text-neutral-400">{{ __('Avg / Order') }}</p>
                </div>
                <div class="mt-3 text-2xl font-semibold text-neutral-900 dark:text-white">Rp {{ number_format($avgOrder, 0, ',', '.') }}</div>
                <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">{{ __('Gross average') }}</p>
            </div>
            <div class="{{ $summaryCard }}">
                <div class="flex items-center gap-2">
                    <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                    <p class="text-[11px] sm:text-xs font-medium uppercase tracking-[0.2em] text-neutral-400">{{ __('Discounts') }}</p>
                </div>
                <div class="mt-3 text-2xl font-semibold text-neutral-900 dark:text-white">Rp {{ number_format($discountTotal, 0, ',', '.') }}</div>
                <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">{{ __('Total discount given') }}</p>
            </div>
            <div class="{{ $summaryCard }}">
                <div class="flex items-center gap-2">
                    <span class="h-2 w-2 rounded-full bg-sky-500"></span>
                    <p class="text-[11px] sm:text-xs font-medium uppercase tracking-[0.2em] text-neutral-400">{{ __('Taxes') }}</p>
                </div>
                <div class="mt-3 text-2xl font-semibold text-neutral-900 dark:text-white">Rp {{ number_format($taxTotal, 0, ',', '.') }}</div>
                <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">{{ __('Total tax collected') }}</p>
            </div>
        </div>

        <!-- Charts (wire:ignore to avoid canvas diff issues) -->
    <div
        wire:key="dashboard-charts-{{ $chartsRevision }}"
        x-data="dashboardCharts()"
        data-charts='@json($charts)'
        data-revenue-label="{{ e(__('Revenue')) }}"
        data-revenue-per-hour-label="{{ e(__('Revenue per hour')) }}"
        data-revenue-per-day-label="{{ e(__('Revenue per day')) }}"
        class="grid gap-3 md:grid-cols-3"
    >
        <div class="{{ $panelCard }} p-3 sm:p-4 md:col-span-2">
                <div>
                    <flux:heading size="lg">
                        <span x-text="charts?.timeseries?.mode === 'hour' ? options.revenuePerHourLabel : options.revenuePerDayLabel"></span>
                    </flux:heading>
                    <flux:subheading>{{ __('Paid revenue (orders completed).') }}</flux:subheading>
                </div>
            <div class="mt-4 h-56 sm:h-64">
                <canvas wire:ignore x-ref="revenueChart" class="h-full w-full"></canvas>
            </div>
        </div>

        <div class="{{ $panelCard }} p-3 sm:p-4">
                <div>
                    <flux:heading size="lg">{{ __('Payment method') }}</flux:heading>
                    <flux:subheading>{{ __('Distribution by total amount.') }}</flux:subheading>
                </div>
            <div class="mt-4 h-56 sm:h-64">
                <canvas wire:ignore x-ref="paymentChart" class="h-full w-full"></canvas>
            </div>
        </div>
    </div>

        <!-- Payment snapshot + status -->
        <div class="grid gap-3 md:grid-cols-3">
            <div class="{{ $panelCard }} p-3 sm:p-4 md:col-span-2">
                <div>
                    <flux:heading size="lg">{{ __('Payment Snapshot') }}</flux:heading>
                    <flux:subheading>{{ __('Cash vs non-cash for paid orders.') }}</flux:subheading>
                </div>

                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <div class="{{ $innerCard }} p-4">
                        <div class="flex items-center justify-between">
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-neutral-400">{{ __('Cash') }}</p>
                            <span class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-[11px] font-semibold text-emerald-700 ring-1 ring-emerald-100 dark:bg-emerald-900/40 dark:text-emerald-200 dark:ring-emerald-800/60">
                                <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                {{ $cashCount }} {{ __('orders') }}
                            </span>
                        </div>
                        <p class="mt-2 text-xl font-semibold text-neutral-900 dark:text-white">Rp {{ number_format($cashTotal, 0, ',', '.') }}</p>
                        <div class="mt-2 grid grid-cols-2 gap-2 text-xs text-neutral-600 dark:text-neutral-300">
                            <div>
                                <p class="text-neutral-400">{{ __('Cash in') }}</p>
                                <p class="font-semibold text-neutral-900 dark:text-white">Rp {{ number_format($cashIn, 0, ',', '.') }}</p>
                            </div>
                            <div>
                                <p class="text-neutral-400">{{ __('Change') }}</p>
                                <p class="font-semibold text-neutral-900 dark:text-white">Rp {{ number_format($changeOut, 0, ',', '.') }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="{{ $innerCard }} p-4">
                        <div class="flex items-center justify-between">
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-neutral-400">{{ __('Non-cash') }}</p>
                            <span class="inline-flex items-center gap-2 rounded-full bg-neutral-100 px-3 py-1 text-[11px] font-semibold text-neutral-600 ring-1 ring-neutral-200 dark:bg-neutral-800/60 dark:text-neutral-200 dark:ring-neutral-700">
                                <span class="h-2 w-2 rounded-full bg-neutral-400"></span>
                                {{ $nonCashCount }} {{ __('orders') }}
                            </span>
                        </div>
                        <p class="mt-2 text-xl font-semibold text-neutral-900 dark:text-white">Rp {{ number_format($nonCashTotal, 0, ',', '.') }}</p>
                        <p class="mt-2 text-xs text-neutral-500 dark:text-neutral-400">{{ __('Includes QRIS and Transfer.') }}</p>
                    </div>
                </div>
            </div>

            <div class="{{ $panelCard }} p-3 sm:p-4">
                <flux:heading size="lg">{{ __('Order Status') }}</flux:heading>
                <flux:subheading>{{ __('Counts based on order time.') }}</flux:subheading>

                @php
                    $statusLabels = [
                        'menunggu' => __('Waiting'),
                        'diproses' => __('Processing'),
                        'siap' => __('Ready'),
                        'selesai' => __('Completed'),
                        'batal' => __('Canceled'),
                    ];
                    $statusColors = [
                        'menunggu' => 'bg-amber-500',
                        'diproses' => 'bg-sky-500',
                        'siap' => 'bg-emerald-500',
                        'selesai' => 'bg-neutral-700 dark:bg-neutral-300',
                        'batal' => 'bg-red-500',
                    ];
                @endphp

                <div class="mt-4 space-y-2">
                    @foreach ($statusLabels as $key => $label)
                        <div class="flex items-center justify-between {{ $innerCard }} px-3 py-2 text-sm">
                            <div class="flex items-center gap-2">
                                <span class="h-2 w-2 rounded-full {{ $statusColors[$key] }}"></span>
                                <span class="text-neutral-700 dark:text-neutral-200">{{ $label }}</span>
                            </div>
                            <span class="font-semibold text-neutral-900 dark:text-white">{{ (int) ($statusCounts[$key] ?? 0) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

    <!-- Top lists + recent -->
    <div class="grid gap-3 md:grid-cols-2">
        <div class="{{ $panelCard }} p-3 sm:p-4">
            <flux:heading size="lg">{{ __('Top Menus') }}</flux:heading>
            <flux:subheading>{{ __('By quantity within the selected range.') }}</flux:subheading>

                <div class="mt-4 space-y-2">
                    @forelse ($topMenus as $row)
                        <div class="flex items-start justify-between gap-3 {{ $innerCard }} px-3 py-2">
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-neutral-900 dark:text-white">{{ $row->nama_menu }}</p>
                                <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ (int) $row->qty }} {{ __('items') }}</p>
                            </div>
                            <p class="shrink-0 text-sm font-semibold text-neutral-900 dark:text-white">Rp {{ number_format((float) $row->total, 0, ',', '.') }}</p>
                        </div>
                    @empty
                        <div class="{{ $innerCard }} p-6 text-center text-sm text-neutral-500 dark:text-neutral-400">{{ __('No data') }}</div>
                    @endforelse
                </div>
            </div>

            <div class="{{ $panelCard }} p-3 sm:p-4">
            <flux:heading size="lg">{{ __('Recent payments') }}</flux:heading>
            <flux:subheading>{{ __('Last 10 paid orders in range.') }}</flux:subheading>

                <div class="mt-4 space-y-2">
                    @forelse ($recent as $p)
                        <a href="{{ route('pembayaran.receipt', $p) }}" class="block {{ $innerCard }} px-3 py-2 transition hover:bg-white dark:hover:bg-neutral-900/60" wire:navigate>
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate font-mono text-xs font-semibold text-neutral-900 dark:text-white">{{ $p->kode_pesanan }}</p>
                                    <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ __('Table') }}: {{ $p->meja?->nomor_meja ?? '—' }} · {{ $p->waktu_selesai?->format('d M H:i') }}</p>
                                    <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ __('Cashier') }}: {{ $p->kasir?->name ?? '—' }}</p>
                                </div>
                                <div class="shrink-0 text-right">
                                    <p class="text-sm font-semibold text-neutral-900 dark:text-white">Rp {{ number_format((float) $p->total_harga, 0, ',', '.') }}</p>
                                    <p class="text-[11px] text-neutral-500 dark:text-neutral-400">{{ strtoupper((string) $p->metode_pembayaran) }}</p>
                                </div>
                            </div>
                        </a>
                    @empty
                        <div class="{{ $innerCard }} p-6 text-center text-sm text-neutral-500 dark:text-neutral-400">{{ __('No payments found.') }}</div>
                    @endforelse
                </div>
            </div>
    </div>
</section>
