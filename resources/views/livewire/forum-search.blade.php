@php
    $_keywords = trim($keywords);
@endphp

<div class="space-y-6">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <nav class="text-xs text-zinc-500 dark:text-zinc-400">
                <a href="/forum" class="hover:text-zinc-700 dark:hover:text-zinc-200">Forum</a>
                <span class="mx-1">/</span>
                <span>Search</span>
            </nav>
            <x-ui.page-header title="Search forum posts">
                <x-slot:description>Substring match against topic titles (first post) and post bodies, restricted to forums you can read.</x-slot:description>
            </x-ui.page-header>
        </div>
        <div class="flex justify-end gap-2">
            <x-ui.button href="/forum" variant="secondary" size="sm" title="Back to forum index" wire:navigate.hover>
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Forum index
            </x-ui.button>
        </div>
    </div>

    <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
        <form wire:submit.prevent class="flex flex-col gap-2 sm:flex-row sm:items-end">
            <label class="flex-1 text-sm">
                <span class="mb-1 block text-xs font-medium text-zinc-500 dark:text-zinc-400">Keywords</span>
                <input
                    type="text"
                    wire:model.live.debounce.400ms="keywords"
                    placeholder="word or phrase..."
                    class="block w-full rounded-md border-zinc-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100"
                />
            </label>
            @if ($_keywords !== '')
                <x-ui.button type="button" wire:click="clear" variant="secondary" size="sm">Clear</x-ui.button>
            @endif
        </form>
    </div>

    @if ($_keywords === '')
        <x-ui.empty-state title="Type a keyword above to search the forum.">
            Searches scan both topic titles and post bodies.
        </x-ui.empty-state>
    @elseif ($results->total() === 0)
        <x-ui.empty-state title="No posts matched your search.">
            Try fewer or different keywords.
        </x-ui.empty-state>
    @else
        <div class="text-xs text-zinc-500 dark:text-zinc-400">
            {{ $results->total() }} matching post{{ $results->total() === 1 ? '' : 's' }}.
        </div>
        <div class="overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700">
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead class="bg-zinc-50 text-left text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                    <tr>
                        <th class="px-3 py-2">Topic</th>
                        <th class="px-3 py-2">Forum</th>
                        <th class="px-3 py-2">Posted by</th>
                        <th class="px-3 py-2">When</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 bg-white dark:divide-zinc-800 dark:bg-zinc-900">
                    @foreach ($results->items() as $row)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                            <td class="px-3 py-2">
                                <a
                                    href="/forum/{{ (int) $row->forumid }}/topic/{{ (int) $row->topicid }}#post-{{ (int) $row->post_id }}"
                                    class="font-medium text-primary-600 hover:underline dark:text-primary-400"
                                >
                                    {{ $row->subject }}
                                </a>
                            </td>
                            <td class="px-3 py-2">
                                <a
                                    href="/forum/{{ (int) $row->forumid }}"
                                    class="text-zinc-700 hover:underline dark:text-zinc-200"
                                >
                                    {{ $row->forumname }}
                                </a>
                            </td>
                            <td class="px-3 py-2">
                                <a
                                    href="/userdetails.php?id={{ (int) $row->userid }}"
                                    class="text-zinc-700 hover:underline dark:text-zinc-200"
                                >
                                    user #{{ (int) $row->userid }}
                                </a>
                            </td>
                            <td class="px-3 py-2 text-zinc-500 dark:text-zinc-400">
                                {{ $row->added }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div>{{ $results->links() }}</div>
    @endif
</div>
