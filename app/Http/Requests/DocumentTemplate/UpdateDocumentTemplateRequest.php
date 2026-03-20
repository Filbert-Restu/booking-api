<?php

namespace App\Http\Requests\DocumentTemplate;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDocumentTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'template_name' => 'nullable|string|max:255',
            'file'          => 'nullable|file|mimes:docx,doc|max:10240',
            'description'   => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'template_name.max' => 'Nama template maksimal 255 karakter',
            'file.mimes'        => 'Format file harus DOCX atau DOC',
            'file.max'          => 'Ukuran file maksimal 10MB',
        ];
    }
}
