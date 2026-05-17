@php
    /**
     * @var string $action
     * @var \App\Models\User $target
     * @var \App\Services\UserHistoryPage $pageData
     * @var \App\Services\UserHistoryService $service
     * @var string $requestUri
     * @var \App\Models\User|null $viewer
     */
    $totalPages = $pageData->totalPages();
    $page = $pageData->page;
    $pagerBase = (function () use ($requestUri) {
        $parts = parse_url($requestUri);
        $path = $parts['path'] ?? '/userhistory.php';
        $query = [];
        if (! empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }
        unset($query['page']);
        $base = $path;
        if (! empty($query)) {
            $base .= '?' . http_build_query($query) . '&';
        } else {
            $base .= '?';
        }
        return $base;
    })();
    $perPage = $pageData->perPage;
@endphp

@if ($action === \App\Services\UserHistoryService::ACTION_VIEWPOSTS)
    <h1>Posts history for {{ $target->username }}</h1>

    @forelse ($pageData->rows as $row)
        @php
            $newPost = (int) ($row->r_lastpostread ?? 0) < (int) ($row->t_lastpost ?? 0)
                && isset($viewer) && (int) $viewer->id === (int) $target->id;
            $editorUsername = (int) ($row->editedby ?? 0) > 0
                ? $service->editorUsername((int) $row->editedby)
                : null;
        @endphp
        <p class="sub">
            <table border="0" cellspacing="0" cellpadding="0">
                <tr>
                    <td class="embedded">
                        {{ $row->added }}
                        &nbsp;--&nbsp;Forum:
                        <a href="forums.php?action=viewforum&forumid={{ (int) ($row->f_id ?? 0) }}">{{ $row->f_name ?? '' }}</a>
                        &nbsp;--&nbsp;Topic:
                        <a href="forums.php?action=viewtopic&topicid={{ (int) ($row->t_id ?? 0) }}">{{ $row->t_subject ?? '' }}</a>
                        &nbsp;--&nbsp;Post:
                        <a href="forums.php?action=viewtopic&topicid={{ (int) ($row->t_id ?? 0) }}&page=p{{ (int) $row->id }}#pid{{ (int) $row->id }}">#{{ (int) $row->id }}</a>
                        @if ($newPost)
                            &nbsp;<b>(<font class="new">new</font>)</b>
                        @endif
                    </td>
                </tr>
            </table>
        </p>
        <br />
        <table class="main" width="100%" border="1" cellspacing="0" cellpadding="5">
            <tr valign="top">
                <td class="comment">
                    {!! format_comment((string) ($row->body ?? '')) !!}
                    @if ($editorUsername !== null)
                        <p><font size="1" class="small">Last edited by {{ $editorUsername }} at {{ $row->editdate ?? '' }}</font></p>
                    @endif
                </td>
            </tr>
        </table>
        <br />
    @empty
        <p>Nothing found.</p>
    @endforelse
@elseif ($action === \App\Services\UserHistoryService::ACTION_VIEWCOMMENTS)
    <h1>Comments history for {{ $target->username }}</h1>

    @forelse ($pageData->rows as $row)
        @php
            $torrentName = (string) ($row->t_name ?? '');
            $torrentDisplay = strlen($torrentName) > 55
                ? substr($torrentName, 0, 52) . '...'
                : $torrentName;
            $torrentId = (int) ($row->t_id ?? 0);
            $commentId = (int) $row->id;
            $commPage = $service->commentPageOnDetails($torrentId, $commentId);
            $pageUrl = $commPage > 0 ? '&page=' . $commPage : '';
        @endphp
        <p class="sub">
            <table border="0" cellspacing="0" cellpadding="0">
                <tr>
                    <td class="embedded">
                        {{ $row->added }}
                        &nbsp;---&nbsp;Torrent:
                        @if ($torrentName !== '')
                            <a href="details.php?id={{ $torrentId }}&tocomm=1&hit=1">{{ $torrentDisplay }}</a>
                        @else
                            [Deleted]
                        @endif
                        &nbsp;---&nbsp;Comment:
                        #<a href="details.php?id={{ $torrentId }}&tocomm=1&hit=1{{ $pageUrl }}">{{ $commentId }}</a>
                    </td>
                </tr>
            </table>
        </p>
        <br />
        <table class="main" width="100%" border="1" cellspacing="0" cellpadding="5">
            <tr valign="top">
                <td class="comment">
                    {!! format_comment((string) ($row->text ?? '')) !!}
                </td>
            </tr>
        </table>
        <br />
    @empty
        <p>Nothing found.</p>
    @endforelse
@endif

@if ($totalPages > 1)
    <p align="center">
        @if ($page > 0)
            <a href="{{ $pagerBase . 'page=' . ($page - 1) }}">&lt;&lt; Prev</a>
        @endif
        <b>{{ $page + 1 }} / {{ $totalPages }}</b>
        @if ($page + 1 < $totalPages)
            <a href="{{ $pagerBase . 'page=' . ($page + 1) }}">Next &gt;&gt;</a>
        @endif
    </p>
@endif
