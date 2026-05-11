@props([
    'title' => '',
])

<div {{ $attributes->class([
    'rounded-xl border border-dashed border-zinc-300 bg-white p-10 text-center dark:border-zinc-700 dark:bg-zinc-900',
]) }}>
    @if ($title !== '')
        <p class="text-base font-medium text-zinc-700 dark:text-zinc-300">{{ $title }}</p>
    @endif
    @if (trim($slot) !== '')
        <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $slot }}</div>
    @endif
    @isset($action)
        <div class="mt-4 flex justify-center">{{ $action }}</div>
    @endisset
</div>
