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
});
