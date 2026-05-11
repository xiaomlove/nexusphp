@props([
    'variant' => 'neutral',
    'size' => 'md',
])

@php
    $variants = [
        'neutral' => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300',
        'primary' => 'bg-primary-100 text-primary-800 dark:bg-primary-900/40 dark:text-primary-200',
        'success' => 'bg-success-100 text-success-800 dark:bg-success-900/40 dark:text-success-200',
        'warning' => 'bg-warning-100 text-warning-800 dark:bg-warning-900/40 dark:text-warning-200',
        'danger' => 'bg-danger-100 text-danger-800 dark:bg-danger-900/40 dark:text-danger-200',
    ];
    $sizes = [
        'sm' => 'px-1.5 py-0.5 text-[10px]',
        'md' => 'px-2 py-0.5 text-xs',
        'lg' => 'px-2.5 py-1 text-sm',
    ];
    $classes = [
        'inline-flex items-center rounded-md font-semibold uppercase tracking-wider',
        $variants[$variant] ?? $variants['neutral'],
        $sizes[$size] ?? $sizes['md'],
    ];
@endphp

<span {{ $attributes->class($classes) }}>{{ $slot }}</span>
