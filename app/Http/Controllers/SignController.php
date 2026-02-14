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
        $validator = Validator::make($request->all(), [
            'signature' => 'required|file|mimes:png,jpg,jpeg|max:2048',
        ]);

        if ($validator->fails()) {
            \Log::error('Signature validation failed:', $validator->errors()->toArray());
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal: ' . implode(', ', $validator->errors()->all()),
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.'
            ], 401);
        }

        try {
            return \DB::transaction(function () use ($request, $user) {
                $path = $request->file('signature')->store('signatures', 'private');

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
            });
        } catch (\Exception $e) {
            \Log::error('Failed to store signature: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan tanda tangan ke server.'
            ], 500);
        }
    }

    /**
     * Update a signature (replace file)
     */
    public function update(Request $request, $id)
    {
        $sign = Sign::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'signature' => 'required|file|mimes:png,jpg,jpeg|max:2048',
        ]);

        if ($validator->fails()) {
             \Log::error('Signature update validation failed:', $validator->errors()->toArray());
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal: ' . implode(', ', $validator->errors()->all()),
                'errors' => $validator->errors()
            ], 422);
        }

        // ownership check
        $user = $request->user();
        if (!$user || $sign->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden.'
            ], 403);
        }

        try {
            return \DB::transaction(function () use ($request, $sign) {
                $oldPath = $sign->signature;
                $newPath = $request->file('signature')->store('signatures', 'private');

                $sign->update([
                    'signature' => $newPath,
                    'signed_at' => now(),
                ]);

                // Hapus file lama SETELAH database berhasil di-update
                if ($oldPath && Storage::disk('private')->exists($oldPath)) {
                    Storage::disk('private')->delete($oldPath);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Tanda tangan berhasil diupdate',
                    'data' => $sign->load('user'),
                ]);
            });
        } catch (\Exception $e) {
            \Log::error('Failed to update signature: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupdate tanda tangan di server.'
            ], 500);
        }
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

        if ($sign->signature && Storage::disk('private')->exists($sign->signature)) {
            Storage::disk('private')->delete($sign->signature);
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

        if (!Storage::disk('private')->exists($sign->signature)) {
            return response()->json([
                'success' => false,
                'message' => 'File tidak ditemukan.'
            ], 404);
        }

        // Get file content from MinIO
        $fileContent = Storage::disk('private')->get($sign->signature);
        $mimeType = Storage::disk('private')->mimeType($sign->signature) ?: 'image/png';

        return response($fileContent, 200, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'public, max-age=31536000',
        ]);
    }
}
