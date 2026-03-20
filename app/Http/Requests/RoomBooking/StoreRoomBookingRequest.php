<?php

namespace App\Http\Requests\RoomBooking;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoomBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document_id'           => 'required|exists:documents,id',
            'room_id'               => 'required|exists:rooms,id',
            'booking_date'          => 'required|date|after_or_equal:today',
            'start_time'            => 'required|date_format:H:i',
            'end_time'              => 'required|date_format:H:i|after:start_time',
            'purpose'               => 'required|string',
            'special_requirements'  => 'nullable|string',
            'expected_participants' => 'nullable|integer|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'document_id.required'     => 'Document ID wajib diisi',
            'document_id.exists'       => 'Document tidak ditemukan',
            'room_id.required'         => 'Room ID wajib diisi',
            'room_id.exists'           => 'Ruangan tidak ditemukan',
            'booking_date.required'    => 'Tanggal booking wajib diisi',
            'booking_date.after_or_equal' => 'Tanggal booking tidak boleh di masa lalu',
            'start_time.required'      => 'Waktu mulai wajib diisi',
            'start_time.date_format'   => 'Format waktu mulai harus HH:MM',
            'end_time.required'        => 'Waktu selesai wajib diisi',
            'end_time.date_format'     => 'Format waktu selesai harus HH:MM',
            'end_time.after'           => 'Waktu selesai harus setelah waktu mulai',
            'purpose.required'         => 'Tujuan peminjaman wajib diisi',
            'expected_participants.integer' => 'Jumlah peserta harus berupa angka',
            'expected_participants.min'     => 'Jumlah peserta minimal 1',
        ];
    }
}
