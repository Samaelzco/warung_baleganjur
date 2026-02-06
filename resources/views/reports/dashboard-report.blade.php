<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <title>{{ __('Dashboard Report') }}</title>
    <style>
        :root {
            --brand-primary: #F5BB2F;
            --brand-accent: #080A35;
        }
        @page { margin: 12mm; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 0;
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            color: #0f172a;
            font-size: 11px;
        }
        .muted { color: #64748b; }
        .row { width: 100%; }
        .title {
            font-size: 18px;
            font-weight: 700;
            color: var(--brand-accent);
            margin: 0 0 2mm 0;
        }
        .subtitle { margin: 0; }
        .bar {
            height: 3mm;
            background: var(--brand-primary);
            border-radius: 999px;
            margin: 4mm 0 6mm 0;
        }
        .grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 4mm 4mm;
            table-layout: fixed;
        }
        .grid td { vertical-align: top; }
        .card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
            background: #fff;
        }
        .cardInner { padding: 4mm; }
        .kpiLabel {
            font-size: 9px;
            letter-spacing: 0.25em;
            text-transform: uppercase;
            color: #64748b;
            margin: 0;
        }
        .kpiValue {
            margin: 2mm 0 0 0;
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
        }
        .sectionTitle {
            font-size: 12px;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 2mm 0;
        }
        table.simple {
            width: 100%;
            border-collapse: collapse;
        }
        table.simple th, table.simple td {
            padding: 2.5mm 2.5mm;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }
        table.simple th {
            text-align: left;
            font-size: 9px;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: #64748b;
            background: #f8fafc;
        }
        .right { text-align: left; white-space: nowrap; }
        .mono { font-family: DejaVu Sans Mono, ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
        .pill {
            display: inline-block;
            padding: 1mm 2.5mm;
            border-radius: 999px;
            background: #f1f5f9;
            color: #334155;
            font-size: 10px;
            white-space: nowrap;
        }
        thead { display: table-header-group; }
        tfoot { display: table-footer-group; }
        .pageBreak { page-break-before: always; }
    </style>
</head>
<body>
    <div class="row">
        <div style="float:left; width: 70%;">
            <p class="title">{{ __('Sales & Payments Report') }}</p>
            <p class="subtitle muted">
                {{ __('Period') }}:
                <strong>{{ $start->format('d M Y') }}</strong> — <strong>{{ $end->format('d M Y') }}</strong>
            </p>
            <p class="subtitle muted">
                {{ __('Generated') }}: {{ $generatedAt->format('d M Y H:i') }}
                @if(!blank($generatedBy))
                    · {{ __('By') }}: {{ $generatedBy }}
                @endif
            </p>
        </div>
        <div style="float:right; width: 30%; text-align:right;">
            <span class="pill">Warung Baleganjur</span>
        </div>
        <div style="clear:both;"></div>
        <div class="bar"></div>
    </div>

    <table class="grid">
        <tr>
            <td class="card">
                <div class="cardInner">
                    <p class="kpiLabel">{{ __('Orders') }}</p>
                    <p class="kpiValue">{{ number_format($paidCount) }}</p>
                </div>
            </td>
            <td class="card">
                <div class="cardInner">
                    <p class="kpiLabel">{{ __('Revenue') }}</p>
                    <p class="kpiValue">Rp&nbsp;{{ number_format($revenue, 0, ',', '.') }}</p>
                </div>
            </td>
            <td class="card">
                <div class="cardInner">
                    <p class="kpiLabel">{{ __('Avg / Order') }}</p>
                    <p class="kpiValue">Rp&nbsp;{{ number_format($avgOrder, 0, ',', '.') }}</p>
                </div>
            </td>
        </tr>
        <tr>
            <td class="card">
                <div class="cardInner">
                    <p class="kpiLabel">{{ __('Discounts') }}</p>
                    <p class="kpiValue">Rp&nbsp;{{ number_format($discountTotal, 0, ',', '.') }}</p>
                </div>
            </td>
            <td class="card">
                <div class="cardInner">
                    <p class="kpiLabel">{{ __('Taxes') }}</p>
                    <p class="kpiValue">Rp&nbsp;{{ number_format($taxTotal, 0, ',', '.') }}</p>
                </div>
            </td>
            <td class="card">
                <div class="cardInner">
                    <p class="kpiLabel">{{ __('Net') }}</p>
                    <p class="kpiValue">Rp&nbsp;{{ number_format(($revenue - $discountTotal + $taxTotal), 0, ',', '.') }}</p>
                </div>
            </td>
        </tr>
    </table>

    <table class="grid">
        <tr>
            <td class="card" style="width: 60%;">
                <div class="cardInner">
                    <p class="sectionTitle">{{ __('Payment Method Breakdown') }}</p>
                    <table class="simple">
                        <thead>
                            <tr>
                                <th>{{ __('Method') }}</th>
                                <th class="right">{{ __('Orders') }}</th>
                                <th class="right">{{ __('Total') }}</th>
                                <th class="right">{{ __('Paid In') }}</th>
                                <th class="right">{{ __('Change') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($methodRows as $row)
                                <tr>
                                    <td>{{ $row['label'] }}</td>
                                    <td class="right">{{ number_format($row['count']) }}</td>
                                    <td class="right">Rp&nbsp;{{ number_format($row['total'], 0, ',', '.') }}</td>
                                    <td class="right">Rp&nbsp;{{ number_format($row['paid_in'], 0, ',', '.') }}</td>
                                    <td class="right">Rp&nbsp;{{ number_format($row['change_out'], 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </td>
            <td class="card" style="width: 40%;">
                <div class="cardInner">
                    <p class="sectionTitle">{{ __('Top Menus') }}</p>
                    <table class="simple">
                        <thead>
                            <tr>
                                <th>{{ __('Item') }}</th>
                                <th class="right">{{ __('Qty') }}</th>
                                <th class="right">{{ __('Total') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($topMenus as $m)
                                <tr>
                                    <td>{{ $m->nama_menu }}</td>
                                    <td class="right">{{ (int) $m->qty }}</td>
                                    <td class="right">Rp&nbsp;{{ number_format((float) $m->total, 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="muted">{{ __('No data') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </td>
        </tr>
        <tr>
            <td class="card" colspan="2">
                <div class="cardInner">
                    <p class="sectionTitle">{{ __('Top Add-ons') }}</p>
                    <table class="simple">
                        <thead>
                            <tr>
                                <th>{{ __('Item') }}</th>
                                <th class="right">{{ __('Qty') }}</th>
                                <th class="right">{{ __('Total') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($topAddons as $a)
                                <tr>
                                    <td>{{ $a->nama_addon }}</td>
                                    <td class="right">{{ (int) $a->qty }}</td>
                                    <td class="right">Rp&nbsp;{{ number_format((float) $a->total, 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="muted">{{ __('No data') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <div class="pageBreak"></div>

    <p class="sectionTitle">{{ __('Transaction Details') }}</p>
    <table class="simple">
        <thead>
            <tr>
                <th style="width: 18%;">{{ __('Order') }}</th>
                <th style="width: 16%;">{{ __('Time') }}</th>
                <th style="width: 10%;">{{ __('Table') }}</th>
                <th style="width: 18%;">{{ __('Cashier') }}</th>
                <th style="width: 12%;">{{ __('Method') }}</th>
                <th class="right" style="width: 13%;">{{ __('Total') }}</th>
                <th class="right" style="width: 13%;">{{ __('Paid') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($payments as $p)
                <tr>
                    <td class="mono">{{ $p->kode_pesanan }}</td>
                    <td>{{ optional($p->waktu_selesai)->format('d M Y H:i') }}</td>
                    <td>{{ optional($p->meja)->nomor_meja ?? '-' }}</td>
                    <td>{{ optional($p->kasir)->name ?? '-' }}</td>
                    <td>{{ $p->metode_pembayaran ?? '-' }}</td>
                    <td class="right">Rp&nbsp;{{ number_format((float) $p->total_harga, 0, ',', '.') }}</td>
                    <td class="right">Rp&nbsp;{{ number_format((float) ($p->dibayar ?? 0), 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="muted">{{ __('No transactions in range.') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
