<?php

namespace App\Http\Requests\Document;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'workflow_id'        => 'required|exists:workflows,id',
            'title'              => 'required|string|max:255',
            'content'            => 'nullable|array',
            'meta_data'          => 'nullable|array',
            'executive_summary'  => 'nullable|file|mimes:pdf,doc,docx|max:10240',
            'approval_sheet'     => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'proposal'           => 'nullable|file|mimes:pdf|max:20480',
        ];
    }

    public function messages(): array
    {
        return [
            'workflow_id.required'       => 'Workflow wajib dipilih',
            'workflow_id.exists'         => 'Workflow tidak ditemukan',
            'title.required'             => 'Judul dokumen wajib diisi',
            'title.max'                  => 'Judul dokumen maksimal 255 karakter',
            'executive_summary.mimes'    => 'Executive summary harus berformat PDF, DOC, atau DOCX',
            'executive_summary.max'      => 'Ukuran executive summary maksimal 10MB',
            'approval_sheet.mimes'       => 'Lembar pengesahan harus berformat PDF, JPG, JPEG, atau PNG',
            'approval_sheet.max'         => 'Ukuran lembar pengesahan maksimal 5MB',
            'proposal.mimes'             => 'Proposal harus berformat PDF',
            'proposal.max'               => 'Ukuran proposal maksimal 20MB',
        ];
    }
}
