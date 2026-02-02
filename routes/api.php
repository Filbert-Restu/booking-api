<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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
// TODO: Remove in production
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

// Routes yang membutuhkan autentikasi
Route::middleware('auth:sanctum')->group(function () {

    // Signature (Tanda Tangan) Routes
    Route::prefix('signs')->group(function () {
        Route::get('/', [SignController::class, 'index']);      // Lihat tanda tangan sendiri
        Route::get('/file', [SignController::class, 'file']);   // Ambil file tanda tangan (authed user)
        Route::post('/', [SignController::class, 'store']);     // Upload tanda tangan
        Route::put('/{id}', [SignController::class, 'update']); // Update tanda tangan
        Route::delete('/{id}', [SignController::class, 'destroy']); // Hapus tanda tangan
    });

    // Document Template Routes (Kemahasiswaan)
    Route::prefix('document-templates')->group(function () {
        Route::get('/active', [DocumentTemplateController::class, 'getActiveTemplates']); // Get active templates (HARUS DI ATAS /{id})
        Route::get('/test/libreoffice', [DocumentTemplateController::class, 'testLibreOffice']); // Test LibreOffice (HARUS DI ATAS /{id})
        Route::get('/', [DocumentTemplateController::class, 'index']);                    // List templates
        Route::get('/{id}', [DocumentTemplateController::class, 'show']);                 // Detail template
        Route::get('/{id}/download', [DocumentTemplateController::class, 'download']);    // Download template
        Route::get('/{id}/preview-pdf', [DocumentTemplateController::class, 'previewPdf']); // Preview as PDF
        Route::post('/', [DocumentTemplateController::class, 'store']);                   // Upload template
        Route::post('/{id}', [DocumentTemplateController::class, 'update']);              // Update template (form-data PUT workaround)
        Route::patch('/{id}/activate', [DocumentTemplateController::class, 'activate']);  // Set as active
        Route::delete('/{id}', [DocumentTemplateController::class, 'destroy']);           // Delete template
    });

    // Workflow Routes
    Route::prefix('workflows')->group(function () {
        Route::get('/', [WorkflowController::class, 'index']);           // List workflows
        Route::get('/{id}', [WorkflowController::class, 'show']);        // Detail workflow
        Route::post('/', [WorkflowController::class, 'store']);          // Buat workflow (admin)
        Route::put('/{id}', [WorkflowController::class, 'update']);      // Update workflow
        Route::delete('/{id}', [WorkflowController::class, 'destroy']);  // Hapus workflow

        // Workflow Steps Management
        Route::post('/{id}/steps', [WorkflowController::class, 'addStep']);              // Tambah step
        Route::put('/{workflowId}/steps/{stepId}', [WorkflowController::class, 'updateStep']);   // Update step
        Route::delete('/{workflowId}/steps/{stepId}', [WorkflowController::class, 'deleteStep']); // Hapus step
    });

    // Document Routes
    Route::prefix('documents')->group(function () {
        Route::get('/', [DocumentController::class, 'index']);           // List dokumen user
        Route::get('/{id}', [DocumentController::class, 'show']);        // Detail dokumen
        Route::post('/', [DocumentController::class, 'store']);          // Buat dokumen baru
        Route::put('/{id}', [DocumentController::class, 'update']);      // Update dokumen

        // Workflow Actions
        Route::post('/{id}/submit', [DocumentController::class, 'submit']);    // Submit dokumen
        Route::post('/{id}/approve', [DocumentController::class, 'approve']);  // Approve dokumen
        Route::post('/{id}/reject', [DocumentController::class, 'reject']);    // Reject dokumen
        Route::post('/{id}/revise', [DocumentController::class, 'revise']);    // Kembalikan untuk revisi

        // Generate documents from templates
        Route::post('/{id}/generate/executive-summary', [DocumentController::class, 'generateExecutiveSummary']);
        Route::post('/{id}/generate/approval-sheet', [DocumentController::class, 'generateApprovalSheet']);

        // Serve stored document files (proposal, executive_summary, approval_sheet)
        Route::get('/{id}/file/{type}', [DocumentController::class, 'file'])->name('api.documents.file');  // Example: /documents/123/file/proposal
    });

    // Unit Routes
    Route::prefix('units')->group(function () {
        Route::get('/', [UnitController::class, 'index']);           // List units
        Route::get('/{id}', [UnitController::class, 'show']);        // Detail unit
        Route::post('/', [UnitController::class, 'store']);          // Buat unit (admin)
        Route::put('/{id}', [UnitController::class, 'update']);      // Update unit (admin)
        Route::delete('/{id}', [UnitController::class, 'destroy']);  // Hapus unit (admin)
    });

    // Role Routes
    Route::prefix('roles')->group(function () {
        Route::get('/', [RoleController::class, 'index']);           // List roles
        Route::get('/{id}', [RoleController::class, 'show']);        // Detail role
        Route::post('/', [RoleController::class, 'store']);          // Buat role (admin)
        Route::put('/{id}', [RoleController::class, 'update']);      // Update role (admin)
        Route::delete('/{id}', [RoleController::class, 'destroy']);  // Hapus role (admin)
    });

    // User Routes
    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index']);           // List users
        Route::get('/{id}', [UserController::class, 'show']);        // Detail user
        Route::post('/', [UserController::class, 'store']);          // Buat user (admin)
        Route::put('/{id}', [UserController::class, 'update']);      // Update user (admin)
        Route::delete('/{id}', [UserController::class, 'destroy']);  // Hapus user (admin)
    });

    // Room Routes (Ruangan)
    Route::prefix('rooms')->group(function () {
        Route::get('/', [RoomController::class, 'index']);                              // List rooms dengan filter
        Route::get('/{id}', [RoomController::class, 'show']);                           // Detail room + upcoming bookings
        Route::post('/', [RoomController::class, 'store']);                             // Buat room baru (admin/unit manager)
        Route::put('/{id}', [RoomController::class, 'update']);                         // Update room
        Route::delete('/{id}', [RoomController::class, 'destroy']);                     // Hapus room (soft delete)

        // Room Availability & Schedule
        Route::post('/{id}/check-availability', [RoomController::class, 'checkAvailability']); // Cek ketersediaan
        Route::get('/{id}/schedule', [RoomController::class, 'schedule']);              // Jadwal booking

        // Room Images
        Route::post('/{id}/upload-image', [RoomController::class, 'uploadImage']);      // Upload foto
        Route::delete('/{id}/images', [RoomController::class, 'deleteImage']);          // Hapus foto
    });

    // Room Booking Routes (Peminjaman Ruangan)
    Route::prefix('room-bookings')->group(function () {
        Route::get('/', [RoomBookingController::class, 'index']);                       // List bookings dengan filter
        Route::get('/statistics', [RoomBookingController::class, 'statistics']);        // Statistik booking
        Route::get('/{id}', [RoomBookingController::class, 'show']);                    // Detail booking
        Route::post('/', [RoomBookingController::class, 'store']);                      // Buat booking baru (wajib ada document)
        Route::put('/{id}', [RoomBookingController::class, 'update']);                  // Update booking (hanya PENDING)
        Route::delete('/{id}', [RoomBookingController::class, 'destroy']);              // Hapus booking (soft delete)

        // Booking Actions
        Route::post('/{id}/approve', [RoomBookingController::class, 'approve']);        // Approve booking (room manager/admin)
        Route::post('/{id}/reject', [RoomBookingController::class, 'reject']);          // Reject booking (room manager/admin)
        Route::post('/{id}/cancel', [RoomBookingController::class, 'cancel']);          // Cancel booking (booker)
        Route::post('/{id}/complete', [RoomBookingController::class, 'complete']);      // Complete booking
    });
});
