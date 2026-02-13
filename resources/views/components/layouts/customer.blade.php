<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head-customer')
    </head>
    <body class="flex min-h-svh flex-col bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        <main class="mx-auto flex w-full max-w-3xl flex-1 flex-col px-4 pb-12 pt-10 sm:px-6">
            {{ $slot }}
        </main>

        <footer class="hidden mx-auto w-full max-w-3xl px-4 pb-6 sm:px-6">
            <div class="flex items-center justify-between border-t border-neutral-200/70 pt-4 text-xs font-semibold tracking-wide text-neutral-500 dark:border-neutral-800/70 dark:text-neutral-400">
                <span class="text-[var(--brand-accent)] dark:text-white">Warung Baleganjur</span>
                <span>© {{ now()->format('Y') }}</span>
            </div>
        </footer>

        @fluxScripts
    </body>
</html>
