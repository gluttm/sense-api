<?php

namespace Modules\Base\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests; // to access the authorize method

use Illuminate\Contracts\Support\Renderable;

use Session;
use Validator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Base\Entities\User;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

use Modules\Base\Http\Requests\CreateUserRequest;
use Modules\Base\Http\Requests\UpdateUserRequest;

class UsersController extends Controller
{
    use AuthorizesRequests; // to access the authorize method

    public $successStatus = 200;
    /**
     * Handles user logins
     *
     * @return void
     */
    public function index()
    {
        $this->authorize('view', User::class);
        return response()->json(['users' => User::with('roles')->get()]);
    }


    public function login()
    {
        if (Auth::attempt(['username' => request('username'), 'password' => request('password')])) {
            $user = Auth::user();
            $user_roles = $user->roles;
            $permissions = [];
            foreach ($user_roles as $role) {
                $permissions[] = $role->name;
                $perms = $role->permissions;
                foreach ($perms as $p) {
                    $permissions[] = $p->name;
                }
            }
            //  dd($permissions);
            $success['token'] =  $user->createToken('ttmGest')->accessToken;

            $success['business_id'] = base64_encode($user->businesses[0]->id);
            $success['authorities'] = $permissions;

            return response()->json(
                [
                    'success' => $success
                ],
                $this->successStatus
            );
        } else {
            return response()->json(['error' => 'Utilizador não encontrado.'], 401);
        }
    }
    /** 
     * Register api 
     * 
     * @return \Illuminate\Http\Response 
     */
    public function store(CreateUserRequest $request)
    {
        $this->authorize('create', User::class);
        if (isset($request->validator) && $request->validator->fails()) {
            return response()->json(['errors' => $request->validator->messages()], 400);
        }

        $success = User::registeUser($request);


        return response()->json(['success' => $success], $this->successStatus);
    }

    /** 
     * Register api 
     * 
     * @return \Illuminate\Http\Response 
     */
    public function update(UpdateUserRequest $request, $id)
    {
        $this->authorize('update', User::class);
        if (isset($request->validator) && $request->validator->fails()) {
            return response()->json(['errors' => $request->validator->messages()], 400);
        }

        $success = User::updateUser($request, $id);

        return response()->json(['success' => $success], $this->successStatus);
    }
    /** 
     * details api 
     * 
     * @return \Illuminate\Http\Response 
     */
    public function show($id)
    {
        return response()->json(['user' => User::find($id)], $this->successStatus);
    }
    /** 
     * details api 
     * 
     * @return \Illuminate\Http\Response 
     */
    public function notlogged()
    {
        return response()->json(['error' => 'You have no permission.'], 403);
    }

    /**
     * Handles user logins
     *
     * @return void
     */
    public function logout()
    {
        auth()->user()->token()->revoke();
    }

    public function destroy($id)
    {
        $user = User::find($id);
        $user->roles()->detach();
        $user->businesses()->detach();

        $user->delete();

        return response()->json(['success' => "Utilizador removido com sucesso."]);
    }
}
