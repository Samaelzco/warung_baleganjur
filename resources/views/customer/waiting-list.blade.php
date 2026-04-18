<x-layouts.customer :title="__('Waiting List')">
    @php
        $totalTables = $mejas->count();
        $totalWaiting = collect($waitingListCounts ?? [])->sum();
        $totalCapacity = $mejas->sum(fn ($m) => (int) ($m->kapasitas ?? 4));
        $totalOccupied = collect($occupiedByTable ?? [])->sum();
        $totalAvailable = max($totalCapacity - $totalOccupied, 0);
    @endphp

    <div class="space-y-6" data-waiting-list-page data-waiting-list-url="{{ route('customer.waiting-list.data') }}" data-poll-ms="5000">
        <header class="sticky top-0 z-30 -mx-4 space-y-4 border-b border-neutral-200/70 bg-white/80 px-4 pb-3 pt-4 shadow-sm backdrop-blur dark:border-neutral-800/70 dark:bg-neutral-950/60">
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <div class="text-xl font-semibold tracking-tight text-neutral-900 dark:text-white">
                        <span>{{ __('Warung') }}</span>
                        <span class="font-extrabold text-[var(--brand-accent)] dark:text-white">{{ __('Baleganjur') }}</span>
                    </div>

                    <div class="mt-1 flex items-center gap-2 text-xs font-medium tracking-wide text-neutral-600 dark:text-neutral-300">
                        <span class="h-1.5 w-1.5 rounded-full bg-[var(--brand-primary)]"></span>
                        <span>{{ __('Waiting List') }}</span>
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
                </div>
            </div>
        </header>

        <section class="space-y-4">
            <div class="customer-card-depth rounded-3xl border border-neutral-200/70 bg-white/70 p-5 shadow-sm backdrop-blur dark:border-neutral-800/70 dark:bg-neutral-900/40">
                <div class="space-y-2">
                    <div class="text-2xl font-semibold tracking-tight text-neutral-900 dark:text-white">
                        {{ __('Choose a table') }}
                    </div>
                    <p class="text-sm leading-6 text-neutral-600 dark:text-neutral-300">
                        {{ __('Select a table to place your order. If the table is full, your order will stay in the waiting list until seats are available.') }}
                    </p>
                </div>

                <div class="mt-4 grid grid-cols-3 gap-2">
                    <div class="rounded-2xl border border-neutral-200/70 bg-white/60 px-3 py-2 text-center shadow-sm backdrop-blur dark:border-neutral-800/70 dark:bg-neutral-950/20">
                        <div class="text-base font-extrabold text-neutral-900 dark:text-white transition-colors duration-300" data-summary-tables>{{ $totalTables }}</div>
                        <div class="mt-0.5 text-[11px] font-semibold text-neutral-500 dark:text-neutral-400">{{ __('Tables') }}</div>
                    </div>
                    <div class="rounded-2xl border border-neutral-200/70 bg-white/60 px-3 py-2 text-center shadow-sm backdrop-blur dark:border-neutral-800/70 dark:bg-neutral-950/20">
                        <div class="text-base font-extrabold text-neutral-900 dark:text-white transition-colors duration-300" data-summary-seats-left>{{ $totalAvailable }}</div>
                        <div class="mt-0.5 text-[11px] font-semibold text-neutral-500 dark:text-neutral-400">{{ __('Seats left') }}</div>
                    </div>
                    <div class="rounded-2xl border border-neutral-200/70 bg-white/60 px-3 py-2 text-center shadow-sm backdrop-blur dark:border-neutral-800/70 dark:bg-neutral-950/20">
                        <div class="text-base font-extrabold text-neutral-900 dark:text-white transition-colors duration-300" data-summary-waiting>{{ $totalWaiting }}</div>
                        <div class="mt-0.5 text-[11px] font-semibold text-neutral-500 dark:text-neutral-400">{{ __('Waiting') }}</div>
                    </div>
                </div>
            </div>
        </section>

        <section class="-mx-2 space-y-3">
            @forelse ($mejas as $m)
                @php
                    $capacity = (int) ($m->kapasitas ?? 4);
                    $occupied = (int) ($occupiedByTable[$m->id] ?? 0);
                    $waiting = (int) ($waitingListCounts[$m->id] ?? 0);
                    $remaining = max($capacity - $occupied, 0);
                    $isFull = $remaining <= 0;
                @endphp

                <a
                    href="{{ route('customer.waiting-list.order', ['meja' => $m->id]) }}"
                    data-table-card="{{ $m->id }}"
                    class="customer-card-depth block rounded-3xl border border-neutral-200/70 bg-white/70 p-4 shadow-sm backdrop-blur transition-colors hover:bg-white/90 dark:border-neutral-800/70 dark:bg-neutral-900/40 dark:hover:bg-neutral-900/60"
                >
                    <div class="flex items-start gap-4">
                        <div class="flex h-20 w-20 shrink-0 flex-col items-center justify-center rounded-2xl bg-neutral-100 text-neutral-900 ring-1 ring-black/5 dark:bg-neutral-800 dark:text-white dark:ring-white/10">
                            <div class="text-xs font-semibold text-neutral-500 dark:text-neutral-400">{{ __('Table') }}</div>
                            <div class="text-2xl font-extrabold">{{ $m->nomor_meja }}</div>
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="line-clamp-1 text-base font-semibold text-neutral-900 dark:text-white">
                                        {{ __('Table') }} {{ $m->nomor_meja }}
                                    </div>
                                    <div class="mt-1 text-xs leading-5 text-neutral-500 dark:text-neutral-400">
                                        {{ __('Capacity') }} <span data-capacity>{{ $capacity }}</span> · {{ __('Seats left') }} <span class="transition-colors duration-300" data-seats-left>{{ $remaining }}</span>
                                    </div>
                                </div>

                                <span data-status-badge class="shrink-0 inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-[11px] font-extrabold shadow-sm backdrop-blur transition-colors duration-300 {{ $isFull ? 'border-amber-200/70 bg-amber-50/80 text-amber-800 dark:border-amber-900/40 dark:bg-amber-900/20 dark:text-amber-200' : 'border-emerald-200/70 bg-emerald-50/80 text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-900/20 dark:text-emerald-200' }}">
                                    <span data-status-dot class="h-1.5 w-1.5 rounded-full transition-colors duration-300 {{ $isFull ? 'bg-amber-500' : 'bg-emerald-500' }}"></span>
                                    <span data-status-text>{{ $isFull ? __('Full') : __('Open') }}</span>
                                </span>
                            </div>

                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                <span class="inline-flex items-center gap-1.5 rounded-full border border-neutral-200/70 bg-white/60 px-3 py-1 text-[11px] font-semibold text-neutral-600 shadow-sm backdrop-blur dark:border-neutral-800/70 dark:bg-neutral-950/20 dark:text-neutral-300">
                                    <span class="h-1.5 w-1.5 rounded-full bg-[var(--brand-primary)]"></span>
                                    <span class="transition-colors duration-300" data-waiting-count>{{ $waiting }}</span> {{ __('waiting') }}
                                </span>
                                <span class="inline-flex items-center gap-1.5 rounded-full border border-neutral-200/70 bg-white/60 px-3 py-1 text-[11px] font-semibold text-neutral-600 shadow-sm backdrop-blur dark:border-neutral-800/70 dark:bg-neutral-950/20 dark:text-neutral-300">
                                    {{ __('Occupied') }} <span class="transition-colors duration-300" data-occupied-count>{{ $occupied }}</span>
                                </span>
                            </div>

                            <div class="mt-3 flex items-center justify-between gap-3">
                                <div class="text-xs font-medium text-neutral-500 dark:text-neutral-400" data-action-text>
                                    {{ $isFull ? __('Join the waiting list') : __('Order now') }}
                                </div>
                                <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-[var(--brand-primary)] text-white shadow-sm ring-1 ring-black/5 dark:text-black">
                                    <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M5 12h14"></path>
                                        <path d="m13 6 6 6-6 6"></path>
                                    </svg>
                                </span>
                            </div>
                        </div>
                    </div>
                </a>
            @empty
                <div class="customer-card-depth rounded-3xl border border-neutral-200/70 bg-white/75 p-6 text-center text-sm text-neutral-600 shadow-sm backdrop-blur dark:border-neutral-800/70 dark:bg-neutral-900/50 dark:text-neutral-300">
                    {{ __('No tables available.') }}
                </div>
            @endforelse
        </section>

        <div class="pb-3 text-center text-[11px] font-medium text-neutral-400 dark:text-neutral-500" data-sync-status>
            {{ __('Updating every 5 seconds') }}
        </div>
    </div>

    <script>
        (() => {
            const page = document.querySelector('[data-waiting-list-page]')
            if (!page) return

            const endpoint = page.dataset.waitingListUrl || ''
            const intervalMs = Math.max(Number(page.dataset.pollMs || 5000), 3000)
            if (!endpoint) return

            const labels = {
                open: @json(__('Open')),
                full: @json(__('Full')),
                orderNow: @json(__('Order now')),
                joinWaitingList: @json(__('Join the waiting list')),
                updating: @json(__('Updating...')),
                updated: @json(__('Updated')),
                offline: @json(__('Connection issue. Retrying...')),
                paused: @json(__('Updates paused while this tab is inactive')),
            }

            const statusEl = page.querySelector('[data-sync-status]')
            let timer = null
            let inflight = false
            let stopped = false

            const setText = (selector, value, root = page) => {
                const el = root.querySelector(selector)
                if (!el) return
                const next = String(value ?? '')
                if (el.textContent.trim() === next) return
                el.textContent = next
                pulse(el)
            }

            const pulse = (el) => {
                el.classList.add('text-[var(--brand-primary)]')
                window.setTimeout(() => el.classList.remove('text-[var(--brand-primary)]'), 700)
            }

            const setFullState = (card, isFull) => {
                const badge = card.querySelector('[data-status-badge]')
                const dot = card.querySelector('[data-status-dot]')
                const text = card.querySelector('[data-status-text]')
                const action = card.querySelector('[data-action-text]')

                badge?.classList.toggle('border-amber-200/70', isFull)
                badge?.classList.toggle('bg-amber-50/80', isFull)
                badge?.classList.toggle('text-amber-800', isFull)
                badge?.classList.toggle('dark:border-amber-900/40', isFull)
                badge?.classList.toggle('dark:bg-amber-900/20', isFull)
                badge?.classList.toggle('dark:text-amber-200', isFull)

                badge?.classList.toggle('border-emerald-200/70', !isFull)
                badge?.classList.toggle('bg-emerald-50/80', !isFull)
                badge?.classList.toggle('text-emerald-800', !isFull)
                badge?.classList.toggle('dark:border-emerald-900/40', !isFull)
                badge?.classList.toggle('dark:bg-emerald-900/20', !isFull)
                badge?.classList.toggle('dark:text-emerald-200', !isFull)

                dot?.classList.toggle('bg-amber-500', isFull)
                dot?.classList.toggle('bg-emerald-500', !isFull)

                if (text) text.textContent = isFull ? labels.full : labels.open
                if (action) action.textContent = isFull ? labels.joinWaitingList : labels.orderNow
            }

            const render = (payload) => {
                if (!payload?.ok) return

                setText('[data-summary-tables]', payload.summary?.tables ?? 0)
                setText('[data-summary-seats-left]', payload.summary?.seats_left ?? 0)
                setText('[data-summary-waiting]', payload.summary?.waiting ?? 0)

                for (const table of (Array.isArray(payload.tables) ? payload.tables : [])) {
                    const card = page.querySelector(`[data-table-card="${table.id}"]`)
                    if (!card) continue

                    setText('[data-capacity]', table.capacity ?? 0, card)
                    setText('[data-seats-left]', table.seats_left ?? 0, card)
                    setText('[data-waiting-count]', table.waiting ?? 0, card)
                    setText('[data-occupied-count]', table.occupied ?? 0, card)
                    setFullState(card, !!table.is_full)
                }
            }

            const schedule = () => {
                if (stopped) return
                window.clearTimeout(timer)
                timer = window.setTimeout(fetchData, intervalMs)
            }

            const fetchData = async () => {
                if (stopped || inflight) return
                if (document.hidden) {
                    if (statusEl) statusEl.textContent = labels.paused
                    schedule()
                    return
                }

                inflight = true
                if (statusEl) statusEl.textContent = labels.updating

                try {
                    const res = await fetch(endpoint, {
                        headers: { 'Accept': 'application/json' },
                        cache: 'no-store',
                    })
                    if (!res.ok) throw new Error('Request failed')

                    render(await res.json())
                    if (statusEl) statusEl.textContent = `${labels.updated} ${new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`
                } catch (e) {
                    if (statusEl) statusEl.textContent = labels.offline
                } finally {
                    inflight = false
                    schedule()
                }
            }

            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) fetchData()
                else if (statusEl) statusEl.textContent = labels.paused
            })

            window.addEventListener('beforeunload', () => {
                stopped = true
                window.clearTimeout(timer)
            })

            schedule()
        })()
    </script>
</x-layouts.customer>
