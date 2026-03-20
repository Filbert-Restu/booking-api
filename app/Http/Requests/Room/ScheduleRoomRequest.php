<?php

namespace App\Http\Requests\Room;

use Illuminate\Foundation\Http\FormRequest;

class ScheduleRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
        ];
    }

    public function messages(): array
    {
        return [
            'start_date.required'      => 'Tanggal mulai wajib diisi',
            'end_date.required'        => 'Tanggal selesai wajib diisi',
            'end_date.after_or_equal'  => 'Tanggal selesai harus setelah atau sama dengan tanggal mulai',
        ];
    }
}
