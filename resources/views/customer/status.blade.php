<x-layouts.customer :title="__('Order status')">
    @php
        $idr = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
        $status = (string) ($order?->status ?? '');
        $justSubmitted = (bool) ($justSubmitted ?? false);
        $stepIndex = match ($status) {
            'menunggu' => 0,
            'diproses' => 1,
            'siap' => 2,
            default => -1,
        };
        $isStepActive = fn (int $i) => $stepIndex >= $i;
        $statusLabel = match ($status) {
            'menunggu' => __('Waiting'),
            'diproses' => __('Preparing'),
            'siap' => __('Ready'),
            default => $status,
        };
    @endphp

    <div class="space-y-6" data-table-token="{{ $token }}">
        <header class="sticky top-0 z-30 -mx-4 space-y-4 border-b border-neutral-200/70 bg-white/80 px-4 pb-3 pt-4 shadow-sm backdrop-blur dark:border-neutral-800/70 dark:bg-neutral-950/60 sm:-mx-6 sm:px-6">
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="min-w-0">
                        <div class="text-lg font-semibold tracking-tight text-neutral-900 dark:text-white">
                            {{ __('Order status') }}
                        </div>
                        <div class="mt-1 flex items-center gap-2 text-xs font-medium tracking-wide text-neutral-600 dark:text-neutral-300">
                            <span class="h-1.5 w-1.5 rounded-full bg-[var(--brand-primary)]"></span>
                            <span>{{ __('Table') }} {{ $meja->nomor_meja }}</span>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <form method="POST" action="{{ route('locale.set') }}" class="contents">
                        @csrf
                        <input type="hidden" name="locale" value="{{ app()->getLocale() === 'id' ? 'en' : 'id' }}" />
                        <button
                            type="submit"
                            class="inline-flex h-10 min-w-10 items-center justify-center rounded-full border border-neutral-200/70 bg-white/60 px-3 text-xs font-extrabold tracking-wide text-neutral-700 shadow-sm backdrop-blur hover:bg-white/80 dark:border-neutral-800/70 dark:bg-neutral-900/40 dark:text-neutral-200 dark:hover:bg-neutral-900/60"
                            aria-label="{{ __('Switch language') }}"
                        >
                            {{ app()->getLocale() === 'id' ? 'ID' : 'EN' }}
                        </button>
                    </form>

                    <button
                        type="button"
                        x-data="{
                            dark: false,
                            sync() {
                                const mode = $flux.appearance || 'system'
                                this.dark = mode === 'dark' || (mode === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)
                            },
                        }"
                        x-init="sync(); window.matchMedia('(prefers-color-scheme: dark)').addEventListener?.('change', () => sync())"
                        x-effect="sync()"
                        x-on:click="$flux.appearance = dark ? 'light' : 'dark'"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-neutral-200/70 bg-white/60 text-neutral-700 shadow-sm backdrop-blur hover:bg-white/80 dark:border-neutral-800/70 dark:bg-neutral-900/40 dark:text-neutral-200 dark:hover:bg-neutral-900/60"
                        aria-label="{{ __('Toggle theme') }}"
                    >
                        <template x-if="!dark">
                            <flux:icon icon="moon" class="size-5" />
                        </template>
                        <template x-if="dark">
                            <flux:icon icon="sun" class="size-5" />
                        </template>
                    </button>

                    <button
                        type="button"
                        onclick="location.reload()"
                        class="inline-flex h-10 items-center justify-center rounded-full border border-neutral-200/70 bg-white/60 px-4 text-sm font-semibold text-neutral-700 shadow-sm backdrop-blur hover:bg-white/80 dark:border-neutral-800/70 dark:bg-neutral-900/40 dark:text-neutral-200 dark:hover:bg-neutral-900/60"
                    >
                        {{ __('Refresh') }}
                    </button>
                </div>
            </div>
        </header>

        <!-- toast -->
        <div
            x-data="{
                show: false,
                message: '',
                timeout: null,
                handle(event) {
                    this.message = event.detail?.message || '';
                    if (!this.message) return;
                    this.show = true;
                    clearTimeout(this.timeout);
                    this.timeout = setTimeout(() => this.show = false, 2600);
                }
            }"
            x-on:customer-toast.window="handle($event)"
            class="pointer-events-none fixed inset-x-0 top-[max(env(safe-area-inset-top),1rem)] z-50 flex justify-center px-4"
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

        <div id="statusEmpty" class="@if($order) hidden @endif customer-card-depth rounded-3xl border border-neutral-200/70 bg-white/70 p-6 text-center text-sm text-neutral-600 shadow-sm backdrop-blur dark:border-neutral-800/70 dark:bg-neutral-900/40 dark:text-neutral-300">
            {{ __('No active order for this table yet.') }}
        </div>

        <div id="statusCard" class="@if(!$order) hidden @endif customer-card-depth rounded-3xl border border-neutral-200/70 bg-white/70 p-5 shadow-sm backdrop-blur dark:border-neutral-800/70 dark:bg-neutral-900/40">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="min-w-0">
                    <div class="text-xs font-semibold tracking-wide text-neutral-500 dark:text-neutral-400">{{ __('ORDER') }}</div>
                    <div id="orderCodeText" class="mt-1 truncate text-sm font-semibold text-neutral-900 dark:text-white">{{ $order?->kode_pesanan }}</div>
                </div>
                <div class="shrink-0">
                    <span class="inline-flex items-center gap-2 rounded-full border border-neutral-200/70 bg-white/60 px-3 py-1.5 text-xs font-semibold text-neutral-700 shadow-sm backdrop-blur dark:border-neutral-800/70 dark:bg-neutral-950/20 dark:text-neutral-200">
                        <span class="h-2 w-2 rounded-full bg-[var(--brand-primary)]"></span>
                        <span id="orderStatusText">{{ $statusLabel }}</span>
                    </span>
                </div>
            </div>

            <div class="mt-4">
                <div class="relative flex items-center justify-between gap-3" data-stepper>
                    <div class="absolute left-4 right-4 top-[0.9rem] h-px bg-neutral-200/80 dark:bg-neutral-800/70"></div>

                    <div class="relative flex flex-1 flex-col items-center gap-2 text-center" data-step="menunggu">
                        <div class="flex h-7 w-7 items-center justify-center rounded-full border border-neutral-200/70 bg-white/80 shadow-sm backdrop-blur dark:border-neutral-800/70 dark:bg-neutral-950/40">
                            <div class="h-3 w-3 rounded-full @if($isStepActive(0)) bg-[var(--brand-primary)] @else bg-neutral-200/70 dark:bg-neutral-700/60 @endif" data-step-dot></div>
                        </div>
                        <div class="text-[11px] font-semibold tracking-wide @if($isStepActive(0)) text-neutral-900 dark:text-white @else text-neutral-500 dark:text-neutral-400 @endif" data-step-label>
                            {{ __('Waiting') }}
                        </div>
                    </div>

                    <div class="relative flex flex-1 flex-col items-center gap-2 text-center" data-step="diproses">
                        <div class="flex h-7 w-7 items-center justify-center rounded-full border border-neutral-200/70 bg-white/80 shadow-sm backdrop-blur dark:border-neutral-800/70 dark:bg-neutral-950/40">
                            <div class="h-3 w-3 rounded-full @if($isStepActive(1)) bg-[var(--brand-primary)] @else bg-neutral-200/70 dark:bg-neutral-700/60 @endif" data-step-dot></div>
                        </div>
                        <div class="text-[11px] font-semibold tracking-wide @if($isStepActive(1)) text-neutral-900 dark:text-white @else text-neutral-500 dark:text-neutral-400 @endif" data-step-label>
                            {{ __('Preparing') }}
                        </div>
                    </div>

                    <div class="relative flex flex-1 flex-col items-center gap-2 text-center" data-step="siap">
                        <div class="flex h-7 w-7 items-center justify-center rounded-full border border-neutral-200/70 bg-white/80 shadow-sm backdrop-blur dark:border-neutral-800/70 dark:bg-neutral-950/40">
                            <div class="h-3 w-3 rounded-full @if($isStepActive(2)) bg-[var(--brand-primary)] @else bg-neutral-200/70 dark:bg-neutral-700/60 @endif" data-step-dot></div>
                        </div>
                        <div class="text-[11px] font-semibold tracking-wide @if($isStepActive(2)) text-neutral-900 dark:text-white @else text-neutral-500 dark:text-neutral-400 @endif" data-step-label>
                            {{ __('Ready') }}
                        </div>
                    </div>
                </div>
            </div>

            <div id="processingNote" class="@if($status !== 'diproses') hidden @endif mt-3 rounded-2xl border border-neutral-200/70 bg-white/60 p-3 text-sm text-neutral-700 shadow-sm backdrop-blur dark:border-neutral-800/70 dark:bg-neutral-950/20 dark:text-neutral-200">
                {{ __('Your order is being prepared. Dishes will be served gradually as they become ready.') }}
            </div>

            <div id="readyNote" class="@if($status !== 'siap') hidden @endif mt-3 rounded-2xl border border-[color-mix(in_srgb,var(--brand-primary)_35%,transparent)] bg-[color-mix(in_srgb,var(--brand-primary)_12%,transparent)] p-3 text-sm font-semibold text-neutral-900 shadow-sm backdrop-blur dark:text-white">
                {{ __('Please go to the cashier to complete payment.') }}
            </div>
        </div>

        <div id="itemsSection" class="@if(!$order) hidden @endif space-y-3">
            <div class="text-sm font-semibold text-neutral-700 dark:text-neutral-200">{{ __('Order Items') }}</div>
            <div id="itemsList" class="space-y-3">
                @foreach (($order?->details ?? collect()) as $d)
                    <div class="customer-card-depth rounded-3xl border border-neutral-200/70 bg-white/70 p-4 shadow-sm backdrop-blur dark:border-neutral-800/70 dark:bg-neutral-900/40">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="truncate text-sm font-semibold text-neutral-900 dark:text-white">
                                    {{ $d->menu?->nama_menu ?? __('Menu') }}
                                </div>
                                @if ($d->addons->isNotEmpty())
                                    <div class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                                        @foreach ($d->addons as $a)
                                            <div>+ {{ $a->nama_addon }}</div>
                                        @endforeach
                                    </div>
                                @endif
                                <div class="mt-2 text-xs text-neutral-500 dark:text-neutral-400">{{ __('Qty') }}: {{ (int) $d->qty }}</div>
                            </div>
                            <div class="shrink-0 text-sm font-semibold text-neutral-900 dark:text-white">
                                {{ $idr($d->subtotal) }}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div id="pollHint" class="text-center text-xs font-medium text-neutral-500 dark:text-neutral-400"></div>
    </div>

    <script>
        ;(() => {
            const token = document.querySelector('[data-table-token]')?.getAttribute('data-table-token') || ''
            if (!token) return

            // Ensure only one poll loop exists (handles BFCache / navigate swaps)
            try {
                window.__customerStatusPoll?.stop?.()
            } catch (e) {}

            const statusEmpty = document.getElementById('statusEmpty')
            const statusCard = document.getElementById('statusCard')
            const itemsSection = document.getElementById('itemsSection')
            const itemsList = document.getElementById('itemsList')

            const orderCodeText = document.getElementById('orderCodeText')
            const orderStatusText = document.getElementById('orderStatusText')
            const processingNote = document.getElementById('processingNote')
            const readyNote = document.getElementById('readyNote')
            const pollHint = document.getElementById('pollHint')

            const toast = (message) => {
                try {
                    window.dispatchEvent(new CustomEvent('customer-toast', { detail: { message } }))
                } catch (e) {}
            }

            const submitted = @json($justSubmitted);
            if (submitted) {
                setTimeout(() => toast(@json(__('Order sent to kitchen.'))), 80)
            }

            const labelQty = @json(__('Qty'));
            const labelMenu = @json(__('Menu'));
            const labelWaiting = @json(__('Waiting'));
            const labelPreparing = @json(__('Preparing'));
            const labelReady = @json(__('Ready'));

            const formatIDR = (value) => {
                try {
                    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value)
                } catch (e) {
                    return `Rp ${Math.round(Number(value || 0)).toString().replace(/\\B(?=(\\d{3})+(?!\\d))/g, '.')}`
                }
            }

            let timer = null
            let stopped = false
            const stopPolling = () => {
                stopped = true
                if (timer) clearTimeout(timer)
                timer = null
            }

            window.__customerStatusPoll = {
                stop: stopPolling,
            }

            const statusLabel = (status) => {
                const s = String(status || '')
                if (s === 'menunggu') return labelWaiting
                if (s === 'diproses') return labelPreparing
                if (s === 'siap') return labelReady
                return s
            }

            const stepIndex = (status) => {
                const s = String(status || '')
                if (s === 'menunggu') return 0
                if (s === 'diproses') return 1
                if (s === 'siap') return 2
                return -1
            }

            const updateStepper = (status) => {
                const idx = stepIndex(status)
                const steps = document.querySelectorAll('[data-stepper] [data-step]')
                steps.forEach((el) => {
                    const step = el.getAttribute('data-step') || ''
                    const myIdx = stepIndex(step)
                    const active = idx >= 0 && myIdx >= 0 && idx >= myIdx

                    const dot = el.querySelector('[data-step-dot]')
                    const label = el.querySelector('[data-step-label]')

                    if (dot) {
                        dot.classList.toggle('bg-[var(--brand-primary)]', active)
                        dot.classList.toggle('bg-neutral-200/70', !active)
                        dot.classList.toggle('dark:bg-neutral-700/60', !active)
                    }

                    if (label) {
                        label.classList.toggle('text-neutral-900', active)
                        label.classList.toggle('dark:text-white', active)
                        label.classList.toggle('text-neutral-500', !active)
                        label.classList.toggle('dark:text-neutral-400', !active)
                    }
                })
            }

            const render = (payload) => {
                const isPaid = !!payload?.paid
                if (isPaid) {
                    stopPolling()
                    toast(@json(__('Payment completed. Thank you!')))

                    // Clear customer state after payment completed
                    const cartKey = token ? `customerCart:${token}` : 'customerCart'
                    const voucherKey = token ? `customerVoucher:${token}` : 'customerVoucher'
                    const nameKey = token ? `customerName:${token}` : 'customerName'
                    const remove = (k) => {
                        try { sessionStorage.removeItem(k) } catch (e) {}
                        try { localStorage.removeItem(k) } catch (e) {}
                    }
                    remove(cartKey)
                    remove(voucherKey)
                    // Keep name (optional) - uncomment if you want to reset name too
                    // remove(nameKey)

                    statusEmpty?.classList.add('hidden')
                    statusCard?.classList.add('hidden')
                    itemsSection?.classList.add('hidden')

                    const redirectUrl = payload?.redirect || @json(route('customer.order', ['token' => $token]));
                    setTimeout(() => {
                        window.location.href = redirectUrl;
                    }, 1200)

                    return
                }

                const order = payload?.order || null
                const hasOrder = !!order

                statusEmpty?.classList.toggle('hidden', hasOrder)
                statusCard?.classList.toggle('hidden', !hasOrder)
                itemsSection?.classList.toggle('hidden', !hasOrder)

                if (!hasOrder) {
                    if (pollHint) pollHint.textContent = ''
                    return
                }

                if (orderCodeText) orderCodeText.textContent = order.kode_pesanan || ''
                if (orderStatusText) orderStatusText.textContent = statusLabel(order.status)
                updateStepper(order.status)
                if (processingNote) processingNote.classList.toggle('hidden', order.status !== 'diproses')
                if (readyNote) readyNote.classList.toggle('hidden', order.status !== 'siap')

                if (itemsList) {
                    const rows = Array.isArray(order.items) ? order.items : []
                    itemsList.innerHTML = rows.map((it) => {
                        const addons = Array.isArray(it.addons) ? it.addons : []
                        const addonHtml = addons.length
                            ? `<div class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">${addons.map(a => `<div>+ ${a}</div>`).join('')}</div>`
                            : ''

                        return `
                            <div class="customer-card-depth rounded-3xl border border-neutral-200/70 bg-white/70 p-4 shadow-sm backdrop-blur dark:border-neutral-800/70 dark:bg-neutral-900/40">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="truncate text-sm font-semibold text-neutral-900 dark:text-white">${it.menu || labelMenu}</div>
                                        ${addonHtml}
                                        <div class="mt-2 text-xs text-neutral-500 dark:text-neutral-400">${labelQty}: ${Number(it.qty || 0)}</div>
                                    </div>
                                    <div class="shrink-0 text-sm font-semibold text-neutral-900 dark:text-white">
                                        ${formatIDR(Number(it.subtotal || 0))}
                                    </div>
                                </div>
                            </div>
                        `
                    }).join('')
                }
            }

            let inflight = false
            const POLL_MS = 5000
            const schedule = () => {
                if (stopped) return
                if (timer) clearTimeout(timer)
                timer = setTimeout(tick, POLL_MS)
            }

            const tick = () => {
                if (stopped) return
                if (document.hidden) {
                    schedule()
                    return
                }
                if (inflight) {
                    schedule()
                    return
                }
                inflight = true
                fetch(@json(route('customer.status.json', ['token' => $token])), { cache: 'no-store' })
                    .then(r => r.json())
                    .then((json) => {
                        render(json)
                        if (pollHint) pollHint.textContent = ''
                    })
                    .catch(() => {
                        if (pollHint) pollHint.textContent = @json(__('Network issue. Pull to refresh or try again.'));
                    })
                    .finally(() => {
                        inflight = false
                        schedule()
                    })
            }

            // Initial + poll every 5s (pauses when tab is hidden)
            tick()
            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) tick()
            })
            window.addEventListener('beforeunload', () => stopPolling())
        })()
    </script>
</x-layouts.customer>
