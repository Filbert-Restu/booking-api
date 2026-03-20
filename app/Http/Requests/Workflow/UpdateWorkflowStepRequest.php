<?php

namespace App\Http\Requests\Workflow;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkflowStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'step_order'             => 'sometimes|integer',
            'step_name'              => 'sometimes|string',
            'target_role_slug'       => 'sometimes|string',
            'scope_type'             => 'sometimes|in:SELF,PARENT,FACULTY_LEADER,SPECIFIC_CATEGORY',
            'target_category_lookup' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'scope_type.in' => 'Tipe scope harus SELF, PARENT, FACULTY_LEADER, atau SPECIFIC_CATEGORY',
        ];
    }
}
