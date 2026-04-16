<x-layouts.customer :title="__('Booking')">
    <div class="space-y-6">
        <header class="space-y-2">
            <div class="text-2xl font-semibold tracking-tight text-neutral-900 dark:text-white">
                {{ __('Booking') }}
            </div>
            <div class="text-sm text-neutral-600 dark:text-neutral-300">
                {{ __('Choose a table, place your order, and we will queue it until the table is available.') }}
            </div>
        </header>

        <div class="grid gap-3">
            @forelse ($mejas as $m)
                @php
                    $capacity = (int) ($m->kapasitas ?? 4);
                    $occupied = (int) ($occupiedByTable[$m->id] ?? 0);
                    $queue = (int) ($bookingCounts[$m->id] ?? 0);
                    $remaining = max($capacity - $occupied, 0);
                @endphp
                <a
                    href="{{ route('booking.order', ['meja' => $m->id]) }}"
                    class="customer-card-depth rounded-3xl border border-neutral-200/70 bg-white/75 p-4 shadow-sm backdrop-blur transition hover:bg-white/90 dark:border-neutral-800/70 dark:bg-neutral-900/50 dark:hover:bg-neutral-900/70"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="text-lg font-semibold text-neutral-900 dark:text-white">{{ __('Table') }} {{ $m->nomor_meja }}</div>
                            <div class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                                {{ __('Capacity') }} {{ $capacity }} · {{ __('Available') }} {{ $remaining }}
                            </div>
                        </div>
                        <span class="inline-flex items-center rounded-full border border-neutral-200/70 bg-white/70 px-3 py-1 text-xs font-semibold text-neutral-700 dark:border-neutral-800/70 dark:bg-neutral-950/30 dark:text-neutral-200">
                            {{ $queue }} {{ __('queued') }}
                        </span>
                    </div>
                </a>
            @empty
                <div class="customer-card-depth rounded-3xl border border-neutral-200/70 bg-white/75 p-6 text-center text-sm text-neutral-600 shadow-sm backdrop-blur dark:border-neutral-800/70 dark:bg-neutral-900/50 dark:text-neutral-300">
                    {{ __('No tables available.') }}
                </div>
            @endforelse
        </div>
    </div>
</x-layouts.customer>
