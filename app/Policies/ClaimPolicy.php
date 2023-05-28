<?php

namespace App\Policies;

use App\Models\Claim;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ClaimPolicy extends BasePolicy
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
     * @param  \App\Models\Claim  $claim
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, Claim $claim)
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
        return $this->can($user);
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Claim  $claim
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, Claim $claim)
    {
        return $this->can($user);
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Claim  $claim
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, Claim $claim)
    {
        return $this->can($user);
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Claim  $claim
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, Claim $claim)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Claim  $claim
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, Claim $claim)
    {
        //
    }

    private function can(User $user)
    {
        if ($user->class >= User::CLASS_SYSOP) {
            return true;
        }
        return false;
    }
}
