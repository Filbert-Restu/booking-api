<?php

namespace App\Http\Requests\Room;

use Illuminate\Foundation\Http\FormRequest;

class CheckAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date'                => 'required|date|after_or_equal:today',
            'start_time'          => 'required|date_format:H:i',
            'end_time'            => 'required|date_format:H:i|after:start_time',
            'exclude_document_id' => 'nullable|integer',
        ];
    }

    public function messages(): array
    {
        return [
            'date.required'          => 'Tanggal wajib diisi',
            'date.after_or_equal'    => 'Tanggal tidak boleh di masa lalu',
            'start_time.required'    => 'Waktu mulai wajib diisi',
            'start_time.date_format' => 'Format waktu mulai harus HH:MM',
            'end_time.required'      => 'Waktu selesai wajib diisi',
            'end_time.date_format'   => 'Format waktu selesai harus HH:MM',
            'end_time.after'         => 'Waktu selesai harus setelah waktu mulai',
        ];
    }
}
