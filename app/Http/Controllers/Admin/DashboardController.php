<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\RoomBooking;
use App\Models\Room;
use App\Models\User;

class DashboardController extends Controller
{
    public function stats()
    {
        try {
            $pendingApprovals = RoomBooking::where('status', 'PENDING')->count();
            $activeRooms = Room::where('status', 'ACTIVE')->count();
            $totalUsers = User::count();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'pending_approvals' => $pendingApprovals,
                    'active_rooms' => $activeRooms,
                    'total_users' => $totalUsers,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch dashboard stats',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
