<div class="space-y-6">
    <div>
        <nav class="text-xs text-zinc-500 dark:text-zinc-400">
            <a href="/forum" class="hover:text-zinc-700 dark:hover:text-zinc-200">Forum</a>
            <span class="mx-1">/</span>
            <a href="/forum/{{ $forum->id }}" class="hover:text-zinc-700 dark:hover:text-zinc-200">{{ $forum->name }}</a>
            <span class="mx-1">/</span>
            <span>New topic</span>
        </nav>
        <x-ui.page-header title="Start a new topic">
            <x-slot:description>
                Posting in <strong>{{ $forum->name }}</strong>.
            </x-slot:description>
        </x-ui.page-header>
    </div>

    <x-ui.card>
        <form wire:submit="submit" class="space-y-4">
            @if ($errorMessage)
                <x-ui.alert variant="danger">{{ $errorMessage }}</x-ui.alert>
            @endif

            <div>
                <x-ui.form-label for="topic-subject">Subject</x-ui.form-label>
                <x-ui.form-input
                    id="topic-subject"
                    type="text"
                    wire:model="subject"
                    maxlength="255"
                    autocomplete="off"
                    :invalid="$errors->has('subject')"
                />
                @error('subject')
                    <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <x-ui.form-label for="topic-body">Body</x-ui.form-label>
                <textarea
                    id="topic-body"
                    wire:model="body"
                    rows="10"
                    placeholder="Write your post… BBCode supported (b, i, u, url, img, quote, code, list)."
                    class="block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100 @error('body') border-rose-400 dark:border-rose-600 @enderror"
                ></textarea>
                @error('body')
                    <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center justify-between border-t border-zinc-200 pt-3 dark:border-zinc-800">
                <x-ui.button href="/forum/{{ $forum->id }}" variant="ghost" size="sm">Cancel</x-ui.button>
                <x-ui.button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="submit"
                >
                    <span wire:loading.remove wire:target="submit">Post topic</span>
                    <span wire:loading wire:target="submit">Posting…</span>
                </x-ui.button>
            </div>
        </form>
    </x-ui.card>
</div>
