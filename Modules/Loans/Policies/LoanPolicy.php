<?php

namespace Modules\Loans\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;

use Modules\Base\Entities\User;

use Modules\Base\Entities\Permission;

class LoanPolicy
{
    use HandlesAuthorization;

    public function view(User $user)
    {
        $user = auth()->user();
        $user = User::find($user->id);

        $permission = Permission::where('name', 'loan-view')->first();
        return $user->hasRole($permission->roles) && $user->hasBusiness($user);
    }
    /**
     * Determine whether the user can create items.
     *
     * @param  \App\User  $user
     * @return mixed
     */
    public function create(User $user)
    {

        $user = auth()->user();
        $user = User::find($user->id);

        $permission = Permission::where('name', 'loan-create')->first();
        return $user->hasRole($permission->roles) && $user->hasBusiness($user);
    }
    /**
     * Determine whether the user can update the item.
     *
     * @param  \App\User  $user
     * @return mixed
     */
    public function update(User $user)
    {

        $user = auth()->user();
        $user = User::find($user->id);

        $permission = Permission::where('name', 'loan-edit')->first();
        return $user->hasRole($permission->roles) && $user->hasBusiness($user);
    }
    /**
     * Determine whether the user can delete the item.
     *
     * @param  \App\User  $user
     * @return mixed
     */
    public function delete(User $user)
    {
        $user = auth()->user();
        $user = User::find($user->id);

        $permission = Permission::where('name', 'loan-delete')->first();
        return $user->hasRole($permission->roles) && $user->hasBusiness($user);
    }
}
