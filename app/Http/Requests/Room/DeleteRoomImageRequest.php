<?php

namespace App\Http\Requests\Room;

use Illuminate\Foundation\Http\FormRequest;

class DeleteRoomImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'path' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'path.required' => 'Path gambar wajib diisi',
        ];
    }
}
