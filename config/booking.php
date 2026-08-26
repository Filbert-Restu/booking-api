<?php

return [
    // Number of days to hold a PENDING booking before it is released
    'hold_days' => env('BOOKING_HOLD_DAYS', 14),

    // Minimum number of calendar days before the booking date
    // Booking must be made at least this many days in advance
    'min_booking_days' => env('BOOKING_MIN_DAYS', 8),
];
