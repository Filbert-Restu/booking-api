<?php

namespace App\Http\Requests\Document;

use Illuminate\Foundation\Http\FormRequest;

class ReviseDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'target_user_id' => 'required|exists:users,id',
            'note'           => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'target_user_id.required' => 'User tujuan wajib dipilih',
            'target_user_id.exists'   => 'User tujuan tidak ditemukan',
            'note.required'           => 'Catatan revisi wajib diisi',
        ];
    }
}
