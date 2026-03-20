<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'name'     => 'sometimes|string|max:255',
            'email'    => 'sometimes|string|email|max:255|unique:users,email,' . $id,
            'password' => ['sometimes', Password::defaults()],
            'role_id'  => 'sometimes|exists:roles,id',
            'unit_id'  => 'sometimes|exists:units,id',
        ];
    }

    public function messages(): array
    {
        return [
            'email.email'  => 'Format email tidak valid',
            'email.unique' => 'Email sudah digunakan',
            'role_id.exists' => 'Role tidak ditemukan',
            'unit_id.exists' => 'Unit tidak ditemukan',
        ];
    }
}
