{{--
    Body of the `aboutnexus.php` page, rendered into the legacy site
    chrome via `view('layouts.legacy', ['title' => ..., 'content' => ...])`
    by `App\Http\Controllers\Legacy\AboutNexusController`.

    Phase 3 of the legacy migration. The original `public/aboutnexus.php`
    interleaved DB lookups, version constants, and HTML emission inline;
    this view receives a typed bag from `AboutNexusService` and only
    deals with rendering.

    All translated labels come pre-resolved in the `$labels` map, which
    overlays a per-locale `lang_aboutnexus.php` on top of the English
    defaults. See `AboutNexusService::loadTranslations()` for details.

    Variables in scope:
      - $labels         array<string,string>  translated UI labels
      - $version        array{project_name,project_url,version_number,release_date,site_name}
      - $languages      Collection<int, array{id,lang_name,flagpic,trans_state}>
      - $stylesheets    Collection<int, array{id,name,designer,comment}>
--}}
@php
    /** @var array<string,string> $labels */
    /** @var array{project_name:string,project_url:string,version_number:string,release_date:string,site_name:string} $version */
    /** @var \Illuminate\Support\Collection $languages */
    /** @var \Illuminate\Support\Collection $stylesheets */

    $label = static fn (string $key, string $fallback = '') => $labels[$key] ?? $fallback;
@endphp

<h1>{{ $version['project_name'] }}</h1>

<div id="version-frame">
    <h2><span id="version">{{ $label('text_version', 'Version') }}</span></h2>
    <p>{!! sprintf(
        e($label('text_version_note', 'This tracker %s is powered by %s. The following is version detail.')),
        e($version['site_name']),
        e($version['project_name']),
    ) !!}</p>
    <table class="main" border="1" cellspacing="0" cellpadding="5" align="center">
        <tr>
            <td class="rowhead">{{ $label('text_main_version', 'Main Version') }}</td>
            <td>{{ $version['project_name'] }}</td>
        </tr>
        <tr>
            <td class="rowhead">{{ $label('text_sub_version', 'Sub Version') }}</td>
            <td>{{ $version['version_number'] }}</td>
        </tr>
        <tr>
            <td class="rowhead">{{ $label('text_release_date', 'Release Date') }}</td>
            <td>{{ $version['release_date'] }}</td>
        </tr>
    </table>
</div>

<div id="nexus-frame">
    <h2><span id="nexus">{{ $label('text_nexus', 'About ').$version['project_name'] }}</span></h2>
    <p>{!! $version['project_name'].sprintf(
        e($label('text_nexus_note', '')),
        e($version['project_name']),
    ) !!}</p>
</div>

<div id="authorization-frame">
    <h2><span id="authorization">{{ $label('text_authorization', 'About Authorization') }}</span></h2>
    <p>{!! sprintf(
        e($label('text_authorization_note', '')),
        e($version['project_name']),
    ) !!}</p>
</div>

<div id="translation-frame">
    <h2><span id="translation">{{ $label('text_translation', 'About Translation') }}</span></h2>
    <p>{!! $version['project_name'].e($label('text_translation_note', '')) !!}</p>
    <table class="main" border="1" cellspacing="0" cellpadding="5" align="center">
        <tr>
            <td class="colhead">{{ $label('text_flag', 'Flag') }}</td>
            <td class="colhead">{{ $label('text_language', 'Language') }}</td>
            <td class="colhead">{{ $label('text_state', 'State') }}</td>
        </tr>
        @foreach ($languages as $row)
            <tr>
                <td class="rowfollow">
                    <img width="24" height="15"
                         src="pic/flag/{{ $row['flagpic'] }}"
                         alt="{{ $row['lang_name'] }}"
                         title="{{ $row['lang_name'] }}"
                         style="padding-bottom:1px;" />
                </td>
                <td class="rowfollow">{{ $row['lang_name'] }}</td>
                <td class="rowfollow">{{ $row['trans_state'] }}</td>
            </tr>
        @endforeach
    </table>
</div>

<div id="stylesheet-frame">
    <h2><span id="stylesheet">{{ $label('text_stylesheet', 'About Stylesheet') }}</span></h2>
    <p>{!! sprintf(
        e($label('text_stylesheet_note', '')),
        e($version['project_name']),
        e($version['site_name']),
    ) !!}</p>
    <table class="main" border="1" cellspacing="0" cellpadding="5" align="center">
        <tr>
            <td class="colhead">{{ $label('text_name', 'Name') }}</td>
            <td class="colhead">{{ $label('text_designer', 'Designer') }}</td>
            <td class="colhead">{{ $label('text_comment', 'Comment') }}</td>
        </tr>
        @foreach ($stylesheets as $row)
            <tr>
                <td class="rowfollow">{{ $row['name'] }}</td>
                <td class="rowfollow">{{ $row['designer'] }}</td>
                <td class="rowfollow">{{ $row['comment'] }}</td>
            </tr>
        @endforeach
    </table>
</div>

<div id="contact-frame">
    <h2><span id="contact">{{ $label('text_contact', 'Contact ').$version['project_name'] }}</span></h2>
    <p>{{ $label('text_contact_note', '') }}</p>
    <table class="main" border="1" cellspacing="0" cellpadding="5" align="center">
        <tr>
            <td class="rowhead">{{ $label('text_web_site', 'Web Site') }}</td>
            <td><a href="{{ $version['project_url'] }}" target="_blank">{{ $version['project_url'] }}</a></td>
        </tr>
    </table>
</div>
