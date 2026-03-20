<?php

namespace App\Http\Requests\Workflow;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                         => 'required|string|max:255',
            'description'                  => 'nullable|string',
            'applies_to_category'          => 'required|string',
            'steps'                        => 'required|array|min:1',
            'steps.*.step_order'           => 'required|integer',
            'steps.*.step_name'            => 'required|string',
            'steps.*.target_role_slug'     => 'required|string',
            'steps.*.scope_type'           => 'required|in:SELF,PARENT,FACULTY_LEADER,SPECIFIC_CATEGORY',
            'steps.*.target_category_lookup' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'                    => 'Nama workflow wajib diisi',
            'applies_to_category.required'     => 'Kategori unit tujuan wajib diisi',
            'steps.required'                   => 'Workflow harus memiliki minimal 1 langkah',
            'steps.min'                        => 'Workflow harus memiliki minimal 1 langkah',
            'steps.*.step_order.required'      => 'Urutan langkah wajib diisi',
            'steps.*.step_name.required'       => 'Nama langkah wajib diisi',
            'steps.*.target_role_slug.required' => 'Role target wajib diisi',
            'steps.*.scope_type.required'      => 'Tipe scope wajib diisi',
            'steps.*.scope_type.in'            => 'Tipe scope harus SELF, PARENT, FACULTY_LEADER, atau SPECIFIC_CATEGORY',
        ];
    }
}
