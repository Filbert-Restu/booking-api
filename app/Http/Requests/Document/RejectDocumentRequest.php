<?php

namespace App\Http\Requests\Document;

use Illuminate\Foundation\Http\FormRequest;

class RejectDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'note' => 'required|string|min:10',
        ];
    }

    public function messages(): array
    {
        return [
            'note.required' => 'Alasan penolakan wajib diisi',
            'note.min'       => 'Alasan penolakan minimal 10 karakter',
        ];
    }
}
