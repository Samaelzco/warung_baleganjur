document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;

    try {
        const action = new URL(form.action, window.location.href);
        const current = new URL(window.location.href);

        if (action.origin === current.origin && action.pathname.endsWith('/logout')) {
            window.dispatchEvent(new CustomEvent('app-logout-start'));
        }
    } catch (_) {}
}, true);

document.addEventListener('alpine:init', () => {
    if (!window.Alpine?.data) return;

    Alpine.data('dashboardCharts', (chartsEntangle = null) => ({
        charts: null,
        _timeseriesMode: null,
        options: {
            revenueLabel: 'Revenue',
            revenuePerHourLabel: 'Revenue per hour',
            revenuePerDayLabel: 'Revenue per day',
        },
        revenueChart: null,
        paymentChart: null,
        init() {
            this.loadOptionsFromDataset();

            if (chartsEntangle) {
                this.charts = chartsEntangle;
            } else {
                this.loadChartsFromDataset();
            }

            const waitForChartJs = () => {
                if (!window.Chart) return setTimeout(waitForChartJs, 50);
                this.build();
            };
            waitForChartJs();
        },
        loadOptionsFromDataset() {
            const dataset = this.$el?.dataset || {};

            this.options = {
                revenueLabel: dataset.revenueLabel || this.options.revenueLabel,
                revenuePerHourLabel:
                    dataset.revenuePerHourLabel || this.options.revenuePerHourLabel,
                revenuePerDayLabel: dataset.revenuePerDayLabel || this.options.revenuePerDayLabel,
            };
        },
        loadChartsFromDataset() {
            const dataset = this.$el?.dataset || {};

            const raw = dataset.charts;
            if (!raw) {
                this.charts = { timeseries: { labels: [], revenue: [], mode: 'day' }, payment: { labels: [], totals: [] } };
                return;
            }

            try {
                this.charts = JSON.parse(raw);
            } catch (_) {
                this.charts = { timeseries: { labels: [], revenue: [], mode: 'day' }, payment: { labels: [], totals: [] } };
            }
        },
        destroyCharts() {
            try {
                this.revenueChart?.destroy?.();
            } catch (_) {}
            try {
                this.paymentChart?.destroy?.();
            } catch (_) {}
            this.revenueChart = null;
            this.paymentChart = null;
        },
        build() {
            if (!this.$refs?.revenueChart || !this.$refs?.paymentChart) {
                this.$nextTick(() => this.build());
                return;
            }

            if (this.revenueChart || this.paymentChart) {
                this.destroyCharts();
            }

            const primary =
                getComputedStyle(document.documentElement)
                    .getPropertyValue('--brand-primary')
                    .trim() || '#f59e0b';

            const revenueLabel = this.options?.revenueLabel || 'Revenue';

            this.revenueChart = new Chart(this.$refs.revenueChart.getContext('2d'), {
                type: 'line',
                data: {
                    labels: this.charts?.timeseries?.labels || [],
                    datasets: [
                        {
                            label: revenueLabel,
                            data: this.charts?.timeseries?.revenue || [],
                            borderColor: primary,
                            backgroundColor: primary + '22',
                            tension: 0.35,
                            fill: true,
                            pointRadius: 2,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: (ctx) =>
                                    'Rp ' + new Intl.NumberFormat('id-ID').format(ctx.parsed.y || 0),
                            },
                        },
                    },
                    scales: {
                        x: { grid: { display: false } },
                        y: {
                            ticks: {
                                callback: (v) =>
                                    'Rp ' +
                                    new Intl.NumberFormat('id-ID', { notation: 'compact' }).format(v),
                            },
                        },
                    },
                },
            });

            this._timeseriesMode = this.charts?.timeseries?.mode || null;

            this.paymentChart = new Chart(this.$refs.paymentChart.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: this.charts?.payment?.labels || [],
                    datasets: [
                        {
                            data: this.charts?.payment?.totals || [],
                            backgroundColor: ['#10b981', '#0ea5e9', '#a3a3a3'],
                            borderWidth: 0,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' },
                        tooltip: {
                            callbacks: {
                                label: (ctx) => {
                                    const v = ctx.parsed || 0;
                                    return (
                                        (ctx.label || '') +
                                        ': Rp ' +
                                        new Intl.NumberFormat('id-ID').format(v)
                                    );
                                },
                            },
                        },
                    },
                },
            });

            requestAnimationFrame(() => {
                try {
                    this.revenueChart?.resize?.();
                    this.paymentChart?.resize?.();
                } catch (_) {}
            });
        },
    }));

    Alpine.data('kitchenSound', ({ src } = {}) => ({
        enabled: false,
        src: src || '',
        lastPlayedAt: 0,
        init() {
            try {
                this.enabled = JSON.parse(localStorage.getItem('kitchenSoundEnabled') || 'false') === true;
            } catch (_) {
                this.enabled = false;
            }

            this.$refs.audio.src = this.src;
            try { this.$refs.audio.load?.(); } catch (_) {}

            const onNewOrder = () => {
                if (!this.enabled) return;
                this.play();
            };

            const onOrderUpdated = () => {
                if (!this.enabled) return;
                this.play();
            };

            window.addEventListener('kitchen-new-order', onNewOrder);
            document.addEventListener('kitchen-new-order', onNewOrder);
            window.addEventListener('kitchen-order-created', onNewOrder);
            document.addEventListener('kitchen-order-created', onNewOrder);

            window.addEventListener('kitchen-order-updated', onOrderUpdated);
            document.addEventListener('kitchen-order-updated', onOrderUpdated);
        },
        async toggle() {
            this.enabled = !this.enabled;
            localStorage.setItem('kitchenSoundEnabled', JSON.stringify(this.enabled));

            if (this.enabled) {
                // Try to play once from the user gesture to satisfy autoplay policies.
                this.play();
            }
        },
        play() {
            const now = Date.now();
            if (now - this.lastPlayedAt < 300) return;
            this.lastPlayedAt = now;

            try {
                const audio = this.$refs.audio;
                audio.currentTime = 0;
                const res = audio.play();
                if (res?.catch) res.catch(() => {});
            } catch (_) {}
        },
    }));

    Alpine.data('kitchenOrderChanges', () => ({
        fingerprints: new Map(),
        updatedIds: new Set(),
        initialized: false,
        scanQueued: false,
        lastToastAt: 0,
        observer: null,
        init() {
            try {
                const raw = sessionStorage.getItem('kitchenUpdatedIds:v2');
                if (raw) {
                    JSON.parse(raw).forEach((id) => this.updatedIds.add(String(id)));
                }
            } catch (_) {}

            const handler = () => this.queueScan();

            window.addEventListener('kitchen-new-order', handler);
            document.addEventListener('kitchen-new-order', handler);
            window.addEventListener('kitchen-poll-tick', handler);
            document.addEventListener('kitchen-poll-tick', handler);

            this.observer = new MutationObserver(() => this.queueScan());
            this.observer.observe(this.$el, {
                subtree: true,
                childList: true,
                attributes: true,
                attributeFilter: ['data-order-fp', 'data-order-id', 'data-order-code'],
            });

            this.queueScan();
        },
        toast(message) {
            const now = Date.now();
            if (now - this.lastToastAt < 900) return;
            this.lastToastAt = now;
            window.dispatchEvent(new CustomEvent('kitchen-toast', { detail: { message } }));
        },
        markUpdated(el) {
            el.querySelectorAll('.kitchen-updated-badge').forEach((badge) => badge.setAttribute('data-updated', '1'));
        },
        clearUpdated(el) {
            el.querySelectorAll('.kitchen-updated-badge').forEach((badge) => badge.removeAttribute('data-updated'));
        },
        scan() {
            const orderEls = this.$el.querySelectorAll('[data-kitchen-order][data-order-id][data-order-fp]');
            const byId = new Map();

            orderEls.forEach((el) => {
                const id = String(el.getAttribute('data-order-id') || '');
                const fp = String(el.getAttribute('data-order-fp') || '');
                const code = String(el.getAttribute('data-order-code') || '');
                if (!id || !fp) return;

                const existing = byId.get(id);
                if (existing) {
                    existing.els.push(el);
                    return;
                }

                byId.set(id, { fp, code, els: [el] });
            });

            const seen = new Set(byId.keys());
            const changed = [];
            const created = [];

            for (const [id, data] of byId.entries()) {
                const prev = this.fingerprints.get(id);
                this.fingerprints.set(id, data.fp);

                if (!this.initialized) continue;

                if (prev === undefined) {
                    created.push(data.code || `#${id}`);
                    continue;
                }

                if (prev !== data.fp) {
                    changed.push(data.code || `#${id}`);
                    this.updatedIds.add(id);
                }
            }

            for (const id of Array.from(this.fingerprints.keys())) {
                if (!seen.has(id)) this.fingerprints.delete(id);
            }

            // Keep the UI in sync after Livewire DOM morphs.
            for (const [id, data] of byId.entries()) {
                if (this.updatedIds.has(id)) data.els.forEach((el) => this.markUpdated(el));
                else data.els.forEach((el) => this.clearUpdated(el));
            }

            if (!this.initialized) {
                this.initialized = true;
                return;
            }

            try {
                sessionStorage.setItem('kitchenUpdatedIds:v2', JSON.stringify(Array.from(this.updatedIds)));
            } catch (_) {}

            if (created.length === 1) this.toast(`${this.$el.dataset.newOrderLabel || 'New order'}: ${created[0]}`);
            else if (created.length > 1) this.toast(`${created.length} ${this.$el.dataset.newOrdersLabel || 'new orders'}`);

            if (created.length > 0) {
                window.dispatchEvent(new CustomEvent('kitchen-order-created', { detail: { orders: created } }));
            }

            if (changed.length === 1) this.toast(`${this.$el.dataset.orderUpdatedLabel || 'Order updated'}: ${changed[0]}`);
            else if (changed.length > 1) this.toast(`${changed.length} ${this.$el.dataset.ordersUpdatedLabel || 'orders updated'}`);

            if (changed.length > 0) {
                window.dispatchEvent(new CustomEvent('kitchen-order-updated', { detail: { orders: changed } }));
            }
        },
        queueScan() {
            if (this.scanQueued) return;
            this.scanQueued = true;
            // Use a microtask so we can re-apply the badge before the next paint
            // (prevents a visible flicker after Livewire DOM morphs).
            const run = () => {
                this.scanQueued = false;
                this.scan();
            };

            if (typeof queueMicrotask === 'function') queueMicrotask(run);
            else Promise.resolve().then(run);
        },
    }));

    Alpine.data('paymentSound', ({ src } = {}) => ({
        enabled: false,
        src: src || '',
        init() {
            try {
                this.enabled = JSON.parse(localStorage.getItem('paymentSoundEnabled') || 'false') === true;
            } catch (_) {
                this.enabled = false;
            }

            this.$refs.audio.src = this.src;
            try { this.$refs.audio.load?.(); } catch (_) {}

            const onSuccess = () => {
                if (!this.enabled) return;
                this.play();
            };

            window.addEventListener('payment-success', onSuccess);
            document.addEventListener('payment-success', onSuccess);
        },
        async toggle() {
            this.enabled = !this.enabled;
            localStorage.setItem('paymentSoundEnabled', JSON.stringify(this.enabled));
            window.dispatchEvent(new CustomEvent('payment-sound-toggled', { detail: { enabled: this.enabled } }));

            if (this.enabled) {
                this.play();
            }
        },
        play() {
            try {
                const audio = this.$refs.audio;
                audio.currentTime = 0;
                const res = audio.play();
                if (res?.catch) res.catch(() => {});
            } catch (_) {}
        },
    }));

    Alpine.data('paymentNewOrderSound', ({ src } = {}) => ({
        enabled: false,
        src: src || '',
        orderIds: new Set(),
        initialized: false,
        scanQueued: false,
        observer: null,
        lastPlayedAt: 0,
        init() {
            try {
                this.enabled = JSON.parse(localStorage.getItem('paymentSoundEnabled') || 'false') === true;
            } catch (_) {
                this.enabled = false;
            }

            this.$refs.audio.src = this.src;
            try { this.$refs.audio.load?.(); } catch (_) {}

            window.addEventListener('payment-sound-toggled', (event) => {
                this.enabled = event.detail?.enabled === true;
            });

            this.observer = new MutationObserver(() => this.queueScan());
            this.observer.observe(this.$el, {
                subtree: true,
                childList: true,
                attributes: true,
                attributeFilter: ['data-order-id'],
            });

            this.queueScan();
        },
        scan() {
            const nextIds = new Set();
            let hasNewOrder = false;

            this.$el.querySelectorAll('[data-payment-order][data-order-id]').forEach((el) => {
                const id = String(el.getAttribute('data-order-id') || '');
                if (!id) return;

                nextIds.add(id);
                if (this.initialized && !this.orderIds.has(id)) {
                    hasNewOrder = true;
                }
            });

            this.orderIds = nextIds;

            if (!this.initialized) {
                this.initialized = true;
                return;
            }

            if (hasNewOrder && this.enabled) {
                this.play();
            }
        },
        queueScan() {
            if (this.scanQueued) return;
            this.scanQueued = true;
            const run = () => {
                this.scanQueued = false;
                this.scan();
            };

            if (typeof queueMicrotask === 'function') queueMicrotask(run);
            else Promise.resolve().then(run);
        },
        play() {
            const now = Date.now();
            if (now - this.lastPlayedAt < 1200) return;
            this.lastPlayedAt = now;

            try {
                const audio = this.$refs.audio;
                audio.currentTime = 0;
                const res = audio.play();
                if (res?.catch) res.catch(() => {});
            } catch (_) {}
        },
    }));
});
