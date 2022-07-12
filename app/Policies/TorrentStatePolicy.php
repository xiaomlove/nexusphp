<?php

namespace App\Policies;

use App\Models\TorrentState;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TorrentStatePolicy extends BasePolicy
{
    use HandlesAuthorization;


    /**
     * Determine whether the user can view any models.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user)
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\TorrentState  $torrentState
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, TorrentState $torrentState)
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user)
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\TorrentState  $torrentState
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, TorrentState $torrentState)
    {
        return $this->can($user);
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\TorrentState  $torrentState
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, TorrentState $torrentState)
    {

    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\TorrentState  $torrentState
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, TorrentState $torrentState)
    {

    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\TorrentState  $torrentState
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, TorrentState $torrentState)
    {
        //
    }

    private function can(User $user)
    {
        if ($user->class >= User::CLASS_ADMINISTRATOR) {
            return true;
        }
        return false;
    }
}
