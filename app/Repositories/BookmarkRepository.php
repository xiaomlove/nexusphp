<?php

namespace App\Repositories;

use App\Exceptions\NexusException;
use App\Models\Torrent;
use App\Models\User;

class BookmarkRepository extends BaseRepository
{
    public function add(User $user, $torrentId)
    {
        $torrent = Torrent::query()->findOrFail($torrentId);
        $torrent->checkIsNormal();
        $exists = $user->bookmarks()->where('torrentid', $torrentId)->exists();
        if ($exists) {
            throw new NexusException("torrent: $torrentId already bookmarked.");
        }
        $result = $user->bookmarks()->create(['torrentid' => $torrentId]);
        return $result;
    }

    public function remove(User $user, $torrentId)
    {
        $torrent = Torrent::query()->findOrFail($torrentId);
        $exists = $user->bookmarks()->where('torrentid', $torrentId)->exists();
        if (!$exists) {
            throw new NexusException("torrent: $torrentId has not been bookmarked.");
        }
        $result = $user->bookmarks()->where('torrentid', $torrentId)->delete();
        return $result;
    }
}
