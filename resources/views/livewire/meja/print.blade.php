<?php

use App\Models\Meja;
use Livewire\Volt\Component;

new class extends Component {
    public array $ids = [];

    public function mount(): void
    {
        $q = request()->query('ids');
        if ($q) {
            $this->ids = collect(explode(',', (string) $q))
                ->filter()
                ->map(fn ($v) => (int) $v)
                ->values()
                ->all();
        }
    }
}; ?>

<section class="w-full">
    <div class="flex items-center justify-between print:hidden">
        <flux:heading size="xl" level="1">{{ __('Print QR') }}</flux:heading>
        <div class="flex items-center gap-2">
            <flux:link :href="route('meja.index', [], false)" wire:navigate>
                <flux:button icon="arrow-left" variant="ghost">{{ __('Back') }}</flux:button>
            </flux:link>
            <flux:button icon="printer" class="btn-brand" onclick="window.print()">{{ __('Print') }}</flux:button>
        </div>
    </div>

    <div class="qr-grid mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        @php
            $items = empty($ids)
                ? Meja::orderBy('nomor_meja')->get()
                : Meja::whereIn('id', $ids)->orderBy('nomor_meja')->get();
        @endphp

        @forelse ($items as $m)
            <div class="qr-card overflow-hidden rounded-xl border border-neutral-200 bg-white break-inside-avoid">
                <div class="h-1.5 w-full" style="background: var(--brand-primary);"></div>
                <div class="p-4">
                    <div class="mb-3 text-center">
                        <div class="qr-title text-lg font-semibold text-neutral-900">{{ __('Table') }} {{ $m->nomor_meja }}</div>
                    </div>
                    <div class="qr-inner rounded-md border border-neutral-200 bg-white p-3">
                        <img alt="QR" class="mx-auto h-48 w-48 object-contain" src="{{ route('meja.qr', ['token' => $m->qr_token, 'size' => 512, 'format' => 'svg'], false) }}" />
                    </div>
                </div>
            </div>
        @empty
            <div class="text-sm text-neutral-500">{{ __('No data') }}</div>
        @endforelse
    </div>

    <style>
        @media print {
            .print\:hidden { display: none !important; }
            body {
                background: white;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                font-family: var(--font-sans, system-ui, sans-serif);
            }
            /* 3 columns on print, with room for margins */
            .qr-grid { grid-template-columns: repeat(3, 1fr) !important; gap: 14mm !important; }
            @page { margin: 14mm; }

            /* Ensure consistent light styling even if dark mode class is present */
            .qr-title { color: var(--brand-accent) !important; }
            .qr-card, .qr-inner { background: #ffffff !important; border-color: #e5e5e5 !important; }
        }
        /* Slight brand tint for titles */
        .qr-title { color: var(--brand-accent); }
        .dark .qr-title { color: var(--brand-primary); }
    </style>
</section>
