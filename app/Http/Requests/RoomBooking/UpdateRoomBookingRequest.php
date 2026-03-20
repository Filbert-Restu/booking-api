<?php

namespace App\Http\Requests\RoomBooking;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoomBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'room_id'               => 'sometimes|required|exists:rooms,id',
            'booking_date'          => 'sometimes|required|date|after_or_equal:today',
            'start_time'            => 'sometimes|required|date_format:H:i',
            'end_time'              => 'sometimes|required|date_format:H:i|after:start_time',
            'purpose'               => 'sometimes|required|string',
            'special_requirements'  => 'nullable|string',
            'expected_participants' => 'nullable|integer|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'room_id.exists'           => 'Ruangan tidak ditemukan',
            'booking_date.after_or_equal' => 'Tanggal booking tidak boleh di masa lalu',
            'start_time.date_format'   => 'Format waktu mulai harus HH:MM',
            'end_time.date_format'     => 'Format waktu selesai harus HH:MM',
            'end_time.after'           => 'Waktu selesai harus setelah waktu mulai',
            'purpose.required'         => 'Tujuan peminjaman wajib diisi',
            'expected_participants.min' => 'Jumlah peserta minimal 1',
        ];
    }
}
