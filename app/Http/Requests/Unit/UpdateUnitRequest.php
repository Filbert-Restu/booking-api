<?php

namespace App\Http\Requests\Unit;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'      => 'sometimes|string|max:255',
            'category'  => 'sometimes|in:FAKULTAS,JURUSAN,PRODI,HIMA',
            'parent_id' => 'nullable|exists:units,id',
        ];
    }

    public function messages(): array
    {
        return [
            'category.in'      => 'Kategori harus FAKULTAS, JURUSAN, PRODI, atau HIMA',
            'parent_id.exists' => 'Unit induk tidak ditemukan',
        ];
    }
}
