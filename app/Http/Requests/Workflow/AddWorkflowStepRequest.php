<?php

namespace App\Http\Requests\Workflow;

use Illuminate\Foundation\Http\FormRequest;

class AddWorkflowStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'step_order'             => 'required|integer',
            'step_name'              => 'required|string',
            'target_role_slug'       => 'required|string',
            'scope_type'             => 'required|in:SELF,PARENT,FACULTY_LEADER,SPECIFIC_CATEGORY',
            'target_category_lookup' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'step_order.required'       => 'Urutan langkah wajib diisi',
            'step_name.required'        => 'Nama langkah wajib diisi',
            'target_role_slug.required' => 'Role target wajib diisi',
            'scope_type.required'       => 'Tipe scope wajib diisi',
            'scope_type.in'             => 'Tipe scope harus SELF, PARENT, FACULTY_LEADER, atau SPECIFIC_CATEGORY',
        ];
    }
}
