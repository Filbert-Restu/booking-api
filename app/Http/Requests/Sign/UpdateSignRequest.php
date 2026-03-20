<?php

namespace App\Http\Requests\Sign;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'signature' => 'required|file|mimes:png,jpg,jpeg|max:2048',
        ];
    }

    public function messages(): array
    {
        return [
            'signature.required' => 'File tanda tangan wajib diupload',
            'signature.mimes'    => 'Format tanda tangan harus PNG, JPG, atau JPEG',
            'signature.max'      => 'Ukuran file tanda tangan maksimal 2MB',
        ];
    }
}
