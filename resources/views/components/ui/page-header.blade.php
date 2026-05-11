@props([
    'title' => '',
])

<div {{ $attributes->class(['flex flex-col gap-2']) }}>
    <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">
        {{ $title === '' ? $slot : $title }}
    </h1>
    @isset($description)
        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $description }}</p>
    @endisset
</div>
