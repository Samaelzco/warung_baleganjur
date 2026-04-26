<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        <div class="relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0">
            <div class="relative hidden h-full flex-col overflow-hidden bg-[var(--brand-accent)] p-10 text-white lg:flex">
                <img
                    src="{{ asset('img/Gemini_Generated_Image_wlrwmywlrwmywlrw.webp') }}"
                    alt=""
                    class="absolute inset-0 h-full w-full object-cover"
                >
                <div class="absolute inset-0 bg-[linear-gradient(90deg,rgba(8,10,53,0.86)_0%,rgba(8,10,53,0.52)_48%,rgba(8,10,53,0.32)_100%)]"></div>
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_24%_26%,rgba(245,187,47,0.24),transparent_34%),linear-gradient(180deg,rgba(8,10,53,0.18),rgba(8,10,53,0.58))]"></div>
                <div class="absolute inset-y-0 end-0 w-px bg-black/10"></div>

                <a href="{{ route('home') }}" class="relative z-20 flex items-center" wire:navigate>
                    <img src="{{ asset('img/logo/logo 2.svg') }}" alt="Warung Baleganjur Logo" class="h-14 w-auto object-contain" />
                </a>

                <div class="relative z-20 my-auto max-w-xl space-y-8">
                    <div class="space-y-4">
                        <h1 class="text-5xl font-semibold leading-tight">
                            {{ __('Run your restaurant from one simple dashboard.') }}
                        </h1>
                        <p class="max-w-lg text-lg leading-8 text-white/75">
                            {{ __('Manage QR orders, tables, kitchen display, payments, and sales reports for Warung Baleganjur.') }}
                        </p>
                    </div>
                </div>

                <div class="relative z-20 mt-auto text-sm font-medium text-white/65">
                    &copy; 2026 Warung Baleganjur. {{ __('All rights reserved.') }}
                </div>
            </div>
            <div class="w-full lg:p-8">
                <div class="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[350px]">
                    <a href="{{ route('home') }}" class="z-20 flex flex-col items-center gap-2 font-medium lg:hidden" wire:navigate>
                        <span class="flex w-full items-center justify-center">
                            <img src="{{ asset('img/logo/logo 2 black.svg') }}" alt="Warung Baleganjur Logo" class="h-24 w-auto max-w-[220px] object-contain dark:hidden" />
                            <img src="{{ asset('img/logo/logo 2.svg') }}" alt="Warung Baleganjur Logo" class="hidden h-24 w-auto max-w-[220px] object-contain dark:block" />
                        </span>

                        <span class="sr-only">{{ config('app.name', 'Laravel') }}</span>
                    </a>
                    {{ $slot }}
                </div>
            </div>
        </div>
        @fluxScripts
    </body>
</html>
