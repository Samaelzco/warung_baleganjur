<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <title>{{ __('Print QR') }}</title>
    <style>
        :root {
            --brand-primary: #F5BB2F;
            --brand-accent: #080A35;
        }
        @page { margin: 10mm; }
        body {
            margin: 0;
            padding: 0;
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            color: #111827;
        }
        /* 2x2 per page, sized to printable area (A4 minus margins = 190mm x 277mm) */
        .sheet {
            width: 190mm;
            height: 277mm;
        }
        .sheet + .sheet {
            page-break-before: always;
        }
        .grid {
            width: 190mm;
            height: 277mm;
            table-layout: fixed;
            border-collapse: collapse;
        }
        .cell {
            width: 95mm;
            height: 138.5mm;
            padding: 6mm;
            vertical-align: middle;
            text-align: center;
        }
        .card {
            display: inline-block;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            overflow: hidden;
            background: #ffffff;
            width: 83mm;
            height: 122mm;
        }
        .bar {
            height: 8px;
            background: var(--brand-primary);
        }
        .content {
            padding: 7mm 7mm;
        }
        .label {
            font-size: 10px;
            letter-spacing: 0.25em;
            text-transform: uppercase;
            color: #6b7280;
            text-align: center;
        }
        .title {
            margin-top: 2mm;
            font-size: 20px;
            font-weight: 700;
            color: var(--brand-accent);
            text-align: center;
        }
        .qrBox {
            margin-top: 7mm;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 6mm;
            text-align: center;
        }
        .qr {
            width: 58mm;
            height: 58mm;
        }
        .hint {
            margin-top: 6mm;
            font-size: 10px;
            color: #6b7280;
            text-align: center;
        }
    </style>
</head>
<body>
    @php
        $pages = collect($items)->chunk(4)->values();
    @endphp

    @foreach ($pages as $pageItems)
        @php($pageItems = $pageItems->values())
        <div class="sheet">
            <table class="grid">
                @for ($r = 0; $r < 2; $r++)
                    <tr>
                        @for ($c = 0; $c < 2; $c++)
                            @php($m = $pageItems->get(($r * 2) + $c))
                            <td class="cell">
                                @if ($m)
                                    <div class="card">
                                        <div class="bar"></div>
                                        <div class="content">
                                            <div class="label">{{ __('Table') }}</div>
                                            <div class="title">{{ $m['nomor_meja'] }}</div>
                                            <div class="qrBox">
                                                <img class="qr" alt="QR" src="{{ $m['qr_data_uri'] }}" />
                                            </div>
                                            <div class="hint">{{ __('Scan to open order page') }}</div>
                                        </div>
                                    </div>
                                @endif
                            </td>
                        @endfor
                    </tr>
                @endfor
            </table>
        </div>
    @endforeach
</body>
</html>
