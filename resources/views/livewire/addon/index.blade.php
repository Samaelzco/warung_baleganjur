<?php

use App\Models\Addon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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

    public function toggleStatus(int $id): void
    {
        $this->authorizeManage();

        $addon = Addon::query()->select(['id', 'status'])->findOrFail($id);
        $nextStatus = $addon->status === 'tersedia' ? 'habis' : 'tersedia';

        $addon->forceFill(['status' => $nextStatus])->save();

        Cache::forget('customer:menus_available:v1');
        Cache::forget('admin:addon:stats:v1');

        $this->dispatch('addon-toast', message: $nextStatus === 'habis'
            ? __('Add-on marked as out of stock.')
            : __('Add-on marked as available.'));
    }

    public function delete(): void
    {
        $this->authorizeManage();
        if (!$this->confirmingDeleteId) {
            return;
        }

        $isUsedInOrders = \Illuminate\Support\Facades\DB::table('pesanan_detail_addons')
            ->where('addon_id', $this->confirmingDeleteId)
            ->exists();

        if ($isUsedInOrders) {
            $this->dispatch('modal-close', name: 'confirm-delete-addon');
            $this->dispatch('modal-close', name: 'confirm-delete-addon-desktop');
            $this->dispatch('addon-toast', message: __('This add-on cannot be deleted because it is already used in orders.'));
            $this->confirmingDeleteId = null;
            return;
        }

        DB::transaction(function () {
            $addon = Addon::query()->select(['id'])->whereKey($this->confirmingDeleteId)->lockForUpdate()->first();
            if (!$addon) {
                return;
            }

            $addon->menus()->detach();
            $addon->delete();
        });
        Cache::forget('customer:menus_available:v1');
        Cache::forget('admin:addon:stats:v1');

        $this->confirmingDeleteId = null;
        $this->dispatch('modal-close', name: 'confirm-delete-addon');
        $this->dispatch('modal-close', name: 'confirm-delete-addon-desktop');
        $this->dispatch('addon-toast', message: __('Add-on deleted successfully.'));
    }

    protected function authorizeManage(): void
    {
        abort_unless(auth()->check() && auth()->user()->can('addon.manage'), 403);
    }
}; ?>

    <section class="w-full">
    @php
        $query = Addon::query()->select([
            'id',
            'nama_addon',
            'harga',
            'status',
        ]);

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_addon', 'like', "%{$search}%")
                    ->orWhere('nama_addon_en', 'like', "%{$search}%");
            });
        }

        if ($statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        $items = $query->orderBy('nama_addon')->paginate(10);

        $stats = Cache::remember('admin:addon:stats:v1', 10, fn () => [
            'total'     => Addon::query()->count(),
            'available' => Addon::query()->where('status', 'tersedia')->count(),
            'out'       => Addon::query()->where('status', 'habis')->count(),
            'linked'    => Addon::query()->has('menus')->count(),
        ]);

        $totalCount = (int) ($stats['total'] ?? 0);
        $availableCount = (int) ($stats['available'] ?? 0);
        $outCount = (int) ($stats['out'] ?? 0);
        $linkedCount = (int) ($stats['linked'] ?? 0);
    @endphp

    <div class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <flux:heading size="xl" level="1">{{ __('Add-ons') }}</flux:heading>
            <div class="flex flex-wrap items-center gap-2">
                @can('addon.manage')
                    <flux:link :href="route('addon.create', [], false)" wire:navigate>
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
                        <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                        <span class="text-neutral-600 dark:text-neutral-300">{{ __('Available') }}</span>
                        <span class="font-semibold text-neutral-900 dark:text-white">{{ $availableCount }}</span>
                    </span>
                </div>
                <div class="whitespace-nowrap rounded-full border border-neutral-200/70 bg-white/85 px-3 py-2 text-[11px] shadow-sm dark:border-neutral-700/60 dark:bg-neutral-900/70">
                    <span class="inline-flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-red-500"></span>
                        <span class="text-neutral-600 dark:text-neutral-300">{{ __('Out of stock') }}</span>
                        <span class="font-semibold text-neutral-900 dark:text-white">{{ $outCount }}</span>
                    </span>
                </div>
            </div>
        </div>

        <!-- Desktop summary cards (match discount) -->
        <div class="hidden sm:grid gap-2 sm:gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl sm:rounded-2xl border border-neutral-200/70 bg-white/85 p-3 sm:p-4 shadow-sm sm:shadow-lg shadow-neutral-200/40 dark:border-neutral-700/60 dark:bg-neutral-900/70 dark:shadow-black/30">
                <p class="text-[11px] sm:text-xs font-medium uppercase tracking-[0.18em] sm:tracking-[0.2em] text-neutral-400">{{ __('Total Add-ons') }}</p>
                <div class="mt-2 sm:mt-3 flex items-baseline gap-2">
                    <span class="text-2xl sm:text-3xl font-semibold text-neutral-900 dark:text-white">{{ $totalCount }}</span>
                    <span class="text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('items') }}</span>
                </div>
            </div>
            <div class="rounded-xl sm:rounded-2xl border border-neutral-200/70 bg-white/85 p-3 sm:p-4 shadow-sm sm:shadow-lg shadow-neutral-200/40 dark:border-neutral-700/60 dark:bg-neutral-900/70 dark:shadow-black/30">
                <div class="flex items-center gap-2 text-[11px] sm:text-xs font-medium uppercase tracking-[0.18em] sm:tracking-[0.2em] text-neutral-400">
                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                    <span>{{ __('Available') }}</span>
                </div>
                <div class="mt-2 sm:mt-3 flex items-baseline gap-2">
                    <span class="text-2xl sm:text-3xl font-semibold text-neutral-900 dark:text-white">{{ $availableCount }}</span>
                    <span class="text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('items') }}</span>
                </div>
                <p class="mt-1 text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('Can be selected on menus') }}</p>
            </div>
            <div class="rounded-xl sm:rounded-2xl border border-neutral-200/70 bg-white/85 p-3 sm:p-4 shadow-sm sm:shadow-lg shadow-neutral-200/40 dark:border-neutral-700/60 dark:bg-neutral-900/70 dark:shadow-black/30">
                <div class="flex items-center gap-2 text-[11px] sm:text-xs font-medium uppercase tracking-[0.18em] sm:tracking-[0.2em] text-neutral-400">
                    <span class="h-2 w-2 rounded-full bg-red-500"></span>
                    <span>{{ __('Out of stock') }}</span>
                </div>
                <div class="mt-2 sm:mt-3 flex items-baseline gap-2">
                    <span class="text-2xl sm:text-3xl font-semibold text-neutral-900 dark:text-white">{{ $outCount }}</span>
                    <span class="text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('items') }}</span>
                </div>
                <p class="mt-1 text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('Hidden from selection') }}</p>
            </div>
            <div class="rounded-xl sm:rounded-2xl border border-neutral-200/70 bg-white/85 p-3 sm:p-4 shadow-sm sm:shadow-lg shadow-neutral-200/40 dark:border-neutral-700/60 dark:bg-neutral-900/70 dark:shadow-black/30">
                <p class="text-[11px] sm:text-xs font-medium uppercase tracking-[0.18em] sm:tracking-[0.2em] text-neutral-400">{{ __('Linked to Menus') }}</p>
                <div class="mt-2 sm:mt-3 flex items-baseline gap-2">
                    <span class="text-2xl sm:text-3xl font-semibold text-neutral-900 dark:text-white">{{ $linkedCount }}</span>
                    <span class="text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('items') }}</span>
                </div>
                <p class="mt-1 text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('Used by at least one menu') }}</p>
            </div>
        </div>

        <!-- filters -->
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex-1 min-w-0">
                <flux:input
                    wire:model.live.debounce.1000ms="search"
                    :placeholder="__('Search add-on name')"
                />
            </div>
            <div class="grid grid-cols-[1fr_auto] items-center gap-2 sm:flex sm:items-center sm:justify-end sm:gap-2">
                <flux:select
                    wire:model.live="statusFilter"
                    class="min-w-0 w-full sm:w-44 rounded-full border-neutral-200 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900 focus:outline-hidden focus:ring-2 focus:ring-[color:var(--brand-accent)] focus:ring-offset-2 focus:ring-offset-[color:var(--brand-accent-foreground)]"
                >
                    <option value="all">{{ __('All Status') }}</option>
                    <option value="tersedia">{{ __('Available') }}</option>
                    <option value="habis">{{ __('Out of stock') }}</option>
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
            <div class="mt-2 grid gap-3">
                @forelse ($items as $addon)
                    <div class="rounded-2xl border border-neutral-200/80 bg-white p-4 shadow-sm dark:border-neutral-800/70 dark:bg-neutral-900">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="font-semibold text-neutral-900 dark:text-white">{{ $addon->nama_addon }}</div>
                                <div class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                                    Rp {{ number_format((float) $addon->harga, 0, ',', '.') }}
                                </div>
                            </div>
                            <div>
                                @php($isAvailable = $addon->status === 'tersedia')
                                <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold {{ $isAvailable ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100 dark:bg-emerald-900/40 dark:text-emerald-200 dark:ring-emerald-800/60' : 'bg-red-50 text-red-700 ring-1 ring-red-100 dark:bg-red-900/40 dark:text-red-200 dark:ring-red-800/60' }}">
                                    <span class="h-2 w-2 rounded-full {{ $isAvailable ? 'bg-emerald-500' : 'bg-red-500' }}"></span>
                                    {{ $isAvailable ? __('Available') : __('Out') }}
                                </span>
                            </div>
                        </div>

                        @can('addon.manage')
                            <div class="mt-4 flex items-center gap-2">
                                <flux:button
                                    size="sm"
                                    icon="{{ $isAvailable ? 'pause-circle' : 'check-circle' }}"
                                    variant="ghost"
                                    class="flex-1 btn-ghost-accent"
                                    wire:click="toggleStatus({{ $addon->id }})"
                                >
                                    {{ $isAvailable ? __('Out of stock') : __('Available') }}
                                </flux:button>
                                <flux:link class="flex-1" :href="route('addon.edit', $addon, false)" wire:navigate>
                                    <flux:button
                                        size="sm"
                                        icon="pencil-square"
                                        variant="primary"
                                        class="w-full btn-accent rounded-2xl shadow-sm transition"
                                    >
                                        {{ __('Edit') }}
                                    </flux:button>
                                </flux:link>
                            </div>
                            <div class="mt-2">
                                <flux:modal.trigger name="confirm-delete-addon" class="w-full">
                                    <flux:button
                                        size="sm"
                                        icon="trash"
                                        variant="danger"
                                        class="w-full rounded-2xl shadow-sm transition"
                                        wire:click="confirmDelete({{ $addon->id }})"
                                    >
                                        {{ __('Delete') }}
                                    </flux:button>
                                </flux:modal.trigger>
                            </div>
                        @endcan
                    </div>
                @empty
                    <div class="rounded-2xl border border-neutral-200/80 bg-white p-6 text-center text-sm text-neutral-500 dark:border-neutral-800/70 dark:bg-neutral-900 dark:text-neutral-400">
                        {{ __('No add-ons found.') }}
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
                @forelse ($items as $addon)
                    @php($isAvailable = $addon->status === 'tersedia')
                    <div class="flex h-full flex-col rounded-2xl border border-neutral-200/80 bg-white p-4 shadow-sm dark:border-neutral-800/70 dark:bg-neutral-900">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="truncate text-base font-semibold text-neutral-900 dark:text-white">{{ $addon->nama_addon }}</div>
                                <div class="mt-1 text-right text-sm font-semibold text-neutral-900 dark:text-white">
                                    Rp {{ number_format((float) $addon->harga, 0, ',', '.') }}
                                </div>
                            </div>
                            <span class="shrink-0 inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold {{ $isAvailable ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100 dark:bg-emerald-900/40 dark:text-emerald-200 dark:ring-emerald-800/60' : 'bg-red-50 text-red-700 ring-1 ring-red-100 dark:bg-red-900/40 dark:text-red-200 dark:ring-red-800/60' }}">
                                <span class="h-2 w-2 rounded-full {{ $isAvailable ? 'bg-emerald-500' : 'bg-red-500' }}"></span>
                                {{ $isAvailable ? __('Available') : __('Out') }}
                            </span>
                        </div>

                        @can('addon.manage')
                            <div class="mt-4 grid grid-cols-2 gap-2">
                                <flux:button
                                    size="sm"
                                    icon="{{ $isAvailable ? 'pause-circle' : 'check-circle' }}"
                                    variant="ghost"
                                    class="w-full btn-ghost-accent"
                                    wire:click="toggleStatus({{ $addon->id }})"
                                >
                                    {{ $isAvailable ? __('Out of stock') : __('Available') }}
                                </flux:button>
                                <flux:link :href="route('addon.edit', $addon, false)" wire:navigate>
                                    <flux:button
                                        size="sm"
                                        icon="pencil-square"
                                        variant="primary"
                                        class="w-full btn-accent rounded-2xl shadow-sm transition"
                                    >
                                        {{ __('Edit') }}
                                    </flux:button>
                                </flux:link>
                            </div>
                            <div class="mt-2">
                                <flux:modal.trigger name="confirm-delete-addon-desktop">
                                    <flux:button
                                        size="sm"
                                        icon="trash"
                                        variant="danger"
                                        class="w-full rounded-2xl shadow-sm transition"
                                        wire:click="confirmDelete({{ $addon->id }})"
                                    >
                                        {{ __('Delete') }}
                                    </flux:button>
                                </flux:modal.trigger>
                            </div>
                        @endcan
                    </div>
                @empty
                    <div class="sm:col-span-2 rounded-2xl border border-neutral-200/80 bg-white p-6 text-center text-sm text-neutral-500 dark:border-neutral-800/70 dark:bg-neutral-900 dark:text-neutral-400">
                        {{ __('No add-ons found.') }}
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
                                <th class="hidden md:table-cell px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">{{ __('ID') }}</th>
                                <th class="px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">{{ __('Name') }}</th>
                                <th class="px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">{{ __('Price') }}</th>
                                <th class="px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">{{ __('Status') }}</th>
                                <th class="px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100/80 text-neutral-700 dark:divide-neutral-900/40 dark:text-neutral-200">
                            @forelse ($items as $addon)
                                @php($isAvailable = $addon->status === 'tersedia')
                                <tr class="group transition hover:bg-white/70 focus-within:bg-white/90 dark:hover:bg-neutral-900/40 dark:focus-within:bg-neutral-900/50">
                                    <td class="hidden md:table-cell px-6 py-4 align-middle">
                                        <span class="inline-flex items-center rounded-full bg-neutral-900/5 px-3 py-1 text-xs font-semibold text-neutral-500 dark:bg-white/5 dark:text-neutral-300">
                                            #{{ str_pad($addon->id, 3, '0', STR_PAD_LEFT) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 align-middle">
                                        <div class="flex flex-col">
                                            <span class="text-sm font-semibold text-neutral-900 dark:text-white">{{ $addon->nama_addon }}</span>
                                            <span class="hidden sm:block text-[11px] text-neutral-500 dark:text-neutral-400">
                                                {{ $isAvailable ? __('Available for selection') : __('Hidden from selection') }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 align-middle text-neutral-800 dark:text-neutral-100">
                                        Rp {{ number_format((float) $addon->harga, 0, ',', '.') }}
                                    </td>
                                    <td class="px-6 py-4 align-middle">
                                        <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold {{ $isAvailable ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100 dark:bg-emerald-900/40 dark:text-emerald-200 dark:ring-emerald-800/60' : 'bg-red-50 text-red-700 ring-1 ring-red-100 dark:bg-red-900/40 dark:text-red-200 dark:ring-red-800/60' }}">
                                            <span class="h-2 w-2 rounded-full {{ $isAvailable ? 'bg-emerald-500' : 'bg-red-500' }}"></span>
                                            {{ $isAvailable ? __('Available') : __('Out of stock') }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 align-middle">
                                        <div class="flex flex-wrap items-center gap-2">
                                            @can('addon.manage')
                                                <flux:button
                                                    size="sm"
                                                    icon="{{ $isAvailable ? 'pause-circle' : 'check-circle' }}"
                                                    variant="ghost"
                                                    class="btn-ghost-accent rounded-2xl shadow-sm transition whitespace-nowrap justify-center md:w-24 lg:w-auto"
                                                    wire:click="toggleStatus({{ $addon->id }})"
                                                    title="{{ $isAvailable ? __('Out of stock') : __('Available') }}"
                                                >
                                                    {{ $isAvailable ? __('Out of stock') : __('Available') }}
                                                </flux:button>
                                                <flux:link :href="route('addon.edit', $addon, false)" wire:navigate>
                                                    <flux:button
                                                        size="sm"
                                                        icon="pencil-square"
                                                        variant="primary"
                                                        class="btn-accent rounded-2xl shadow-sm transition"
                                                    >
                                                        {{ __('Edit') }}
                                                    </flux:button>
                                                </flux:link>
                                                <flux:modal.trigger name="confirm-delete-addon-desktop">
                                                    <flux:button
                                                        size="sm"
                                                        icon="trash"
                                                        variant="danger"
                                                        class="rounded-2xl shadow-sm transition"
                                                        wire:click="confirmDelete({{ $addon->id }})"
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
                                    <td colspan="5" class="px-6 py-8 text-center text-sm text-neutral-500 dark:text-neutral-400">
                                        {{ __('No add-ons found.') }}
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

        @php($selectedAddon = $items->firstWhere('id', $confirmingDeleteId))

        <!-- Delete confirm modal - mobile flyout -->
        <flux:modal name="confirm-delete-addon" focusable variant="flyout" position="bottom" :closable="false" class="rounded-t-3xl sm:rounded-xl">
            <div class="space-y-4 p-2 max-h-[60dvh] overflow-y-auto">
                <div class="flex items-center justify-center">
                    <div class="mx-auto mb-1 h-1.5 w-12 rounded-full bg-neutral-300 dark:bg-neutral-700"></div>
                </div>
                <div class="space-y-2">
                    <flux:heading size="lg">{{ __('Delete this add-on?') }}</flux:heading>
                    <flux:subheading>{{ __('This action cannot be undone. Add-ons that are already used in orders cannot be deleted.') }}</flux:subheading>
                </div>
                @if ($selectedAddon)
                    <div class="rounded-2xl border border-red-200/60 bg-red-50/60 p-4 text-sm text-red-800 dark:border-red-800/60 dark:bg-red-900/20 dark:text-red-200">
                        <p class="font-semibold">{{ $selectedAddon->nama_addon }}</p>
                        <p class="text-xs opacity-80">
                            Rp {{ number_format((float) $selectedAddon->harga, 0, ',', '.') }} ·
                            {{ $selectedAddon->status === 'tersedia' ? __('Available') : __('Out of stock') }}
                        </p>
                    </div>
                @endif
                <div class="sticky bottom-0 -mx-2 mt-2 flex items-center justify-end gap-2 border-t border-neutral-200 bg-white/85 px-2 py-2 pb-[max(env(safe-area-inset-bottom),0.75rem)] backdrop-blur dark:border-neutral-700 dark:bg-neutral-900/70">
                    <flux:modal.close>
                        <flux:button variant="filled" wire:click="$wire.set('confirmingDeleteId', null)">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button variant="danger" wire:click="delete">{{ __('Yes, delete') }}</flux:button>
                </div>
            </div>
        </flux:modal>

        <!-- Delete confirm modal - desktop centered -->
        <flux:modal name="confirm-delete-addon-desktop" focusable class="mx-4 max-w-full sm:mx-auto sm:max-w-lg">
            <div class="space-y-4 p-2">
                <div class="space-y-2">
                    <flux:heading size="lg">{{ __('Delete this add-on?') }}</flux:heading>
                    <flux:subheading>{{ __('This action cannot be undone. Add-ons that are already used in orders cannot be deleted.') }}</flux:subheading>
                </div>
                @if ($selectedAddon)
                    <div class="rounded-2xl border border-red-200/60 bg-red-50/60 p-4 text-sm text-red-800 dark:border-red-800/60 dark:bg-red-900/20 dark:text-red-200">
                        <p class="font-semibold">{{ $selectedAddon->nama_addon }}</p>
                        <p class="text-xs opacity-80">
                            Rp {{ number_format((float) $selectedAddon->harga, 0, ',', '.') }} ·
                            {{ $selectedAddon->status === 'tersedia' ? __('Available') : __('Out of stock') }}
                        </p>
                    </div>
                @endif
                <div class="mt-2 flex items-center justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="filled" wire:click="$wire.set('confirmingDeleteId', null)">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button variant="danger" wire:click="delete">{{ __('Yes, delete') }}</flux:button>
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
                    this.message = event.detail?.message || '{{ __('Saved.') }}';
                    this.show = true;
                    clearTimeout(this.timeout);
                    this.timeout = setTimeout(() => this.show = false, 3500);
                }
            }"
            x-on:addon-toast.window="handle($event)"
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

@if (session('addon_toast'))
    <script>
        window.addEventListener('load', () => {
            try {
                const message = @js(session('addon_toast'));
                window.dispatchEvent(new CustomEvent('addon-toast', { detail: { message } }));
            } catch (e) {}
        });
    </script>
@endif
