<?php

namespace App\Http\Requests\Unit;

use Illuminate\Foundation\Http\FormRequest;

class StoreUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => 'required|string|max:255',
            'code'        => 'required|string|max:255|unique:units,code',
            'description' => 'nullable|string',
            'category'    => 'required|in:FAKULTAS,PRODI,HIMA',
            'parent_id'   => 'nullable|exists:units,id',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'     => 'Nama unit wajib diisi',
            'code.required'     => 'Kode unit wajib diisi',
            'code.unique'       => 'Kode unit sudah digunakan',
            'category.required' => 'Kategori unit wajib dipilih',
            'category.in'       => 'Kategori harus FAKULTAS, PRODI, atau HIMA',
            'parent_id.exists'  => 'Unit induk tidak ditemukan',
        ];
    }
}
