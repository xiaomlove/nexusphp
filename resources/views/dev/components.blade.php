@extends('layouts.livewire-app', ['title' => 'Components gallery'])

@section('content')
    <div class="space-y-10" data-test-id="components-gallery">
        <x-ui.page-header title="UI components">
            <x-slot:description>
                Internal showcase of <code class="rounded bg-zinc-100 px-1 py-0.5 text-xs dark:bg-zinc-800">resources/views/components/ui/</code>
                — the shared Modern UI design system. New Livewire / Blade pages should
                compose these blocks rather than re-implementing Tailwind class strings.
            </x-slot:description>
        </x-ui.page-header>

        {{-- ============================================================ --}}
        <section class="space-y-3" data-section="page-header">
            <h2 class="text-lg font-semibold text-zinc-800 dark:text-zinc-200">Page header</h2>
            <x-ui.card>
                <x-ui.page-header title="Example page title">
                    <x-slot:description>
                        Optional supporting copy. Use the <code class="text-xs">description</code> slot.
                    </x-slot:description>
                </x-ui.page-header>
            </x-ui.card>
        </section>

        {{-- ============================================================ --}}
        <section class="space-y-3" data-section="buttons">
            <h2 class="text-lg font-semibold text-zinc-800 dark:text-zinc-200">Buttons</h2>
            <x-ui.card>
                <div class="flex flex-wrap items-center gap-2">
                    <x-ui.button>Primary</x-ui.button>
                    <x-ui.button variant="secondary">Secondary</x-ui.button>
                    <x-ui.button variant="ghost">Ghost</x-ui.button>
                    <x-ui.button variant="danger">Danger</x-ui.button>
                </div>
                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <x-ui.button size="sm">Small</x-ui.button>
                    <x-ui.button size="md">Medium</x-ui.button>
                    <x-ui.button size="lg">Large</x-ui.button>
                </div>
                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <x-ui.button href="#" variant="primary">Link as button</x-ui.button>
                    <x-ui.button disabled>Disabled</x-ui.button>
                </div>
            </x-ui.card>
        </section>

        {{-- ============================================================ --}}
        <section class="space-y-3" data-section="badges">
            <h2 class="text-lg font-semibold text-zinc-800 dark:text-zinc-200">Badges</h2>
            <x-ui.card>
                <div class="flex flex-wrap items-center gap-2">
                    <x-ui.badge>Neutral</x-ui.badge>
                    <x-ui.badge variant="primary">Primary</x-ui.badge>
                    <x-ui.badge variant="success">Free</x-ui.badge>
                    <x-ui.badge variant="warning">50%</x-ui.badge>
                    <x-ui.badge variant="danger">H&amp;R</x-ui.badge>
                </div>
                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <x-ui.badge size="sm" variant="success">Small free</x-ui.badge>
                    <x-ui.badge size="md" variant="success">Medium free</x-ui.badge>
                    <x-ui.badge size="lg" variant="success">Large free</x-ui.badge>
                </div>
            </x-ui.card>
        </section>

        {{-- ============================================================ --}}
        <section class="space-y-3" data-section="form-controls">
            <h2 class="text-lg font-semibold text-zinc-800 dark:text-zinc-200">Form controls</h2>
            <x-ui.card>
                <div class="grid gap-4 md:grid-cols-3">
                    <div>
                        <x-ui.form-label for="demo-search">Search</x-ui.form-label>
                        <x-ui.form-input id="demo-search" type="search" placeholder="Title or description…" />
                    </div>
                    <div>
                        <x-ui.form-label for="demo-category">Category</x-ui.form-label>
                        <x-ui.form-select id="demo-category">
                            <option>All categories</option>
                            <option>Movies</option>
                            <option>TV</option>
                            <option>Music</option>
                        </x-ui.form-select>
                    </div>
                    <div>
                        <x-ui.form-label for="demo-invalid">Invalid state</x-ui.form-label>
                        <x-ui.form-input id="demo-invalid" :invalid="true" value="bad value" />
                    </div>
                </div>
            </x-ui.card>
        </section>

        {{-- ============================================================ --}}
        <section class="space-y-3" data-section="alerts">
            <h2 class="text-lg font-semibold text-zinc-800 dark:text-zinc-200">Alerts</h2>
            <div class="grid gap-3 md:grid-cols-2">
                <x-ui.alert variant="info" title="Heads up">
                    Modern UI is opt-in for now. Existing classic browse still works.
                </x-ui.alert>
                <x-ui.alert variant="success" title="Saved">
                    Your changes were saved successfully.
                </x-ui.alert>
                <x-ui.alert variant="warning" title="Heads up">
                    H&amp;R requirements not yet met on this torrent.
                </x-ui.alert>
                <x-ui.alert variant="danger" title="Login locked">
                    Too many failed attempts. Try again in 10 minutes.
                </x-ui.alert>
            </div>
        </section>

        {{-- ============================================================ --}}
        <section class="space-y-3" data-section="stats">
            <h2 class="text-lg font-semibold text-zinc-800 dark:text-zinc-200">Stats</h2>
            <div class="grid gap-3 md:grid-cols-4">
                <x-ui.stat label="Uploaded" value="1.42 TB" />
                <x-ui.stat label="Downloaded" value="320 GB" variant="primary" />
                <x-ui.stat label="Ratio" value="4.55" variant="success" />
                <x-ui.stat label="Hit &amp; Run" value="2" variant="danger" />
            </div>
        </section>

        {{-- ============================================================ --}}
        <section class="space-y-3" data-section="cards">
            <h2 class="text-lg font-semibold text-zinc-800 dark:text-zinc-200">Cards</h2>
            <div class="grid gap-3 md:grid-cols-3">
                <x-ui.card>
                    <p class="text-sm text-zinc-700 dark:text-zinc-300">Default card.</p>
                </x-ui.card>
                <x-ui.card variant="muted">
                    <p class="text-sm text-zinc-700 dark:text-zinc-300">Muted card.</p>
                </x-ui.card>
                <x-ui.card variant="dashed">
                    <p class="text-sm text-zinc-700 dark:text-zinc-300">Dashed card (for placeholders).</p>
                </x-ui.card>
            </div>
        </section>

        {{-- ============================================================ --}}
        <section class="space-y-3" data-section="empty-state">
            <h2 class="text-lg font-semibold text-zinc-800 dark:text-zinc-200">Empty state</h2>
            <x-ui.empty-state title="No results">
                Try clearing filters or broadening the search.
                <x-slot:action>
                    <x-ui.button variant="secondary" size="sm">Clear filters</x-ui.button>
                </x-slot:action>
            </x-ui.empty-state>
        </section>

        {{-- ============================================================ --}}
        <section class="space-y-3" data-section="theme-toggle">
            <h2 class="text-lg font-semibold text-zinc-800 dark:text-zinc-200">Theme toggle</h2>
            <x-ui.card>
                <div class="flex items-center gap-4">
                    <x-ui.theme-toggle data-test-id="inline-theme-toggle" />
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">
                        Click to switch the page between light and dark. State is persisted in
                        <code class="rounded bg-zinc-100 px-1 py-0.5 text-xs dark:bg-zinc-800">localStorage['theme']</code>
                        and the same <code class="rounded bg-zinc-100 px-1 py-0.5 text-xs dark:bg-zinc-800">.dark</code>
                        class is applied to <code class="text-xs">&lt;html&gt;</code> as elsewhere on the site.
                    </p>
                </div>
            </x-ui.card>
        </section>
    </div>
@endsection
