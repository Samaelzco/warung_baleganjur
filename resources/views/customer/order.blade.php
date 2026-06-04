<x-layouts.customer :title="__('Order')">
    @php
        $status = (string) ($order?->status ?? '');
        $idr = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');

        $menuPricingIndex = $menus
            ->map(fn ($m) => [
                'id' => (int) $m->id,
                'available' => $m->status === 'tersedia',
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
                'available' => $m->status === 'tersedia',
                'name' => (string) $m->nama_menu_localized,
                'price' => (float) $m->harga,
                'addons' => $m->addons
                    ->map(fn ($a) => [
                        'id' => (int) $a->id,
                        'name' => (string) $a->nama_addon_localized,
                        'price' => (float) $a->harga,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    @endphp

    <div
        class="space-y-6"
        data-table-token="{{ $token }}"
        data-add-mode="{{ !empty($addMode) ? '1' : '0' }}"
        data-edit-mode="{{ !empty($editMode) ? '1' : '0' }}"
        data-menu-updates-url="{{ $menuUpdatesUrl ?? '' }}"
        data-menu-version="{{ $menuVersion ?? 1 }}"
    >
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

            <div class="-mx-4 overflow-x-auto px-4 pt-2 no-scrollbar category-tabs-scroll">
                <nav class="flex w-max min-w-full snap-x snap-mandatory gap-2 pb-2 text-sm scroll-px-4" data-category-tabs aria-label="{{ __('Menu categories') }}">
                    <a
                        href="#all"
                        data-cat-link="all"
                        class="snap-center shrink-0 whitespace-nowrap rounded-full border border-[color-mix(in_srgb,var(--brand-primary)_45%,transparent)] bg-[color-mix(in_srgb,var(--brand-primary)_16%,transparent)] px-4 py-2 font-extrabold text-neutral-900 shadow-sm dark:text-white"
                    >
                    {{ __('All Items') }}
                    </a>
                    @foreach ($categories as $c)
                        <a
                            href="#cat-{{ $c->id }}"
                            data-cat-link="cat-{{ $c->id }}"
                            class="snap-center shrink-0 whitespace-nowrap rounded-full border border-transparent px-4 py-2 font-semibold text-neutral-600 hover:bg-neutral-100/70 hover:text-neutral-900 dark:text-neutral-300 dark:hover:bg-neutral-900/40 dark:hover:text-white"
                        >
                            {{ $c->nama_kategori_localized }}
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
                    $catName = $cat?->nama_kategori_localized ?? __('All Items');
                @endphp

                <div id="cat-{{ (int) $kategoriId }}" data-cat-section="cat-{{ (int) $kategoriId }}" class="scroll-mt-24 space-y-3">
                    <div data-cat-heading class="text-sm font-semibold text-neutral-700 dark:text-neutral-200">
                        {{ $catName }}
                    </div>

                            <div class="-mx-2 space-y-3">
                        @foreach ($rows as $m)
                            @php
                                $menuName = (string) $m->nama_menu_localized;
                                $menuDesc = (string) ($m->deskripsi_localized ?? '');
                                $isAvailable = $m->status === 'tersedia';
                            @endphp
                            <div
                                class="customer-card-depth flex items-start gap-4 rounded-3xl border border-neutral-200/70 bg-white/70 p-4 shadow-sm backdrop-blur transition-colors dark:border-neutral-800/70 dark:bg-neutral-900/40 {{ $isAvailable ? '' : 'opacity-75' }}"
                                data-menu-group="cat-{{ (int) $kategoriId }}"
                                data-menu-card
                                data-menu-id="{{ $m->id }}"
                                data-menu-available="{{ $isAvailable ? '1' : '0' }}"
                                data-menu-name="{{ $menuName }}"
                                data-menu-price="{{ (float) $m->harga }}"
                                data-menu-search="{{ \Illuminate\Support\Str::lower(trim($menuName . ' ' . $menuDesc)) }}"
                            >
                                <div class="relative h-20 w-20 shrink-0 overflow-hidden rounded-2xl bg-neutral-100 ring-1 ring-black/5 dark:bg-neutral-800 dark:ring-white/10">
                                    @if (!empty($m->gambar))
                                        @php
                                            $img = (string) $m->gambar;
                                            $isExternal = \Illuminate\Support\Str::startsWith($img, ['http://', 'https://', '/']);
                                            $src = $isExternal ? $img : \Illuminate\Support\Facades\Storage::url($img);

                                            $thumb160 = null;
                                            $thumb320 = null;

                                            if (!$isExternal) {
                                                $thumbPaths = \App\Services\MenuImageService::thumbnailPaths($img, [160, 320, 480, 640]);
                                                $thumb160 = \Illuminate\Support\Facades\Storage::url($thumbPaths[160] ?? '');
                                                $thumb320 = \Illuminate\Support\Facades\Storage::url($thumbPaths[320] ?? '');
                                                $thumb480 = \Illuminate\Support\Facades\Storage::url($thumbPaths[480] ?? '');
                                                $thumb640 = \Illuminate\Support\Facades\Storage::url($thumbPaths[640] ?? '');
                                            }
                                        @endphp
                                        <img
                                            alt="{{ $menuName }}"
                                            src="{{ $thumb160 ?: $src }}"
                                            @if ($thumb160 && $thumb320 && $thumb480 && $thumb640)
                                                srcset="{{ $thumb160 }} 160w, {{ $thumb320 }} 320w, {{ $thumb480 }} 480w, {{ $thumb640 }} 640w"
                                                sizes="80px"
                                            @endif
                                            class="{{ $isAvailable ? 'h-full w-full object-cover' : 'h-full w-full object-cover grayscale opacity-60' }}"
                                            data-menu-image-media
                                            width="80"
                                            height="80"
                                            loading="lazy"
                                            decoding="async"
                                            fetchpriority="low"
                                        />
                                    @else
                                        <div data-menu-image-media class="h-full w-full bg-linear-to-br from-neutral-100 to-neutral-200 dark:from-neutral-800 dark:to-neutral-900 {{ $isAvailable ? '' : 'grayscale opacity-60' }}"></div>
                                    @endif
                                    <div data-menu-out-overlay class="{{ $isAvailable ? 'hidden' : '' }} absolute inset-0 bg-white/25 dark:bg-black/20"></div>
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="line-clamp-1 text-base font-semibold text-neutral-900 dark:text-white">
                                            {{ $menuName }}
                                        </div>
                                        <span data-menu-out-badge class="{{ $isAvailable ? 'hidden' : '' }} shrink-0 rounded-full bg-red-50 px-2 py-1 text-[10px] font-extrabold uppercase tracking-wide text-red-700 ring-1 ring-red-100 dark:bg-red-900/30 dark:text-red-200 dark:ring-red-800/60">
                                            {{ __('Out of stock') }}
                                        </span>
                                    </div>
                                    @if (!empty($menuDesc))
                                        <div class="mt-1 line-clamp-2 text-xs leading-5 text-neutral-500 dark:text-neutral-400">
                                            {{ $menuDesc }}
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
                                        <div class="min-w-0">
                                            <div class="text-sm font-semibold text-neutral-900 dark:text-white">
                                                {{ $idr($m->harga) }}
                                            </div>
                                            @if ($m->addons->isNotEmpty())
                                                <button
                                                    type="button"
                                                    class="mt-1 hidden items-center gap-1 text-xs font-semibold text-[var(--brand-primary)] hover:opacity-90"
                                                    data-action="customize"
                                                    aria-label="{{ __('Customize') }}"
                                                    @disabled(! $isAvailable)
                                                >
                                                    {{ __('Customize') }}
                                                </button>
                                            @endif
                                        </div>

                                        <div class="shrink-0 flex w-[8.5rem] justify-end" data-menu-actions>
                                            @if ($isAvailable)
                                                <button
                                                    type="button"
                                                    class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-[var(--brand-primary)] text-white shadow-sm ring-1 ring-black/5 hover:bg-[var(--brand-primary-hover)] active:bg-[var(--brand-primary-active)] dark:text-black"
                                                    data-action="add"
                                                    aria-label="{{ __('Add') }}"
                                                >
                                                    <span class="text-2xl font-bold">+</span>
                                                </button>
                                            @else
                                                <button
                                                    type="button"
                                                    class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-neutral-200/70 bg-neutral-100/80 text-neutral-400 shadow-sm dark:border-neutral-800/70 dark:bg-neutral-900/50 dark:text-neutral-500"
                                                    disabled
                                                    aria-label="{{ __('Out of stock') }}"
                                                >
                                                    <span class="text-2xl font-bold">+</span>
                                                </button>
                                            @endif
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
    <div id="cartDrawer" class="fixed inset-0 z-50 hidden opacity-0 transition-opacity duration-200 ease-out motion-reduce:transition-none">
        <button type="button" class="absolute inset-0 bg-black/30" data-close-cart aria-label="{{ __('Close') }}"></button>

        <div data-cart-panel class="absolute inset-x-0 bottom-0 translate-y-6 transition-transform duration-200 ease-out motion-reduce:translate-y-0 motion-reduce:transition-none">
            <div class="mx-auto w-full max-w-3xl px-4 sm:px-6">
                <div class="customer-card-depth rounded-t-3xl border border-neutral-200/70 bg-white/90 p-4 shadow-2xl backdrop-blur dark:border-neutral-800/70 dark:bg-neutral-950/85">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="text-lg font-semibold text-neutral-900 dark:text-white">{{ __('Your Cart') }}</div>
                            <div class="mt-1 text-sm text-neutral-600 dark:text-neutral-300">{{ __('Review your items before sending to kitchen.') }}</div>
                        </div>
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
                            data-checkout-url="{{ $checkoutUrl ?? route('customer.checkout', ['token' => $token] + (!empty($addMode) ? ['add' => 1] : [])) }}"
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

    <!-- Customize sheet (per individual item) -->
    <div id="customizeSheet" class="fixed inset-0 z-50 hidden opacity-0 transition-opacity duration-200 ease-out motion-reduce:transition-none">
        <button type="button" class="absolute inset-0 bg-black/30" data-close-customize aria-label="{{ __('Close') }}"></button>

        <div data-customize-panel class="absolute inset-x-0 bottom-0 translate-y-6 transition-transform duration-200 ease-out motion-reduce:translate-y-0 motion-reduce:transition-none">
            <div class="mx-auto w-full max-w-3xl px-4 sm:px-6">
                <div class="customer-card-depth rounded-t-3xl border border-neutral-200/70 bg-white/90 p-4 shadow-2xl backdrop-blur dark:border-neutral-800/70 dark:bg-neutral-950/85">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div id="customizeTitle" class="text-lg font-semibold text-neutral-900 dark:text-white">{{ __('Customize') }}</div>
                            <div class="mt-1 text-sm text-neutral-600 dark:text-neutral-300">{{ __('Select add-ons for each item.') }}</div>
                        </div>
                    </div>

                    <div id="customizeList" class="mt-4 max-h-[55dvh] space-y-3 overflow-y-auto pr-1 no-scrollbar"></div>

                    <div class="mt-4 flex items-center justify-end gap-2 border-t border-neutral-200/70 pt-4 dark:border-neutral-800/70">
                        <button type="button" class="inline-flex h-11 items-center justify-center rounded-2xl bg-[var(--brand-primary)] px-5 text-sm font-semibold text-[var(--brand-accent)] shadow-sm ring-1 ring-black/5 hover:bg-[var(--brand-primary-hover)] active:bg-[var(--brand-primary-active)]" data-close-customize>
                            {{ __('Done') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script id="menuCartJson" type="application/json">@json($menuCartIndex)</script>
    <script id="menuPricingJson" type="application/json">@json($menuPricingIndex)</script>
    <script id="baselineCartJson" type="application/json">@json($baselineCart ?? [])</script>
    <script id="baselineMinQtyJson" type="application/json">@json($baselineMinQty ?? [])</script>
    <script id="prefillCartJson" type="application/json">@json($prefillCart ?? [])</script>

    <script>
        (() => {
            const root = document.querySelector('[data-table-token]')
            const token = root?.dataset.tableToken || ''
            const addMode = root?.dataset.addMode === '1'
            const editMode = root?.dataset.editMode === '1'
            const menuUpdatesUrl = root?.dataset.menuUpdatesUrl || ''
            let menuVersion = Number(root?.dataset.menuVersion || 1)

            const storageKey = token ? `customerCart:${token}` : 'customerCart'
            const cartBar = document.getElementById('cartBar')
            const cartDrawer = document.getElementById('cartDrawer')
            const cartPanel = cartDrawer?.querySelector('[data-cart-panel]')
            const cartItems = document.getElementById('cartItems')
            const cartTotalDrawer = document.getElementById('cartTotalDrawer')
            const cartTotal = document.getElementById('cartTotal')
            const checkoutBtn = document.getElementById('checkoutBtn')
            const drawerCheckoutBtn = document.getElementById('drawerCheckoutBtn')
            const customizeSheet = document.getElementById('customizeSheet')
            const customizePanel = customizeSheet?.querySelector('[data-customize-panel]')
            const customizeTitle = document.getElementById('customizeTitle')
            const customizeList = document.getElementById('customizeList')
            const cartTransitionMs = 220
            let cartCloseTimer = null
            let customizeCloseTimer = null

            const openCartDrawer = () => {
                if (!cartDrawer) return

                if (cartCloseTimer) {
                    window.clearTimeout(cartCloseTimer)
                    cartCloseTimer = null
                }

                cartDrawer.classList.remove('hidden')
                document.body.style.overflow = 'hidden'

                window.requestAnimationFrame(() => {
                    cartDrawer.classList.remove('opacity-0')
                    cartDrawer.classList.add('opacity-100')
                    cartPanel?.classList.remove('translate-y-6')
                    cartPanel?.classList.add('translate-y-0')
                })
            }

            const closeCartDrawer = () => {
                if (!cartDrawer) return

                cartDrawer.classList.remove('opacity-100')
                cartDrawer.classList.add('opacity-0')
                cartPanel?.classList.remove('translate-y-0')
                cartPanel?.classList.add('translate-y-6')
                document.body.style.overflow = ''

                cartCloseTimer = window.setTimeout(() => {
                    if (cartDrawer.classList.contains('opacity-0')) {
                        cartDrawer.classList.add('hidden')
                    }
                }, cartTransitionMs)
            }

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
                            available: m.available !== false,
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

            let customizeMenuId = null
            let customizeUnits = []
            let customizeLockedCount = 0

            const closeCustomize = () => {
                if (!customizeSheet) return

                customizeMenuId = null
                customizeUnits = []
                customizeLockedCount = 0
                customizeSheet.classList.remove('opacity-100')
                customizeSheet.classList.add('opacity-0')
                customizePanel?.classList.remove('translate-y-0')
                customizePanel?.classList.add('translate-y-6')
                document.body.style.overflow = ''

                customizeCloseTimer = window.setTimeout(() => {
                    if (customizeSheet.classList.contains('opacity-0')) {
                        customizeSheet.classList.add('hidden')
                    }
                }, cartTransitionMs)
            }

            const unitsFromCart = (menuId, cart) => {
                const mid = String(menuId || '')
                const units = []
                for (const [key, row] of Object.entries(cart || {})) {
                    const qty = Math.max(Number(row?.qty || 0), 0)
                    if (qty <= 0) continue
                    const parsed = parseKey(key, Array.isArray(row?.addons) ? row.addons : null)
                    if (String(parsed.menuId || '') !== mid) continue
                    const addons = Array.isArray(parsed.addons) ? parsed.addons.map(String).filter(Boolean) : []
                    for (let i = 0; i < qty; i++) {
                        units.push({ addons })
                    }
                }
                return units
            }

            const baselineUnitsForMenu = (menuId) => {
                if (!addMode) return []
                const mid = String(menuId || '')
                const base = baselineCart && typeof baselineCart === 'object' ? baselineCart : {}
                const units = []
                for (const [key, row] of Object.entries(base)) {
                    const parsed = parseKey(String(key || ''), Array.isArray(row?.addons) ? row.addons : null)
                    if (String(parsed.menuId || '') !== mid) continue
                    const qty = Math.max(Number(row?.qty || 0), 0)
                    const addons = Array.isArray(parsed.addons) ? parsed.addons.map(String).filter(Boolean) : []
                    for (let i = 0; i < qty; i++) {
                        units.push({ addons })
                    }
                }
                return units
            }

            const applyUnitsToCart = (menuId, units) => {
                const mid = String(menuId || '')
                if (!mid) return
                const cart = readCart()

                // Remove existing keys for menu
                for (const key of Object.keys(cart)) {
                    const parsed = parseKey(key, Array.isArray(cart[key]?.addons) ? cart[key].addons : null)
                    if (String(parsed.menuId || '') === mid) {
                        delete cart[key]
                    }
                }

                // Rebuild by addon signature
                const counts = new Map()
                for (const u of (Array.isArray(units) ? units : [])) {
                    const addons = Array.isArray(u?.addons) ? u.addons.map(String).filter(Boolean) : []
                    const key = makeKey(mid, addons)
                    if (!key) continue
                    counts.set(key, (counts.get(key) || 0) + 1)
                }

                for (const [key, qty] of counts.entries()) {
                    cart[key] = { qty, addons: parseKey(key, null).addons }
                }

                writeCart(cart)
                recompute()
            }

            const renderCustomize = () => {
                if (!customizeMenuId || !customizeList) return
                const menu = cartMenuMap.get(String(customizeMenuId))
                const addons = Array.isArray(menu?.addons) ? menu.addons : []
                const labelNoAddons = @json(__('No add-ons'));
                const labelLocked = @json(__('Locked'));

                customizeList.innerHTML = customizeUnits.map((u, idx) => {
                    const locked = idx < customizeLockedCount
                    const selected = new Set((u.addons || []).map(String))
                    const picked = addons.filter(a => selected.has(String(a.id)))
                    const pickedLabel = picked.length ? picked.map(a => a.name).join(', ') : labelNoAddons

                    const addonButtons = addons.length
                        ? `
                            <div class="mt-3 flex flex-wrap gap-2">
                                ${addons.map((a) => {
                                    const aid = String(a.id || '')
                                    const aName = String(a.name || '')
                                    const aPrice = Number(a.price || 0)
                                    const on = selected.has(aid)
                                    const cls = on
                                        ? 'border-[var(--brand-primary)] bg-[color-mix(in_srgb,var(--brand-primary)_12%,transparent)] text-neutral-900 dark:text-white'
                                        : 'border-neutral-200/70 bg-white/60 text-neutral-700 hover:bg-white/80 dark:border-neutral-800/70 dark:bg-neutral-900/40 dark:text-neutral-200 dark:hover:bg-neutral-900/60'
                                    return `
                                        <button type="button"
                                            class="inline-flex items-center gap-2 rounded-full border px-3 py-2 text-xs font-semibold shadow-sm backdrop-blur ${cls}"
                                            data-addon-toggle="1"
                                            data-unit-index="${idx}"
                                            data-addon-id="${aid}"
                                            ${locked ? 'disabled' : ''}>
                                            <span>${aName}</span>
                                            <span class="font-semibold text-neutral-500 dark:text-neutral-400">+${formatIDR(aPrice)}</span>
                                        </button>
                                    `
                                }).join('')}
                            </div>
                        `
                        : ''

                    return `
                        <div class="rounded-3xl border border-neutral-200/70 bg-white/70 p-4 shadow-sm backdrop-blur dark:border-neutral-800/70 dark:bg-neutral-900/40">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="text-sm font-semibold text-neutral-900 dark:text-white">${menu?.name || ''} <span class="text-neutral-400">#${idx + 1}</span></div>
                                    <div class="mt-1 text-xs font-semibold text-neutral-600 dark:text-neutral-300">${pickedLabel}</div>
                                    ${addonButtons}
                                </div>
                                ${locked ? `<div class="shrink-0 text-xs font-semibold text-neutral-500 dark:text-neutral-400">${labelLocked}</div>` : ''}
                            </div>
                        </div>
                    `
                }).join('')
            }

            const openCustomize = (menuId) => {
                const id = String(menuId || '')
                if (!id || !customizeSheet || !customizeList) return
                const menu = cartMenuMap.get(id)
                if (!menu || !(Array.isArray(menu.addons) && menu.addons.length)) return

                const cart = readCart()
                const units = unitsFromCart(id, cart)
                if (!units.length) return

                const baseUnits = baselineUnitsForMenu(id)
                const lockedCount = baseUnits.length
                customizeMenuId = id
                customizeLockedCount = lockedCount
                customizeUnits = [...baseUnits, ...units.slice(lockedCount)]

                if (customizeTitle) customizeTitle.textContent = menu?.name || @json(__('Customize'));
                renderCustomize()

                if (customizeCloseTimer) {
                    window.clearTimeout(customizeCloseTimer)
                    customizeCloseTimer = null
                }

                customizeSheet.classList.remove('hidden')
                document.body.style.overflow = 'hidden'

                window.requestAnimationFrame(() => {
                    customizeSheet.classList.remove('opacity-0')
                    customizeSheet.classList.add('opacity-100')
                    customizePanel?.classList.remove('translate-y-6')
                    customizePanel?.classList.add('translate-y-0')
                })
            }

            const pricingMap = (() => {
                try {
                    const raw = document.getElementById('menuPricingJson')?.textContent || '[]'
                    const list = JSON.parse(raw)
                    const map = new Map()
                    for (const m of (Array.isArray(list) ? list : [])) {
                        const id = String(m.id ?? '')
                        if (!id) continue
                        map.set(id, {
                            available: m.available !== false,
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

            const sigFromAddons = (addonIds) => {
                const ids = (Array.isArray(addonIds) ? addonIds : [])
                    .map((v) => String(v))
                    .filter(Boolean)
                const uniq = Array.from(new Set(ids))
                uniq.sort((a, b) => Number(a) - Number(b))
                return uniq.join(',')
            }

            const makeKey = (menuId, addonIds) => {
                const mid = String(menuId || '')
                return `${mid}:${sigFromAddons(addonIds)}`
            }

            const parseKey = (key, rowAddons = null) => {
                const raw = String(key || '')
                let menuId = raw
                let addons = []

                if (raw.includes(':')) {
                    const parts = raw.split(':')
                    menuId = String(parts[0] || '')
                    const sig = String(parts.slice(1).join(':') || '')
                    addons = sig ? sig.split(',').map(String).filter(Boolean) : []
                }

                if (Array.isArray(rowAddons)) {
                    addons = rowAddons.map((v) => String(v)).filter(Boolean)
                }

                return { menuId, addons }
            }

            const readCart = () => {
                try {
                    const raw =
                        sessionStorage.getItem(storageKey) ||
                        localStorage.getItem(storageKey)
                    if (!raw) return {}
                    const data = JSON.parse(raw)
                    if (!data || typeof data !== 'object') return {}

                    // Normalize legacy format: { [id]: number } and v1 object rows into composite keys { [menuId:addonSig]: {qty, addons} }
                    const normalized = {}
                    for (const [id, v] of Object.entries(data)) {
                        let qty = 0
                        let addons = []

                        if (typeof v === 'number') {
                            qty = Math.max(Number(v || 0), 0)
                        } else if (v && typeof v === 'object') {
                            qty = Math.max(Number(v.qty || 0), 0)
                            addons = Array.isArray(v.addons) ? v.addons.map(x => String(x)) : []
                        }

                        if (qty <= 0) continue

                        const parsed = parseKey(id, addons.length ? addons : null)
                        const key = makeKey(parsed.menuId, parsed.addons)
                        if (!key || !parsed.menuId) continue

                        if (!normalized[key]) normalized[key] = { qty: 0, addons: parsed.addons }
                        normalized[key].qty = Math.max(Number(normalized[key].qty || 0), 0) + qty
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

            const baselineCart = (() => {
                try {
                    const raw = document.getElementById('baselineCartJson')?.textContent || '{}'
                    const data = JSON.parse(raw)
                    return data && typeof data === 'object' ? data : {}
                } catch (e) {
                    return {}
                }
            })()

            const baselineMinQty = (() => {
                try {
                    const raw = document.getElementById('baselineMinQtyJson')?.textContent || '{}'
                    const data = JSON.parse(raw)
                    return data && typeof data === 'object' ? data : {}
                } catch (e) {
                    return {}
                }
            })()

            const prefillCart = (() => {
                try {
                    const raw = document.getElementById('prefillCartJson')?.textContent || '{}'
                    const data = JSON.parse(raw)
                    return data && typeof data === 'object' ? data : {}
                } catch (e) {
                    return {}
                }
            })()

            const getMinQty = (id) => {
                if (!addMode) return 0
                return Math.max(Number(baselineMinQty[String(id)] || 0), 0)
            }

            const seedBaseline = () => {
                if (!addMode) return
                const base = baselineCart && typeof baselineCart === 'object' ? baselineCart : {}
                const entries = Object.entries(base)
                if (!entries.length) return

                const cart = readCart()
                let changed = false

                for (const [idRaw, row] of entries) {
                    const baseQty = Math.max(Number(row?.qty || 0), 0)
                    const baseAddons = Array.isArray(row?.addons) ? row.addons.map(x => String(x)) : []
                    if (baseQty <= 0) continue

                    const parsed = parseKey(String(idRaw || ''), baseAddons.length ? baseAddons : null)
                    const id = makeKey(parsed.menuId, parsed.addons)
                    if (!id) continue

                    if (!cart[id] || typeof cart[id] !== 'object') {
                        cart[id] = { qty: baseQty, addons: baseAddons }
                        changed = true
                        continue
                    }

                    const currentQty = Math.max(Number(cart[id].qty || 0), 0)
                    if (currentQty < baseQty) {
                        cart[id].qty = baseQty
                        changed = true
                    }

                    const currentAddons = Array.isArray(cart[id].addons) ? cart[id].addons.map(x => String(x)) : []
                    if (!currentAddons.length && baseAddons.length) {
                        cart[id].addons = baseAddons
                        changed = true
                    }
                }

                if (changed) writeCart(cart)
            }

            const seedEditCart = () => {
                if (!editMode) return
                const cart = prefillCart && typeof prefillCart === 'object' ? prefillCart : {}
                writeCart(cart)
            }

            const getMenuIndex = () => {
                const cards = Array.from(document.querySelectorAll('[data-menu-card]'))
                const index = new Map()
                for (const card of cards) {
                    const id = String(card.dataset.menuId || '')
                    if (!id) continue
                    index.set(id, {
                        id,
                        available: card.dataset.menuAvailable !== '0',
                        name: card.dataset.menuName || '',
                        price: Number(card.dataset.menuPrice || 0),
                        card,
                    })
                }
                return index
            }

            const updateCardUI = (menu, qty, hasAddons = false) => {
                const host = menu.card.querySelector('[data-menu-actions]')
                if (!host) return
                const customizeBtn = menu.card.querySelector('[data-action="customize"]')
                const available = menu.available !== false

                if (!available) {
                    menu.card.classList.remove('customer-menu-card--selected', 'shadow-md')
                    customizeBtn?.classList.add('hidden')
                    customizeBtn?.setAttribute('disabled', 'disabled')
                    host.innerHTML = `
                        <button type="button"
                            class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-neutral-200/70 bg-neutral-100/80 text-neutral-400 shadow-sm dark:border-neutral-800/70 dark:bg-neutral-900/50 dark:text-neutral-500"
                            disabled
                            aria-label="{{ __('Out of stock') }}">
                            <span class="text-2xl font-bold">+</span>
                        </button>
                    `
                    return
                }

                // Selected state (border primary)
                menu.card.classList.toggle('customer-menu-card--selected', qty > 0)
                menu.card.classList.toggle('shadow-md', qty > 0)

                if (customizeBtn) {
                    const show = hasAddons && qty > 0
                    customizeBtn.classList.toggle('hidden', !show)
                    customizeBtn.toggleAttribute('disabled', !show)
                }

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

            const applyMenuAvailability = (menuId, available) => {
                const id = String(menuId || '')
                if (!id) return false

                const card = document.querySelector(`[data-menu-card][data-menu-id="${CSS.escape(id)}"]`)
                if (!card) return false

                const isAvailable = available === true
                card.dataset.menuAvailable = isAvailable ? '1' : '0'
                card.classList.toggle('opacity-75', !isAvailable)

                card.querySelectorAll('[data-menu-image-media]').forEach((el) => {
                    el.classList.toggle('grayscale', !isAvailable)
                    el.classList.toggle('opacity-60', !isAvailable)
                })

                card.querySelectorAll('[data-menu-out-overlay], [data-menu-out-badge]').forEach((el) => {
                    el.classList.toggle('hidden', isAvailable)
                })

                const cartEntry = cartMenuMap.get(id)
                if (cartEntry) cartEntry.available = isAvailable

                const priceEntry = pricingMap.get(id)
                if (priceEntry) priceEntry.available = isAvailable

                return true
            }

            const recompute = () => {
                const cart = readCart()
                const menuIndex = getMenuIndex()

                let total = 0
                for (const [key, row] of Object.entries(cart)) {
                    const q = Math.max(Number(row?.qty || 0), 0)
                    if (q <= 0) continue

                    const parsed = parseKey(key, Array.isArray(row?.addons) ? row.addons : null)
                    const menuId = String(parsed.menuId || '')
                    const menu = menuIndex.get(menuId)
                    if (!menu || menu.available === false) continue

                    const pricing = pricingMap.get(menuId)
                    const base = Number(pricing?.price ?? menu.price ?? 0)
                    const allowedAddons = Array.isArray(pricing?.addons) ? pricing.addons : []
                    const selected = new Set((parsed.addons || []).map(String))
                    const addonsTotal = allowedAddons
                        .filter(a => selected.has(String(a.id)))
                        .reduce((sum, a) => sum + Number(a.price || 0), 0)

                    total += q * (base + addonsTotal)
                }

                // Clean invalid entries
                for (const oldKey of Object.keys(cart)) {
                    const row = cart[oldKey]
                    const q = Math.max(Number(row?.qty || 0), 0)
                    const parsed = parseKey(oldKey, Array.isArray(row?.addons) ? row.addons : null)
                    const menuId = String(parsed.menuId || '')
                    const indexedMenu = menuIndex.get(menuId)
                    if (q <= 0 || !indexedMenu || indexedMenu.available === false) {
                        delete cart[oldKey]
                        continue
                    }

                    const pricing = pricingMap.get(menuId)
                    const allowed = new Set((pricing?.addons || []).map(a => String(a.id)))
                    const filteredAddons = (Array.isArray(row.addons) ? row.addons : [])
                        .map(String)
                        .filter(aId => allowed.has(aId))

                    const nextKey = makeKey(menuId, filteredAddons)
                    row.addons = filteredAddons

                    if (nextKey !== oldKey) {
                        if (!cart[nextKey]) cart[nextKey] = { qty: 0, addons: filteredAddons }
                        cart[nextKey].qty = Math.max(Number(cart[nextKey].qty || 0), 0) + q
                        delete cart[oldKey]
                    }
                }
                writeCart(cart)

                const qtyByMenu = new Map()
                for (const [key, row] of Object.entries(cart)) {
                    const qty = Math.max(Number(row?.qty || 0), 0)
                    if (qty <= 0) continue
                    const { menuId } = parseKey(key, Array.isArray(row?.addons) ? row.addons : null)
                    if (!menuId) continue
                    qtyByMenu.set(menuId, (qtyByMenu.get(menuId) || 0) + qty)
                }

                for (const menu of menuIndex.values()) {
                    const qty = Math.max(Number(qtyByMenu.get(menu.id) || 0), 0)
                    const hasAddons = (cartMenuMap.get(String(menu.id))?.addons || []).length > 0
                    updateCardUI(menu, qty, hasAddons)
                }

                const hasItems = Object.keys(cart).length > 0
                if (cartBar) cartBar.classList.toggle('hidden', !hasItems)
                if (cartTotal) cartTotal.textContent = formatIDR(total)
                if (cartTotalDrawer) cartTotalDrawer.textContent = formatIDR(total)

                renderDrawer(cart)
            }

            const renderDrawer = (cart) => {
                if (!cartItems) return

                const entries = Object.entries(cart)
                    .map(([key, row]) => {
                        const qty = Math.max(Number(row?.qty || 0), 0)
                        const parsed = parseKey(key, Array.isArray(row?.addons) ? row.addons : null)
                        return {
                            key: String(key || ''),
                            qty,
                            menuId: String(parsed.menuId || ''),
                            addons: Array.isArray(parsed.addons) ? parsed.addons.map(String).filter(Boolean) : [],
                        }
                    })
                    .filter((x) => x.key && x.menuId && x.qty > 0)

                if (!entries.length) {
                    cartItems.innerHTML = `
                        <div class="rounded-3xl border border-neutral-200/70 bg-white/70 p-6 text-center text-sm text-neutral-600 shadow-sm backdrop-blur dark:border-neutral-800/70 dark:bg-neutral-900/40 dark:text-neutral-300">
                            {{ __('Your cart is empty.') }}
                        </div>
                    `
                    return
                }

                entries.sort((a, b) => {
                    const am = cartMenuMap.get(a.menuId)?.name || ''
                    const bm = cartMenuMap.get(b.menuId)?.name || ''
                    return am.localeCompare(bm)
                })

                cartItems.innerHTML = entries.map((e) => {
                    const menu = cartMenuMap.get(e.menuId)
                    const name = menu?.name || 'Item'
                    const base = Number(menu?.price || 0)
                    const addons = Array.isArray(menu?.addons) ? menu.addons : []
                    const selected = new Set((e.addons || []).map(String))
                    const picked = addons.filter(a => selected.has(String(a.id)))
                    const addonsTotal = picked.reduce((sum, a) => sum + Number(a.price || 0), 0)

                    const unit = base + addonsTotal
                    const line = e.qty * unit

                    const addonChips = (() => {
                        if (!addons.length) return ''
                        if (!picked.length) {
                            return `<div class="mt-3 text-xs text-neutral-500 dark:text-neutral-400">{{ __('No add-ons') }}</div>`
                        }
                        return `
                            <div class="mt-3 flex flex-wrap gap-2">
                                ${picked.map((a) => {
                                    return `
                                        <span class="inline-flex items-center gap-2 rounded-full border border-[var(--brand-primary)] bg-[color-mix(in_srgb,var(--brand-primary)_12%,transparent)] px-3 py-1.5 text-xs font-semibold text-neutral-900 shadow-sm backdrop-blur dark:text-white">
                                            <span>${a.name}</span>
                                            <span class="font-semibold text-neutral-500 dark:text-neutral-400">(+${formatIDR(Number(a.price || 0))})</span>
                                        </span>
                                    `
                                }).join('')}
                            </div>
                        `
                    })()

                    return `
                        <div class="rounded-3xl border border-neutral-200/70 bg-white/70 p-4 shadow-sm backdrop-blur dark:border-neutral-800/70 dark:bg-neutral-900/40">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="truncate text-sm font-semibold text-neutral-900 dark:text-white">${name}</div>
                                    <div class="mt-1 text-sm font-semibold text-neutral-900 dark:text-white">${formatIDR(line)}</div>
                                    <div class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">${formatIDR(unit)} × ${e.qty}</div>
                                    ${addonChips}
                                </div>
                                <div class="shrink-0">
                                    <div class="inline-flex items-center gap-2 rounded-2xl border border-neutral-200/70 bg-white/70 px-2 py-2 shadow-sm backdrop-blur dark:border-neutral-800/70 dark:bg-neutral-950/30">
                                        <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-xl border border-neutral-200/70 bg-white/60 text-neutral-700 hover:bg-white/80 dark:border-neutral-800/70 dark:bg-neutral-900/40 dark:text-neutral-200 dark:hover:bg-neutral-900/60" data-cart-action="dec" data-id="${e.key}">
                                            <span class="text-lg font-semibold">−</span>
                                        </button>
                                        <div class="min-w-[1.5rem] text-center text-sm font-semibold text-neutral-900 dark:text-white">${e.qty}</div>
                                        <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-[var(--brand-primary)] text-[var(--brand-accent)] shadow-sm ring-1 ring-black/5 hover:bg-[var(--brand-primary-hover)] active:bg-[var(--brand-primary-active)]" data-cart-action="inc" data-id="${e.key}">
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
                const minQty = getMinQty(id)
                const q = Math.max(Number(nextQty || 0), minQty, 0)
                if (!cart[id]) {
                    const parsed = parseKey(id, null)
                    cart[id] = { qty: 0, addons: parsed.addons || [] }
                }
                cart[id].qty = q
                if (q <= 0) delete cart[id]
                writeCart(cart)
                recompute()
            }

            const incMenu = (menuId) => {
                const mid = String(menuId || '')
                if (!mid) return
                const key = makeKey(mid, [])
                const cart = readCart()
                const current = Math.max(Number(cart[key]?.qty || 0), 0)
                setQty(key, current + 1)
            }

            const decMenu = (menuId) => {
                const mid = String(menuId || '')
                if (!mid) return
                const cart = readCart()

                const candidates = Object.keys(cart)
                    .map((key) => {
                        const row = cart[key]
                        const qty = Math.max(Number(row?.qty || 0), 0)
                        const parsed = parseKey(key, Array.isArray(row?.addons) ? row.addons : null)
                        const sameMenu = String(parsed.menuId || '') === mid
                        return { key: String(key), qty, sameMenu }
                    })
                    .filter((x) => x.sameMenu && x.qty > 0)

                if (!candidates.length) return

                // Prefer decrementing default variant (no add-ons). If it doesn't exist, decrement any other variant.
                const defaultKey = makeKey(mid, [])
                let chosen = candidates.find((c) => c.key === defaultKey) || null
                if (!chosen) chosen = candidates[0]

                // Respect minQty lock in add-mode.
                const minQty = getMinQty(chosen.key)
                if (chosen.qty <= minQty) {
                    // Try find another variant that can be decremented.
                    chosen = candidates.find((c) => c.qty > getMinQty(c.key)) || null
                    if (!chosen) return
                }

                setQty(chosen.key, chosen.qty - 1)
            }

            document.addEventListener('click', (e) => {
                const btn = e.target.closest('[data-action]')
                if (btn) {
                    const card = btn.closest('[data-menu-card]')
                    const id = String(card?.dataset.menuId || '')
                    if (!id) return
                    if (card?.dataset.menuAvailable === '0') return
                    const action = btn.dataset.action
                    const hasAddons = (cartMenuMap.get(id)?.addons || []).length > 0

                    if (action === 'customize') {
                        openCustomize(id)
                        return
                    }

                    if (hasAddons) {
                        if (action === 'add' || action === 'inc') incMenu(id)
                        if (action === 'dec') decMenu(id)
                        return
                    }

                    const key = makeKey(id, [])
                    const cart = readCart()
                    const current = Math.max(Number(cart[key]?.qty || 0), 0)
                    if (action === 'add' || action === 'inc') setQty(key, current + 1)
                    if (action === 'dec') setQty(key, current - 1)
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
                    closeCartDrawer()
                    return
                }

                if (e.target.closest('[data-close-customize]')) {
                    if (customizeMenuId) {
                        applyUnitsToCart(customizeMenuId, customizeUnits)
                    }
                    closeCustomize()
                    return
                }
            })

            document.addEventListener('click', (e) => {
                const t = e.target.closest('[data-addon-toggle]')
                if (!t) return
                if (!customizeMenuId) return
                const idx = Number(t.dataset.unitIndex || -1)
                const aid = String(t.dataset.addonId || '')
                if (!Number.isFinite(idx) || idx < 0) return
                if (!aid) return
                if (idx < customizeLockedCount) return
                if (!customizeUnits[idx]) return

                const current = new Set((customizeUnits[idx].addons || []).map(String))
                if (current.has(aid)) current.delete(aid)
                else current.add(aid)
                customizeUnits[idx] = { addons: Array.from(current.values()) }
                renderCustomize()
            })

            checkoutBtn?.addEventListener('click', () => {
                openCartDrawer()
                recompute()
            })

            drawerCheckoutBtn?.addEventListener('click', async () => {
                const checkoutUrl = drawerCheckoutBtn.getAttribute('data-checkout-url') || ''
                if (!checkoutUrl) return

                window.location.href = checkoutUrl
            })

            // Init cart UI
            seedEditCart()
            seedBaseline()
            recompute()

            let menuPollTimer = null
            let menuPollStopped = false
            let menuPollInflight = false
            const MENU_POLL_MS = 2000

            const stopMenuPolling = () => {
                menuPollStopped = true
                if (menuPollTimer) window.clearTimeout(menuPollTimer)
                menuPollTimer = null
            }

            const scheduleMenuPolling = () => {
                if (menuPollStopped || !menuUpdatesUrl) return
                if (menuPollTimer) window.clearTimeout(menuPollTimer)
                menuPollTimer = window.setTimeout(pollMenuUpdates, MENU_POLL_MS)
            }

            const pollMenuUpdates = async () => {
                if (!menuUpdatesUrl || menuPollStopped) return
                if (document.hidden) {
                    scheduleMenuPolling()
                    return
                }
                if (menuPollInflight) {
                    scheduleMenuPolling()
                    return
                }

                menuPollInflight = true

                try {
                    const url = new URL(menuUpdatesUrl, window.location.origin)
                    url.searchParams.set('version', String(menuVersion || 0))

                    const response = await fetch(url.toString(), {
                        headers: { Accept: 'application/json' },
                        cache: 'no-store',
                    })

                    if (!response.ok) return

                    const data = await response.json()
                    const nextVersion = Number(data?.version || menuVersion || 1)

                    if (nextVersion === menuVersion || data?.changed === false) {
                        menuVersion = nextVersion
                        return
                    }

                    menuVersion = nextVersion
                    if (root) root.dataset.menuVersion = String(menuVersion)
                    stopMenuPolling()
                    window.location.reload()
                } catch (_) {
                } finally {
                    menuPollInflight = false
                    scheduleMenuPolling()
                }
            }

            if (menuUpdatesUrl) {
                pollMenuUpdates()
                document.addEventListener('visibilitychange', () => {
                    if (!document.hidden) pollMenuUpdates()
                })
                window.addEventListener('beforeunload', () => stopMenuPolling())
            }

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
                    openCartDrawer()
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
