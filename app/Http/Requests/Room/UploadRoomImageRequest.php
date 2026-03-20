<?php

namespace App\Http\Requests\Room;

use Illuminate\Foundation\Http\FormRequest;

class UploadRoomImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'image' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
        ];
    }

    public function messages(): array
    {
        return [
            'image.required' => 'File gambar wajib diupload',
            'image.image'    => 'File harus berupa gambar',
            'image.mimes'    => 'Format gambar harus JPEG, PNG, JPG, atau WebP',
            'image.max'      => 'Ukuran gambar maksimal 2MB',
        ];
    }
}
