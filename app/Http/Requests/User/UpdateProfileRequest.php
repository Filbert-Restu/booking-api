<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'    => 'required|string|max:255',
            'nim_nip' => 'required|string|max:50',
            'role_id' => 'required|exists:roles,id',
            'unit_id' => 'required|exists:units,id',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'    => 'Nama wajib diisi',
            'nim_nip.required' => 'NIM/NIP wajib diisi',
            'nim_nip.max'      => 'NIM/NIP maksimal 50 karakter',
            'role_id.required' => 'Role wajib dipilih',
            'role_id.exists'   => 'Role tidak ditemukan',
            'unit_id.required' => 'Unit wajib dipilih',
            'unit_id.exists'   => 'Unit tidak ditemukan',
        ];
    }
}
