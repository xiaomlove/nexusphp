<div class="space-y-6">
    <x-ui.page-header title="Unread topics">
        <x-slot:description>
            Topics with new posts since you last caught up. Same data as the legacy "view unread posts" page.
        </x-slot:description>
    </x-ui.page-header>

    <div class="flex justify-end gap-2">
        <x-ui.button href="/forum" variant="secondary" size="sm" title="Back to the forum index">
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Forum index
        </x-ui.button>
        <x-ui.button
            type="button"
            wire:click="catchUp"
            wire:confirm="Mark every forum post as read? This clears your per-topic read markers."
            variant="secondary"
            size="sm"
            title="Mark every post as read"
        >
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
            Catch up
        </x-ui.button>
    </div>

    @if ($rows->isEmpty())
        <x-ui.empty-state title="Nothing unread.">
            You're all caught up. New posts will show up here.
        </x-ui.empty-state>
    @else
        <x-ui.card padding="none" class="overflow-hidden">
            <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @foreach ($rows as $row)
                    @php
                        $unreadDeepLink = '/forum/'.$row['forum_id'].'/topic/'.$row['id'];
                    @endphp
                    <li class="p-3 sm:px-4">
                        <div class="grid grid-cols-12 items-center gap-3">
                            <div class="col-span-12 sm:col-span-8">
                                <a href="{{ $unreadDeepLink }}"
                                   class="text-sm font-semibold text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300"
                                   wire:navigate.hover>
                                    {{ $row['subject'] }}
                                </a>
                            </div>
                            <div class="col-span-12 text-xs text-zinc-500 dark:text-zinc-400 sm:col-span-4 sm:text-right">
                                in
                                <a href="/forum/{{ $row['forum_id'] }}"
                                   class="font-medium text-zinc-700 hover:text-primary-600 dark:text-zinc-300 dark:hover:text-primary-400"
                                   wire:navigate.hover>
                                    {{ $row['forum_name'] }}
                                </a>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>

        @if ($nextCursor !== null)
            <div class="flex justify-center">
                <x-ui.button href="/forum/unread?beforepostid={{ $nextCursor }}" variant="secondary" size="sm">
                    Show more
                </x-ui.button>
            </div>
        @endif
    @endif
</div>
