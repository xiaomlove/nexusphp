<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#2b8aef">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">
    <title>{{ $title ?? 'Browse' }} | {{ \App\Models\Setting::getSiteName() }}</title>
    @php
        $reverbCfg = [
            'key' => (string) (env('REVERB_APP_KEY') ?: ''),
            'host' => (string) (env('REVERB_HOST') ?: ($_SERVER['HTTP_HOST'] ?? '')),
            'port' => (int) (env('REVERB_PORT') ?: 8080),
            'scheme' => (string) (env('REVERB_SCHEME') ?: 'http'),
        ];
    @endphp
    <script>window.__REVERB__ = @json($reverbCfg);</script>
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/echo.js', 'resources/js/pwa.js'])
    @livewireStyles
    <script>
        (function () {
            const stored = localStorage.getItem('theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (stored === 'dark' || (!stored && prefersDark)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
</head>
<body class="h-full bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
<header class="sticky top-0 z-30 border-b border-zinc-200 bg-white/80 backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/80">
    <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
        <div class="flex items-center gap-3">
            <a href="/index.php" class="text-lg font-semibold tracking-tight text-zinc-900 dark:text-zinc-50">
                {{ \App\Models\Setting::getSiteName() }}
            </a>
            <nav class="hidden items-center gap-1 text-sm md:flex">
                <a href="/torrents.php" class="rounded-md px-3 py-1.5 text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100">
                    Classic browse
                </a>
                <a href="/browse" class="rounded-md bg-primary-50 px-3 py-1.5 font-medium text-primary-700 dark:bg-primary-950/40 dark:text-primary-300">
                    Modern browse
                </a>
                <a href="/forums.php" class="rounded-md px-3 py-1.5 text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100">
                    Forums
                </a>
                <a href="/messages.php" class="rounded-md px-3 py-1.5 text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100">
                    Messages
                </a>
                <a href="/usercp.php" class="rounded-md px-3 py-1.5 text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100">
                    Control panel
                </a>
            </nav>
        </div>
        <x-ui.theme-toggle />
    </div>
</header>

<main class="mx-auto max-w-7xl px-4 py-6 sm:px-6">
    {{-- Supports two render modes:
         - Livewire ->layout('layouts.livewire-app', ...) passes content via $slot.
         - Plain Blade views can @extends this layout and @section('content').
         When the @section is undefined @yield prints nothing, so $slot still wins for Livewire. --}}
    @hasSection('content')
        @yield('content')
    @else
        {{ $slot ?? '' }}
    @endif
</main>

@livewireScripts
</body>
</html>
