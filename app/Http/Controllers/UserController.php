<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    /**
     * Daftar semua user
     *
     * Query params:
     * - role_id: Filter by role
     * - unit_id: Filter by unit
     * - status: Filter by status (ACTIVE/INACTIVE)
     * - search: Search by name atau email
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = User::with(['role', 'unit']);

        // Admin bisa lihat semua, user biasa hanya lihat user di unit yang sama
        if ($user->unit->category !== 'FAKULTAS') {
            $query->where('unit_id', $user->unit_id);
        }

        // Filter by role
        if ($request->has('role_id')) {
            $query->where('role_id', $request->role_id);
        }

        // Filter by unit
        if ($request->has('unit_id')) {
            $query->where('unit_id', $request->unit_id);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Search by name or email
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }

    /**
     * Detail user tertentu
     */
    public function show($id)
    {
        $user = User::with(['role', 'unit'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }

    /**
     * Buat user baru (Admin only)
     */
    public function store(Request $request)
    {
        // Pastikan hanya admin yang bisa membuat user
        abort_if(
            $request->user()->unit->category !== 'FAKULTAS',
            403,
            'Hanya admin yang dapat membuat user'
        );

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', Password::defaults()],
            'role_id' => 'required|exists:roles,id',
            'unit_id' => 'required|exists:units,id',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role_id' => $validated['role_id'],
            'unit_id' => $validated['unit_id'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User berhasil dibuat',
            'data' => $user->load(['role', 'unit'])
        ], 201);
    }

    /**
     * Update user (Admin only)
     */
    public function update(Request $request, $id)
    {
        // Pastikan hanya admin yang bisa mengupdate user
        abort_if(
            $request->user()->unit->category !== 'FAKULTAS',
            403,
            'Hanya admin yang dapat mengupdate user'
        );

        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $id,
            'password' => ['sometimes', Password::defaults()],
            'role_id' => 'sometimes|exists:roles,id',
            'unit_id' => 'sometimes|exists:units,id',
        ]);

        // Hash password jika ada
        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'User berhasil diupdate',
            'data' => $user->load(['role', 'unit'])
        ]);
    }

    /**
     * Hapus user (Admin only)
     */
    public function destroy(Request $request, $id)
    {
        // Pastikan hanya admin yang bisa menghapus user
        abort_if(
            $request->user()->unit->category !== 'FAKULTAS',
            403,
            'Hanya admin yang dapat menghapus user'
        );

        $user = User::findOrFail($id);

        // Validasi: Tidak bisa hapus diri sendiri
        if ($user->id === $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat menghapus akun sendiri'
            ], 400);
        }

        // Validasi: Tidak bisa hapus user yang sedang memegang dokumen aktif
        if ($user->currentDocuments()->where('status', 'IN_PROGRESS')->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat menghapus user yang sedang memegang dokumen aktif'
            ], 400);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User berhasil dihapus'
        ]);
    }
}
