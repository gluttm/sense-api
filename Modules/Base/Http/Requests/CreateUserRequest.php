<?php

namespace Modules\Base\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

use Illuminate\Http\Response;

class CreateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'first_name' => 'required', 
            'email' => 'required|email|unique:users', 
            'password' => 'required',
            'password_confirmation' => 'required|same:password',
            'surname' => 'required',
            'roles' => 'required',
            'username' => 'required',
            'businesses' => 'required'
        ];
    }

    public function messages()
    {
        return [
            'name' => 'O campo senha e obrigatorio.',
            'password' => 'O campo senha e obrigatorio.',
            'password' => 'O campo senha e obrigatorio.',
           // 'description.min'  => 'description minimum length bla bla bla'
        ];
    }

    public $validator = null;
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        $this->validator = $validator;
    }
}
