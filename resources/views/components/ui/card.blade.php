@props([
    'variant' => 'default',
    'padding' => 'md',
])

@php
    $variants = [
        'default' => 'border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900',
        'dashed' => 'border-dashed border-zinc-300 bg-white dark:border-zinc-700 dark:bg-zinc-900',
        'muted' => 'border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-950',
    ];
    $paddings = [
        'none' => '',
        'sm' => 'p-3',
        'md' => 'p-4',
        'lg' => 'p-6',
    ];
    $classes = ['rounded-xl border', $variants[$variant] ?? $variants['default'], $paddings[$padding] ?? $paddings['md']];
@endphp

<div {{ $attributes->class($classes) }}>
    {{ $slot }}
</div>
