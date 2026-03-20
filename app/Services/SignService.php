<?php

namespace App\Services;

use App\Models\Sign;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SignService
{
    public function getSignature(User $user): ?Sign
    {
        return Sign::with('user')->where('user_id', $user->id)->latest()->first();
    }

    public function storeSignature(User $user, UploadedFile $file): Sign
    {
        return DB::transaction(function () use ($user, $file) {
            $path = $file->store('signatures', 'private');

            return Sign::create([
                'user_id'   => $user->id,
                'signature' => $path,
                'signed_at' => now(),
            ]);
        });
    }

    public function updateSignature(Sign $sign, UploadedFile $file): Sign
    {
        return DB::transaction(function () use ($sign, $file) {
            $oldPath = $sign->signature;
            $newPath = $file->store('signatures', 'private');

            $sign->update([
                'signature' => $newPath,
                'signed_at' => now(),
            ]);

            // Hapus file lama SETELAH database berhasil di-update
            if ($oldPath && Storage::disk('private')->exists($oldPath)) {
                Storage::disk('private')->delete($oldPath);
            }

            return $sign;
        });
    }

    public function deleteSignature(Sign $sign): void
    {
        if ($sign->signature && Storage::disk('private')->exists($sign->signature)) {
            Storage::disk('private')->delete($sign->signature);
        }

        $sign->delete();
    }

    public function serveSignatureFile(User $user): array
    {
        $sign = Sign::where('user_id', $user->id)->latest()->first();

        if (!$sign) {
            throw new \Exception('Tidak ada tanda tangan.', 404);
        }

        if (!Storage::disk('private')->exists($sign->signature)) {
            throw new \Exception('File tidak ditemukan.', 404);
        }

        $fileContent = Storage::disk('private')->get($sign->signature);
        $mimeType = Storage::disk('private')->mimeType($sign->signature) ?: 'image/png';

        return [
            'content'  => $fileContent,
            'mimeType' => $mimeType,
        ];
    }
}
