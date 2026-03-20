<?php

namespace App\Http\Requests\Workflow;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                => 'sometimes|string|max:255',
            'description'         => 'nullable|string',
            'applies_to_category' => 'sometimes|string',
        ];
    }
}
