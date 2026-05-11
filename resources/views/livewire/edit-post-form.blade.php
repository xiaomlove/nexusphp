<div class="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950/30">
    @if ($errorMessage)
        <div class="mb-3">
            <x-ui.alert variant="danger">{{ $errorMessage }}</x-ui.alert>
        </div>
    @endif

    <form wire:submit="submit" class="space-y-3">
        @if ($isFirstPost)
            <div>
                <x-ui.form-label for="edit-subject-{{ $postId }}">Subject</x-ui.form-label>
                <x-ui.form-input
                    id="edit-subject-{{ $postId }}"
                    type="text"
                    wire:model="subject"
                    maxlength="255"
                    :invalid="$errors->has('subject')"
                />
                @error('subject')
                    <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                @enderror
            </div>
        @endif

        <div>
            <x-ui.form-label for="edit-body-{{ $postId }}">Body</x-ui.form-label>
            <textarea
                id="edit-body-{{ $postId }}"
                wire:model="body"
                rows="8"
                class="block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100 @error('body') border-rose-400 dark:border-rose-600 @enderror"
            ></textarea>
            @error('body')
                <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center justify-between gap-2">
            <x-ui.button type="button" wire:click="toggleHistory" variant="secondary" size="sm">
                {{ $showHistory ? 'Hide history' : 'Edit history' }}
            </x-ui.button>
            <div class="flex items-center gap-2">
                <x-ui.button type="button" wire:click="cancel" variant="secondary" size="sm">Cancel</x-ui.button>
                <x-ui.button
                    type="submit"
                    size="sm"
                    wire:loading.attr="disabled"
                    wire:target="submit"
                >
                    <span wire:loading.remove wire:target="submit">Save changes</span>
                    <span wire:loading wire:target="submit">Saving…</span>
                </x-ui.button>
            </div>
        </div>
    </form>

    @if ($showHistory)
        <section class="mt-4 border-t border-amber-200 pt-3 dark:border-amber-800">
            <h3 class="text-xs font-semibold uppercase tracking-wider text-amber-800 dark:text-amber-300">Edit history</h3>
            @if ($history->isEmpty())
                <p class="mt-2 text-xs text-zinc-600 dark:text-zinc-400">No edits recorded for this post yet.</p>
            @else
                <ol class="mt-2 space-y-3">
                    @foreach ($history as $entry)
                        @php($entryDiff = $diffs[(int) $entry->id] ?? [])
                        <li class="rounded border border-amber-200 bg-white p-3 text-sm dark:border-amber-800 dark:bg-zinc-900/40">
                            <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-zinc-600 dark:text-zinc-400">
                                <span>
                                    Edited by
                                    <span class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $entry->editor?->username ?? 'unknown' }}</span>
                                </span>
                                <span class="flex items-center gap-2">
                                    <span title="{{ optional($entry->edited_at)->toDateTimeString() }}">
                                        {{ optional($entry->edited_at)->diffForHumans() ?? '—' }}
                                    </span>
                                    <x-ui.button
                                        type="button"
                                        wire:click="revertTo({{ (int) $entry->id }})"
                                        wire:confirm="Replace the current body with this snapshot? The current state will be saved as a new history entry."
                                        variant="secondary"
                                        size="sm"
                                        title="Restore the post body / subject from this snapshot"
                                    >Revert to this</x-ui.button>
                                </span>
                            </div>
                            @if ($entry->subject_before !== null)
                                <p class="mt-2 text-xs">
                                    <span class="font-semibold text-zinc-700 dark:text-zinc-300">Previous subject:</span>
                                    <span class="text-zinc-800 dark:text-zinc-200">{{ $entry->subject_before }}</span>
                                </p>
                            @endif
                            @if (! empty($entryDiff))
                                <pre class="mt-2 overflow-x-auto rounded bg-amber-50 p-2 font-mono text-[11px] leading-snug text-zinc-800 dark:bg-zinc-950/40 dark:text-zinc-200"><code>@foreach ($entryDiff as $line)@php($op = $line['op'])@if ($op === 'added')<span class="block bg-emerald-100 dark:bg-emerald-950/40">+ {{ $line['text'] }}</span>@elseif ($op === 'removed')<span class="block bg-rose-100 dark:bg-rose-950/40">- {{ $line['text'] }}</span>@else<span class="block">  {{ $line['text'] }}</span>@endif
@endforeach</code></pre>
                            @else
                                <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">No body changes versus the current draft.</p>
                            @endif
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>
    @endif
</div>
