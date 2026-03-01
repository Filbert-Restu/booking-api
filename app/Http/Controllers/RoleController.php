<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    /**
     * Daftar semua role
     */
    public function index()
    {
        $roles = Role::withCount('users')->get();

        return response()->json([
            'success' => true,
            'data' => $roles
        ]);
    }

    /**
     * Detail role tertentu
     */
    public function show($id)
    {
        $role = Role::with('users.unit')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $role
        ]);
    }

    /**
     * Buat role baru (Admin only)
     */
    public function store(Request $request)
    {
        // Pastikan hanya admin yang bisa membuat role
        $user = $request->user();
        abort_if(
            $user->unit?->category !== 'FAKULTAS' && $user->role?->slug !== 'admin',
            403,
            'Hanya admin yang dapat membuat role'
        );

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:roles,slug',
            'description' => 'nullable|string',
        ]);

        $role = Role::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Role berhasil dibuat',
            'data' => $role
        ], 201);
    }

    /**
     * Update role (Admin only)
     */
    public function update(Request $request, $id)
    {
        // Pastikan hanya admin yang bisa mengupdate role
        $user = $request->user();
        abort_if(
            $user->unit?->category !== 'FAKULTAS' && $user->role?->slug !== 'admin',
            403,
            'Hanya admin yang dapat mengupdate role'
        );

        $role = Role::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:255|unique:roles,slug,' . $id,
            'description' => 'nullable|string',
        ]);

        $role->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Role berhasil diupdate',
            'data' => $role
        ]);
    }

    /**
     * Hapus role (Admin only)
     */
    public function destroy(Request $request, $id)
    {
        // Pastikan hanya admin yang bisa menghapus role
        $user = $request->user();
        abort_if(
            $user->unit?->category !== 'FAKULTAS' && $user->role?->slug !== 'admin',
            403,
            'Hanya admin yang dapat menghapus role'
        );

        $role = Role::findOrFail($id);

        // Validasi: Tidak bisa hapus role yang masih digunakan user
        if ($role->users()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat menghapus role yang masih digunakan oleh user'
            ], 400);
        }

        $role->delete();

        return response()->json([
            'success' => true,
            'message' => 'Role berhasil dihapus'
        ]);
    }
}
