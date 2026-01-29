<?php

namespace App\Http\Controllers;

use App\Models\Sign;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class SignController extends Controller
{
    /**
     * Get the authenticated user's signature
     */
    public function index()
    {
        $user = request()->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.'
            ], 401);
        }

        $sign = Sign::with('user')->where('user_id', $user->id)->latest()->first();
        return response()->json([
            'success' => true,
            'data' => $sign
        ]);
    }

    /**
     * Store a new signature for a user
     */
    public function store(Request $request)
    {

        $validated = $request->validate([
            'signature' => 'required|file|mimes:png,jpg,jpeg|max:2048',
        ]);

        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.'
            ], 401);
        }

        $path = $request->file('signature')->store('signatures');

        $sign = Sign::create([
            'user_id' => $user->id,
            'signature' => $path,
            'signed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tanda tangan berhasil disimpan',
            'data' => $sign->load('user'),
        ], 201);
    }

    /**
     * Update a signature (replace file)
     */
    public function update(Request $request, $id)
    {
        $sign = Sign::findOrFail($id);

        $validated = $request->validate([
            'signature' => 'required|file|mimes:png,jpg,jpeg|max:2048',
        ]);

        // ownership check
        $user = $request->user();
        if (!$user || $sign->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden.'
            ], 403);
        }

        // Hapus file lama
        if ($sign->signature && Storage::exists($sign->signature)) {
            Storage::delete($sign->signature);
        }

        $path = $request->file('signature')->store('signatures');
        $sign->update([
            'signature' => $path,
            'signed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tanda tangan berhasil diupdate',
            'data' => $sign->load('user'),
        ]);
    }

    /**
     * Delete a signature
     */
    public function destroy($id)
    {
        $sign = Sign::findOrFail($id);

        // ownership check
        $user = request()->user();
        if (!$user || $sign->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden.'
            ], 403);
        }

        if ($sign->signature && Storage::exists($sign->signature)) {
            Storage::delete($sign->signature);
        }
        $sign->delete();
        return response()->json([
            'success' => true,
            'message' => 'Tanda tangan berhasil dihapus',
        ]);
    }

    /**
     * Serve authenticated user's signature file
     */
    public function file(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.'
            ], 401);
        }

        $sign = Sign::where('user_id', $user->id)->latest()->first();
        if (!$sign) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada tanda tangan.'
            ], 404);
        }

        if (!Storage::exists($sign->signature)) {
            return response()->json([
                'success' => false,
                'message' => 'File tidak ditemukan.'
            ], 404);
        }

        $fullPath = Storage::path($sign->signature);
        $mime = mime_content_type($fullPath) ?: 'application/octet-stream';
        return response()->file($fullPath, ['Content-Type' => $mime]);
    }
}
