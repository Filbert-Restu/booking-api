<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Request;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Requests\User\UpdateProfileRequest;

class UserController extends Controller
{
    protected UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function index(Request $request)
    {
        $users = $this->userService->listUsers($request->user(), $request->all());

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }

    public function show($id)
    {
        $user = User::with(['role', 'unit'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }

    public function store(StoreUserRequest $request)
    {
        abort_if(
            $request->user()->unit->category !== 'FAKULTAS',
            403,
            'Hanya admin yang dapat membuat user'
        );

        $user = $this->userService->createUser($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'User berhasil dibuat',
            'data' => $user->load(['role', 'unit'])
        ], 201);
    }

    public function update(UpdateUserRequest $request, $id)
    {
        abort_if(
            $request->user()->unit->category !== 'FAKULTAS',
            403,
            'Hanya admin yang dapat mengupdate user'
        );

        $user = User::findOrFail($id);
        $user = $this->userService->updateUser($user, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'User berhasil diupdate',
            'data' => $user->load(['role', 'unit'])
        ]);
    }

    public function destroy(Request $request, $id)
    {
        abort_if(
            $request->user()->unit->category !== 'FAKULTAS',
            403,
            'Hanya admin yang dapat menghapus user'
        );

        $user = User::findOrFail($id);

        try {
            $this->userService->deleteUser($user, $request->user());
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $e->getCode() ?: 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'User berhasil dihapus'
        ]);
    }

    public function updateProfile(UpdateProfileRequest $request)
    {
        $user = $this->userService->updateProfile($request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Profil berhasil dilengkapi',
            'data'    => $user->load(['role', 'unit'])
        ]);
    }
}
