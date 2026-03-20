<?php

namespace App\Http\Requests\RoomBooking;

use Illuminate\Foundation\Http\FormRequest;

class CancelRoomBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => 'nullable|string',
        ];
    }
}
