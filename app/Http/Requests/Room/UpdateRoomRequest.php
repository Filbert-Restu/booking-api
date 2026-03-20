<?php

namespace App\Http\Requests\Room;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'name'        => 'sometimes|required|string|max:255',
            'code'        => 'sometimes|required|string|max:50|unique:rooms,code,' . $id,
            'capacity'    => 'nullable|integer|min:1',
            'facilities'  => 'nullable|array',
            'description' => 'nullable|string',
            'status'      => 'nullable|in:ACTIVE,MAINTENANCE,INACTIVE',
            'images.*'    => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'name.max'     => 'Nama ruangan maksimal 255 karakter',
            'code.unique'  => 'Kode ruangan sudah digunakan',
            'code.max'     => 'Kode ruangan maksimal 50 karakter',
            'capacity.min' => 'Kapasitas minimal 1',
            'status.in'    => 'Status harus ACTIVE, MAINTENANCE, atau INACTIVE',
        ];
    }
}
