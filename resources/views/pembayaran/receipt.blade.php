<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Receipt') }} - {{ $pesanan->kode_pesanan }}</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji", "Segoe UI Emoji"; margin: 0; padding: 12px; color: #111827; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .receipt { width: 80mm; max-width: 100%; margin: 0 auto; }
        .center { text-align: center; }
        .muted { color: #6b7280; }
        .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
        .row { display: flex; justify-content: space-between; gap: 12px; }
        .hr { border-top: 1px dashed #d1d5db; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th { text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: .12em; color: #6b7280; padding: 6px 0; }
        td { padding: 6px 0; vertical-align: top; }
        td.num, th.num { text-align: right; }
        .small { font-size: 12px; }
        .xs { font-size: 11px; }
        @media print {
            @page { size: 80mm auto; margin: 0; }
            html, body { width: 80mm; }
            body { padding: 0; }
            .receipt { width: 80mm; max-width: 80mm; margin: 0; padding: 4mm; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
@php
    $methodLabels = [
        'tunai' => __('Cash'),
        'transfer' => __('Bank Transfer'),
        'qris' => __('QRIS'),
    ];
@endphp

<div class="receipt">
    <div class="center">
        <div style="font-weight: 700; font-size: 16px;">{{ config('app.name') }}</div>
        <div class="muted xs">{{ __('Payment Receipt') }}</div>
    </div>

    <div class="hr"></div>

    <div class="xs">
        <div class="row"><span class="muted">{{ __('Order') }}</span><span class="mono">{{ $pesanan->kode_pesanan }}</span></div>
        <div class="row"><span class="muted">{{ __('Table') }}</span><span>{{ $pesanan->meja?->nomor_meja ?? '-' }}</span></div>
        <div class="row"><span class="muted">{{ __('Customer') }}</span><span>{{ $pesanan->customer_name }}</span></div>
        <div class="row"><span class="muted">{{ __('Paid at') }}</span><span>{{ optional($pesanan->waktu_selesai)->format('Y-m-d H:i') }}</span></div>
        <div class="row"><span class="muted">{{ __('Cashier') }}</span><span>{{ $pesanan->kasir?->name ?? '-' }}</span></div>
    </div>

    <div class="hr"></div>

    <table>
        <thead>
        <tr>
            <th>{{ __('Item') }}</th>
            <th class="num">{{ __('Qty') }}</th>
            <th class="num">{{ __('Subtotal') }}</th>
        </tr>
        </thead>
        <tbody>
        @foreach($pesanan->details as $detail)
            <tr>
                <td>
                    <div class="small" style="font-weight: 600;">{{ $detail->menu?->nama_menu ?? __('Menu') }}</div>
                    @if ($detail->addons->isNotEmpty())
                        <div class="muted xs">+ {{ $detail->addons->pluck('nama_addon')->join(', ') }}</div>
                    @endif
                    <div class="muted xs">Rp {{ number_format((float) $detail->harga, 0, ',', '.') }}</div>
                </td>
                <td class="num">{{ (int) $detail->qty }}</td>
                <td class="num">Rp {{ number_format((float) $detail->subtotal, 0, ',', '.') }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="hr"></div>

    <div class="xs">
        <div class="row"><span class="muted">{{ __('Subtotal') }}</span><span>Rp {{ number_format((float) $pesanan->subtotal, 0, ',', '.') }}</span></div>
        <div class="row"><span class="muted">{{ __('Discount') }}</span><span>Rp {{ number_format((float) $pesanan->discount_total, 0, ',', '.') }}</span></div>
        <div class="row"><span class="muted">{{ __('Tax') }}</span><span>Rp {{ number_format((float) $pesanan->tax_total, 0, ',', '.') }}</span></div>
        <div class="row" style="font-weight: 700;"><span>{{ __('Grand Total') }}</span><span>Rp {{ number_format((float) $pesanan->total_harga, 0, ',', '.') }}</span></div>
    </div>

    <div class="hr"></div>

    <div class="xs">
        <div class="row"><span class="muted">{{ __('Payment Method') }}</span><span>{{ $methodLabels[$pesanan->metode_pembayaran] ?? $pesanan->metode_pembayaran }}</span></div>
        @if(!blank($pesanan->referensi_pembayaran))
            <div class="row"><span class="muted">{{ __('Reference') }}</span><span class="mono">{{ $pesanan->referensi_pembayaran }}</span></div>
        @endif
        <div class="row"><span class="muted">{{ __('Paid Amount') }}</span><span>Rp {{ number_format((float) $pesanan->dibayar, 0, ',', '.') }}</span></div>
        <div class="row"><span class="muted">{{ __('Change') }}</span><span>Rp {{ number_format((float) $pesanan->kembalian, 0, ',', '.') }}</span></div>
    </div>

    <div class="hr"></div>

    <div class="center muted xs">
        {{ __('Thank you!') }}
    </div>

    <div class="no-print center" style="margin-top: 12px;">
        <button onclick="window.print()" style="padding: 8px 12px; border-radius: 10px; border: 1px solid #e5e7eb; background: #111827; color: white; font-weight: 600;">
            {{ __('Print') }}
        </button>
    </div>
</div>

<script>
    (function () {
        const params = new URLSearchParams(window.location.search);
        if (params.get('print') === '1') {
            window.addEventListener('load', () => {
                window.print();
                window.onafterprint = () => {
                    try { window.close(); } catch (e) {}
                };
            });
        }
    })();
</script>
</body>
</html>
