<?php

namespace App\Http\Controllers;

use App\Models\Sign;
use App\Services\SignService;
use Illuminate\Http\Request;
use App\Http\Requests\Sign\StoreSignRequest;
use App\Http\Requests\Sign\UpdateSignRequest;

class SignController extends Controller
{
    protected SignService $signService;

    public function __construct(SignService $signService)
    {
        $this->signService = $signService;
    }

    public function index()
    {
        $user = request()->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.'
            ], 401);
        }

        $sign = $this->signService->getSignature($user);

        return response()->json([
            'success' => true,
            'data' => $sign
        ]);
    }

    public function store(StoreSignRequest $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.'
            ], 401);
        }

        try {
            $sign = $this->signService->storeSignature($user, $request->file('signature'));

            return response()->json([
                'success' => true,
                'message' => 'Tanda tangan berhasil disimpan',
                'data' => $sign->load('user'),
            ], 201);
        } catch (\Exception $e) {
            \Log::error('Failed to store signature: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan tanda tangan ke server.'
            ], 500);
        }
    }

    public function update(UpdateSignRequest $request, $id)
    {
        $sign = Sign::findOrFail($id);

        $user = $request->user();
        if (!$user || $sign->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden.'
            ], 403);
        }

        try {
            $sign = $this->signService->updateSignature($sign, $request->file('signature'));

            return response()->json([
                'success' => true,
                'message' => 'Tanda tangan berhasil diupdate',
                'data' => $sign->load('user'),
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to update signature: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupdate tanda tangan di server.'
            ], 500);
        }
    }

    public function destroy($id)
    {
        $sign = Sign::findOrFail($id);

        $user = request()->user();
        if (!$user || $sign->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden.'
            ], 403);
        }

        $this->signService->deleteSignature($sign);

        return response()->json([
            'success' => true,
            'message' => 'Tanda tangan berhasil dihapus',
        ]);
    }

    public function file(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.'
            ], 401);
        }

        try {
            $result = $this->signService->serveSignatureFile($user);

            return response($result['content'], 200, [
                'Content-Type' => $result['mimeType'],
                'Cache-Control' => 'public, max-age=31536000',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $e->getCode() ?: 404);
        }
    }
}
