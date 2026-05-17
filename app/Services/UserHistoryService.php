<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Nexus\Database\NexusDB;

class UserHistoryService
{
    public const DEFAULT_PER_PAGE = 15;

    public const ACTION_VIEWPOSTS = 'viewposts';

    public const ACTION_VIEWCOMMENTS = 'viewcomments';

    public function findUser(int $uid): ?User
    {
        if ($uid <= 0) {
            return null;
        }

        $columns = array_values(array_unique(array_merge(User::$commonFields, ['parked'])));

        /** @var User|null $user */
        $user = User::query()->where('id', $uid)->first($columns);

        return $user;
    }

    public function isParked(User $user): bool
    {
        return ($user->parked ?? 'no') === 'yes';
    }

    public function viewposts(
        int $uid,
        int $viewerClass,
        int $page = 0,
        int $perPage = self::DEFAULT_PER_PAGE,
    ): UserHistoryPage {
        $perPage = $perPage > 0 ? $perPage : self::DEFAULT_PER_PAGE;
        $page = max(0, $page);

        $total = (int) NexusDB::table('posts AS p')
            ->leftJoin('topics AS t', 'p.topicid', '=', 't.id')
            ->leftJoin('forums AS f', 't.forumid', '=', 'f.id')
            ->where('p.userid', $uid)
            ->where('f.minclassread', '<=', $viewerClass)
            ->distinct()
            ->count('p.id');

        if ($total === 0) {
            return new UserHistoryPage($total, $page, $perPage, new Collection);
        }

        $rows = NexusDB::table('posts AS p')
            ->leftJoin('topics AS t', 'p.topicid', '=', 't.id')
            ->leftJoin('forums AS f', 't.forumid', '=', 'f.id')
            ->leftJoin('readposts AS r', function ($join) {
                $join->on('p.topicid', '=', 'r.topicid')->on('p.userid', '=', 'r.userid');
            })
            ->where('p.userid', $uid)
            ->where('f.minclassread', '<=', $viewerClass)
            ->orderByDesc('p.id')
            ->offset($page * $perPage)
            ->limit($perPage)
            ->selectRaw('f.id AS f_id, f.name AS f_name, t.id AS t_id, t.subject AS t_subject, t.lastpost AS t_lastpost, r.lastpostread AS r_lastpostread, p.id, p.userid, p.topicid, p.added, p.body, p.editedby, p.editdate')
            ->get();

        return new UserHistoryPage($total, $page, $perPage, $rows);
    }

    public function viewcomments(
        int $uid,
        int $page = 0,
        int $perPage = self::DEFAULT_PER_PAGE,
    ): UserHistoryPage {
        $perPage = $perPage > 0 ? $perPage : self::DEFAULT_PER_PAGE;
        $page = max(0, $page);

        $total = (int) NexusDB::table('comments')
            ->where('user', $uid)
            ->count();

        if ($total === 0) {
            return new UserHistoryPage($total, $page, $perPage, new Collection);
        }

        $rows = NexusDB::table('comments AS c')
            ->leftJoin('torrents AS t', 'c.torrent', '=', 't.id')
            ->where('c.user', $uid)
            ->orderByDesc('c.id')
            ->offset($page * $perPage)
            ->limit($perPage)
            ->selectRaw('t.name AS t_name, c.torrent AS t_id, c.id, c.added, c.text')
            ->get();

        return new UserHistoryPage($total, $page, $perPage, $rows);
    }

    public function editorUsername(int $userid): ?string
    {
        if ($userid <= 0) {
            return null;
        }

        $row = NexusDB::table('users')
            ->where('id', $userid)
            ->value('username');

        return is_string($row) ? $row : null;
    }

    public function commentPageOnDetails(int $torrentId, int $commentId, int $commentsPerPage = 20): int
    {
        if ($torrentId <= 0 || $commentId <= 0) {
            return 0;
        }

        $earlier = (int) NexusDB::table('comments')
            ->where('torrent', $torrentId)
            ->where('id', '<', $commentId)
            ->count();

        return (int) floor($earlier / max(1, $commentsPerPage));
    }
}
