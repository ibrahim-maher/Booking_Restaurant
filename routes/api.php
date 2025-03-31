<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\MenuItemController;
use App\Http\Controllers\NotificationController;
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

// ======================================================================
// PUBLIC ROUTES (No Authentication Required)
// ======================================================================

// Public authentication routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::post('/check-email', [AuthController::class, 'checkEmail']);
Route::get('/verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])->name('verification.verify');

// Public menu routes
Route::prefix('menu')->group(function () {
    // Get all menu items with optional filters
    Route::get('/items', [MenuItemController::class, 'index']);
    
    // Get a specific menu item
    Route::get('/items/{id}', [MenuItemController::class, 'show']);
    
    // Get all menu categories
    Route::get('/categories', [MenuItemController::class, 'getCategories']);
    
    // Get menu items by category
    Route::get('/categories/{category}', [MenuItemController::class, 'getByCategory']);
    
    // Get featured menu items
    Route::get('/featured', [MenuItemController::class, 'getFeatured']);
});

// Legacy menu routes (for backward compatibility)
Route::get('/menu-items', [MenuItemController::class, 'index']);
Route::get('/menu-items/{id}', [MenuItemController::class, 'show']);
Route::get('/menu-categories', [MenuItemController::class, 'getCategories']);
Route::get('/menu-items/category/{category}', [MenuItemController::class, 'getByCategory']);
Route::get('/menu-items/featured', [MenuItemController::class, 'getFeatured']);

// ======================================================================
// AUTHENTICATED USER ROUTES
// ======================================================================

Route::middleware('auth:sanctum')->group(function () {
    // User authentication & profile
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::delete('/destroy', [AuthController::class, 'destroy']);
    Route::post('/email/resend', [AuthController::class, 'resendVerification'])->middleware('throttle:6,1');
    
    // User bookings
    Route::prefix('bookings')->group(function () {
        Route::get('/', [BookingController::class, 'index']);
        Route::post('/', [BookingController::class, 'store']);
        Route::get('/{id}', [BookingController::class, 'show']);
        Route::put('/{id}', [BookingController::class, 'update']);
        Route::delete('/{id}', [BookingController::class, 'destroy']);
        
        // Booking availability
        Route::post('/check-availability', [BookingController::class, 'checkAvailability']);
        Route::get('/available-slots', [BookingController::class, 'getAvailableTimeSlots']);
    });

    // Notifications
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::put('/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::put('/read-all', [NotificationController::class, 'markAllAsRead']);
    });

    // ======================================================================
    // ADMIN ROUTES
    // ======================================================================
    
    Route::middleware('admin')->group(function () {
        // Admin Dashboard
        Route::prefix('admin')->group(function () {
            // Dashboard statistics
            Route::get('/dashboard/stats', [AdminController::class, 'getDashboardStats']);
            
            // Admin Booking Management
            Route::prefix('bookings')->group(function () {
                Route::get('/', [BookingController::class, 'adminIndex']);
                Route::get('/{id}', [BookingController::class, 'adminShow']);
                Route::put('/{id}/status', [BookingController::class, 'updateStatus']);
                Route::get('/calendar', [BookingController::class, 'getCalendarView']);
                Route::get('/date/{date}', [BookingController::class, 'getBookingsByDate']);
                Route::get('/stats', [BookingController::class, 'getBookingStats']);
            });

            // User Management
            Route::prefix('users')->group(function () {
                Route::get('/', [AdminController::class, 'getUsers']);
                Route::post('/', [AdminController::class, 'createUser']);
                Route::get('/{id}', [AdminController::class, 'getUser']);
                Route::put('/{id}', [AdminController::class, 'updateUser']);
                Route::delete('/{id}', [AdminController::class, 'deleteUser']);
                Route::put('/{id}/role', [AdminController::class, 'updateUserRole']);
                Route::put('/{id}/status', [AdminController::class, 'updateUserStatus']);
                Route::get('/{id}/activity', [AdminController::class, 'getUserActivity']);
            });
            
            // System Settings
            Route::get('/permissions-roles', [AdminController::class, 'getPermissionsAndRoles']);
        });

        // Admin Menu Management
        Route::prefix('admin/menu')->group(function () {
            // Menu items management
            Route::post('/items', [MenuItemController::class, 'store']);
            Route::put('/items/{id}', [MenuItemController::class, 'update']);
            Route::delete('/items/{id}', [MenuItemController::class, 'destroy']);
            Route::put('/items/{id}/toggle-availability', [MenuItemController::class, 'toggleAvailability']);
            Route::put('/items/{id}/toggle-featured', [MenuItemController::class, 'toggleFeatured']);
            Route::get('/stats', [MenuItemController::class, 'getStats']);
            
            // Menu categories management
            Route::post('/categories', [MenuItemController::class, 'createCategory']);
            Route::put('/categories/{id}', [MenuItemController::class, 'updateCategory']);
            Route::delete('/categories/{id}', [MenuItemController::class, 'destroyCategory']);
            Route::post('/categories/reorder', [MenuItemController::class, 'reorderCategories']);
        });
    });
});