@props([
    'type' => 'text',
    'invalid' => false,
])

@php
    $base = 'block w-full rounded-md border bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:outline-none focus:ring-2 dark:bg-zinc-800 dark:text-zinc-100';
    $border = $invalid
        ? 'border-danger-400 focus:border-danger-500 focus:ring-danger-500/30 dark:border-danger-600'
        : 'border-zinc-300 focus:border-primary-500 focus:ring-primary-500/30 dark:border-zinc-700';
@endphp

<input
    type="{{ $type }}"
    {{ $attributes->class([$base, $border]) }}
/>
