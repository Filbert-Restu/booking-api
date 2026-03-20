<?php

namespace App\Http\Requests\DocumentTemplate;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'template_type'     => 'required|in:executive_summary,lembar_pengesahan',
            'organization_type' => 'nullable|in:hmd,bem_ukm,senat',
            'template_name'     => 'required|string|max:255',
            'file'              => 'required|file|mimes:docx,doc|max:10240',
            'description'       => 'nullable|string',
            'set_as_active'     => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'template_type.required' => 'Tipe template wajib dipilih',
            'template_type.in'       => 'Tipe template harus executive_summary atau lembar_pengesahan',
            'organization_type.in'   => 'Tipe organisasi harus hmd, bem_ukm, atau senat',
            'template_name.required' => 'Nama template wajib diisi',
            'template_name.max'      => 'Nama template maksimal 255 karakter',
            'file.required'          => 'File template wajib diupload',
            'file.mimes'             => 'Format file harus DOCX atau DOC',
            'file.max'               => 'Ukuran file maksimal 10MB',
        ];
    }
}
