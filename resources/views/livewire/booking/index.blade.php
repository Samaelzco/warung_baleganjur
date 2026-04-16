<?php

use App\Models\Pesanan;
use App\Services\TableBookingService;
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

    public function activate(int $id): void
    {
        $this->authorizeManage();

        $booking = Pesanan::query()
            ->with('meja:id,nomor_meja,kapasitas,status')
            ->whereKey($id)
            ->where('status', 'booking')
            ->firstOrFail();

        $service = app(TableBookingService::class);
        if (!$booking->meja || !$service->hasCapacity($booking->meja, max((int) $booking->jumlah_orang, 1))) {
            $this->dispatch('booking-toast', message: __('Table capacity is not available yet.'));
            return;
        }

        $booking->forceFill(['status' => 'menunggu'])->save();
        $service->syncMejaStatus($booking->meja);

        $this->dispatch('booking-toast', message: __('Booking activated.'));
    }

    public function cancel(int $id): void
    {
        $this->authorizeManage();

        $booking = Pesanan::query()
            ->whereKey($id)
            ->where('status', 'booking')
            ->firstOrFail();

        $booking->forceFill(['status' => 'batal'])->save();

        $this->dispatch('booking-toast', message: __('Booking cancelled.'));
    }

    public function activateNext(int $mejaId): void
    {
        $this->authorizeManage();

        $count = app(TableBookingService::class)->activateNextBookings($mejaId);
        $this->dispatch('booking-toast', message: $count > 0 ? __('Next booking activated.') : __('No booking could be activated.'));
    }

    protected function authorizeManage(): void
    {
        abort_unless(auth()->check() && auth()->user()->can('pesanan.manage'), 403);
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

        $items = $query->orderBy('waktu_pesan')->orderBy('id')->paginate(10);
        $mejas = \App\Models\Meja::query()->select(['id', 'nomor_meja', 'kapasitas'])->orderBy('nomor_meja')->get();
        $service = app(TableBookingService::class);

        $stats = Cache::remember('booking:list:stats:v1', 10, fn () => [
            'waiting' => Pesanan::query()->where('status', 'booking')->count(),
        ]);
    @endphp

    <div class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <flux:heading size="xl" level="1">{{ __('Booking List') }}</flux:heading>
                <flux:subheading>{{ __('Queued orders waiting for table capacity.') }}</flux:subheading>
            </div>
            <div class="flex flex-wrap gap-2">
                <flux:link :href="route('booking.index')" target="_blank">
                    <flux:button icon="arrow-top-right-on-square" variant="ghost" class="btn-ghost-accent">{{ __('Open Booking Page') }}</flux:button>
                </flux:link>
                <flux:link :href="route('booking.qr')" target="_blank">
                    <flux:button icon="qr-code" variant="primary" class="btn-brand">{{ __('Booking QR') }}</flux:button>
                </flux:link>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-2xl border border-neutral-200/70 bg-white/85 p-4 shadow-sm dark:border-neutral-700/60 dark:bg-neutral-900/70">
                <p class="text-xs font-medium uppercase tracking-[0.2em] text-neutral-400">{{ __('Queued') }}</p>
                <div class="mt-2 text-3xl font-semibold text-neutral-900 dark:text-white">{{ (int) ($stats['waiting'] ?? 0) }}</div>
            </div>
        </div>

        <div class="flex flex-col gap-2 sm:flex-row">
            <flux:input wire:model.live.debounce.800ms="search" :placeholder="__('Search booking, customer, or table')" class="flex-1" />
            <flux:select wire:model.live="tableFilter" class="sm:w-56">
                <option value="all">{{ __('All Tables') }}</option>
                @foreach ($mejas as $m)
                    <option value="{{ $m->id }}">{{ __('Table') }} {{ $m->nomor_meja }}</option>
                @endforeach
            </flux:select>
        </div>

        <div class="overflow-hidden rounded-3xl border border-neutral-200/80 bg-white shadow-sm dark:border-neutral-800/70 dark:bg-neutral-900">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-neutral-50 dark:bg-neutral-950/40">
                        <tr>
                            <th class="px-4 py-3 text-left">{{ __('Queue') }}</th>
                            <th class="px-4 py-3 text-left">{{ __('Customer') }}</th>
                            <th class="px-4 py-3 text-left">{{ __('Table') }}</th>
                            <th class="px-4 py-3 text-left">{{ __('Items') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Total') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                        @forelse ($items as $item)
                            @php
                                $remaining = $item->meja ? $service->remainingSeats($item->meja) : 0;
                                $canActivate = $item->meja && $remaining >= max((int) $item->jumlah_orang, 1);
                            @endphp
                            <tr>
                                <td class="px-4 py-3 align-top">
                                    <div class="font-mono font-semibold text-neutral-900 dark:text-white">{{ $item->kode_pesanan }}</div>
                                    <div class="text-xs text-neutral-500">{{ $item->waktu_pesan?->format('d M Y H:i') }}</div>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <div class="font-semibold text-neutral-900 dark:text-white">{{ $item->customer_name }}</div>
                                    <div class="text-xs text-neutral-500">{{ __('Guests') }}: {{ (int) $item->jumlah_orang }}</div>
                                    @if ($item->customer_note)
                                        <div class="mt-1 max-w-xs text-xs text-neutral-500">{{ $item->customer_note }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <div class="font-semibold">{{ __('Table') }} {{ $item->meja?->nomor_meja ?? '-' }}</div>
                                    <div class="text-xs text-neutral-500">{{ __('Remaining') }} {{ $remaining }} / {{ (int) ($item->meja?->kapasitas ?? 0) }}</div>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <div class="space-y-1">
                                        @foreach ($item->details as $detail)
                                            <div class="text-xs">
                                                {{ $detail->menu?->nama_menu ?? __('Menu') }} x{{ (int) $detail->qty }}
                                            </div>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right align-top font-semibold">
                                    Rp {{ number_format((float) $item->total_harga, 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <div class="flex justify-end gap-2">
                                        @can('pesanan.manage')
                                            <flux:button size="sm" variant="primary" class="btn-brand" wire:click="activate({{ $item->id }})" :disabled="!$canActivate">{{ __('Activate') }}</flux:button>
                                            <flux:button size="sm" variant="danger" wire:click="cancel({{ $item->id }})">{{ __('Cancel') }}</flux:button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-sm text-neutral-500">{{ __('No booking queue.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3">{{ $items->links() }}</div>
        </div>

        <div
            x-data="{ show:false, message:'', timeout:null, handle(e){ this.message=e.detail?.message || ''; this.show=!!this.message; clearTimeout(this.timeout); this.timeout=setTimeout(()=>this.show=false,3500); } }"
            x-on:booking-toast.window="handle($event)"
            class="pointer-events-none fixed inset-x-0 top-6 flex justify-center px-4"
        >
            <div x-show="show" class="pointer-events-auto rounded-2xl toast-brand px-4 py-3 text-sm">
                <span x-text="message"></span>
            </div>
        </div>
    </div>
</section>
