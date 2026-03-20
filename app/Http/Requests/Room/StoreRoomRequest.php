<?php

namespace App\Http\Requests\Room;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => 'required|string|max:255',
            'code'        => 'required|string|max:50|unique:rooms,code',
            'capacity'    => 'nullable|integer|min:1',
            'facilities'  => 'nullable|array',
            'status'      => 'nullable|in:ACTIVE,MAINTENANCE,INACTIVE',
            'description' => 'nullable|string',
            'images'      => 'nullable|array',
            'images.*'    => 'file|image|mimes:png,jpg,jpeg|max:5120',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'   => 'Nama ruangan wajib diisi',
            'name.max'        => 'Nama ruangan maksimal 255 karakter',
            'code.required'   => 'Kode ruangan wajib diisi',
            'code.unique'     => 'Kode ruangan sudah digunakan',
            'code.max'        => 'Kode ruangan maksimal 50 karakter',
            'capacity.min'    => 'Kapasitas minimal 1',
            'status.in'       => 'Status harus ACTIVE, MAINTENANCE, atau INACTIVE',
            'images.*.image'  => 'File harus berupa gambar',
            'images.*.mimes'  => 'Format gambar harus PNG, JPG, atau JPEG',
            'images.*.max'    => 'Ukuran gambar maksimal 5MB',
        ];
    }
}
