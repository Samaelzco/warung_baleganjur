<x-layouts.customer :title="__('Checkout')">
    @php
        $idr0 = fn () => 'Rp 0';

        $menuIndex = $menus
            ->map(function ($m) {
                $img = (string) ($m->gambar ?? '');
                $src = '';
                if ($img !== '') {
                    $src = \Illuminate\Support\Str::startsWith($img, ['http://', 'https://', '/'])
                        ? $img
                        : \Illuminate\Support\Facades\Storage::url($img);
                }

                return [
                    'id' => (int) $m->id,
                    'name' => (string) $m->nama_menu,
                    'price' => (float) $m->harga,
                    'image' => (string) $src,
                    'addons' => $m->addons
                        ->map(fn ($a) => [
                            'id' => (int) $a->id,
                            'name' => (string) $a->nama_addon,
                            'price' => (float) $a->harga,
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();

        $diskonIndex = $diskons
            ->map(fn ($d) => [
                'id' => (int) $d->id,
                'code' => (string) $d->kode,
                'type' => (string) $d->tipe,
                'value' => (float) $d->nilai,
                'min_subtotal' => $d->min_subtotal === null ? null : (float) $d->min_subtotal,
                'start' => $d->tanggal_mulai?->format('Y-m-d'),
                'end' => $d->tanggal_selesai?->format('Y-m-d'),
                'active' => (bool) $d->is_active,
            ])
            ->values()
            ->all();

        $taxIndex = $taxes
            ->map(fn ($t) => [
                'id' => (int) $t->id,
                'name' => (string) $t->nama,
                'percent' => (float) $t->persentase,
                'active' => (bool) $t->is_active,
            ])
            ->values()
            ->all();
    @endphp

    <div class="space-y-6" data-table-token="{{ $token }}">
        <header class="sticky top-0 z-30 -mx-4 space-y-4 border-b border-neutral-200/70 bg-white/80 px-4 pb-3 pt-4 shadow-sm backdrop-blur dark:border-neutral-800/70 dark:bg-neutral-950/60 sm:-mx-6 sm:px-6">
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <a
                        href="{{ route('customer.order', ['token' => $token]) }}"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-neutral-200/70 bg-white/60 text-neutral-700 shadow-sm backdrop-blur hover:bg-white/80 dark:border-neutral-800/70 dark:bg-neutral-900/40 dark:text-neutral-200 dark:hover:bg-neutral-900/60"
                        aria-label="{{ __('Back') }}"
                    >
                        <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M15 18l-6-6 6-6"></path>
                        </svg>
                    </a>

                    <div class="min-w-0">
                        <div class="text-lg font-semibold tracking-tight text-neutral-900 dark:text-white">
                            {{ __('Checkout') }}
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
                </div>
            </div>
        </header>

        <section class="space-y-3">
            <div class="text-sm font-semibold text-neutral-700 dark:text-neutral-200">{{ __('Order Summary') }}</div>
            <div id="checkoutItems" class="space-y-3"></div>
            <div id="checkoutEmpty" class="customer-card-depth hidden rounded-3xl border border-neutral-200/70 bg-white/70 p-6 text-center text-sm text-neutral-600 shadow-sm backdrop-blur dark:border-neutral-800/70 dark:bg-neutral-900/40 dark:text-neutral-300">
                {{ __('Your cart is empty.') }}
                <div class="mt-4">
                    <a href="{{ route('customer.order', ['token' => $token]) }}" class="inline-flex h-11 items-center justify-center rounded-2xl bg-[var(--brand-primary)] px-5 text-sm font-semibold text-[var(--brand-accent)] shadow-sm ring-1 ring-black/5 hover:bg-[var(--brand-primary-hover)] active:bg-[var(--brand-primary-active)]">
                        {{ __('Back to menu') }}
                    </a>
                </div>
            </div>
        </section>

        <section class="space-y-3">
            <div class="customer-card-depth rounded-3xl border border-neutral-200/70 bg-white/70 p-4 shadow-sm backdrop-blur dark:border-neutral-800/70 dark:bg-neutral-900/40">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-sm font-semibold text-neutral-700 dark:text-neutral-200">{{ __('Subtotal') }}</div>
                    <div id="subtotalText" class="text-sm font-semibold text-neutral-900 dark:text-white">{{ $idr0() }}</div>
                </div>
                <div id="discountRow" class="mt-2 hidden flex items-center justify-between gap-3">
                    <div class="text-sm font-semibold text-neutral-700 dark:text-neutral-200">{{ __('Discount') }}</div>
                    <div id="discountText" class="text-sm font-semibold text-neutral-900 dark:text-white">{{ $idr0() }}</div>
                </div>
                <div id="taxRow" class="mt-2 hidden flex items-center justify-between gap-3">
                    <div id="taxLabel" class="text-sm font-semibold text-neutral-700 dark:text-neutral-200">{{ __('Tax') }}</div>
                    <div id="taxText" class="text-sm font-semibold text-neutral-900 dark:text-white">{{ $idr0() }}</div>
                </div>
                <div class="mt-3 border-t border-neutral-200/70 pt-3 dark:border-neutral-800/70">
                    <div class="flex items-center justify-between gap-3">
                        <div class="text-base font-semibold text-neutral-900 dark:text-white">{{ __('Total') }}</div>
                        <div id="totalText" class="text-base font-semibold text-neutral-900 dark:text-white">{{ $idr0() }}</div>
                    </div>
                </div>
            </div>

            <div class="customer-card-depth rounded-3xl border border-neutral-200/70 bg-white/70 p-4 shadow-sm backdrop-blur dark:border-neutral-800/70 dark:bg-neutral-900/40">
                <div class="text-sm font-semibold text-neutral-700 dark:text-neutral-200">{{ __('Voucher') }}</div>
                <div class="mt-3 flex items-center gap-2">
                    <input
                        id="voucherInput"
                        type="text"
                        inputmode="text"
                        autocapitalize="characters"
                        autocomplete="off"
                        placeholder="{{ __('Enter voucher code') }}"
                        class="h-12 w-full rounded-2xl border border-neutral-200/70 bg-white/70 px-4 text-sm font-semibold tracking-wide text-neutral-900 shadow-sm outline-none placeholder:font-medium placeholder:tracking-normal placeholder:text-neutral-400 focus:border-[var(--brand-primary)] focus:ring-4 focus:ring-[color-mix(in_srgb,var(--brand-primary)_15%,transparent)] dark:border-neutral-800/70 dark:bg-neutral-950/30 dark:text-white dark:placeholder:text-neutral-500"
                    />
                    <button
                        type="button"
                        id="clearVoucherBtn"
                        onclick="window.CustomerCheckout?.clearVoucher?.()"
                        class="hidden inline-flex h-12 shrink-0 items-center justify-center rounded-2xl border border-neutral-200/70 bg-white/60 px-4 text-sm font-semibold text-neutral-700 shadow-sm backdrop-blur hover:bg-white/80 dark:border-neutral-800/70 dark:bg-neutral-900/40 dark:text-neutral-200 dark:hover:bg-neutral-900/60"
                    >
                        {{ __('Clear') }}
                    </button>
                    <button
                        type="button"
                        id="applyVoucherBtn"
                        onclick="window.CustomerCheckout?.applyVoucher?.()"
                        class="inline-flex h-12 shrink-0 items-center justify-center rounded-2xl bg-[var(--brand-accent)] px-4 text-sm font-semibold text-white shadow-sm hover:bg-[var(--brand-accent-hover)]"
                    >
                        {{ __('Apply') }}
                    </button>
                </div>
                <div id="voucherHint" class="mt-2 text-xs font-medium text-neutral-500 dark:text-neutral-400"></div>
            </div>
        </section>

        <section class="pb-20">
            <button
                type="button"
                id="confirmCheckoutBtn"
                onclick="window.CustomerCheckout?.openNameModal?.()"
                class="inline-flex h-12 w-full items-center justify-center gap-2 rounded-2xl bg-[var(--brand-primary)] px-5 text-sm font-semibold text-[var(--brand-accent)] shadow-sm ring-1 ring-black/5 hover:bg-[var(--brand-primary-hover)] active:bg-[var(--brand-primary-active)]"
            >
                {{ __('Confirm order') }}
                <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M5 12h14"></path>
                    <path d="M13 6l6 6-6 6"></path>
                </svg>
            </button>
        </section>
    </div>

    <div id="nameModal" class="fixed inset-0 z-50 hidden">
        <button type="button" class="absolute inset-0 bg-black/40" onclick="window.CustomerCheckout?.closeNameModal?.()" aria-label="{{ __('Close') }}"></button>

        <div class="relative flex min-h-svh items-center justify-center p-4">
            <div class="customer-card-depth w-full max-w-md rounded-3xl border border-neutral-200/70 bg-white/90 p-5 shadow-2xl backdrop-blur dark:border-neutral-800/70 dark:bg-neutral-950/85">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="text-lg font-semibold text-neutral-900 dark:text-white">{{ __('Confirm order') }}</div>
                        <div class="mt-1 text-sm text-neutral-600 dark:text-neutral-300">{{ __('Enter your name for this order.') }}</div>
                    </div>
                    <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-neutral-200/70 bg-white/60 text-neutral-700 shadow-sm backdrop-blur hover:bg-white/80 dark:border-neutral-800/70 dark:bg-neutral-900/40 dark:text-neutral-200 dark:hover:bg-neutral-900/60" onclick="window.CustomerCheckout?.closeNameModal?.()" aria-label="{{ __('Close') }}">
                        <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 6L6 18"></path>
                            <path d="M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <div class="mt-4 space-y-3">
                    <div>
                        <label for="customerName" class="text-sm font-semibold text-neutral-700 dark:text-neutral-200">{{ __('Customer Name') }}</label>
                        <input
                            id="customerName"
                            type="text"
                            class="mt-2 h-12 w-full rounded-2xl border border-neutral-200/70 bg-white/70 px-4 text-sm text-neutral-900 shadow-sm outline-none placeholder:text-neutral-400 focus:border-[var(--brand-primary)] focus:ring-4 focus:ring-[color-mix(in_srgb,var(--brand-primary)_15%,transparent)] dark:border-neutral-800/70 dark:bg-neutral-950/30 dark:text-white dark:placeholder:text-neutral-500"
                            placeholder="{{ __('Your name') }}"
                            autocomplete="name"
                        />
                    </div>

                    <div id="checkoutError" class="hidden rounded-2xl border border-rose-200/70 bg-rose-50/80 p-3 text-sm font-semibold text-rose-800 shadow-sm backdrop-blur dark:border-rose-900/40 dark:bg-rose-900/20 dark:text-rose-200"></div>

                    <div class="flex items-center justify-end gap-2 pt-1">
                        <button type="button" class="inline-flex h-11 items-center justify-center rounded-2xl border border-neutral-200/70 bg-white/60 px-4 text-sm font-semibold text-neutral-700 shadow-sm backdrop-blur hover:bg-white/80 dark:border-neutral-800/70 dark:bg-neutral-900/40 dark:text-neutral-200 dark:hover:bg-neutral-900/60" onclick="window.CustomerCheckout?.closeNameModal?.()">
                            {{ __('Cancel') }}
                        </button>
                        <button type="button" id="saveNameBtn" onclick="window.CustomerCheckout?.submitOrder?.()" class="inline-flex h-11 items-center justify-center rounded-2xl bg-[var(--brand-primary)] px-4 text-sm font-semibold text-[var(--brand-accent)] shadow-sm ring-1 ring-black/5 hover:bg-[var(--brand-primary-hover)] active:bg-[var(--brand-primary-active)]">
                            {{ __('Confirm') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script id="menuIndexJson" type="application/json">@json($menuIndex)</script>
    <script id="diskonIndexJson" type="application/json">@json($diskonIndex)</script>
    <script id="taxIndexJson" type="application/json">@json($taxIndex)</script>

    <script>
        (() => {
            const root = document.querySelector('[data-table-token]')
            if (!root) return

            const token = root.getAttribute('data-table-token') || ''
            const cartKey = token ? `customerCart:${token}` : 'customerCart'
            const voucherKey = token ? `customerVoucher:${token}` : 'customerVoucher'
            const nameKey = token ? `customerName:${token}` : 'customerName'

            const checkoutItems = document.getElementById('checkoutItems')
            const checkoutEmpty = document.getElementById('checkoutEmpty')
            const subtotalText = document.getElementById('subtotalText')
            const discountRow = document.getElementById('discountRow')
            const discountText = document.getElementById('discountText')
            const taxRow = document.getElementById('taxRow')
            const taxLabel = document.getElementById('taxLabel')
            const taxText = document.getElementById('taxText')
            const totalText = document.getElementById('totalText')

            const voucherInput = document.getElementById('voucherInput')
            const voucherHint = document.getElementById('voucherHint')
            const clearVoucherBtn = document.getElementById('clearVoucherBtn')

            const confirmCheckoutBtn = document.getElementById('confirmCheckoutBtn')
            const nameModal = document.getElementById('nameModal')
            const customerName = document.getElementById('customerName')
            const saveNameBtn = document.getElementById('saveNameBtn')
            const checkoutError = document.getElementById('checkoutError')

            const labelInvalidVoucher = @json(__('Invalid voucher.'));
            const labelVoucherApplied = @json(__('Voucher applied.'));
            const labelNetwork = @json(__('Network issue. Please try again.'));
            const labelSubmitFailed = @json(__('Failed to submit order. Please try again.'));
            const labelMenu = @json(__('Menu'));
            const labelTaxDefault = @json(__('Tax'));

            const formatIDR = (value) => {
                try {
                    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value || 0))
                } catch (e) {
                    return `Rp ${Math.round(Number(value || 0)).toString().replace(/\\B(?=(\\d{3})+(?!\\d))/g, '.')}`
                }
            }

            const parseJsonScript = (id, fallback) => {
                try {
                    const raw = document.getElementById(id)?.textContent || ''
                    if (!raw) return fallback
                    return JSON.parse(raw)
                } catch (e) {
                    return fallback
                }
            }

            const menuMap = (() => {
                const list = parseJsonScript('menuIndexJson', [])
                const map = new Map()
                for (const m of (Array.isArray(list) ? list : [])) {
                    const id = String(m.id ?? '')
                    if (!id) continue
                    map.set(id, {
                        id,
                        name: String(m.name || ''),
                        price: Number(m.price || 0),
                        image: String(m.image || ''),
                        addons: Array.isArray(m.addons) ? m.addons.map(a => ({
                            id: String(a.id ?? ''),
                            name: String(a.name || ''),
                            price: Number(a.price || 0),
                        })) : [],
                    })
                }
                return map
            })()

            const diskonMap = (() => {
                const list = parseJsonScript('diskonIndexJson', [])
                const map = new Map()
                for (const d of (Array.isArray(list) ? list : [])) {
                    const code = String(d.code || '').toUpperCase()
                    if (!code) continue
                    map.set(code, d)
                }
                return map
            })()

            const activeTaxPercent = (() => {
                const list = parseJsonScript('taxIndexJson', [])
                return (Array.isArray(list) ? list : [])
                    .filter(t => t && t.active)
                    .reduce((sum, t) => sum + Number(t.percent || 0), 0)
            })()

            const activeTaxNames = (() => {
                const list = parseJsonScript('taxIndexJson', [])
                return (Array.isArray(list) ? list : [])
                    .filter(t => t && t.active)
                    .map(t => String(t.name || '').trim())
                    .filter(Boolean)
            })()

            const normalizeCart = (data) => {
                if (!data || typeof data !== 'object') return {}
                const normalized = {}
                for (const [id, v] of Object.entries(data)) {
                    if (typeof v === 'number') {
                        const qty = Math.max(Number(v || 0), 0)
                        if (qty > 0) normalized[id] = { qty, addons: [] }
                        continue
                    }
                    if (v && typeof v === 'object') {
                        const qty = Math.max(Number(v.qty || 0), 0)
                        const addons = Array.isArray(v.addons) ? v.addons.map(x => String(x)) : []
                        if (qty > 0) normalized[id] = { qty, addons }
                    }
                }
                return normalized
            }

            const readCart = () => {
                try {
                    const getItem = (k) => {
                        try { return sessionStorage.getItem(k) } catch (e) {}
                        try { return localStorage.getItem(k) } catch (e) {}
                        return null
                    }

                    const setItem = (k, v) => {
                        try { sessionStorage.setItem(k, v) } catch (e) {}
                        try { localStorage.setItem(k, v) } catch (e) {}
                    }

                    let raw = getItem(cartKey)

                    // Robust fallbacks for token casing / legacy keys (mobile Safari quirks).
                    if (!raw && token) {
                        const tLower = String(token).toLowerCase()
                        const tUpper = String(token).toUpperCase()
                        if (tLower !== token) raw = getItem(`customerCart:${tLower}`)
                        if (!raw && tUpper !== token) raw = getItem(`customerCart:${tUpper}`)
                    }

                    // Case-insensitive exact key match
                    if (!raw && token && typeof localStorage.key === 'function') {
                        const target = `customercart:${String(token).toLowerCase()}`
                        for (let i = 0; i < localStorage.length; i++) {
                            const k = localStorage.key(i)
                            if (!k) continue
                            if (String(k).toLowerCase() === target) {
                                raw = getItem(k)
                                if (raw) {
                                    setItem(cartKey, raw)
                                }
                                break
                            }
                        }
                    }

                    // If only one cart key exists, reuse it
                    if (!raw && typeof localStorage.key === 'function') {
                        const keys = []
                        for (let i = 0; i < localStorage.length; i++) {
                            const k = localStorage.key(i)
                            if (k && String(k).toLowerCase().startsWith('customercart:')) keys.push(k)
                        }
                        if (keys.length === 1) {
                            raw = getItem(keys[0])
                            if (raw) {
                                setItem(cartKey, raw)
                            }
                        }
                    }

                    // Very old fallback (no token)
                    if (!raw) {
                        raw = getItem('customerCart')
                        if (raw) {
                            setItem(cartKey, raw)
                        }
                    }

                    if (!raw) return {}
                    return normalizeCart(JSON.parse(raw))
                } catch (e) {
                    return {}
                }
            }

            const writeCart = (cart) => {
                try {
                    const json = JSON.stringify(cart || {})
                    try { sessionStorage.setItem(cartKey, json) } catch (e) {}
                    try { localStorage.setItem(cartKey, json) } catch (e) {}
                } catch (e) {}
            }

            const readVoucher = () => {
                try {
                    const raw = (sessionStorage.getItem(voucherKey) || localStorage.getItem(voucherKey) || '')
                    return String(raw || '').toUpperCase().trim()
                } catch (e) {
                    return ''
                }
            }

            const writeVoucher = (code) => {
                try {
                    const v = String(code || '').toUpperCase().trim()
                    try { sessionStorage.setItem(voucherKey, v) } catch (e) {}
                    try { localStorage.setItem(voucherKey, v) } catch (e) {}
                } catch (e) {}
            }

            const parseYmd = (ymd) => {
                if (!ymd) return null
                const [y, m, d] = String(ymd).split('-').map(Number)
                if (!y || !m || !d) return null
                return new Date(y, m - 1, d)
            }

            const isTodayWithin = (startYmd, endYmd) => {
                const now = new Date()
                const today = new Date(now.getFullYear(), now.getMonth(), now.getDate())
                const start = parseYmd(startYmd)
                const end = parseYmd(endYmd)
                if (start && today < start) return false
                if (end && today > end) return false
                return true
            }

            const calc = () => {
                const cart = readCart()
                const voucherCode = String(voucherInput?.value || readVoucher() || '').toUpperCase().trim()

                const lines = []
                let subtotal = 0
                for (const [id, row] of Object.entries(cart)) {
                    const qty = Math.max(Number(row?.qty || 0), 0)
                    const menu = menuMap.get(String(id))
                    if (!menu || qty <= 0) continue

                    const allowed = new Set(menu.addons.map(a => String(a.id)))
                    const selectedAddonIds = (Array.isArray(row.addons) ? row.addons : []).map(String).filter(aId => allowed.has(aId))
                    const selectedAddons = menu.addons.filter(a => selectedAddonIds.includes(String(a.id)))
                    const addonsTotal = selectedAddons.reduce((sum, a) => sum + Number(a.price || 0), 0)

                    const unit = Number(menu.price || 0) + addonsTotal
                    const line = unit * qty
                    subtotal += line

                    lines.push({
                        id: String(id),
                        name: menu.name || labelMenu,
                        image: menu.image || '',
                        qty,
                        unit,
                        line,
                        addons: selectedAddons.map(a => ({ name: a.name, price: a.price })),
                    })
                }

                const code = String(voucherCode || '').toUpperCase().trim()
                let discount = 0
                let voucherOk = false
                let voucherMessage = ''
                if (code) {
                    const d = diskonMap.get(code)
                    const ok = !!d && !!d.active && isTodayWithin(d.start, d.end) && (d.min_subtotal == null || subtotal >= Number(d.min_subtotal))
                    if (ok) {
                        voucherOk = true
                        if (d.type === 'percent') discount = Math.max(subtotal * (Number(d.value || 0) / 100), 0)
                        else discount = Math.max(Number(d.value || 0), 0)
                        discount = Math.min(discount, subtotal)
                        voucherMessage = labelVoucherApplied
                    } else {
                        voucherOk = false
                        discount = 0
                        voucherMessage = labelInvalidVoucher
                    }
                }

                const taxable = Math.max(subtotal - discount, 0)
                const tax = Math.max(taxable * (activeTaxPercent / 100), 0)
                const total = taxable + tax

                return { cart, voucherCode: code, lines, subtotal, discount, tax, total, voucherOk, voucherMessage }
            }

            const render = () => {
                const { voucherCode, lines, subtotal, discount, tax, total, voucherOk, voucherMessage } = calc()

                if (voucherInput && voucherCode !== String(voucherInput.value || '').toUpperCase().trim()) {
                    voucherInput.value = voucherCode
                }
                writeVoucher(voucherCode)

                if (clearVoucherBtn) {
                    clearVoucherBtn.classList.toggle('hidden', !voucherCode)
                }

                if (checkoutItems) {
                    checkoutItems.innerHTML = lines.map((l) => {
                        const addonHtml = l.addons.length
                            ? `<div class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">${l.addons.map(a => `+ ${a.name}`).join(', ')}</div>`
                            : ''
                        const img = l.image
                            ? `<img src="${l.image}" alt="" class="h-16 w-20 rounded-2xl object-cover ring-1 ring-black/5 dark:ring-white/10" loading="lazy" />`
                            : `<div class="h-16 w-20 rounded-2xl bg-neutral-100 ring-1 ring-black/5 dark:bg-neutral-800 dark:ring-white/10"></div>`

                        return `
                            <div class="customer-card-depth rounded-3xl border border-neutral-200/70 bg-white/70 p-4 shadow-sm backdrop-blur dark:border-neutral-800/70 dark:bg-neutral-900/40">
                                <div class="flex items-start gap-4">
                                    ${img}
                                    <div class="min-w-0 flex-1">
                                        <div class="truncate text-sm font-semibold text-neutral-900 dark:text-white">${l.name}</div>
                                        ${addonHtml}
                                        <div class="mt-2 flex items-center justify-between gap-3">
                                            <div class="text-xs text-neutral-500 dark:text-neutral-400">${formatIDR(l.unit)} × ${l.qty}</div>
                                            <div class="text-sm font-semibold text-neutral-900 dark:text-white">${formatIDR(l.line)}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `
                    }).join('')
                }

                const hasItems = lines.length > 0
                checkoutEmpty?.classList.toggle('hidden', hasItems)

                if (subtotalText) subtotalText.textContent = formatIDR(subtotal)
                if (discountText) discountText.textContent = formatIDR(discount)
                if (discountRow) discountRow.classList.toggle('hidden', !(discount > 0))
                if (taxText) taxText.textContent = formatIDR(tax)
                if (totalText) totalText.textContent = formatIDR(total)
                if (taxRow) taxRow.classList.toggle('hidden', !(tax > 0))
                if (taxLabel) taxLabel.textContent = activeTaxNames.length ? activeTaxNames.join(' + ') : labelTaxDefault

                if (voucherHint) {
                    voucherHint.textContent = voucherCode ? voucherMessage : ''
                    voucherHint.classList.toggle('text-emerald-600', voucherOk)
                    voucherHint.classList.toggle('dark:text-emerald-400', voucherOk)
                    voucherHint.classList.toggle('text-rose-600', voucherCode && !voucherOk)
                    voucherHint.classList.toggle('dark:text-rose-400', voucherCode && !voucherOk)
                }

                const disabled = !hasItems
                if (confirmCheckoutBtn) {
                    confirmCheckoutBtn.toggleAttribute('disabled', disabled)
                    confirmCheckoutBtn.classList.toggle('opacity-60', disabled)
                    confirmCheckoutBtn.classList.toggle('pointer-events-none', disabled)
                }
            }

            const openNameModal = () => {
                checkoutError?.classList.add('hidden')
                if (nameModal) nameModal.classList.remove('hidden')
                try {
                    const saved = (localStorage.getItem(nameKey) || '').trim()
                    if (saved && customerName && !customerName.value) customerName.value = saved
                } catch (e) {}
                setTimeout(() => customerName?.focus(), 50)
            }

            const closeNameModal = () => {
                if (nameModal) nameModal.classList.add('hidden')
            }

            const applyVoucher = () => {
                render()
            }

            const clearVoucher = () => {
                if (voucherInput) voucherInput.value = ''
                writeVoucher('')
                render()
            }

            const submitOrder = async () => {
                checkoutError?.classList.add('hidden')

                const { cart, voucherCode, lines } = calc()
                if (!lines.length) return

                const name = String(customerName?.value || '').trim()
                if (!name) {
                    if (checkoutError) {
                        checkoutError.textContent = @json(__('Please enter your name.'));
                        checkoutError.classList.remove('hidden')
                    }
                    customerName?.focus()
                    return
                }

                try { localStorage.setItem(nameKey, name) } catch (e) {}

                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                saveNameBtn?.setAttribute('disabled', 'disabled')

                try {
                    const res = await fetch(@json(route('customer.checkout.submit', ['token' => $token])), {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                        body: JSON.stringify({
                            cart,
                            voucher: voucherCode,
                            customer_name: name,
                        }),
                    })

                    const json = await res.json().catch(() => ({}))

                    if (res.ok && json && json.ok) {
                        writeCart({})
                        writeVoucher('')
                        window.location.href = json.redirect || @json(route('customer.status', ['token' => $token]));
                        return
                    }

                    if (res.status === 409 && json && json.redirect) {
                        window.location.href = json.redirect;
                        return
                    }

                    if (checkoutError) {
                        checkoutError.textContent = json?.message || labelSubmitFailed
                        checkoutError.classList.remove('hidden')
                    } else {
                        alert(json?.message || labelSubmitFailed)
                    }
                } catch (e) {
                    if (checkoutError) {
                        checkoutError.textContent = labelNetwork
                        checkoutError.classList.remove('hidden')
                    } else {
                        alert(labelNetwork)
                    }
                } finally {
                    saveNameBtn?.removeAttribute('disabled')
                }
            }

            window.CustomerCheckout = {
                applyVoucher,
                clearVoucher,
                openNameModal,
                closeNameModal,
                submitOrder,
            }

            voucherInput?.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault()
                    applyVoucher()
                }
            })

            customerName?.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault()
                    submitOrder()
                }
            })

            render()
            window.addEventListener('pageshow', () => render())
        })()
    </script>
</x-layouts.customer>
