<?php

use App\Models\Diskon;
use Illuminate\Support\Facades\Cache;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public ?int $confirmingDeleteId = null;
    public ?int $editingId = null;
    public array $form = [];
    public string $search = '';
    public string $statusFilter = 'all';

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => 'all'],
        'page' => ['except' => 1],
    ];

    public function mount(): void
    {
        $this->resetCreateForm();
    }

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingStatusFilter(): void { $this->resetPage(); }

    public function openCreateModal(): void
    {
        $this->authorizeManage();
        $this->resetCreateForm();
        $this->dispatch('modal-show', name: 'create-diskon');
    }

    public function openEditModal(int $id): void
    {
        $this->authorizeManage();
        $diskon = Diskon::query()
            ->select([
                'id',
                'kode',
                'tipe',
                'nilai',
                'min_subtotal',
                'tanggal_mulai',
                'tanggal_selesai',
                'is_active',
            ])
            ->findOrFail($id);
        $this->editingId = $diskon->id;

        $this->form = [
            'kode'            => $diskon->kode,
            'tipe'            => $diskon->tipe,
            'nilai'           => $diskon->nilai,
            'min_subtotal'    => $diskon->min_subtotal,
            'is_active'       => (bool) $diskon->is_active,
            'tanggal_mulai'   => optional($diskon->tanggal_mulai)?->format('Y-m-d'),
            'tanggal_selesai' => optional($diskon->tanggal_selesai)?->format('Y-m-d'),
        ];

        $this->dispatch('modal-show', name: 'edit-diskon');
    }

    public function save(): void
    {
        $this->authorizeManage();
        $validated = validator($this->form, [
            'kode'            => ['required', 'string', 'max:50', 'unique:diskons,kode'],
            'tipe'            => ['required', 'in:percent,nominal'],
            'nilai'           => ['required', 'numeric', 'min:0'],
            'min_subtotal'    => ['nullable', 'numeric', 'min:0'],
            'is_active'       => ['boolean'],
            'tanggal_mulai'   => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
        ])->validate();

        $validated['is_active'] = (bool) ($validated['is_active'] ?? false);

        Diskon::create($validated);
        Cache::forget('admin:diskon:stats:v1');

        $this->resetCreateForm();
        $this->dispatch('modal-close', name: 'create-diskon');
        $this->dispatch('diskon-toast', message: __('Discount created successfully.'));
    }

    public function update(): void
    {
        $this->authorizeManage();
        if (!$this->editingId) return;

        $validated = validator($this->form, [
            'kode'            => ['required', 'string', 'max:50', 'unique:diskons,kode,' . $this->editingId],
            'tipe'            => ['required', 'in:percent,nominal'],
            'nilai'           => ['required', 'numeric', 'min:0'],
            'min_subtotal'    => ['nullable', 'numeric', 'min:0'],
            'is_active'       => ['boolean'],
            'tanggal_mulai'   => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
        ])->validate();

        $validated['is_active'] = (bool) ($validated['is_active'] ?? false);

        Diskon::where('id', $this->editingId)->update($validated);
        Cache::forget('admin:diskon:stats:v1');

        $this->editingId = null;
        $this->dispatch('modal-close', name: 'edit-diskon');
        $this->dispatch('diskon-toast', message: __('Discount updated successfully.'));
    }

    public function confirmDelete(int $id): void
    {
        $this->authorizeManage();
        $this->confirmingDeleteId = $id;
    }

    public function delete(): void
    {
        $this->authorizeManage();
        if ($this->confirmingDeleteId) {
            Diskon::where('id', $this->confirmingDeleteId)->delete();
            Cache::forget('admin:diskon:stats:v1');
            $this->confirmingDeleteId = null;

            $this->dispatch('modal-close', name: 'confirm-delete-diskon');
            $this->dispatch('modal-close', name: 'confirm-delete-diskon-desktop');
            $this->dispatch('diskon-toast', message: __('Discount deleted successfully.'));
        }
    }

    protected function resetCreateForm(): void
    {
        $this->form = [
            'kode'            => '',
            'tipe'            => 'percent',
            'nilai'           => null,
            'min_subtotal'    => null,
            'is_active'       => true,
            'tanggal_mulai'   => null,
            'tanggal_selesai' => null,
        ];
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
                    <flux:button
                        variant="primary"
                        icon="plus"
                        class="btn-brand"
                        wire:click="openCreateModal"
                    >
                        {{ __('Create') }}
                    </flux:button>
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
                                    icon="pencil-square"
                                    variant="primary"
                                    class="flex-1 btn-accent"
                                    wire:click="openEditModal({{ $d->id }})"
                                >
                                    {{ __('Edit') }}
                                </flux:button>
                                <flux:modal.trigger name="confirm-delete-diskon" class="flex-1">
                                    <flux:button
                                        size="sm"
                                        icon="trash"
                                        variant="danger"
                                        class="w-full"
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
                                    icon="pencil-square"
                                    variant="primary"
                                    class="w-full btn-accent"
                                    wire:click="openEditModal({{ $d->id }})"
                                >
                                    {{ __('Edit') }}
                                </flux:button>
                                <flux:modal.trigger name="confirm-delete-diskon-desktop">
                                    <flux:button
                                        size="sm"
                                        icon="trash"
                                        variant="danger"
                                        class="w-full"
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
                    <table class="min-w-full text-sm text-left">
                        <thead>
                            <tr>
                                <th class="px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">{{ __('ID') }}</th>
                                <th class="px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">{{ __('Code') }}</th>
                                <th class="px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">{{ __('Value') }}</th>
                                <th class="hidden md:table-cell px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">{{ __('Requirements') }}</th>
                                <th class="hidden lg:table-cell px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">{{ __('Period') }}</th>
                                <th class="px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">{{ __('Status') }}</th>
                                <th class="px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100/80 text-neutral-700 dark:divide-neutral-900/40 dark:text-neutral-200">
                            @forelse($items as $d)
                                <tr class="group transition hover:bg-white/70 focus-within:bg-white/90 dark:hover:bg-neutral-900/40 dark:focus-within:bg-neutral-900/50">
                                    <td class="px-6 py-4 align-middle">
                                        <span class="inline-flex items-center rounded-full bg-neutral-900/5 px-3 py-1 text-xs font-semibold text-neutral-500 dark:bg-white/5 dark:text-neutral-300">
                                            #{{ str_pad($d->id, 3, '0', STR_PAD_LEFT) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 align-middle">
                                        <div class="flex flex-col">
                                            <span class="font-mono text-sm font-semibold text-neutral-900 dark:text-white">
                                                {{ $d->kode }}
                                            </span>
                                            <span class="hidden sm:block text-[11px] text-neutral-500 dark:text-neutral-400">
                                                {{ $d->tipe === 'percent' ? __('Percent') : __('Nominal') }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 align-middle">
                                        <span class="text-sm font-medium text-neutral-900 dark:text-neutral-50">
                                            @if($d->tipe === 'percent')
                                                -{{ number_format($d->nilai, 2) }}%
                                            @else
                                                -Rp {{ number_format($d->nilai, 0, ',', '.') }}
                                            @endif
                                        </span>
                                    </td>
                                    <td class="hidden md:table-cell px-6 py-4 align-middle">
                                        <span class="text-xs text-neutral-500 dark:text-neutral-400">
                                            @if($d->min_subtotal !== null)
                                                {{ __('Min. subtotal') }}: Rp {{ number_format($d->min_subtotal, 0, ',', '.') }}
                                            @else
                                                {{ __('No minimum subtotal') }}
                                            @endif
                                        </span>
                                    </td>
                                    <td class="hidden lg:table-cell px-6 py-4 align-middle">
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
                                    <td class="px-6 py-4 align-middle">
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
                                    <td class="px-6 py-4 align-middle">
                                        <div class="flex flex-wrap items-center gap-2">
                                            @can('diskon.manage')
                                                <flux:button
                                                    size="sm"
                                                    icon="pencil-square"
                                                    variant="primary"
                                                    class="btn-accent rounded-2xl shadow-sm transition"
                                                    wire:click="openEditModal({{ $d->id }})"
                                                >
                                                    {{ __('Edit') }}
                                                </flux:button>
                                                <flux:modal.trigger name="confirm-delete-diskon-desktop">
                                                    <flux:button
                                                        size="sm"
                                                        icon="trash"
                                                        variant="danger"
                                                        class="rounded-2xl shadow-sm transition"
                                                        wire:click="confirmDelete({{ $d->id }})"
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
                        {{ __('This action cannot be undone. This record will be permanently deleted.') }}
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
                        {{ __('This action cannot be undone. This record will be permanently deleted.') }}
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

        <!-- Create discount modal -->
        <flux:modal name="create-diskon" focusable class="mx-4 w-[calc(100%-2rem)] sm:mx-auto sm:max-w-4xl md:max-w-3xl lg:max-w-4xl">
            <div class="flex flex-col max-h-[85dvh] overflow-y-auto no-scrollbar md:max-h-none md:overflow-visible">
                <div class="sticky top-0 z-0 -mx-4 flex items-start justify-between gap-2 border-b border-neutral-200 bg-white/85 px-4 py-3 pr-12 backdrop-blur dark:border-neutral-700 dark:bg-neutral-900/70">
                    <div>
                        <flux:heading size="lg">{{ __('Create Discount') }}</flux:heading>
                        <flux:subheading>{{ __('Fill the details below to add a new discount code.') }}</flux:subheading>
                    </div>
                </div>

                <form id="create-diskon-form" wire:submit.prevent="save" class="flex-1 space-y-6 px-1 py-4 pb-[calc(env(safe-area-inset-bottom)+0.75rem)] md:pb-0">
                    <div class="grid gap-6 md:grid-cols-2">
                        <div class="space-y-4">
                            <flux:input
                                wire:model.defer="form.kode"
                                :label="__('Code')"
                                required
                                maxlength="50"
                                help="{{ __('Suggestion: use uppercase, e.g. WEEKNIGHT10') }}"
                            />
                            @error('form.kode')
                                <p class="text-xs text-red-500">{{ $message }}</p>
                            @enderror

                            <div data-flux-field>
                                <flux:select wire:model.defer="form.tipe" :label="__('Type')">
                                    <option value="percent">{{ __('Percent') }}</option>
                                    <option value="nominal">{{ __('Nominal') }}</option>
                                </flux:select>
                                @error('form.tipe')
                                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <flux:input
                                wire:model.defer="form.nilai"
                                type="number"
                                min="0"
                                step="0.01"
                                inputmode="numeric"
                                placeholder="0"
                                :label="__('Value')"
                                required
                                help="{{ __('Percent: 10 = 10% · Nominal: 10000 = Rp 10.000') }}"
                            />
                            @error('form.nilai')
                                <p class="text-xs text-red-500">{{ $message }}</p>
                            @enderror

                            <flux:input
                                wire:model.defer="form.min_subtotal"
                                type="number"
                                min="0"
                                step="100"
                                :label="__('Minimum Subtotal')"
                                help="{{ __('Optional. Leave empty for no minimum.') }}"
                            />
                            @error('form.min_subtotal')
                                <p class="text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-4">
                            <div class="grid gap-3 md:grid-cols-2">
                                <flux:input
                                    wire:model.defer="form.tanggal_mulai"
                                    type="date"
                                    :label="__('Start Date')"
                                />
                                @error('form.tanggal_mulai')
                                    <p class="text-xs text-red-500 md:col-span-2">{{ $message }}</p>
                                @enderror

                                <flux:input
                                    wire:model.defer="form.tanggal_selesai"
                                    type="date"
                                    :label="__('End Date')"
                                />
                                @error('form.tanggal_selesai')
                                    <p class="text-xs text-red-500 md:col-span-2">{{ $message }}</p>
                                @enderror
                            </div>

                            <flux:checkbox
                                wire:model.defer="form.is_active"
                                :label="__('Active')"
                            />
                            @error('form.is_active')
                                <p class="text-xs text-red-500">{{ $message }}</p>
                            @enderror

                            <div class="rounded-2xl border border-neutral-200/70 bg-white p-3 text-xs text-neutral-500 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-400">
                                <flux:heading size="sm">{{ __('Tips') }}</flux:heading>
                                <ul class="mt-2 list-disc space-y-1 pl-4">
                                    <li>{{ __('Use uppercase codes without spaces, e.g. "BALEGANJUR10".') }}</li>
                                    <li>{{ __('Percent type uses the value as a percentage (e.g. 10 = 10%).') }}</li>
                                    <li>{{ __('Nominal type uses the value as a fixed amount (e.g. 10000 = Rp 10.000).') }}</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="hidden md:flex items-center justify-end gap-3 pt-2 px-3">
                        <flux:modal.close>
                            <flux:button type="button" variant="ghost" class="btn-ghost-accent">{{ __('Cancel') }}</flux:button>
                        </flux:modal.close>
                        <flux:button type="submit" form="create-diskon-form" variant="primary" icon="plus" class="btn-brand">{{ __('Create') }}</flux:button>
                    </div>
                </form>

                <div class="sticky bottom-0 z-10 -mx-4 md:hidden border-t border-neutral-200 bg-white/90 px-4 py-3 pb-[max(env(safe-area-inset-bottom),0.75rem)] backdrop-blur dark:border-neutral-700 dark:bg-neutral-900/80">
                    <div class="grid grid-cols-2 gap-2">
                        <flux:modal.close>
                            <flux:button type="button" variant="ghost" class="btn-ghost-accent w-full">{{ __('Cancel') }}</flux:button>
                        </flux:modal.close>
                        <flux:button type="submit" form="create-diskon-form" variant="primary" icon="plus" class="btn-brand w-full">{{ __('Create') }}</flux:button>
                    </div>
                </div>
            </div>
        </flux:modal>

        <!-- Edit discount modal -->
        <flux:modal name="edit-diskon" focusable class="mx-4 w-[calc(100%-2rem)] sm:mx-auto sm:max-w-4xl md:max-w-3xl lg:max-w-4xl">
            <div class="flex flex-col max-h-[85dvh] overflow-y-auto no-scrollbar md:max-h-none md:overflow-visible">
                <div class="sticky top-0 z-0 -mx-4 flex items-start justify-between gap-2 border-b border-neutral-200 bg-white/85 px-4 py-3 pr-12 backdrop-blur dark:border-neutral-700 dark:bg-neutral-900/70">
                    <div>
                        <flux:heading size="lg">{{ __('Edit Discount') }}</flux:heading>
                        <flux:subheading>{{ __('Update the details for this discount code.') }}</flux:subheading>
                    </div>
                </div>

                <form id="edit-diskon-form" wire:submit.prevent="update" class="flex-1 space-y-6 px-1 py-4 pb-[calc(env(safe-area-inset-bottom)+0.75rem)] md:pb-0">
                    <div class="grid gap-6 md:grid-cols-2">
                        <div class="space-y-4">
                            <flux:input
                                wire:model.defer="form.kode"
                                :label="__('Code')"
                                required
                                maxlength="50"
                                help="{{ __('Suggestion: use uppercase, e.g. WEEKNIGHT10') }}"
                            />
                            @error('form.kode')
                                <p class="text-xs text-red-500">{{ $message }}</p>
                            @enderror

                            <div data-flux-field>
                                <flux:select wire:model.defer="form.tipe" :label="__('Type')">
                                    <option value="percent">{{ __('Percent') }}</option>
                                    <option value="nominal">{{ __('Nominal') }}</option>
                                </flux:select>
                                @error('form.tipe')
                                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <flux:input
                                wire:model.defer="form.nilai"
                                type="number"
                                min="0"
                                step="0.01"
                                inputmode="numeric"
                                placeholder="0"
                                :label="__('Value')"
                                required
                                help="{{ __('Percent: 10 = 10% · Nominal: 10000 = Rp 10.000') }}"
                            />
                            @error('form.nilai')
                                <p class="text-xs text-red-500">{{ $message }}</p>
                            @enderror

                            <flux:input
                                wire:model.defer="form.min_subtotal"
                                type="number"
                                min="0"
                                step="100"
                                :label="__('Minimum Subtotal')"
                                help="{{ __('Optional. Leave empty for no minimum.') }}"
                            />
                            @error('form.min_subtotal')
                                <p class="text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-4">
                            <div class="grid gap-3 md:grid-cols-2">
                                <flux:input
                                    wire:model.defer="form.tanggal_mulai"
                                    type="date"
                                    :label="__('Start Date')"
                                />
                                @error('form.tanggal_mulai')
                                    <p class="text-xs text-red-500 md:col-span-2">{{ $message }}</p>
                                @enderror

                                <flux:input
                                    wire:model.defer="form.tanggal_selesai"
                                    type="date"
                                    :label="__('End Date')"
                                />
                                @error('form.tanggal_selesai')
                                    <p class="text-xs text-red-500 md:col-span-2">{{ $message }}</p>
                                @enderror
                            </div>

                            <flux:checkbox
                                wire:model.defer="form.is_active"
                                :label="__('Active')"
                            />
                            @error('form.is_active')
                                <p class="text-xs text-red-500">{{ $message }}</p>
                            @enderror

                            <div class="rounded-2xl border border-neutral-200/70 bg-white p-3 text-xs text-neutral-500 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-400">
                                <flux:heading size="sm">{{ __('Tips') }}</flux:heading>
                                <ul class="mt-2 list-disc space-y-1 pl-4">
                                    <li>{{ __('Adjust the value and period carefully, especially for running promotions.') }}</li>
                                    <li>{{ __('Deactivate discounts that should no longer be used instead of deleting them.') }}</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="hidden md:flex items-center justify-end gap-3 pt-2 px-3">
                        <flux:modal.close>
                            <flux:button type="button" variant="ghost" class="btn-ghost-accent">{{ __('Cancel') }}</flux:button>
                        </flux:modal.close>
                        <flux:button type="submit" form="edit-diskon-form" variant="primary" icon="check" class="btn-brand">{{ __('Update') }}</flux:button>
                    </div>
                </form>

                <div class="sticky bottom-0 z-10 -mx-4 md:hidden border-t border-neutral-200 bg-white/90 px-4 py-3 pb-[max(env(safe-area-inset-bottom),0.75rem)] backdrop-blur dark:border-neutral-700 dark:bg-neutral-900/80">
                    <div class="grid grid-cols-2 gap-2">
                        <flux:modal.close>
                            <flux:button type="button" variant="ghost" class="btn-ghost-accent w-full">{{ __('Cancel') }}</flux:button>
                        </flux:modal.close>
                        <flux:button type="submit" form="edit-diskon-form" variant="primary" icon="check" class="btn-brand w-full">{{ __('Update') }}</flux:button>
                    </div>
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
