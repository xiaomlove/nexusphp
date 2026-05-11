{{--
    Renders a Laravel-controller body inside the legacy site chrome.

    The chrome (header markup, navigation, footer) is produced by the
    legacy `stdhead()` / `stdfoot()` functions in
    `include/functions.php`, captured into strings by
    `App\Legacy\LegacyChrome`. This keeps callers entirely in modern
    Laravel land — no `require_once "include/bittorrent.php"` at the
    controller level.

    Usage:

        return view('layouts.legacy', [
            'title'   => 'Logout',
            'content' => '<p>You are logged out.</p>',
        ]);

    `content` is rendered as-is (it is HTML produced by the
    controller). Components that prefer slots can pass `$slot` —
    e.g. when used as an `x-layouts.legacy` component.

    Phase 1.3 of the strangler-fig migration. See
    docs/legacy-strategy.md. When Phase 5 replaces stdhead/stdfoot
    with native Blade partials, only this layout has to change —
    every controller using it stays the same.
--}}
@php
    /** @var \App\Legacy\LegacyChrome $__legacyChrome */
    $__legacyChrome = app(\App\Legacy\LegacyChrome::class);
    $__legacyTitle = $title ?? '';
    $__legacyBody = $content ?? ($slot ?? '');
@endphp
{!! $__legacyChrome->head($__legacyTitle) !!}
{!! $__legacyBody !!}
{!! $__legacyChrome->foot() !!}
