<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>{{ __('Print QR') }}</title>

    @vite(['resources/css/app.css'])

    <style>
        :root {
            color-scheme: light;
        }
        body {
            background: #fff;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .no-print { display: block; }
        .page {
            min-height: calc(100vh - 4rem);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem 1rem;
        }
        .card {
            width: 100%;
            max-width: 720px;
            border: 1px solid rgba(229, 229, 229, 1);
            border-radius: 1.25rem;
            overflow: hidden;
            background: #fff;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
        }
        .brand-bar {
            height: 10px;
            background: var(--brand-primary);
        }
        .qr-wrap {
            border: 1px solid rgba(229, 229, 229, 1);
            border-radius: 0.9rem;
            background: #fff;
            padding: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .qr-img {
            width: min(70vw, 420px);
            height: min(70vw, 420px);
        }

        @media print {
            @page { margin: 10mm; }
            .no-print { display: none !important; }
            .page {
                padding: 0;
                min-height: auto;
                break-after: page;
            }
            .card {
                max-width: none;
                box-shadow: none;
            }
            .qr-img {
                width: 140mm;
                height: 140mm;
            }
        }
    </style>
</head>
<body class="text-neutral-900">
    <div class="no-print mx-auto flex max-w-5xl items-center justify-between gap-3 px-4 py-4">
        <div>
            <div class="text-lg font-semibold">{{ __('Print QR') }}</div>
            <div class="text-sm text-neutral-500">{{ __('Only the table card will be printed.') }}</div>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('meja.index') }}" class="btn-ghost-accent inline-flex items-center gap-2 rounded-xl px-3 py-2 text-sm">
                {{ __('Back') }}
            </a>
            <button onclick="window.print()" class="btn-brand inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm">
                {{ __('Print') }}
            </button>
        </div>
    </div>

    @forelse ($items as $m)
        <div class="page">
            <article class="card">
                <div class="brand-bar"></div>
                <div class="p-5 sm:p-7">
                    <div class="text-center">
                        <div class="text-xs font-semibold uppercase tracking-[0.2em] text-neutral-400">{{ __('Table') }}</div>
                        <div class="mt-1 text-3xl font-semibold text-[color:var(--brand-accent)]">{{ $m->nomor_meja }}</div>
                    </div>

                    <div class="mt-5 qr-wrap">
                        <img
                            alt="QR"
                            class="qr-img object-contain"
                            src="{{ route('meja.qr', ['token' => $m->qr_token, 'size' => 1024, 'format' => 'svg'], false) }}"
                        />
                    </div>

                    <div class="mt-5 text-center text-xs text-neutral-500">
                        {{ __('Scan to open order page') }}
                    </div>
                </div>
            </article>
        </div>
    @empty
        <div class="page">
            <div class="text-sm text-neutral-500">{{ __('No data') }}</div>
        </div>
    @endforelse
</body>
</html>

