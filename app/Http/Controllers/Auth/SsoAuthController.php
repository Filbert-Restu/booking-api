<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SsoAuthController extends Controller
{
    /**
     * Handle SSO callback from SSO Engine UNDIP.
     *
     * SSO Engine calls: GET /auth/sso
     * with header: Authorization: <sso_token>  (with or without "Bearer " prefix)
     *
     * This method:
     * 1. Extracts SSO token from Authorization header
     * 2. Validates the token against SSO API (/users/me)
     * 3. Finds or creates the local user based on email
     * 4. Creates a Sanctum token
     * 5. Returns { callback_url: "/redirect?token=..." }
     */
    public function authenticate(Request $request): JsonResponse
    {
        // Step 1: Extract SSO token from Authorization header
        // SSO may or may not include "Bearer " prefix — handle both cases
        $authHeader = $request->header('Authorization');
        $ssoToken = null;

        if ($authHeader) {
            if (str_starts_with($authHeader, 'Bearer ')) {
                $ssoToken = substr($authHeader, 7);
            } else {
                $ssoToken = $authHeader;
            }
        }

        if (!$ssoToken) {
            return response()->json(['message' => 'Token missing'], 400);
        }

        // Step 2: Validate token against SSO Engine
        // Always add "Bearer " prefix when calling SSO API
        $ssoApiUrl = env('SSO_API_URL', 'https://apps-fsm.undip.ac.id/sso_api');

        try {
            $ssoResponse = Http::withToken($ssoToken)
                ->timeout(10)
                ->get("{$ssoApiUrl}/users/me");

            if (!$ssoResponse->successful()) {
                Log::warning('[SSO] Token validation failed', [
                    'status' => $ssoResponse->status(),
                    'body' => $ssoResponse->body(),
                ]);
                return response()->json(['message' => 'Invalid SSO token'], 401);
            }

            $ssoData = $ssoResponse->json();
            Log::info('[SSO] Token validated', ['data' => $ssoData]);

        } catch (\Exception $e) {
            Log::error('[SSO] Failed to validate token against SSO API', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Invalid SSO token'], 401);
        }

        // Step 3: Extract user data from SSO response
        // IMPORTANT: email is in "username" field, NOT "email"
        // สำหรับ role superadmin, username อาจจะเป็น string 'superadmin' ไม่ใช่ email
        $ssoUser = $ssoData['data'] ?? null;
        $email = $ssoUser['username'] ?? null;  // "username" = identifier
        $name  = $ssoUser['name'] ?? null;
        $role  = $ssoUser['role'] ?? null;      // "mahasiswa", "dosen", "staff", "superadmin"

        if (!$email) {
            Log::error('[SSO] Username missing in SSO response', ['ssoUser' => $ssoUser]);
            return response()->json(['message' => 'Invalid SSO token payload'], 401);
        }

        // Relax email validation: If it's not a valid email, check if it's 'superadmin' 
        // or just allow it as a generic identifier from SSO.
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) && $email !== 'superadmin') {
            Log::warning('[SSO] Non-email username detected', ['username' => $email, 'role' => $role]);
        }

        // Step 4: Find existing user or auto-register
        $user = User::where('email', $email)->with(['role', 'unit'])->first();

        if (!$user) {
            // Auto-register: Create user with default role and unit
            // NOTE: Database has NOT NULL constraints on role_id and unit_id
            Log::info('[SSO] User not found, auto-registering', ['email' => $email, 'name' => $name, 'sso_role' => $role]);

            // Default Mapping (Berdasarkan data DB):
            // 1: Admin, 2: Dosen Pendamping, 5: Sekretaris, 7: Kemahasiswaan
            $roleId = 5; // Default: Sekretaris (Peminjam)
            $unitId = 1; // Default: Fakultas Sains dan Matematika

            // Role Mapping from SSO
            if ($role === 'dosen') {
                $roleId = 2; // Dosen Pendamping Ormawa
            } elseif ($role === 'staff') {
                $roleId = 7; // Kemahasiswaan (Fakultas)
            } elseif ($role === 'admin' || $role === 'superadmin') {
                $roleId = 1; // Admin
            }

            // Superadmin/Admin safety - assign role Admin if email matches specific pattern or list
            // For now, let's treat specific emails as Admins if needed
            $adminEmails = ['admin@undip.ac.id', 'tim8@undip.ac.id']; 
            if (in_array($email, $adminEmails)) {
                $roleId = 1; // Force Admin
            }

            $user = User::create([
                'name'        => $name ?? $email,
                'email'       => $email,
                'password'    => bcrypt(str()->random(32)), // Random unusable password
                'nim_nip'     => null,
                'role_id'     => $roleId,
                'unit_id'     => $unitId,
                'is_profile_completed' => false,
            ]);

            // Reload with relationships
            $user->load(['role', 'unit']);

            Log::info('[SSO] User auto-registered', [
                'user_id' => $user->id, 
                'email'   => $email,
                'role'    => $user->role->name ?? $roleId,
                'unit'    => $user->unit->name ?? $unitId,
            ]);
        } else {
            Log::info('[SSO] Existing user found', ['user_id' => $user->id, 'email' => $email]);
        }

        // Step 5: Create a Sanctum token for the local session
        $token = $user->createToken('sso-auth-token')->plainTextToken;

        // Step 6: Return callback_url as relative path
        // SSO Engine will concat: application_url_callback + callback_url
        // Flow A: "http://apps-fsm.undip.ac.id/peminjaman-ruang-api/auth/sso" + "/redirect?token=..."
        //       = "http://apps-fsm.undip.ac.id/peminjaman-ruang-api/auth/sso/redirect?token=..."
        // Flow B: SSO uses frontend URL directly → frontend /redirect page handles it
        return response()->json([
            'callback_url' => '/redirect?token=' . $token,
        ]);
    }

    /**
     * Handle redirect from SSO backend (Flow A — App-Initiated).
     *
     * SSO calls our backend at: /auth/sso/redirect?token=...
     * We perform a 302 redirect to the frontend /sso/callback page.
     */
    public function redirect(Request $request)
    {
        $token = $request->query('token');

        if (!$token) {
            return response()->json(['message' => 'Token missing'], 400);
        }

        $frontendUrl = env('FRONTEND_APP_URL', 'http://localhost:20081');

        return redirect()->away("{$frontendUrl}/sso/callback?token={$token}");
    }
}
