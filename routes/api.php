<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\WorkflowController;
use App\Http\Controllers\DocumentController;

// Get authenticated user
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Routes yang membutuhkan autentikasi
Route::middleware('auth:sanctum')->group(function () {

    // Workflow Routes
    Route::prefix('workflows')->group(function () {
        Route::get('/', [WorkflowController::class, 'index']);           // List workflows
        Route::get('/{id}', [WorkflowController::class, 'show']);        // Detail workflow
        Route::post('/', [WorkflowController::class, 'store']);          // Buat workflow (admin)
        Route::put('/{id}', [WorkflowController::class, 'update']);      // Update workflow
        Route::delete('/{id}', [WorkflowController::class, 'destroy']);  // Hapus workflow
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
    });
});
