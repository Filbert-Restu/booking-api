<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    /**
     * Daftar semua unit
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Admin bisa lihat semua, user biasa hanya lihat unit mereka dan children
        if ($user->unit?->category === 'FAKULTAS' || $user->role?->slug === 'admin') {
            $units = Unit::with(['parent', 'children'])->get();
        } else {
            // User biasa hanya lihat unit mereka dan sub-unit
            $units = Unit::with(['parent', 'children'])
                ->where('id', $user->unit_id)
                ->orWhere('parent_id', $user->unit_id)
                ->get();
        }

        return response()->json([
            'success' => true,
            'data' => $units
        ]);
    }

    /**
     * Detail unit tertentu
     */
    public function show($id)
    {
        $unit = Unit::with(['parent', 'children', 'users.role'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $unit
        ]);
    }

    /**
     * Buat unit baru (Admin only)
     */
    public function store(Request $request)
    {
        // Pastikan hanya admin yang bisa membuat unit
        $user = $request->user();
        abort_if(
            $user->unit?->category !== 'FAKULTAS' && $user->role?->slug !== 'admin',
            403,
            'Hanya admin yang dapat membuat unit'
        );

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:units,code',
            'description' => 'nullable|string',
            'category' => 'required|in:FAKULTAS,PRODI,HIMA',
            'parent_id' => 'nullable|exists:units,id',
        ]);

        $unit = Unit::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Unit berhasil dibuat',
            'data' => $unit->load('parent')
        ], 201);
    }

    /**
     * Update unit (Admin only)
     */
    public function update(Request $request, $id)
    {
        // Pastikan hanya admin yang bisa mengupdate unit
        $user = $request->user();
        abort_if(
            $user->unit?->category !== 'FAKULTAS' && $user->role?->slug !== 'admin',
            403,
            'Hanya admin yang dapat mengupdate unit'
        );

        $unit = Unit::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'category' => 'sometimes|in:FAKULTAS,JURUSAN,PRODI,HIMA',
            'parent_id' => 'nullable|exists:units,id',
        ]);

        $unit->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Unit berhasil diupdate',
            'data' => $unit->load('parent')
        ]);
    }

    /**
     * Hapus unit (Admin only)
     */
    public function destroy(Request $request, $id)
    {
        // Pastikan hanya admin yang bisa menghapus unit
        $user = $request->user();
        abort_if(
            $user->unit?->category !== 'FAKULTAS' && $user->role?->slug !== 'admin',
            403,
            'Hanya admin yang dapat menghapus unit'
        );

        $unit = Unit::findOrFail($id);

        // Validasi: Tidak bisa hapus unit yang masih punya user
        if ($unit->users()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat menghapus unit yang masih memiliki user'
            ], 400);
        }

        // Validasi: Tidak bisa hapus unit yang masih punya child
        if ($unit->children()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat menghapus unit yang masih memiliki sub-unit'
            ], 400);
        }

        $unit->delete();

        return response()->json([
            'success' => true,
            'message' => 'Unit berhasil dihapus'
        ]);
    }
}
