@php
    /**
     * @var \App\Models\User $target
     * @var string $category
     * @var int $businessType
     * @var array<string,string> $categoryOptions
     * @var array<int,string> $businessTypeOptions
     * @var \App\Services\BonusLogPage $pageData
     * @var string $requestUri
     */
    $totalPages = $pageData->totalPages();
    $page = $pageData->page;
    $pagerBase = (function () use ($requestUri) {
        $parts = parse_url($requestUri);
        $path = $parts['path'] ?? '/bonus-log.php';
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
@endphp

<h1 style="text-align: center">
    Bonus log —
    <a href="userdetails.php?id={{ (int) $target->id }}">{{ $target->username }}</a>
</h1>

<div>
    <form id="filterForm" action="{{ $requestUri }}" method="get">
        <input type="hidden" name="uid" value="{{ (int) $target->id }}">
        <span>Category:</span>
        <select name="category">
            @foreach ($categoryOptions as $value => $label)
                <option value="{{ $value }}" @selected($value === $category)>{{ $label }}</option>
            @endforeach
        </select>
        &nbsp;&nbsp;
        <span>Business type:</span>
        <select name="business_type">
            <option value="0">- any -</option>
            @foreach ($businessTypeOptions as $value => $label)
                <option value="{{ $value }}" @selected((int) $value === (int) $businessType)>{{ $label }}</option>
            @endforeach
        </select>
        &nbsp;&nbsp;
        <input type="submit" value="Submit">
    </form>
</div>

<table id="bonus-log-table" width="100%" cellpadding="5" border="1" cellspacing="0">
    <tr>
        <td class="colhead" align="left">Business type</td>
        <td class="colhead" align="left">Old total</td>
        <td class="colhead" align="left">Value</td>
        <td class="colhead" align="left">New total</td>
        <td class="colhead" align="left">Comment</td>
        <td class="colhead" align="left">Created at</td>
    </tr>
    @forelse ($pageData->rows as $row)
        @php
            $old = (float) ($row->old_total_value ?? 0);
            $new = (float) ($row->new_total_value ?? 0);
            $value = (float) ($row->value ?? 0);
            $sign = $old < $new ? '+' : '-';
        @endphp
        <tr>
            <td class="rowfollow nowrap" align="left">{{ $row->businessTypeText }}</td>
            <td class="rowfollow nowrap" align="left">{{ $old > 0 ? number_format($old, 1) : '-' }}</td>
            <td class="rowfollow nowrap" align="left">{{ $sign . number_format($value, 1) }}</td>
            <td class="rowfollow nowrap" align="left">{{ $new > 0 ? number_format($new, 1) : '-' }}</td>
            <td class="rowfollow nowrap" align="left">{{ $row->comment }}</td>
            <td class="rowfollow nowrap" align="left">{{ $row->created_at }}</td>
        </tr>
    @empty
        <tr>
            <td class="rowfollow" align="center" colspan="6">Nothing found.</td>
        </tr>
    @endforelse
</table>

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
