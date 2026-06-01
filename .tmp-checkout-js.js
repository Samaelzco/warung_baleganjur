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
            const discountText = document.getElementById('discountText')
            const taxRow = document.getElementById('taxRow')
            const taxText = document.getElementById('taxText')
            const totalText = document.getElementById('totalText')

            const voucherInput = document.getElementById('voucherInput')
            const voucherHint = document.getElementById('voucherHint')

            const confirmCheckoutBtn = document.getElementById('confirmCheckoutBtn')
            const nameModal = document.getElementById('nameModal')
            const customerName = document.getElementById('customerName')
            const saveNameBtn = document.getElementById('saveNameBtn')
            const checkoutError = document.getElementById('checkoutError')

            const labelInvalidVoucher = "")
            const labelVoucherApplied = "")
            const labelNetwork = "")
            const labelSubmitFailed = "")
            const labelMenu = "")

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

                if (checkoutItems) {
                    checkoutItems.innerHTML = lines.map((l) => {
                        const addonHtml = l.addons.length
                            ? `<div class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">${l.addons.map(a => `+ ${a.name}`).join(', ')}</div>`
                            : ''
                        const img = l.image
                            ? `<img src="${l.image}" alt="" class="h-16 w-20 rounded-2xl object-cover ring-1 ring-black/5 dark:ring-white/10" loading="lazy" />`
                            : `<div class="h-16 w-20 rounded-2xl bg-neutral-100 ring-1 ring-black/5 dark:bg-neutral-800 dark:ring-white/10"></div>`

                        return `
                            <div class="rounded-3xl border border-neutral-200/70 bg-white/70 p-4 shadow-sm backdrop-blur dark:border-neutral-800/70 dark:bg-neutral-900/40">
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
                if (taxText) taxText.textContent = formatIDR(tax)
                if (totalText) totalText.textContent = formatIDR(total)
                if (taxRow) taxRow.classList.toggle('hidden', !(tax > 0))

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

            const submitOrder = async () => {
                checkoutError?.classList.add('hidden')

                const { cart, voucherCode, lines } = calc()
                if (!lines.length) return

                const name = String(customerName?.value || '').trim()
                if (!name) {
                    if (checkoutError) {
                        checkoutError.textContent = "")
                        checkoutError.classList.remove('hidden')
                    }
                    customerName?.focus()
                    return
                }

                try { localStorage.setItem(nameKey, name) } catch (e) {}

                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                saveNameBtn?.setAttribute('disabled', 'disabled')

                try {
                    const res = await fetch(""), {
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
                        window.location.href = json.redirect || "")
                        return
                    }

                    if (res.status === 409 && json && json.redirect) {
                        window.location.href = json.redirect
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
