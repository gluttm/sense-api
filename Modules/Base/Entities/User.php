<?php

namespace Modules\Base\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

use Modules\Base\Entities\Role;

use Modules\Business\Entities\Business;

use Laravel\Passport\HasApiTokens;

use DB;

class User extends Authenticatable
{
    use HasFactory, HasApiTokens, Notifiable, SoftDeletes;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    protected $fillable = [
        'username',
        'email',
        'password',
        'first_name',
        'last_name',
        'language',
        'username',
        'surname'
    ];


    protected static function newFactory()
    {
        return \Modules\Base\Database\factories\UserFactory::new();
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    public function businesses()
    {
        return $this->belongsToMany(Business::class);
    }

    public function assignRole(Role $role)
    {
        return $this->roles()->attach($role->id);
    }


    public function assignBusiness(Business $business)
    {
        return $this->businesses()->attach($business->id);
    }



    // public function hasRole($role)
    // {
    //     if (is_string($role)) {
    //         return $this->roles->contains('name', $role);
    //     }
    //     return !! $role->intersect($this->roles)->count();
    // }

    /**
     * Determine if the user may perform the given permission.
     *
     * @param  Permission $permission
     * @return boolean
     */
    public function hasRole($role)
    {
        if (is_string($role)) {
            return $this->roles->contains('name', $role);
        }
        return !!$role->intersect($this->roles)->count();
    }

    public function hasUser($user)
    {
        if (is_string($user)) {
            return $this->users->contains('name', $user);
        }
        return !!$user->intersect($this->users)->count();
    }

    public function hasBusiness($user)
    {
        $businesses = User::whereHas('businesses', function ($query) {
            $query->where('business_name', 'TTMInc');
        })->get();

        //return $businesses->contains('name', $user->name);
        return true;
    }

    public static function registeUser($request)
    {
        $password = bcrypt($request->password);
        DB::beginTransaction();
        $user = User::create([
            'username' => $request->username,
            'last_name' => $request->last_name,
            'surname' => $request->surname,
            'first_name' => $request->first_name,
            'email' => $request->email,
            'password' => $password
        ]);

        $users = User::find($user->id);

        if (isset($request->customer_user) && $request->customer_user == true) {
            $customer = Role::where('name', 'Cliente')->first();

            # assign customer role
            $users->assignRole(Role::find($customer->id));

            # assign Business
            $users->assignBusiness(Business::find($request->business_id));
        }

        for ($i = 0; $i < count($request->businesses); $i++) {
            $users->assignBusiness(Business::find($request->businesses[$i]));
        }

        for ($i = 0; $i < count($request->roles); $i++) {
            $users->assignRole(Role::find($request->roles[$i]));
        }


        DB::commit();

        return 'Utilizador criado com sucesso.';
    }

    public static function updateUser($request, $id)
    {
        $password = isset($request->password) ? bcrypt($request->password) : null;
        DB::beginTransaction();
        $user = User::where('id', $id)->update([
            'username' => $request->username,
            'last_name' => $request->last_name,
            'surname' => $request->surname,
            'first_name' => $request->first_name,
            'email' => $request->email,
        ]);

        if ($password != null) {
            $user = User::where('id', $id)->update([
                'password' => $password
            ]);
        }

        $users = User::find($id);

        if (!empty($request->roles)) {
            for ($i = 0; $i < count($request->roles); $i++) {
                $users->roles()->sync([$id, $request->roles[$i]]);
            }
        }

        if (!empty($request->businesses)) {
            for ($i = 0; $i < count($request->businesses); $i++) {
                $users->businesses()->sync([$id, $request->businesses[$i]]);
            }
        }


        DB::commit();

        return 'Utilizador actualizado com sucesso.';
    }
}