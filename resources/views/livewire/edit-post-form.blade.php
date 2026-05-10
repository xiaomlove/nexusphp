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

        <div class="flex items-center justify-between gap-2">
            <button
                type="button"
                wire:click="toggleHistory"
                class="rounded border border-zinc-300 px-3 py-1.5 text-xs font-medium text-zinc-700 hover:bg-zinc-100 dark:border-zinc-600 dark:text-zinc-200 dark:hover:bg-zinc-800"
            >{{ $showHistory ? 'Hide history' : 'Edit history' }}</button>
            <div class="flex items-center gap-2">
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
                                    <button
                                        type="button"
                                        wire:click="revertTo({{ (int) $entry->id }})"
                                        wire:confirm="Replace the current body with this snapshot? The current state will be saved as a new history entry."
                                        class="rounded border border-amber-300 bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-900 hover:bg-amber-200 dark:border-amber-700 dark:bg-amber-900/40 dark:text-amber-200 dark:hover:bg-amber-900/60"
                                        title="Restore the post body / subject from this snapshot"
                                    >Revert to this</button>
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
