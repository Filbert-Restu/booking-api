<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users',
            'password' => ['required', Password::defaults()],
            'role_id'  => 'required|exists:roles,id',
            'unit_id'  => 'required|exists:units,id',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'  => 'Nama wajib diisi',
            'email.required' => 'Email wajib diisi',
            'email.email'    => 'Format email tidak valid',
            'email.unique'   => 'Email sudah digunakan',
            'password.required' => 'Password wajib diisi',
            'role_id.required'  => 'Role wajib dipilih',
            'role_id.exists'    => 'Role tidak ditemukan',
            'unit_id.required'  => 'Unit wajib dipilih',
            'unit_id.exists'    => 'Unit tidak ditemukan',
        ];
    }
}
