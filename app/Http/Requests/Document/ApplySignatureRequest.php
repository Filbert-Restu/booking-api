<?php

namespace App\Http\Requests\Document;

use Illuminate\Foundation\Http\FormRequest;

class ApplySignatureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => 'nullable|in:approval-sheet,executive-summary',
        ];
    }

    public function messages(): array
    {
        return [
            'type.in' => 'Tipe harus approval-sheet atau executive-summary',
        ];
    }
}
