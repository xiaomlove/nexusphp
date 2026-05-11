{{--
    Dark-mode toggle button. Reads/writes the same `theme` localStorage
    key + `dark` <html> class that `resources/views/layouts/livewire-app.blade.php`
    bootstraps in <head>, so dropping this button anywhere keeps the
    site in sync.

    Renderable standalone, or via `<x-ui.theme-toggle />` inside any
    Modern UI page.
--}}

<button
    type="button"
    onclick="
        const root = document.documentElement;
        const next = root.classList.toggle('dark');
        try { localStorage.setItem('theme', next ? 'dark' : 'light'); } catch (e) {}
    "
    {{ $attributes->class([
        'inline-flex h-9 w-9 items-center justify-center rounded-md border border-zinc-200 bg-white text-zinc-600 transition hover:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700',
    ])->merge([
        'title' => 'Toggle theme',
        'aria-label' => 'Toggle theme',
    ]) }}
>
    <svg class="h-4 w-4 dark:hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/>
    </svg>
    <svg class="hidden h-4 w-4 dark:inline" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707m12.728 0l-.707-.707M6.343 6.343l-.707-.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
    </svg>
</button>
