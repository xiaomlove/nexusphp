@props([
    'for' => null,
])

<label
    @if ($for) for="{{ $for }}" @endif
    {{ $attributes->class([
        'mb-1 block text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400',
    ]) }}
>
    {{ $slot }}
</label>
