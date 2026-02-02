<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionController extends Controller
{
    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): JsonResponse
    {
        $request->authenticate();

        $user = Auth::user();

        // Load role dan unit untuk user
        $user->load(['role', 'unit']);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'user' => $user,
            'unit_category' => $user->unit ? $user->unit->category : null, // For workflow selection
        ]);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): JsonResponse
    {
        // Revoke current token
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout successful'
        ]);
    }
}
