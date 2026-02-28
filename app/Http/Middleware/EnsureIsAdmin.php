<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIsAdmin
{
    /**
     * Handle an incoming request.
     * Pastikan user memiliki role admin atau unit kategori FAKULTAS.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        $isAdmin = $user->role?->slug === 'admin'
            || $user->unit?->category === 'FAKULTAS';

        if (!$isAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk melakukan aksi ini'
            ], 403);
        }

        return $next($request);
    }
}
