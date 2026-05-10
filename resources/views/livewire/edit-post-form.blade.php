<div class="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950/30">
    @if ($errorMessage)
        <div role="alert" class="mb-3 rounded border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700 dark:border-rose-700 dark:bg-rose-950 dark:text-rose-200">
            {{ $errorMessage }}
        </div>
    @endif

    <form wire:submit="submit" class="space-y-3">
        @if ($isFirstPost)
            <div>
                <label for="edit-subject-{{ $postId }}" class="block text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-400">Subject</label>
                <input
                    id="edit-subject-{{ $postId }}"
                    type="text"
                    wire:model="subject"
                    maxlength="255"
                    class="mt-1 block w-full rounded border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
                >
                @error('subject')
                    <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                @enderror
            </div>
        @endif

        <div>
            <label for="edit-body-{{ $postId }}" class="block text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-400">Body</label>
            <textarea
                id="edit-body-{{ $postId }}"
                wire:model="body"
                rows="8"
                class="mt-1 block w-full rounded border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
            ></textarea>
            @error('body')
                <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center justify-end gap-2">
            <button
                type="button"
                wire:click="cancel"
                class="rounded border border-zinc-300 px-3 py-1.5 text-xs font-medium text-zinc-700 hover:bg-zinc-100 dark:border-zinc-600 dark:text-zinc-200 dark:hover:bg-zinc-800"
            >Cancel</button>
            <button
                type="submit"
                class="inline-flex items-center rounded bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 dark:focus:ring-offset-slate-900"
                wire:loading.attr="disabled"
                wire:target="submit"
            >
                <span wire:loading.remove wire:target="submit">Save changes</span>
                <span wire:loading wire:target="submit">Saving…</span>
            </button>
        </div>
    </form>
</div>
