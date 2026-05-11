@props([
    'variant' => 'info',
    'title' => null,
])

@php
    $variants = [
        'info' => 'border-primary-200 bg-primary-50 text-primary-900 dark:border-primary-800/60 dark:bg-primary-950/30 dark:text-primary-100',
        'success' => 'border-success-200 bg-success-50 text-success-900 dark:border-success-800/60 dark:bg-success-950/30 dark:text-success-100',
        'warning' => 'border-warning-200 bg-warning-50 text-warning-900 dark:border-warning-800/60 dark:bg-warning-950/30 dark:text-warning-100',
        'danger' => 'border-danger-200 bg-danger-50 text-danger-900 dark:border-danger-800/60 dark:bg-danger-950/30 dark:text-danger-100',
    ];
    $classes = ['rounded-lg border p-3 text-sm', $variants[$variant] ?? $variants['info']];
@endphp

<div role="alert" {{ $attributes->class($classes) }}>
    @if ($title)
        <p class="font-semibold">{{ $title }}</p>
    @endif
    <div @class(['mt-0.5' => $title])>{{ $slot }}</div>
</div>
