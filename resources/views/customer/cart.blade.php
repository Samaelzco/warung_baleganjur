<x-layouts.customer :title="__('Your Cart')">
    @php
        $idr = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');

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
    @endphp

    <div class="space-y-6" data-table-token="{{ $token }}">
        <header class="space-y-4">
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
                            {{ __('Your Cart') }}
                        </div>
                        <div class="mt-1 flex items-center gap-2 text-xs font-medium tracking-wide text-neutral-600 dark:text-neutral-300">
                            <span class="h-1.5 w-1.5 rounded-full bg-[var(--brand-primary)]"></span>
                            <span>{{ __('Table') }} {{ $meja->nomor_meja }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <section class="space-y-3">
            <div id="cartItems" class="space-y-3"></div>
        </section>
    </div>

    <script id="menuIndexJson" type="application/json">@json($menuIndex)</script>

    <!-- Checkout bar -->
    <div id="cartFooter" class="fixed inset-x-0 bottom-0 z-40 hidden">
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
                            {{ $idr(0) }}
                        </div>
                    </div>

                    <button
                        type="button"
                        id="confirmOrderBtn"
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

    <!-- Name modal -->
    <div id="nameModal" class="fixed inset-0 z-50 hidden">
        <button type="button" class="absolute inset-0 bg-black/30" data-close-name aria-label="{{ __('Close') }}"></button>

        <div class="absolute inset-x-0 bottom-0">
            <div class="mx-auto w-full max-w-3xl px-4 sm:px-6">
                <div class="rounded-t-3xl border border-neutral-200/70 bg-white/90 p-4 shadow-2xl backdrop-blur dark:border-neutral-800/70 dark:bg-neutral-950/85">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="text-lg font-semibold text-neutral-900 dark:text-white">{{ __('Confirm order') }}</div>
                            <div class="mt-1 text-sm text-neutral-600 dark:text-neutral-300">{{ __('Enter your name for this order.') }}</div>
                        </div>
                        <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-neutral-200/70 bg-white/60 text-neutral-700 shadow-sm backdrop-blur hover:bg-white/80 dark:border-neutral-800/70 dark:bg-neutral-900/40 dark:text-neutral-200 dark:hover:bg-neutral-900/60" data-close-name aria-label="{{ __('Close') }}">
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
                                class="mt-2 w-full rounded-2xl border border-neutral-200/70 bg-white/70 px-4 py-3 text-sm text-neutral-900 shadow-sm outline-none ring-0 placeholder:text-neutral-400 focus:border-[var(--brand-primary)] focus:ring-4 focus:ring-[color:var(--brand-primary)]/15 dark:border-neutral-800/70 dark:bg-neutral-950/30 dark:text-white dark:placeholder:text-neutral-500"
                                placeholder="{{ __('Your name') }}"
                                autocomplete="name"
                            />
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-1">
                            <button type="button" class="inline-flex h-11 items-center justify-center rounded-2xl border border-neutral-200/70 bg-white/60 px-4 text-sm font-semibold text-neutral-700 shadow-sm backdrop-blur hover:bg-white/80 dark:border-neutral-800/70 dark:bg-neutral-900/40 dark:text-neutral-200 dark:hover:bg-neutral-900/60" data-close-name>
                                {{ __('Cancel') }}
                            </button>
                            <button type="button" id="saveNameBtn" class="inline-flex h-11 items-center justify-center rounded-2xl bg-[var(--brand-primary)] px-4 text-sm font-semibold text-[var(--brand-accent)] shadow-sm ring-1 ring-black/5 hover:bg-[var(--brand-primary-hover)] active:bg-[var(--brand-primary-active)]">
                                {{ __('Confirm') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        ;(() => {
            const root = document.querySelector('[data-table-token]')
            if (!root) return

            const token = root.getAttribute('data-table-token') || ''
            const storageKey = token ? `customerCart:${token}` : 'customerCart'
            const nameKey = token ? `customerName:${token}` : 'customerName'

            const cartItems = document.getElementById('cartItems')
            const cartFooter = document.getElementById('cartFooter')
            const cartTotal = document.getElementById('cartTotal')
            const confirmOrderBtn = document.getElementById('confirmOrderBtn')

            const nameModal = document.getElementById('nameModal')
            const customerName = document.getElementById('customerName')
            const saveNameBtn = document.getElementById('saveNameBtn')

            const formatIDR = (value) => {
                try {
                    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value)
                } catch (e) {
                    return `Rp ${Math.round(value).toString().replace(/\\B(?=(\\d{3})+(?!\\d))/g, '.')}`
                }
            }

            const readCart = () => {
                try {
                    const raw = localStorage.getItem(storageKey)
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
                    localStorage.setItem(storageKey, JSON.stringify(cart))
                } catch (e) {}
            }

            const menuIndex = (() => {
                try {
                    const raw = document.getElementById('menuIndexJson')?.textContent || '[]'
                    const list = JSON.parse(raw)
                    const map = new Map()
                    for (const m of (Array.isArray(list) ? list : [])) {
                        const id = String(m.id ?? '')
                        if (!id) continue
                        map.set(id, {
                            id,
                            name: String(m.name || ''),
                            price: Number(m.price || 0),
                            image: String(m.image || ''),
                        })
                    }
                    return map
                } catch (e) {
                    return new Map()
                }
            })()

            const setQty = (id, nextQty) => {
                const cart = readCart()
                const q = Math.max(Number(nextQty || 0), 0)
                if (!cart[id]) cart[id] = { qty: 0, addons: [] }
                cart[id].qty = q
                if (q <= 0) delete cart[id]
                writeCart(cart)
                render()
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
                render()
            }

            const render = () => {
                const cart = readCart()

                // Clean invalid entries (menu not found)
                for (const id of Object.keys(cart)) {
                    const row = cart[id]
                    const q = Math.max(Number(row?.qty || 0), 0)
                    if (q <= 0 || !menuIndex.has(String(id))) {
                        delete cart[id]
                        continue
                    }

                    // Remove invalid addons
                    const menu = menuIndex.get(String(id))
                    const allowed = new Set((menu?.addons || []).map(a => String(a.id)))
                    row.addons = (Array.isArray(row.addons) ? row.addons : [])
                        .map(String)
                        .filter(aId => allowed.has(aId))
                }
                writeCart(cart)

                const ids = Object.keys(cart)
                const hasItems = ids.length > 0

                if (cartFooter) cartFooter.classList.toggle('hidden', !hasItems)

                if (!hasItems) {
                    cartItems.innerHTML = `
                        <div class="customer-card-depth rounded-3xl border border-neutral-200/70 bg-white/70 p-6 text-center text-sm text-neutral-600 shadow-sm backdrop-blur dark:border-neutral-800/70 dark:bg-neutral-900/40 dark:text-neutral-300">
                            {{ __('Your cart is empty.') }}
                            <div class="mt-4">
                                <a href="{{ route('customer.order', ['token' => $token]) }}" class="inline-flex h-11 items-center justify-center rounded-2xl bg-[var(--brand-primary)] px-5 text-sm font-semibold text-[var(--brand-accent)] shadow-sm ring-1 ring-black/5 hover:bg-[var(--brand-primary-hover)] active:bg-[var(--brand-primary-active)]">
                                    {{ __('Back to menu') }}
                                </a>
                            </div>
                        </div>
                    `
                    if (cartTotal) cartTotal.textContent = formatIDR(0)
                    return
                }

                let total = 0
                cartItems.innerHTML = ids.map((id) => {
                    const row = cart[id] || { qty: 0, addons: [] }
                    const qty = Math.max(Number(row.qty || 0), 0)
                    const menu = menuIndex.get(String(id))
                    const name = menu?.name || 'Item'
                    const price = Number(menu?.price || 0)
                    const image = menu?.image || ''
                    const addons = Array.isArray(menu?.addons) ? menu.addons : []
                    const selected = new Set((row.addons || []).map(String))
                    const addonsTotal = addons
                        .filter(a => selected.has(String(a.id)))
                        .reduce((sum, a) => sum + Number(a.price || 0), 0)

                    const unit = price + addonsTotal
                    const line = qty * unit
                    total += line

                    const img = image
                        ? `<img src="${image}" alt="" class="h-20 w-24 rounded-2xl object-cover ring-1 ring-black/5 dark:ring-white/10" loading="lazy" />`
                        : `<div class="h-20 w-24 rounded-2xl bg-neutral-100 ring-1 ring-black/5 dark:bg-neutral-800 dark:ring-white/10"></div>`

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
                        <div class="customer-card-depth rounded-3xl border border-neutral-200/70 bg-white/70 p-4 shadow-sm backdrop-blur dark:border-neutral-800/70 dark:bg-neutral-900/40">
                            <div class="flex items-start gap-4">
                                ${img}
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm font-semibold text-neutral-900 dark:text-white">${name}</div>
                                    <div class="mt-1 text-sm font-semibold text-neutral-900 dark:text-white">${formatIDR(line)}</div>
                                    <div class="mt-3 flex items-center justify-between gap-3">
                                        <div class="text-xs text-neutral-500 dark:text-neutral-400">${formatIDR(unit)} × ${qty}</div>
                                        <div class="inline-flex items-center gap-2 rounded-2xl border border-neutral-200/70 bg-white/70 px-2 py-2 shadow-sm backdrop-blur dark:border-neutral-800/70 dark:bg-neutral-950/30">
                                            <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-xl border border-neutral-200/70 bg-white/60 text-neutral-700 hover:bg-white/80 dark:border-neutral-800/70 dark:bg-neutral-900/40 dark:text-neutral-200 dark:hover:bg-neutral-900/60" data-action="dec" data-id="${id}">
                                                <span class="text-lg font-semibold">−</span>
                                            </button>
                                            <div class="min-w-[1.5rem] text-center text-sm font-semibold text-neutral-900 dark:text-white">${qty}</div>
                                            <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-[var(--brand-primary)] text-[var(--brand-accent)] shadow-sm ring-1 ring-black/5 hover:bg-[var(--brand-primary-hover)] active:bg-[var(--brand-primary-active)]" data-action="inc" data-id="${id}">
                                                <span class="text-lg font-semibold">+</span>
                                            </button>
                                        </div>
                                    </div>
                                    ${addonChips}
                                </div>
                            </div>
                        </div>
                    `
                }).join('')

                if (cartTotal) cartTotal.textContent = formatIDR(total)
            }

            const openNameModal = () => {
                nameModal?.classList.remove('hidden')
                document.body.style.overflow = 'hidden'
                try {
                    customerName.value = localStorage.getItem(nameKey) || ''
                } catch (e) {}
                setTimeout(() => customerName?.focus(), 50)
            }

            confirmOrderBtn?.addEventListener('click', () => {
                openNameModal()
            })

            saveNameBtn?.addEventListener('click', () => {
                const name = (customerName?.value || '').trim()
                try {
                    localStorage.setItem(nameKey, name)
                } catch (e) {}

                nameModal?.classList.add('hidden')
                document.body.style.overflow = ''
            })

            document.addEventListener('click', (e) => {
                const btn = e.target.closest('[data-action]')
                if (btn) {
                    const id = String(btn.dataset.id || '')
                    if (!id) return
                    const action = btn.dataset.action
                    const cart = readCart()
                    const current = Number(cart[id] || 0)
                    if (action === 'inc') setQty(id, current + 1)
                    if (action === 'dec') setQty(id, current - 1)
                    return
                }

                if (e.target.closest('[data-close-name]')) {
                    nameModal?.classList.add('hidden')
                    document.body.style.overflow = ''
                }
            })

            document.addEventListener('change', (e) => {
                const checkbox = e.target.closest('[data-addon-checkbox]')
                if (!checkbox) return
                const id = String(checkbox.dataset.id || '')
                const aId = String(checkbox.dataset.addonId || '')
                toggleAddon(id, aId)
            })

            customerName?.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault()
                    saveNameBtn?.click()
                }
            })

            render()
        })()
    </script>
</x-layouts.customer>
