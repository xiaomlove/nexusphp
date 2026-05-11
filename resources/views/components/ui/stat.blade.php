@props([
    'label' => '',
    'value' => '',
    'variant' => 'neutral',
])

@php
    $variants = [
        'neutral' => 'text-zinc-900 dark:text-zinc-50',
        'primary' => 'text-primary-700 dark:text-primary-300',
        'success' => 'text-success-700 dark:text-success-300',
        'warning' => 'text-warning-700 dark:text-warning-300',
        'danger' => 'text-danger-700 dark:text-danger-300',
    ];
    $valueClass = $variants[$variant] ?? $variants['neutral'];
@endphp

<div {{ $attributes->class([
    'rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900',
]) }}>
    <p class="text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ $label }}</p>
    <p class="mt-2 text-2xl font-semibold {{ $valueClass }}">{{ $value === '' ? $slot : $value }}</p>
</div>
