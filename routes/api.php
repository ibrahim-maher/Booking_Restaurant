<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\MenuItemController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application.
|
*/

// Public authentication routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::post('/check-email', [AuthController::class, 'checkEmail']);
Route::get('/verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])->name('verification.verify');

// Public menu routes (optionally, you might want these to be public)
Route::get('/menu', [MenuItemController::class, 'index']);
Route::get('/menu/{id}', [MenuItemController::class, 'show']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // User profile management
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::delete('/destroy', [AuthController::class, 'destroy']);
    Route::post('/email/resend', [AuthController::class, 'resendVerification'])->middleware('throttle:6,1');
    
    // Booking routes
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::get('/bookings/{id}', [BookingController::class, 'show']);
    Route::put('/bookings/{id}', [BookingController::class, 'update']);
    Route::delete('/bookings/{id}', [BookingController::class, 'destroy']);
    
    // Admin routes
    Route::middleware('admin')->group(function () {
        // User management
        Route::get('/users', [AdminController::class, 'index']);
        Route::delete('/user/{id}', [AdminController::class, 'deleteUser']);
        Route::post('/admin/users', [AdminController::class, 'createUser']);
        Route::put('/admin/users/{id}/role', [AdminController::class, 'updateUserRole']);
        
        // Menu management
        Route::post('/menu', [MenuItemController::class, 'store']);
        Route::put('/menu/{id}', [MenuItemController::class, 'update']);
        Route::delete('/menu/{id}', [MenuItemController::class, 'destroy']);
        
        // Booking management
        Route::get('/admin/bookings', [BookingController::class, 'adminIndex']);
        Route::put('/admin/bookings/{id}/status', [BookingController::class, 'updateStatus']);
        
        // Restaurant settings (if you add this feature)
        Route::get('/admin/settings', [AdminController::class, 'getSettings']);
        Route::put('/admin/settings', [AdminController::class, 'updateSettings']);
        
        // Reports and analytics (if you add this feature)
        Route::get('/admin/reports/bookings', [AdminController::class, 'bookingReports']);
        Route::get('/admin/reports/revenue', [AdminController::class, 'revenueReports']);
    });
});