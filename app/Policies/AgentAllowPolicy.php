<?php

namespace App\Policies;

use App\Models\AgentAllow;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class AgentAllowPolicy extends BasePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     *
     * @return Response|bool
     */
    public function viewAny(User $user)
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     *
     * @return Response|bool
     */
    public function view(User $user, AgentAllow $agentAllow)
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     *
     * @return Response|bool
     */
    public function create(User $user)
    {
        return $this->can($user);
    }

    /**
     * Determine whether the user can update the model.
     *
     * @return Response|bool
     */
    public function update(User $user, AgentAllow $agentAllow)
    {
        return $this->can($user);
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @return Response|bool
     */
    public function delete(User $user, AgentAllow $agentAllow)
    {
        return $this->can($user);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, AgentAllow $agentAllow)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, AgentAllow $agentAllow)
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
