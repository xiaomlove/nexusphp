@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'type' => 'button',
])

@php
    $variants = [
        'primary' => 'border-transparent bg-primary-600 text-white shadow-sm hover:bg-primary-500 focus-visible:ring-primary-500/40 disabled:bg-primary-300 dark:disabled:bg-primary-800',
        'secondary' => 'border-zinc-200 bg-white text-zinc-700 hover:bg-zinc-50 hover:text-zinc-900 focus-visible:ring-primary-500/40 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-700',
        'ghost' => 'border-transparent text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 focus-visible:ring-primary-500/40 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100',
        'danger' => 'border-transparent bg-danger-600 text-white shadow-sm hover:bg-danger-500 focus-visible:ring-danger-500/40',
    ];
    $sizes = [
        'sm' => 'px-2.5 py-1 text-xs',
        'md' => 'px-3 py-1.5 text-sm',
        'lg' => 'px-4 py-2 text-base',
    ];
    $base = 'inline-flex items-center justify-center gap-1.5 rounded-md border font-medium transition focus:outline-none focus-visible:ring-2 disabled:cursor-not-allowed';
    $classes = [
        $base,
        $variants[$variant] ?? $variants['primary'],
        $sizes[$size] ?? $sizes['md'],
    ];
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>
        {{ $slot }}
    </button>
@endif
