<x-layouts.customer :title="__('Order')">
    @php
        $status = (string) ($order?->status ?? '');
        $idr = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');

        $menuPricingIndex = $menus
            ->map(fn ($m) => [
                'id' => (int) $m->id,
                'price' => (float) $m->harga,
                'addons' => $m->addons
                    ->map(fn ($a) => [
                        'id' => (int) $a->id,
                        'price' => (float) $a->harga,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();

        $menuCartIndex = $menus
            ->map(fn ($m) => [
                'id' => (int) $m->id,
                'name' => (string) $m->nama_menu,
                'price' => (float) $m->harga,
                'addons' => $m->addons
                    ->map(fn ($a) => [
                        'id' => (int) $a->id,
                        'name' => (string) $a->nama_addon,
                        'price' => (float) $a->harga,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    @endphp

    <div class="space-y-6" data-table-token="{{ $token }}">
        <header class="sticky top-0 z-30 -mx-4 space-y-4 border-b border-neutral-200/70 bg-white/80 px-4 pb-2 pt-4 shadow-sm backdrop-blur dark:border-neutral-800/70 dark:bg-neutral-950/60">
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <div class="text-xl font-semibold tracking-tight text-neutral-900 dark:text-white">
                        <span>{{ __('Warung') }}</span>
                        <span class="font-extrabold text-[var(--brand-accent)] dark:text-white">{{ __('Baleganjur') }}</span>
                    </div>

                    <div class="mt-1 flex items-center gap-2 text-xs font-medium tracking-wide text-neutral-600 dark:text-neutral-300">
                        <span class="h-1.5 w-1.5 rounded-full bg-[var(--brand-primary)]"></span>
                        <span>{{ __('Table') }} {{ $meja->nomor_meja }}</span>
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
                    <button id="searchToggle" type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-neutral-200/70 bg-white/60 text-neutral-700 shadow-sm backdrop-blur hover:bg-white/80 dark:border-neutral-800/70 dark:bg-neutral-900/40 dark:text-neutral-200 dark:hover:bg-neutral-900/60" aria-label="{{ __('Search') }}">
                        <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="7"></circle>
                            <path d="M20 20l-3.5-3.5"></path>
                        </svg>
                    </button>
                </div>
            </div>

            <div id="searchBar" class="hidden">
                <div class="customer-card-depth rounded-3xl border border-neutral-200/70 bg-white/70 p-3 shadow-sm backdrop-blur dark:border-neutral-800/70 dark:bg-neutral-900/40">
                    <div class="flex items-center gap-2">
                        <input
                            id="searchInput"
                            type="text"
                            inputmode="search"
                            autocomplete="off"
                            placeholder="{{ __('Search menu') }}"
                            class="h-11 w-full rounded-2xl border border-neutral-200/70 bg-white/70 px-4 text-sm font-medium text-neutral-900 shadow-sm outline-none placeholder:text-neutral-400 focus:border-[var(--brand-primary)] focus:ring-4 focus:ring-[color-mix(in_srgb,var(--brand-primary)_15%,transparent)] dark:border-neutral-800/70 dark:bg-neutral-950/30 dark:text-white dark:placeholder:text-neutral-500"
                        />
                        <button id="searchClear" type="button" class="inline-flex h-11 items-center justify-center rounded-2xl border border-neutral-200/70 bg-white/60 px-4 text-sm font-semibold text-neutral-700 shadow-sm backdrop-blur hover:bg-white/80 dark:border-neutral-800/70 dark:bg-neutral-950/30 dark:text-neutral-200 dark:hover:bg-neutral-950/50">
                            {{ __('Clear') }}
                        </button>
                    </div>
                    <div id="searchHint" class="mt-2 text-xs font-medium text-neutral-500 dark:text-neutral-400"></div>
                </div>
            </div>

            <div class="pt-2">
                <nav class="flex snap-x snap-mandatory gap-2 overflow-x-auto pb-2 text-sm no-scrollbar scroll-px-4" data-category-tabs>
                    <a
                        href="#all"
                        data-cat-link="all"
                        class="snap-center shrink-0 rounded-full border border-[color-mix(in_srgb,var(--brand-primary)_45%,transparent)] bg-[color-mix(in_srgb,var(--brand-primary)_16%,transparent)] px-4 py-2 font-extrabold text-neutral-900 shadow-sm dark:text-white"
                    >
                    {{ __('All Items') }}
                    </a>
                    @foreach ($categories as $c)
                        <a
                            href="#cat-{{ $c->id }}"
                            data-cat-link="cat-{{ $c->id }}"
                            class="snap-center shrink-0 rounded-full border border-transparent px-4 py-2 font-semibold text-neutral-600 hover:bg-neutral-100/70 hover:text-neutral-900 dark:text-neutral-300 dark:hover:bg-neutral-900/40 dark:hover:text-white"
                        >
                            {{ $c->nama_kategori }}
                        </a>
                    @endforeach
                </nav>
            </div>
        </header>

        <section id="all" data-cat-section="all" class="scroll-mt-24 space-y-4">
            <div>
                <div
                    id="categoryTitle"
                    data-all-label="{{ __('All Menus') }}"
                    class="text-2xl font-semibold tracking-tight text-neutral-900 dark:text-white"
                >
                    {{ __('All Menus') }}
                </div>
            </div>

            @forelse ($menus->groupBy('kategori_id') as $kategoriId => $rows)
                @php
                    $cat = $categories->firstWhere('id', (int) $kategoriId);
                    $catName = $cat?->nama_kategori ?? __('All Items');
                @endphp

                <div id="cat-{{ (int) $kategoriId }}" data-cat-section="cat-{{ (int) $kategoriId }}" class="scroll-mt-24 space-y-3">
                    <div data-cat-heading class="text-sm font-semibold text-neutral-700 dark:text-neutral-200">
                        {{ $catName }}
                    </div>

                            <div class="-mx-2 space-y-3">
                        @foreach ($rows as $m)
                            <div
                                class="customer-card-depth flex items-start gap-4 rounded-3xl border border-neutral-200/70 bg-white/70 p-4 shadow-sm backdrop-blur transition-colors dark:border-neutral-800/70 dark:bg-neutral-900/40"
                                data-menu-group="cat-{{ (int) $kategoriId }}"
                                data-menu-card
                                data-menu-id="{{ $m->id }}"
                                data-menu-name="{{ $m->nama_menu }}"
                                data-menu-price="{{ (float) $m->harga }}"
                                data-menu-search="{{ \Illuminate\Support\Str::lower(trim($m->nama_menu . ' ' . ($m->deskripsi ?? ''))) }}"
                            >
                                <div class="h-20 w-20 shrink-0 overflow-hidden rounded-2xl bg-neutral-100 ring-1 ring-black/5 dark:bg-neutral-800 dark:ring-white/10">
                                    @if (!empty($m->gambar))
                                        @php
                                            $img = (string) $m->gambar;
                                            $src = \Illuminate\Support\Str::startsWith($img, ['http://', 'https://', '/'])
                                                ? $img
                                                : \Illuminate\Support\Facades\Storage::url($img);
                                        @endphp
                                        <img alt="{{ $m->nama_menu }}" src="{{ $src }}" class="h-full w-full object-cover" />
                                    @else
                                        <div class="h-full w-full bg-linear-to-br from-neutral-100 to-neutral-200 dark:from-neutral-800 dark:to-neutral-900"></div>
                                    @endif
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div class="line-clamp-1 text-base font-semibold text-neutral-900 dark:text-white">
                                        {{ $m->nama_menu }}
                                    </div>
                                    @if (!empty($m->deskripsi))
                                        <div class="mt-1 line-clamp-2 text-xs leading-5 text-neutral-500 dark:text-neutral-400">
                                            {{ $m->deskripsi }}
                                        </div>
                                    @endif
                                    @if ($m->addons->isNotEmpty())
                                        <div class="mt-2">
                                            <span class="inline-flex items-center gap-1 rounded-full border border-neutral-200/70 bg-white/60 px-2 py-1 text-[11px] font-semibold text-neutral-600 shadow-sm backdrop-blur dark:border-neutral-800/70 dark:bg-neutral-950/20 dark:text-neutral-300">
                                                <span class="h-1.5 w-1.5 rounded-full bg-[var(--brand-primary)]"></span>
                                                {{ __('Add-ons') }}
                                            </span>
                                        </div>
                                    @endif
                                    <div class="mt-3 flex items-center justify-between gap-3">
                                        <div class="text-sm font-semibold text-neutral-900 dark:text-white">
                                            {{ $idr($m->harga) }}
                                        </div>

                                        <div class="shrink-0 flex w-[8.5rem] justify-end" data-menu-actions>
                                            <button
                                                type="button"
                                                class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-[var(--brand-primary)] text-white shadow-sm ring-1 ring-black/5 hover:bg-[var(--brand-primary-hover)] active:bg-[var(--brand-primary-active)] dark:text-black"
                                                data-action="add"
                                                aria-label="{{ __('Add') }}"
                                            >
                                                <span class="text-2xl font-bold">+</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="customer-card-depth rounded-3xl border border-neutral-200/70 bg-white/70 p-6 text-center text-sm text-neutral-600 shadow-sm backdrop-blur dark:border-neutral-800/70 dark:bg-neutral-900/40 dark:text-neutral-300">
                    {{ __('No menu items found.') }}
                </div>
            @endforelse

            <div id="searchEmpty" class="customer-card-depth hidden rounded-3xl border border-neutral-200/70 bg-white/70 p-6 text-center text-sm text-neutral-600 shadow-sm backdrop-blur dark:border-neutral-800/70 dark:bg-neutral-900/40 dark:text-neutral-300">
                {{ __('No results found.') }}
            </div>
        </section>
    </div>

    <!-- Cart bar -->
    <div id="cartBar" class="fixed inset-x-0 bottom-0 z-40 hidden">
        <div class="mx-auto w-full max-w-3xl px-4 pb-[max(env(safe-area-inset-bottom),0.75rem)] sm:px-6">
            <div class="customer-card-depth rounded-3xl border border-neutral-200/70 bg-white/80 p-3 shadow-lg backdrop-blur dark:border-neutral-800/70 dark:bg-neutral-950/70">
                <div class="flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-[var(--brand-accent)] text-white shadow-sm">
                        <svg viewBox="0 0 24 24" fill="none" class="h-6 w-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M6 6h15l-1.5 9h-12z"></path>
                            <path d="M6 6l-2-2"></path>
                            <circle cx="9" cy="20" r="1.5"></circle>
                            <circle cx="18" cy="20" r="1.5"></circle>
                        </svg>
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="text-[11px] font-semibold tracking-wide text-neutral-600 dark:text-neutral-300">
                            {{ __('TOTAL') }}
                        </div>
                        <div id="cartTotal" class="truncate text-base font-semibold text-neutral-900 dark:text-white">
                            Rp 0
                        </div>
                    </div>

                    <button
                        type="button"
                        id="checkoutBtn"
                        class="inline-flex h-12 items-center justify-center gap-2 rounded-2xl bg-[var(--brand-primary)] px-5 text-sm font-semibold text-[var(--brand-accent)] shadow-sm ring-1 ring-black/5 hover:bg-[var(--brand-primary-hover)] active:bg-[var(--brand-primary-active)]"
                    >
                        {{ __('Checkout') }}
                        <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5 12h14"></path>
                            <path d="M13 6l6 6-6 6"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Cart drawer -->
    <div id="cartDrawer" class="fixed inset-0 z-50 hidden">
        <button type="button" class="absolute inset-0 bg-black/30" data-close-cart aria-label="{{ __('Close') }}"></button>

        <div class="absolute inset-x-0 bottom-0">
            <div class="mx-auto w-full max-w-3xl px-4 sm:px-6">
                <div class="customer-card-depth rounded-t-3xl border border-neutral-200/70 bg-white/90 p-4 shadow-2xl backdrop-blur dark:border-neutral-800/70 dark:bg-neutral-950/85">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="text-lg font-semibold text-neutral-900 dark:text-white">{{ __('Your Cart') }}</div>
                            <div class="mt-1 text-sm text-neutral-600 dark:text-neutral-300">{{ __('Review your items before sending to kitchen.') }}</div>
                        </div>
                        <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-neutral-200/70 bg-white/60 text-neutral-700 shadow-sm backdrop-blur hover:bg-white/80 dark:border-neutral-800/70 dark:bg-neutral-900/40 dark:text-neutral-200 dark:hover:bg-neutral-900/60" data-close-cart aria-label="{{ __('Close') }}">
                            <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 6L6 18"></path>
                                <path d="M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>

                    <div id="cartItems" class="mt-4 max-h-[55dvh] space-y-3 overflow-y-auto pr-1 no-scrollbar"></div>

                    <div class="mt-4 flex items-center justify-between gap-3 border-t border-neutral-200/70 pt-4 dark:border-neutral-800/70">
                        <div class="min-w-0">
                            <div class="text-xs font-semibold tracking-wide text-neutral-600 dark:text-neutral-300">{{ __('TOTAL') }}</div>
                            <div id="cartTotalDrawer" class="truncate text-lg font-semibold text-neutral-900 dark:text-white">Rp 0</div>
                        </div>

                        <button
                            type="button"
                            id="drawerCheckoutBtn"
                            data-checkout-url="{{ route('customer.checkout', ['token' => $token]) }}"
                            class="inline-flex h-12 items-center justify-center gap-2 rounded-2xl bg-[var(--brand-primary)] px-5 text-sm font-semibold text-[var(--brand-accent)] shadow-sm ring-1 ring-black/5 hover:bg-[var(--brand-primary-hover)] active:bg-[var(--brand-primary-active)]"
                        >
                            {{ __('Confirm order') }}
                            <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M5 12h14"></path>
                                <path d="M13 6l6 6-6 6"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script id="menuCartJson" type="application/json">@json($menuCartIndex)</script>
    <script id="menuPricingJson" type="application/json">@json($menuPricingIndex)</script>

    <script>
        (() => {
            const root = document.querySelector('[data-table-token]')
            const token = root?.dataset.tableToken || ''

            const storageKey = token ? `customerCart:${token}` : 'customerCart'
            const cartBar = document.getElementById('cartBar')
            const cartDrawer = document.getElementById('cartDrawer')
            const cartItems = document.getElementById('cartItems')
            const cartTotalDrawer = document.getElementById('cartTotalDrawer')
            const cartTotal = document.getElementById('cartTotal')
            const checkoutBtn = document.getElementById('checkoutBtn')
            const drawerCheckoutBtn = document.getElementById('drawerCheckoutBtn')

            const cartMenuMap = (() => {
                try {
                    const raw = document.getElementById('menuCartJson')?.textContent || '[]'
                    const list = JSON.parse(raw)
                    const map = new Map()
                    for (const m of (Array.isArray(list) ? list : [])) {
                        const id = String(m.id ?? '')
                        if (!id) continue
                        map.set(id, {
                            id,
                            name: String(m.name || ''),
                            price: Number(m.price || 0),
                            addons: Array.isArray(m.addons) ? m.addons.map(a => ({
                                id: String(a.id ?? ''),
                                name: String(a.name || ''),
                                price: Number(a.price || 0),
                            })) : [],
                        })
                    }
                    return map
                } catch (e) {
                    return new Map()
                }
            })()

            const pricingMap = (() => {
                try {
                    const raw = document.getElementById('menuPricingJson')?.textContent || '[]'
                    const list = JSON.parse(raw)
                    const map = new Map()
                    for (const m of (Array.isArray(list) ? list : [])) {
                        const id = String(m.id ?? '')
                        if (!id) continue
                        map.set(id, {
                            price: Number(m.price || 0),
                            addons: Array.isArray(m.addons) ? m.addons.map(a => ({
                                id: String(a.id ?? ''),
                                price: Number(a.price || 0),
                            })) : [],
                        })
                    }
                    return map
                } catch (e) {
                    return new Map()
                }
            })()

            const formatIDR = (value) => {
                try {
                    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value)
                } catch (e) {
                    return `Rp ${Math.round(value).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.')}`
                }
            }

            const readCart = () => {
                try {
                    const raw =
                        sessionStorage.getItem(storageKey) ||
                        localStorage.getItem(storageKey)
                    if (!raw) return {}
                    const data = JSON.parse(raw)
                    if (!data || typeof data !== 'object') return {}

                    // Normalize legacy format: { [id]: number }
                    const normalized = {}
                    for (const [id, v] of Object.entries(data)) {
                        if (typeof v === 'number') {
                            normalized[id] = { qty: Math.max(v, 0), addons: [] }
                            continue
                        }
                        if (v && typeof v === 'object') {
                            const qty = Math.max(Number(v.qty || 0), 0)
                            const addons = Array.isArray(v.addons) ? v.addons.map(x => String(x)) : []
                            normalized[id] = { qty, addons }
                        }
                    }
                    return normalized
                } catch (e) {
                    return {}
                }
            }

            const writeCart = (cart) => {
                try {
                    const json = JSON.stringify(cart)
                    // Write both: sessionStorage is more reliable on some mobile browsers.
                    try { sessionStorage.setItem(storageKey, json) } catch (e) {}
                    try { localStorage.setItem(storageKey, json) } catch (e) {}
                } catch (e) {}
            }

            const getMenuIndex = () => {
                const cards = Array.from(document.querySelectorAll('[data-menu-card]'))
                const index = new Map()
                for (const card of cards) {
                    const id = String(card.dataset.menuId || '')
                    if (!id) continue
                    index.set(id, {
                        id,
                        name: card.dataset.menuName || '',
                        price: Number(card.dataset.menuPrice || 0),
                        card,
                    })
                }
                return index
            }

            const updateCardUI = (menu, qty) => {
                const host = menu.card.querySelector('[data-menu-actions]')
                if (!host) return

                // Selected state (border primary)
                menu.card.classList.toggle('customer-menu-card--selected', qty > 0)
                menu.card.classList.toggle('shadow-md', qty > 0)

                if (qty <= 0) {
                    host.innerHTML = `
                        <button type="button"
                            class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-[var(--brand-primary)] text-white shadow-sm ring-1 ring-black/5 hover:bg-[var(--brand-primary-hover)] active:bg-[var(--brand-primary-active)] dark:text-black"
                            data-action="add"
                            aria-label="{{ __('Add') }}">
                            <span class="text-2xl font-bold">+</span>
                        </button>
                    `
                    return
                }

                host.innerHTML = `
                    <div class="inline-flex h-11 w-full items-center justify-between gap-2 rounded-2xl border border-neutral-200/70 bg-neutral-100/80 px-2 shadow-sm dark:border-neutral-800/70 dark:bg-neutral-900/50">
                        <button type="button"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-neutral-200/70 bg-white/80 text-neutral-700 shadow-sm hover:bg-white dark:border-neutral-800/70 dark:bg-neutral-950/40 dark:text-neutral-200 dark:hover:bg-neutral-950/60"
                            data-action="dec"
                            aria-label="{{ __('Decrease') }}">
                            <span class="text-lg font-semibold">−</span>
                        </button>
                        <div class="min-w-[1.5rem] text-center text-sm font-semibold text-neutral-900 dark:text-white" data-qty>${qty}</div>
                        <button type="button"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-[var(--brand-primary)] text-white shadow-sm ring-1 ring-black/5 hover:bg-[var(--brand-primary-hover)] active:bg-[var(--brand-primary-active)] dark:text-black"
                            data-action="inc"
                            aria-label="{{ __('Increase') }}">
                            <span class="text-lg font-semibold">+</span>
                        </button>
                    </div>
                `
            }

            const recompute = () => {
                const cart = readCart()
                const menuIndex = getMenuIndex()

                let total = 0
                for (const [id, row] of Object.entries(cart)) {
                    const q = Math.max(Number(row?.qty || 0), 0)
                    const menu = menuIndex.get(String(id))
                    if (!menu || q <= 0) continue

                    const pricing = pricingMap.get(String(id))
                    const base = Number(pricing?.price ?? menu.price ?? 0)
                    const allowedAddons = Array.isArray(pricing?.addons) ? pricing.addons : []
                    const selected = new Set((row.addons || []).map(String))
                    const addonsTotal = allowedAddons
                        .filter(a => selected.has(String(a.id)))
                        .reduce((sum, a) => sum + Number(a.price || 0), 0)

                    total += q * (base + addonsTotal)
                }

                // Clean invalid entries
                for (const id of Object.keys(cart)) {
                    const row = cart[id]
                    const q = Math.max(Number(row?.qty || 0), 0)
                    if (q <= 0 || !menuIndex.has(String(id))) {
                        delete cart[id]
                        continue
                    }

                    const pricing = pricingMap.get(String(id))
                    const allowed = new Set((pricing?.addons || []).map(a => String(a.id)))
                    row.addons = (Array.isArray(row.addons) ? row.addons : [])
                        .map(String)
                        .filter(aId => allowed.has(aId))
                }
                writeCart(cart)

                for (const menu of menuIndex.values()) {
                    const qty = Math.max(Number(cart[menu.id]?.qty || 0), 0)
                    updateCardUI(menu, qty)
                }

                const hasItems = Object.keys(cart).length > 0
                if (cartBar) cartBar.classList.toggle('hidden', !hasItems)
                if (cartTotal) cartTotal.textContent = formatIDR(total)
                if (cartTotalDrawer) cartTotalDrawer.textContent = formatIDR(total)

                renderDrawer(cart)
            }

            const renderDrawer = (cart) => {
                if (!cartItems) return

                const ids = Object.keys(cart)
                if (!ids.length) {
                    cartItems.innerHTML = `
                        <div class="rounded-3xl border border-neutral-200/70 bg-white/70 p-6 text-center text-sm text-neutral-600 shadow-sm backdrop-blur dark:border-neutral-800/70 dark:bg-neutral-900/40 dark:text-neutral-300">
                            {{ __('Your cart is empty.') }}
                        </div>
                    `
                    return
                }

                cartItems.innerHTML = ids.map((id) => {
                    const row = cart[id] || { qty: 0, addons: [] }
                    const qty = Math.max(Number(row.qty || 0), 0)
                    const menu = cartMenuMap.get(String(id))
                    const name = menu?.name || 'Item'
                    const base = Number(menu?.price || 0)
                    const addons = Array.isArray(menu?.addons) ? menu.addons : []
                    const selected = new Set((row.addons || []).map(String))
                    const addonsTotal = addons
                        .filter(a => selected.has(String(a.id)))
                        .reduce((sum, a) => sum + Number(a.price || 0), 0)

                    const unit = base + addonsTotal
                    const line = qty * unit

                    const addonChips = !addons.length
                        ? `<div class="mt-3 text-xs text-neutral-500 dark:text-neutral-400">{{ __('No add-ons') }}</div>`
                        : `
                            <div class="mt-3 flex flex-wrap gap-2">
                                ${addons.map((a) => {
                                    const aid = String(a.id)
                                    const checked = selected.has(aid)
                                    const cls = checked
                                        ? 'border-[var(--brand-primary)] bg-[color-mix(in_srgb,var(--brand-primary)_12%,transparent)] text-neutral-900 dark:text-white'
                                        : 'border-neutral-200/70 bg-white/60 text-neutral-700 dark:border-neutral-800/70 dark:bg-neutral-950/20 dark:text-neutral-200'

                                    return `
                                        <label class="inline-flex cursor-pointer items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-semibold shadow-sm backdrop-blur ${cls}">
                                            <input type="checkbox" class="sr-only" data-addon-checkbox data-id="${id}" data-addon-id="${aid}" ${checked ? 'checked' : ''} />
                                            <span>${a.name}</span>
                                            <span class="font-semibold text-neutral-500 dark:text-neutral-400">(+${formatIDR(Number(a.price || 0))})</span>
                                        </label>
                                    `
                                }).join('')}
                            </div>
                        `

                    return `
                        <div class="rounded-3xl border border-neutral-200/70 bg-white/70 p-4 shadow-sm backdrop-blur dark:border-neutral-800/70 dark:bg-neutral-900/40">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="truncate text-sm font-semibold text-neutral-900 dark:text-white">${name}</div>
                                    <div class="mt-1 text-sm font-semibold text-neutral-900 dark:text-white">${formatIDR(line)}</div>
                                    <div class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">${formatIDR(unit)} × ${qty}</div>
                                    ${addonChips}
                                </div>
                                <div class="shrink-0">
                                    <div class="inline-flex items-center gap-2 rounded-2xl border border-neutral-200/70 bg-white/70 px-2 py-2 shadow-sm backdrop-blur dark:border-neutral-800/70 dark:bg-neutral-950/30">
                                        <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-xl border border-neutral-200/70 bg-white/60 text-neutral-700 hover:bg-white/80 dark:border-neutral-800/70 dark:bg-neutral-900/40 dark:text-neutral-200 dark:hover:bg-neutral-900/60" data-cart-action="dec" data-id="${id}">
                                            <span class="text-lg font-semibold">−</span>
                                        </button>
                                        <div class="min-w-[1.5rem] text-center text-sm font-semibold text-neutral-900 dark:text-white">${qty}</div>
                                        <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-[var(--brand-primary)] text-[var(--brand-accent)] shadow-sm ring-1 ring-black/5 hover:bg-[var(--brand-primary-hover)] active:bg-[var(--brand-primary-active)]" data-cart-action="inc" data-id="${id}">
                                            <span class="text-lg font-semibold">+</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `
                }).join('')
            }

            const setQty = (id, nextQty) => {
                const cart = readCart()
                const q = Math.max(Number(nextQty || 0), 0)
                if (!cart[id]) cart[id] = { qty: 0, addons: [] }
                cart[id].qty = q
                if (q <= 0) delete cart[id]
                writeCart(cart)
                recompute()
            }

            const toggleAddon = (menuId, addonId) => {
                const id = String(menuId || '')
                const aId = String(addonId || '')
                if (!id || !aId) return

                const cart = readCart()
                if (!cart[id]) cart[id] = { qty: 0, addons: [] }

                const current = new Set((cart[id].addons || []).map(String))
                if (current.has(aId)) current.delete(aId)
                else current.add(aId)

                cart[id].addons = Array.from(current.values())
                writeCart(cart)
                recompute()
            }

            document.addEventListener('click', (e) => {
                const btn = e.target.closest('[data-action]')
                if (btn) {
                    const card = btn.closest('[data-menu-card]')
                    const id = String(card?.dataset.menuId || '')
                    if (!id) return
                    const action = btn.dataset.action
                    const cart = readCart()
                    const current = Math.max(Number(cart[id]?.qty || 0), 0)
                    if (action === 'add' || action === 'inc') setQty(id, current + 1)
                    if (action === 'dec') setQty(id, current - 1)
                    return
                }

                const cartBtn = e.target.closest('[data-cart-action]')
                if (cartBtn) {
                    const id = String(cartBtn.dataset.id || '')
                    const action = cartBtn.dataset.cartAction
                    const cart = readCart()
                    const current = Math.max(Number(cart[id]?.qty || 0), 0)
                    if (action === 'inc') setQty(id, current + 1)
                    if (action === 'dec') setQty(id, current - 1)
                    return
                }

                if (e.target.closest('[data-close-cart]')) {
                    cartDrawer?.classList.add('hidden')
                    document.body.style.overflow = ''
                    return
                }
            })

            document.addEventListener('change', (e) => {
                const checkbox = e.target.closest('[data-addon-checkbox]')
                if (!checkbox) return
                const id = String(checkbox.dataset.id || '')
                const aId = String(checkbox.dataset.addonId || '')
                toggleAddon(id, aId)
            })

            checkoutBtn?.addEventListener('click', () => {
                cartDrawer?.classList.remove('hidden')
                document.body.style.overflow = 'hidden'
                recompute()
            })

            drawerCheckoutBtn?.addEventListener('click', async () => {
                const checkoutUrl = drawerCheckoutBtn.getAttribute('data-checkout-url') || ''
                if (!checkoutUrl) return

                window.location.href = checkoutUrl
            })

            // Init cart UI
            recompute()

            // Search (debounced 2s)
            const searchToggle = document.getElementById('searchToggle')
            const searchBar = document.getElementById('searchBar')
            const searchInput = document.getElementById('searchInput')
            const searchClear = document.getElementById('searchClear')
            const searchHint = document.getElementById('searchHint')
            const searchEmpty = document.getElementById('searchEmpty')

            let searchTimer = null

            const normalize = (s) => String(s || '').toLowerCase().trim()

            const applySearch = (raw) => {
                const q = normalize(raw)
                const cards = Array.from(document.querySelectorAll('[data-menu-card]'))
                const sections = Array.from(document.querySelectorAll('[data-cat-section]')).filter(s => s.getAttribute('id') !== 'all')

                let anyVisible = false
                for (const card of cards) {
                    const text = normalize(card.dataset.menuSearch || card.dataset.menuName || '')
                    const match = q === '' ? true : text.includes(q)
                    card.classList.toggle('hidden', !match)
                    if (match) anyVisible = true
                }

                // Hide category sections that have no visible cards
                for (const section of sections) {
                    const visibleCards = section.querySelectorAll('[data-menu-card]:not(.hidden)')
                    section.classList.toggle('hidden', q !== '' && visibleCards.length === 0)
                }

                if (searchEmpty) searchEmpty.classList.toggle('hidden', q === '' || anyVisible)
                if (searchHint) {
                    searchHint.textContent = ''
                }
            }

            const scheduleSearch = (value) => {
                if (searchTimer) clearTimeout(searchTimer)
                const q = normalize(value)
                if (searchHint) searchHint.textContent = q === '' ? '' : '{{ __('Searching...') }}'
                searchTimer = setTimeout(() => {
                    applySearch(value)
                    if (searchHint) searchHint.textContent = ''
                }, 2000)
            }

            searchToggle?.addEventListener('click', () => {
                const isHidden = searchBar?.classList.contains('hidden')
                searchBar?.classList.toggle('hidden', !isHidden)
                if (isHidden) {
                    setTimeout(() => searchInput?.focus(), 0)
                } else {
                    // Closing search resets filter
                    if (searchInput) searchInput.value = ''
                    applySearch('')
                }
            })

            searchInput?.addEventListener('input', (e) => {
                scheduleSearch(e.target.value)
            })

            searchClear?.addEventListener('click', () => {
                if (searchInput) searchInput.value = ''
                applySearch('')
                searchInput?.focus()
            })

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && searchBar && !searchBar.classList.contains('hidden')) {
                    searchBar.classList.add('hidden')
                    if (searchInput) searchInput.value = ''
                    applySearch('')
                }
            })

            // If redirected from /{token}/cart
            try {
                const params = new URLSearchParams(window.location.search)
                if (params.get('cart') === '1') {
                    cartDrawer?.classList.remove('hidden')
                    document.body.style.overflow = 'hidden'
                    recompute()
                }
            } catch (e) {}

            const tabs = document.querySelector('[data-category-tabs]')
            if (!tabs) return

            const links = Array.from(tabs.querySelectorAll('a[data-cat-link]'))
            const sections = Array.from(document.querySelectorAll('[data-cat-section]'))
            if (!links.length || !sections.length) return

            const tabKey = token ? `customerLastTab:${token}` : 'customerLastTab'

            const readTab = () => {
                try {
                    return sessionStorage.getItem(tabKey) || localStorage.getItem(tabKey) || ''
                } catch (e) {
                    return ''
                }
            }

            const writeTab = (value) => {
                try { sessionStorage.setItem(tabKey, value) } catch (e) {}
                try { localStorage.setItem(tabKey, value) } catch (e) {}
            }

            const categorySections = sections.filter((s) => (s.getAttribute('id') || '') !== 'all')
            let currentCategory = 'all'

            const activeClasses = [
                'border-[color-mix(in_srgb,var(--brand-primary)_45%,transparent)]',
                'bg-[color-mix(in_srgb,var(--brand-primary)_16%,transparent)]',
                'font-extrabold',
                'text-neutral-900',
                'shadow-sm',
                'dark:text-white',
            ]
            const inactiveClasses = [
                'border-transparent',
                'bg-transparent',
                'font-semibold',
                'text-neutral-600',
                'dark:text-neutral-300',
            ]

            const setActive = (key, { persist = true } = {}) => {
                for (const link of links) {
                    const isActive = link.dataset.catLink === key
                    for (const c of activeClasses) link.classList.toggle(c, isActive)
                    for (const c of inactiveClasses) link.classList.toggle(c, !isActive)
                    link.classList.toggle('hover:bg-neutral-100/70', !isActive)
                    link.classList.toggle('hover:text-neutral-900', !isActive)
                    link.classList.toggle('dark:hover:bg-neutral-900/40', !isActive)
                    link.classList.toggle('dark:hover:text-white', !isActive)
                }

                const activeLink = links.find(l => l.dataset.catLink === key)
                if (activeLink) {
                    activeLink.scrollIntoView({ block: 'nearest', inline: 'center', behavior: 'smooth' })
                }

                if (persist) writeTab(key)
            }

            const categoryTitle = document.getElementById('categoryTitle')

            const applyCategoryFilter = (key, { scroll = true } = {}) => {
                currentCategory = key || 'all'

                for (const section of categorySections) {
                    const id = section.getAttribute('id') || ''
                    const show = currentCategory === 'all' ? true : id === currentCategory
                    section.classList.toggle('hidden', !show)

                    const heading = section.querySelector('[data-cat-heading]')
                    if (heading) heading.classList.toggle('hidden', currentCategory !== 'all')
                }

                if (categoryTitle) {
                    if (currentCategory === 'all') {
                        categoryTitle.textContent = categoryTitle.getAttribute('data-all-label') || ''
                    } else {
                        const activeLink = links.find(l => l.dataset.catLink === currentCategory)
                        const label = (activeLink?.textContent || '').trim()
                        if (label) categoryTitle.textContent = label
                    }
                }

                // Reset any search-empty message when switching categories
                if (searchEmpty) searchEmpty.classList.add('hidden')
                if (scroll) window.scrollTo({ top: 0, behavior: 'smooth' })
            }

            // Smooth scroll on click
            for (const link of links) {
                link.addEventListener('click', (e) => {
                    const href = link.getAttribute('href') || ''
                    if (!href.startsWith('#')) return
                    const target = document.querySelector(href)
                    if (!target) return
                    e.preventDefault()
                    const key = String(link.dataset.catLink || 'all')
                    history.replaceState(null, '', href)
                    setActive(key)
                    applyCategoryFilter(key)
                })
            }

            const byId = new Map(sections.map(s => [s.getAttribute('id'), s]))

            const pickFromHash = () => {
                const hash = (location.hash || '').replace('#', '')
                const stored = readTab()
                const initial = (hash && byId.has(hash)) ? hash : (stored && byId.has(stored) ? stored : 'all')
                setActive(initial, { persist: false })
                applyCategoryFilter(initial, { scroll: false })
            }

            pickFromHash()
        })()
    </script>
</x-layouts.customer>
