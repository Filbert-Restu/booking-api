<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Admin
use App\Http\Controllers\Admin\AdminUserController;

use App\Http\Controllers\WorkflowController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\RoomBookingController;
use App\Http\Controllers\SignController;
use App\Http\Controllers\DocumentTemplateController;

// Get authenticated user
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    $user = $request->user();
    $user->load(['role', 'unit']);
    return $user;
});

// DEV ONLY: Get all users for development login menu
// Hanya bisa diakses di environment local/development
if (app()->environment('local', 'development')) {
    Route::get('/dev/users', function () {
        return \App\Models\User::with(['role', 'unit'])
            ->select('id', 'name', 'email', 'role_id', 'unit_id')
            ->orderBy('role_id')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role?->name ?? 'Unknown',
                    'unit' => $user->unit?->name ?? 'Unknown',
                ];
            });
    });
}

// Public Room Image Route (Accessed by <img> tags)
Route::get('/rooms/{id}/image', 'App\Http\Controllers\RoomController@serveImage')->name('api.rooms.image');

// Routes yang membutuhkan autentikasi
Route::middleware('auth:sanctum')->group(function () {

    // =============================================
    // ADMIN-ONLY ROUTES
    // =============================================
    Route::middleware('admin')->group(function () {
        // Admin Dashboard
        Route::prefix('admin/dashboard')->group(function () {
            Route::get('/stats', [App\Http\Controllers\Admin\DashboardController::class, 'stats']);
        });

        // Document Template Management (Upload, Edit, Delete, Activate)
        Route::prefix('document-templates')->group(function () {
            Route::post('/', [DocumentTemplateController::class, 'store']);                   // Upload template
            Route::post('/{id}', [DocumentTemplateController::class, 'update']);              // Update template
            Route::put('/{id}/placeholder-metadata', [DocumentTemplateController::class, 'updatePlaceholderMetadata']);
            Route::patch('/{id}/activate', [DocumentTemplateController::class, 'activate']);
            Route::patch('/{id}/deactivate', [DocumentTemplateController::class, 'deactivate']);
            Route::delete('/{id}', [DocumentTemplateController::class, 'destroy']);
        });

        // Room Management (Create, Update, Delete, Upload Image)
        Route::prefix('rooms')->group(function () {
            Route::post('/', [RoomController::class, 'store']);
            Route::put('/{id}', [RoomController::class, 'update']);
            Route::delete('/{id}', [RoomController::class, 'destroy']);
            Route::post('/{id}/upload-image', [RoomController::class, 'uploadImage']);
            Route::delete('/{id}/images', [RoomController::class, 'deleteImage']);
        });

        // Booking Admin Actions (Approve, Reject)
        Route::prefix('room-bookings')->group(function () {
            Route::post('/{id}/approve', [RoomBookingController::class, 'approve']);
            Route::post('/{id}/reject', [RoomBookingController::class, 'reject']);
        });
    });

    // =============================================
    // AUTHENTICATED USER ROUTES
    // =============================================

    // Signature (Tanda Tangan) Routes
    Route::prefix('signs')->group(function () {
        Route::get('/', [SignController::class, 'index']);
        Route::get('/file', [SignController::class, 'file']);
        Route::post('/', [SignController::class, 'store']);
        Route::put('/{id}', [SignController::class, 'update']);
        Route::delete('/{id}', [SignController::class, 'destroy']);
    });

    // Document Template Read Routes
    Route::prefix('document-templates')->group(function () {
        Route::get('/active', [DocumentTemplateController::class, 'getActiveTemplates']);
        Route::get('/available-fields', [DocumentTemplateController::class, 'getAvailableFields']);
        Route::get('/test/libreoffice', [DocumentTemplateController::class, 'testLibreOffice']);
        Route::get('/', [DocumentTemplateController::class, 'index']);
        Route::get('/{id}', [DocumentTemplateController::class, 'show']);
        Route::get('/{id}/download', [DocumentTemplateController::class, 'download']);
        Route::get('/{id}/preview-pdf', [DocumentTemplateController::class, 'previewPdf']);
    });

    // Workflow Routes
    Route::prefix('workflows')->group(function () {
        Route::get('/', [WorkflowController::class, 'index']);
        Route::get('/{id}', [WorkflowController::class, 'show']);
        Route::post('/', [WorkflowController::class, 'store']);          // Has internal admin check
        Route::put('/{id}', [WorkflowController::class, 'update']);      // Has internal admin check
        Route::delete('/{id}', [WorkflowController::class, 'destroy']);  // Has internal admin check

        // Workflow Steps Management
        Route::post('/{id}/steps', [WorkflowController::class, 'addStep']);
        Route::put('/{workflowId}/steps/{stepId}', [WorkflowController::class, 'updateStep']);
        Route::delete('/{workflowId}/steps/{stepId}', [WorkflowController::class, 'deleteStep']);
    });

    // Document Routes
    Route::prefix('documents')->group(function () {
        Route::get('/', [DocumentController::class, 'index']);
        Route::get('/{id}', [DocumentController::class, 'show']);
        Route::post('/', [DocumentController::class, 'store']);
        Route::put('/{id}', [DocumentController::class, 'update']);

        // Workflow Actions
        Route::post('/{id}/submit', [DocumentController::class, 'submit']);
        Route::post('/{id}/approve', [DocumentController::class, 'approve']);
        Route::post('/{id}/reject', [DocumentController::class, 'reject']);
        Route::post('/{id}/revise', [DocumentController::class, 'revise']);
        Route::post('/{id}/apply-signature', [DocumentController::class, 'applySignature']);

        // Generate documents from templates
        Route::post('/{id}/generate/executive-summary', [DocumentController::class, 'generateExecutiveSummary']);
        Route::post('/{id}/generate/approval-sheet', [DocumentController::class, 'generateApprovalSheet']);

        // Serve stored document files
        Route::get('/{id}/file/{type}', [DocumentController::class, 'file'])->name('api.documents.file');
        Route::get('/{id}/file/{type}/pdf', [DocumentController::class, 'filePdf'])->name('api.documents.file.pdf');
    });

    // Unit Routes
    Route::prefix('units')->group(function () {
        Route::get('/', [UnitController::class, 'index']);
        Route::get('/{id}', [UnitController::class, 'show']);
        Route::post('/', [UnitController::class, 'store']);          // Has internal admin check
        Route::put('/{id}', [UnitController::class, 'update']);      // Has internal admin check
        Route::delete('/{id}', [UnitController::class, 'destroy']);  // Has internal admin check
    });

    // Role Routes
    Route::prefix('roles')->group(function () {
        Route::get('/', [RoleController::class, 'index']);
        Route::get('/{id}', [RoleController::class, 'show']);
        Route::post('/', [RoleController::class, 'store']);          // Has internal admin check
        Route::put('/{id}', [RoleController::class, 'update']);      // Has internal admin check
        Route::delete('/{id}', [RoleController::class, 'destroy']);  // Has internal admin check
    });

    // User Routes
    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::get('/{id}', [UserController::class, 'show']);
        Route::post('/', [UserController::class, 'store']);          // Has internal admin check
        Route::put('/{id}', [UserController::class, 'update']);      // Has internal admin check
        Route::delete('/{id}', [UserController::class, 'destroy']);  // Has internal admin check
    });

    // Room Routes — Read Only (semua authenticated user)
    Route::prefix('rooms')->group(function () {
        Route::get('/', [RoomController::class, 'index']);
        Route::get('/{id}', [RoomController::class, 'show']);
        Route::post('/{id}/check-availability', [RoomController::class, 'checkAvailability']);
        Route::get('/{id}/schedule', [RoomController::class, 'schedule']);
    });

    // Room Booking Routes
    Route::prefix('room-bookings')->group(function () {
        Route::get('/', [RoomBookingController::class, 'index']);
        Route::get('/statistics', [RoomBookingController::class, 'statistics']);
        Route::get('/{id}', [RoomBookingController::class, 'show']);
        Route::post('/', [RoomBookingController::class, 'store']);
        Route::put('/{id}', [RoomBookingController::class, 'update']);
        Route::delete('/{id}', [RoomBookingController::class, 'destroy']);

        // User Actions
        Route::post('/{id}/cancel', [RoomBookingController::class, 'cancel']);
        Route::post('/{id}/complete', [RoomBookingController::class, 'complete']);
    });
});
