<x-layouts.customer :title="__('Order status')">
    @php
        $idr = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
        $status = (string) ($order?->status ?? '');
        $justSubmitted = (bool) ($justSubmitted ?? false);
        $justCancelled = (bool) ($justCancelled ?? false);
        $stepIndex = match ($status) {
            'booking' => 0,
            'menunggu' => 1,
            'sedang_diubah' => 1,
            'diproses' => 2,
            'siap' => 3,
            default => -1,
        };
        $isStepActive = fn (int $i) => $stepIndex >= $i;
        $statusLabel = match ($status) {
            'booking' => __('Waiting List'),
            'menunggu' => __('Waiting'),
            'sedang_diubah' => __('Editing'),
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
                        <input type="hidden" name="redirect" value="{{ request()->fullUrl() }}" />
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

            <button
                id="orderTotalToggle"
                type="button"
                class="mt-4 flex w-full items-center justify-between gap-3 rounded-2xl border border-neutral-200/70 bg-white/60 px-4 py-3 text-left shadow-sm backdrop-blur hover:bg-white/75 dark:border-neutral-800/70 dark:bg-neutral-950/20 dark:hover:bg-neutral-950/30"
                aria-expanded="false"
                aria-controls="orderBreakdown"
            >
                <div class="min-w-0">
                    <div class="text-xs font-semibold tracking-wide text-neutral-500 dark:text-neutral-400">{{ __('Total') }}</div>
                    <div class="mt-0.5 text-[11px] font-medium text-neutral-500 dark:text-neutral-400">{{ __('Tap to view details') }}</div>
                </div>

                <div class="flex items-center gap-2">
                    <div id="orderTotalText" class="text-sm font-semibold text-neutral-900 dark:text-white">
                        {{ $idr($order?->total_harga ?? 0) }}
                    </div>
                    <span id="orderTotalChevron" class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-neutral-200/70 bg-white/60 text-neutral-700 shadow-sm backdrop-blur transition-transform duration-200 dark:border-neutral-800/70 dark:bg-neutral-950/20 dark:text-neutral-200">
                        <flux:icon icon="chevron-down" class="size-4" />
                    </span>
                </div>
            </button>

            <div
                id="orderBreakdown"
                class="hidden space-y-3 rounded-2xl border border-neutral-200/70 bg-white/60 p-4 shadow-sm backdrop-blur dark:border-neutral-800/70 dark:bg-neutral-950/20"
            >
                <div class="space-y-2">
                    <div class="text-xs font-semibold tracking-wide text-neutral-500 dark:text-neutral-400">{{ __('Order Items') }}</div>
                    <div id="breakdownItemsList" class="space-y-2"></div>
                </div>

                <div class="space-y-2 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div class="text-neutral-600 dark:text-neutral-300">{{ __('Subtotal') }}</div>
                        <div id="orderSubtotalText" class="font-semibold text-neutral-900 dark:text-white">{{ $idr($order?->subtotal ?? 0) }}</div>
                    </div>
                    <div id="orderDiscountRow" class="@if(((float)($order?->discount_total ?? 0)) <= 0) hidden @endif flex items-center justify-between gap-3">
                        <div class="text-neutral-600 dark:text-neutral-300">{{ __('Discount') }}</div>
                        <div id="orderDiscountText" class="font-semibold text-neutral-900 dark:text-white">- {{ $idr($order?->discount_total ?? 0) }}</div>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <div class="text-neutral-600 dark:text-neutral-300">{{ __('Tax') }}</div>
                        <div id="orderTaxText" class="font-semibold text-neutral-900 dark:text-white">{{ $idr($order?->tax_total ?? 0) }}</div>
                    </div>
                    <div class="h-px bg-neutral-200/70 dark:bg-neutral-800/70"></div>
                    <div class="flex items-center justify-between gap-3">
                        <div class="font-semibold text-neutral-900 dark:text-white">{{ __('Total') }}</div>
                        <div id="orderTotalText2" class="font-semibold text-neutral-900 dark:text-white">{{ $idr($order?->total_harga ?? 0) }}</div>
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <div class="relative flex items-center justify-between gap-3" data-stepper>
                    <div class="absolute left-4 right-4 top-[0.9rem] h-px bg-neutral-200/80 dark:bg-neutral-800/70"></div>

                    <div class="relative flex flex-1 flex-col items-center gap-2 text-center" data-step="booking">
                        <div class="flex h-7 w-7 items-center justify-center rounded-full border border-neutral-200/70 bg-white/80 shadow-sm backdrop-blur dark:border-neutral-800/70 dark:bg-neutral-950/40">
                            <div class="h-3 w-3 rounded-full @if($isStepActive(0)) bg-[var(--brand-primary)] @else bg-neutral-200/70 dark:bg-neutral-700/60 @endif" data-step-dot></div>
                        </div>
                        <div class="text-[11px] font-semibold tracking-wide @if($isStepActive(0)) text-neutral-900 dark:text-white @else text-neutral-500 dark:text-neutral-400 @endif" data-step-label>
                            {{ __('Waiting List') }}
                        </div>
                    </div>

                    <div class="relative flex flex-1 flex-col items-center gap-2 text-center" data-step="menunggu">
                        <div class="flex h-7 w-7 items-center justify-center rounded-full border border-neutral-200/70 bg-white/80 shadow-sm backdrop-blur dark:border-neutral-800/70 dark:bg-neutral-950/40">
                            <div class="h-3 w-3 rounded-full @if($isStepActive(1)) bg-[var(--brand-primary)] @else bg-neutral-200/70 dark:bg-neutral-700/60 @endif" data-step-dot></div>
                        </div>
                        <div class="text-[11px] font-semibold tracking-wide @if($isStepActive(1)) text-neutral-900 dark:text-white @else text-neutral-500 dark:text-neutral-400 @endif" data-step-label>
                            {{ $status === 'sedang_diubah' ? __('Editing') : __('Waiting') }}
                        </div>
                    </div>

                    <div class="relative flex flex-1 flex-col items-center gap-2 text-center" data-step="diproses">
                        <div class="flex h-7 w-7 items-center justify-center rounded-full border border-neutral-200/70 bg-white/80 shadow-sm backdrop-blur dark:border-neutral-800/70 dark:bg-neutral-950/40">
                            <div class="h-3 w-3 rounded-full @if($isStepActive(2)) bg-[var(--brand-primary)] @else bg-neutral-200/70 dark:bg-neutral-700/60 @endif" data-step-dot></div>
                        </div>
                        <div class="text-[11px] font-semibold tracking-wide @if($isStepActive(2)) text-neutral-900 dark:text-white @else text-neutral-500 dark:text-neutral-400 @endif" data-step-label>
                            {{ __('Preparing') }}
                        </div>
                    </div>

                    <div class="relative flex flex-1 flex-col items-center gap-2 text-center" data-step="siap">
                        <div class="flex h-7 w-7 items-center justify-center rounded-full border border-neutral-200/70 bg-white/80 shadow-sm backdrop-blur dark:border-neutral-800/70 dark:bg-neutral-950/40">
                            <div class="h-3 w-3 rounded-full @if($isStepActive(3)) bg-[var(--brand-primary)] @else bg-neutral-200/70 dark:bg-neutral-700/60 @endif" data-step-dot></div>
                        </div>
                        <div class="text-[11px] font-semibold tracking-wide @if($isStepActive(3)) text-neutral-900 dark:text-white @else text-neutral-500 dark:text-neutral-400 @endif" data-step-label>
                            {{ __('Ready') }}
                        </div>
                    </div>
                </div>
            </div>

            <div id="processingNote" class="@if($status !== 'diproses') hidden @endif mt-3 rounded-2xl border border-neutral-200/70 bg-white/60 p-3 text-sm text-neutral-700 shadow-sm backdrop-blur dark:border-neutral-800/70 dark:bg-neutral-950/20 dark:text-neutral-200">
                {{ __('Your order is being prepared. Dishes will be served gradually as they become ready.') }}
            </div>

            <div id="editingNote" class="@if($status !== 'sedang_diubah') hidden @endif mt-3 rounded-2xl border border-fuchsia-200/70 bg-fuchsia-50/80 p-3 text-sm font-semibold text-fuchsia-800 shadow-sm backdrop-blur dark:border-fuchsia-900/40 dark:bg-fuchsia-900/20 dark:text-fuchsia-200">
                {{ __('Your order is being edited. Kitchen preparation is paused until you submit the changes.') }}
            </div>

            <div id="waitingListNote" class="@if($status !== 'booking') hidden @endif mt-3 rounded-2xl border border-purple-200/70 bg-purple-50/80 p-3 text-sm font-semibold text-purple-800 shadow-sm backdrop-blur dark:border-purple-900/40 dark:bg-purple-900/20 dark:text-purple-200">
                {{ __('Your order is queued and will be sent to the kitchen when the table is available.') }}
            </div>

            <div id="readyNote" class="@if($status !== 'siap') hidden @endif mt-3 rounded-2xl border border-[color-mix(in_srgb,var(--brand-primary)_35%,transparent)] bg-[color-mix(in_srgb,var(--brand-primary)_12%,transparent)] p-3 text-sm font-semibold text-neutral-900 shadow-sm backdrop-blur dark:text-white">
                {{ __('Please go to the cashier to complete payment.') }}
            </div>
        </div>

        @if($editOrderUrl || $continueEditUrl || $cancelOrderUrl)
            <div id="customerOrderActions" class="{{ in_array($status, ['menunggu', 'sedang_diubah'], true) ? 'flex' : 'hidden' }} flex-col justify-center gap-2 sm:flex-row">
                @if($editOrderUrl)
                    <form method="POST" action="{{ $editOrderUrl }}" data-customer-action="edit" class="@if($status !== 'menunggu') hidden @endif">
                        @csrf
                        <button
                            type="submit"
                            class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-2xl bg-[var(--brand-primary)] px-5 text-sm font-semibold text-[var(--brand-accent)] shadow-sm ring-1 ring-black/5 hover:bg-[var(--brand-primary-hover)] active:bg-[var(--brand-primary-active)] sm:w-auto"
                        >
                            <flux:icon icon="pencil-square" class="size-4" />
                            {{ __('Edit order') }}
                        </button>
                    </form>
                @endif

                @if($continueEditUrl)
                    <a
                        href="{{ $continueEditUrl }}"
                        data-customer-action="continue"
                        class="{{ $status === 'sedang_diubah' ? 'inline-flex' : 'hidden' }} h-11 w-full items-center justify-center gap-2 rounded-2xl bg-[var(--brand-primary)] px-5 text-sm font-semibold text-[var(--brand-accent)] shadow-sm ring-1 ring-black/5 hover:bg-[var(--brand-primary-hover)] active:bg-[var(--brand-primary-active)] sm:w-auto"
                    >
                        <flux:icon icon="pencil-square" class="size-4" />
                        {{ __('Continue editing') }}
                    </a>
                @endif

                @if($cancelOrderUrl)
                    <form method="POST" action="{{ $cancelOrderUrl }}" data-customer-action="cancel" class="@if(!in_array($status, ['menunggu', 'sedang_diubah'], true)) hidden @endif">
                        @csrf
                        <button
                            type="submit"
                            data-confirm-cancel-trigger
                            class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-2xl border border-red-200/70 bg-red-50/80 px-5 text-sm font-semibold text-red-700 shadow-sm backdrop-blur hover:bg-red-100/80 dark:border-red-900/40 dark:bg-red-900/20 dark:text-red-200 dark:hover:bg-red-900/30 sm:w-auto"
                        >
                            <flux:icon icon="x-circle" class="size-4" />
                            {{ __('Cancel order') }}
                        </button>
                    </form>
                @endif
            </div>
        @endif

        <div id="cancelOrderModal" class="fixed inset-0 z-50 hidden opacity-0 transition-opacity duration-200 ease-out motion-reduce:transition-none" role="dialog" aria-modal="true" aria-labelledby="cancelOrderTitle">
            <button type="button" class="absolute inset-0 bg-black/40" data-confirm-cancel-close aria-label="{{ __('Close') }}"></button>

            <div data-cancel-panel class="relative flex min-h-svh translate-y-4 scale-95 items-center justify-center p-4 transition duration-200 ease-out motion-reduce:translate-y-0 motion-reduce:scale-100 motion-reduce:transition-none">
                <div class="customer-card-depth w-full max-w-md rounded-3xl border border-neutral-200/70 bg-white/90 p-5 shadow-2xl backdrop-blur dark:border-neutral-800/70 dark:bg-neutral-950/85">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div id="cancelOrderTitle" class="text-lg font-semibold text-neutral-900 dark:text-white">
                                {{ __('Cancel order?') }}
                            </div>
                            <div class="mt-1 text-sm text-neutral-600 dark:text-neutral-300">
                                {{ __('Your order will be cancelled and removed from the queue. You can place a new order anytime.') }}
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 flex items-center justify-end gap-2 pt-1">
                        <button type="button" data-confirm-cancel-close class="inline-flex h-11 items-center justify-center rounded-2xl border border-neutral-200/70 bg-white/60 px-4 text-sm font-semibold text-neutral-700 shadow-sm backdrop-blur hover:bg-white/80 dark:border-neutral-800/70 dark:bg-neutral-900/40 dark:text-neutral-200 dark:hover:bg-neutral-900/60">
                            {{ __('Keep order') }}
                        </button>
                        <button type="button" data-confirm-cancel-submit class="inline-flex h-11 items-center justify-center rounded-2xl border border-red-200/70 bg-red-50/80 px-4 text-sm font-semibold text-red-700 shadow-sm backdrop-blur hover:bg-red-100/80 dark:border-red-900/40 dark:bg-red-900/20 dark:text-red-200 dark:hover:bg-red-900/30">
                            {{ __('Cancel order') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        @if($order && in_array($status, ['menunggu', 'diproses'], true) && empty($statusJsonUrl))
            <div class="flex justify-center">
                <a
                    href="{{ route('customer.order', ['token' => $token, 'add' => 1]) }}"
                    class="inline-flex h-11 items-center justify-center gap-2 rounded-2xl bg-[var(--brand-primary)] px-5 text-sm font-semibold text-[var(--brand-accent)] shadow-sm ring-1 ring-black/5 hover:bg-[var(--brand-primary-hover)] active:bg-[var(--brand-primary-active)]"
                >
                    <flux:icon icon="plus" class="size-4" />
                    {{ __('Add more items') }}
                </a>
            </div>
        @endif

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

            const orderCodeText = document.getElementById('orderCodeText')
            const orderStatusText = document.getElementById('orderStatusText')
            const orderTotalText = document.getElementById('orderTotalText')
            const orderTotalText2 = document.getElementById('orderTotalText2')
            const orderSubtotalText = document.getElementById('orderSubtotalText')
            const orderDiscountRow = document.getElementById('orderDiscountRow')
            const orderDiscountText = document.getElementById('orderDiscountText')
            const orderTaxText = document.getElementById('orderTaxText')
            const breakdownItemsList = document.getElementById('breakdownItemsList')
            const processingNote = document.getElementById('processingNote')
            const editingNote = document.getElementById('editingNote')
            const readyNote = document.getElementById('readyNote')
            const customerOrderActions = document.getElementById('customerOrderActions')
            const customerEditAction = customerOrderActions?.querySelector('[data-customer-action="edit"]')
            const customerContinueAction = customerOrderActions?.querySelector('[data-customer-action="continue"]')
            const customerCancelAction = customerOrderActions?.querySelector('[data-customer-action="cancel"]')
            const cancelOrderModal = document.getElementById('cancelOrderModal')
            const cancelOrderPanel = cancelOrderModal?.querySelector('[data-cancel-panel]')
            const cancelOrderSubmit = cancelOrderModal?.querySelector('[data-confirm-cancel-submit]')
            let pendingCancelForm = null
            const modalTransitionMs = 220
            let cancelModalCloseTimer = null
            const pollHint = document.getElementById('pollHint')

            const breakdownEl = document.getElementById('orderBreakdown')
            const breakdownToggle = document.getElementById('orderTotalToggle')
            const breakdownChevron = document.getElementById('orderTotalChevron')
            const setBreakdownOpen = (open) => {
                if (!breakdownEl || !breakdownToggle) return
                breakdownEl.classList.toggle('hidden', !open)
                breakdownToggle.setAttribute('aria-expanded', open ? 'true' : 'false')
                breakdownChevron?.classList.toggle('rotate-180', open)
            }

            if (breakdownToggle) {
                breakdownToggle.addEventListener('click', () => {
                    const expanded = breakdownToggle.getAttribute('aria-expanded') === 'true'
                    setBreakdownOpen(!expanded)
                })
            }

            const toast = (message) => {
                try {
                    window.dispatchEvent(new CustomEvent('customer-toast', { detail: { message } }))
                } catch (e) {}
            }

            const openCancelModal = (form) => {
                pendingCancelForm = form
                if (!cancelOrderModal) {
                    form?.submit?.()
                    return
                }

                clearTimeout(cancelModalCloseTimer)
                cancelOrderModal.classList.remove('hidden')
                requestAnimationFrame(() => {
                    cancelOrderModal.classList.remove('opacity-0')
                    cancelOrderPanel?.classList.remove('translate-y-4', 'scale-95')
                    cancelOrderPanel?.classList.add('translate-y-0', 'scale-100')
                    cancelOrderSubmit?.focus?.()
                })
            }

            const closeCancelModal = () => {
                pendingCancelForm = null
                if (!cancelOrderModal) return

                cancelOrderModal.classList.add('opacity-0')
                cancelOrderPanel?.classList.add('translate-y-4', 'scale-95')
                cancelOrderPanel?.classList.remove('translate-y-0', 'scale-100')
                cancelModalCloseTimer = setTimeout(() => {
                    cancelOrderModal.classList.add('hidden')
                }, modalTransitionMs)
            }

            document.querySelectorAll('[data-confirm-cancel-trigger]').forEach((button) => {
                button.addEventListener('click', (event) => {
                    event.preventDefault()
                    openCancelModal(button.closest('form'))
                })
            })

            cancelOrderModal?.querySelectorAll('[data-confirm-cancel-close]').forEach((button) => {
                button.addEventListener('click', closeCancelModal)
            })

            cancelOrderModal?.addEventListener('click', (event) => {
                if (event.target === cancelOrderModal) closeCancelModal()
            })

            cancelOrderSubmit?.addEventListener('click', () => {
                const form = pendingCancelForm
                closeCancelModal()
                form?.submit?.()
            })

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && cancelOrderModal && !cancelOrderModal.classList.contains('hidden')) {
                    closeCancelModal()
                }
            })

            const submitted = @json($justSubmitted);
            if (submitted) {
                setTimeout(() => toast(@json(__('Order sent to kitchen.'))), 80)
            }

            const cancelled = @json($justCancelled);
            if (cancelled) {
                setTimeout(() => toast(@json(__('Order cancelled successfully.'))), 80)
            }

            try {
                const url = new URL(window.location.href)
                if (url.searchParams.get('noop') === '1') {
                    setTimeout(() => toast(@json(__('No new items were added.'))), 80)
                    url.searchParams.delete('noop')
                    window.history.replaceState({}, '', url.toString())
                }
            } catch (e) {}

            const labelOrderFailed = @json(__('Order failed. Please place your order again.'));
            const redirectOrderUrl = @json($orderUrl ?? route('customer.order', ['token' => $token]));

            const labelQty = @json(__('Qty'));
            const labelMenu = @json(__('Menu'));
            const labelWaitingList = @json(__('Waiting List'));
            const labelWaiting = @json(__('Waiting'));
            const labelEditing = @json(__('Editing'));
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
            let hadOrderEver = @json((bool) $order) || submitted
            let failureNotified = false
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
                if (s === 'booking') return labelWaitingList
                if (s === 'menunggu') return labelWaiting
                if (s === 'sedang_diubah') return labelEditing
                if (s === 'diproses') return labelPreparing
                if (s === 'siap') return labelReady
                return s
            }

            const stepIndex = (status) => {
                const s = String(status || '')
                if (s === 'booking') return 0
                if (s === 'menunggu') return 1
                if (s === 'sedang_diubah') return 1
                if (s === 'diproses') return 2
                if (s === 'siap') return 3
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
                        if (step === 'menunggu') {
                            label.textContent = String(status || '') === 'sedang_diubah' ? labelEditing : labelWaiting
                        }
                        label.classList.toggle('text-neutral-900', active)
                        label.classList.toggle('dark:text-white', active)
                        label.classList.toggle('text-neutral-500', !active)
                        label.classList.toggle('dark:text-neutral-400', !active)
                    }
                })
            }

            const updateCustomerActions = (status) => {
                const s = String(status || '')
                const canEdit = s === 'menunggu'
                const canContinue = s === 'sedang_diubah'
                const canCancel = s === 'menunggu' || s === 'sedang_diubah'
                const showActions = canEdit || canContinue || canCancel

                if (customerOrderActions) {
                    customerOrderActions.classList.toggle('hidden', !showActions)
                    customerOrderActions.classList.toggle('flex', showActions)
                }

                if (customerEditAction) customerEditAction.classList.toggle('hidden', !canEdit)
                if (customerContinueAction) {
                    customerContinueAction.classList.toggle('hidden', !canContinue)
                    customerContinueAction.classList.toggle('inline-flex', canContinue)
                }
                if (customerCancelAction) customerCancelAction.classList.toggle('hidden', !canCancel)
            }

            const render = (payload) => {
                const isPaid = !!payload?.paid
                const isFailed = !!payload?.failed
                if (isFailed) {
                    stopPolling()
                    toast(labelOrderFailed)
                    statusEmpty?.classList.add('hidden')
                    statusCard?.classList.add('hidden')

                    const redirectUrl = payload?.redirect || redirectOrderUrl
                    setTimeout(() => {
                        window.location.href = redirectUrl
                    }, 1200)
                    return
                }

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
                    remove(nameKey)

                    statusEmpty?.classList.add('hidden')
                    statusCard?.classList.add('hidden')

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

                if (!hasOrder) {
                    // If we previously had an order and now it's missing (e.g. deleted/canceled by management),
                    // notify customer and send them back to the order page.
                    if (!failureNotified && hadOrderEver) {
                        failureNotified = true
                        stopPolling()

                        toast(labelOrderFailed)

                        // Clear draft states to avoid confusion
                        const cartKey = token ? `customerCart:${token}` : 'customerCart'
                        const voucherKey = token ? `customerVoucher:${token}` : 'customerVoucher'
                        const nameKey = token ? `customerName:${token}` : 'customerName'
                        const remove = (k) => {
                            try { sessionStorage.removeItem(k) } catch (e) {}
                            try { localStorage.removeItem(k) } catch (e) {}
                        }
                        remove(cartKey)
                        remove(voucherKey)
                        remove(nameKey)

                        setTimeout(() => {
                            window.location.href = redirectOrderUrl
                        }, 1200)
                        return
                    }

                    if (pollHint) pollHint.textContent = ''
                    return
                }

                hadOrderEver = true
                if (orderCodeText) orderCodeText.textContent = order.kode_pesanan || ''
                if (orderStatusText) orderStatusText.textContent = statusLabel(order.status)
                if (orderTotalText) orderTotalText.textContent = formatIDR(Number(order.total_harga || 0))
                if (orderTotalText2) orderTotalText2.textContent = formatIDR(Number(order.total_harga || 0))
                if (orderSubtotalText) orderSubtotalText.textContent = formatIDR(Number(order.subtotal || 0))
                const discountVal = Number(order.discount_total || 0)
                if (orderDiscountRow) orderDiscountRow.classList.toggle('hidden', !(discountVal > 0))
                if (orderDiscountText) orderDiscountText.textContent = `- ${formatIDR(discountVal)}`
                if (orderTaxText) orderTaxText.textContent = formatIDR(Number(order.tax_total || 0))
                updateStepper(order.status)
                if (processingNote) processingNote.classList.toggle('hidden', order.status !== 'diproses')
                if (editingNote) editingNote.classList.toggle('hidden', order.status !== 'sedang_diubah')
                const waitingListNote = document.getElementById('waitingListNote')
                if (waitingListNote) waitingListNote.classList.toggle('hidden', order.status !== 'booking')
                if (readyNote) readyNote.classList.toggle('hidden', order.status !== 'siap')
                updateCustomerActions(order.status)

                if (breakdownItemsList) {
                    const rows = Array.isArray(order.items) ? order.items : []
                    breakdownItemsList.innerHTML = rows.map((it) => {
                        const addons = Array.isArray(it.addons) ? it.addons : []
                        const addonLine = addons.length ? `<div class="mt-0.5 text-[11px] text-neutral-500 dark:text-neutral-400">${addons.map(a => `+ ${a}`).join(', ')}</div>` : ''
                        return `
                            <div class="flex items-start justify-between gap-3 rounded-2xl border border-neutral-200/70 bg-white/60 p-3 text-sm dark:border-neutral-800/70 dark:bg-neutral-950/20">
                                <div class="min-w-0">
                                    <div class="truncate font-semibold text-neutral-900 dark:text-white">${it.menu || labelMenu}</div>
                                    ${addonLine}
                                    <div class="mt-1 text-[11px] text-neutral-500 dark:text-neutral-400">${labelQty}: ${Number(it.qty || 0)}</div>
                                </div>
                                <div class="shrink-0 font-semibold text-neutral-900 dark:text-white">${formatIDR(Number(it.subtotal || 0))}</div>
                            </div>
                        `
                    }).join('')
                }
            }

            let inflight = false
            const POLL_MS = 2000
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
                fetch(@json($statusJsonUrl ?? route('customer.status.json', ['token' => $token])), { cache: 'no-store' })
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
