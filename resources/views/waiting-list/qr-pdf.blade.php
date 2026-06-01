<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('Waiting List QR') }}</title>
    <style>
        :root {
            --brand-primary: #F5BB2F;
            --brand-accent: #080A35;
        }

        @page {
            margin: 18mm;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            color: #111827;
            margin: 0;
        }

        .sheet {
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            overflow: hidden;
        }

        .brand-bar {
            height: 14px;
            background: var(--brand-primary);
        }

        .content {
            padding: 28px;
            text-align: center;
        }

        .title {
            color: var(--brand-accent);
            font-size: 28px;
            font-weight: 700;
            margin: 0;
        }

        .subtitle {
            color: #6b7280;
            font-size: 13px;
            margin-top: 8px;
        }

        .qr-wrap {
            display: inline-block;
            margin-top: 28px;
            padding: 16px;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
        }

        .qr {
            width: 310px;
            height: 310px;
        }

        .url {
            margin-top: 18px;
            color: #374151;
            font-size: 12px;
            word-break: break-all;
        }

        .footer {
            margin-top: 24px;
            color: #6b7280;
            font-size: 11px;
        }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="brand-bar"></div>
        <div class="content">
            <h1 class="title">{{ __('Waiting List') }}</h1>
            <div class="subtitle">{{ __('Scan to open customer waiting list page') }}</div>

            <div class="qr-wrap">
                <img class="qr" alt="QR" src="{{ $qrDataUri }}">
            </div>

            <div class="url">{{ $url }}</div>
            <div class="footer">{{ __('Generated') }}: {{ $generatedAt->format('d M Y H:i') }}</div>
        </div>
    </div>
</body>
</html>
