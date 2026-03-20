<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function listUsers(User $authUser, array $filters): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = User::with(['role', 'unit']);

        // Admin bisa lihat semua, user biasa hanya lihat user di unit yang sama
        if ($authUser->unit?->category !== 'FAKULTAS' && $authUser->role?->slug !== 'admin') {
            $query->where('unit_id', $authUser->unit_id);
        }

        if (!empty($filters['role_id'])) {
            $query->where('role_id', $filters['role_id']);
        }

        if (!empty($filters['unit_id'])) {
            $query->where('unit_id', $filters['unit_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $query->latest()->paginate($filters['per_page'] ?? 15);
    }

    public function createUser(array $data): User
    {
        return User::create([
            'name'    => $data['name'],
            'email'   => $data['email'],
            'password' => Hash::make($data['password']),
            'role_id' => $data['role_id'],
            'unit_id' => $data['unit_id'],
        ]);
    }

    public function updateUser(User $user, array $data): User
    {
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);

        return $user;
    }

    public function deleteUser(User $user, User $authUser): void
    {
        if ($user->id === $authUser->id) {
            throw new \Exception('Tidak dapat menghapus akun sendiri', 400);
        }

        if ($user->currentDocuments()->where('status', 'IN_PROGRESS')->count() > 0) {
            throw new \Exception('Tidak dapat menghapus user yang sedang memegang dokumen aktif', 400);
        }

        $user->delete();
    }

    public function updateProfile(User $user, array $data): User
    {
        $user->update([
            'name'                 => $data['name'],
            'nim_nip'              => $data['nim_nip'],
            'role_id'              => $data['role_id'],
            'unit_id'              => $data['unit_id'],
            'is_profile_completed' => true,
        ]);

        return $user;
    }
}
