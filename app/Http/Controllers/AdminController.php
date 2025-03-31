<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Booking;
use App\Models\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use DB;

class AdminController extends Controller
{
    /**
     * Get dashboard statistics
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDashboardStats()
    {
        try {
            // Users statistics
            $totalUsers = User::count();
            $newUsersThisMonth = User::where('created_at', '>=', Carbon::now()->startOfMonth())->count();

            // Bookings statistics
            $totalBookings = Booking::count();
            $pendingBookings = Booking::where('status', 'pending')->count();
            $confirmedBookings = Booking::where('status', 'confirmed')->count();
            $cancelledBookings = Booking::where('status', 'cancelled')->count();

            // Today's bookings
            $today = Carbon::today()->format('Y-m-d');
            $todayBookings = Booking::whereDate('date', $today)->get();
            $todayGuestsCount = $todayBookings->sum('guests');

            // Tomorrow's bookings
            $tomorrow = Carbon::tomorrow()->format('Y-m-d');
            $tomorrowBookings = Booking::whereDate('date', $tomorrow)->get();
            $tomorrowGuestsCount = $tomorrowBookings->sum('guests');

            // Menu statistics
            $totalMenuItems = MenuItem::count();
            $activeMenuItems = MenuItem::where('is_available', true)->count();

            // Simple revenue calculation (this would be more complex in a real system)
            $thisMonthBookings = Booking::whereMonth('date', Carbon::now()->month)
                ->whereYear('date', Carbon::now()->year)
                ->where('status', 'confirmed')
                ->count();
            
            $lastMonthBookings = Booking::whereMonth('date', Carbon::now()->subMonth()->month)
                ->whereYear('date', Carbon::now()->subMonth()->year)
                ->where('status', 'confirmed')
                ->count();
            
            // Assume average revenue per booking of $50
            $averageRevenuePerBooking = 50;
            $thisMonthRevenue = $thisMonthBookings * $averageRevenuePerBooking;
            $lastMonthRevenue = $lastMonthBookings * $averageRevenuePerBooking;
            
            $revenueGrowth = $lastMonthRevenue > 0 
                ? round((($thisMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100, 2)
                : 100;

            return response()->json([
                'users' => [
                    'total' => $totalUsers,
                    'new_this_month' => $newUsersThisMonth
                ],
                'bookings' => [
                    'total' => $totalBookings,
                    'pending' => $pendingBookings,
                    'confirmed' => $confirmedBookings,
                    'cancelled' => $cancelledBookings
                ],
                'today' => [
                    'date' => $today,
                    'bookings' => $todayBookings->count(),
                    'guests' => $todayGuestsCount
                ],
                'tomorrow' => [
                    'date' => $tomorrow,
                    'bookings' => $tomorrowBookings->count(),
                    'guests' => $tomorrowGuestsCount
                ],
                'menu' => [
                    'total_items' => $totalMenuItems,
                    'active_items' => $activeMenuItems
                ],
                'revenue' => [
                    'this_month' => $thisMonthRevenue,
                    'last_month' => $lastMonthRevenue,
                    'growth' => $revenueGrowth
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error getting dashboard stats: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error getting dashboard statistics',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Get all users with optional filtering
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUsers(Request $request)
    {
        try {
            $query = User::query();

            // Apply role filter if provided
            if ($request->has('role')) {
                $query->where('role', $request->role);
            }

            // Apply search if provided
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            // Apply status filter if provided
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Order by
            $sortBy = $request->sort_by ?? 'created_at';
            $sortDir = $request->sort_dir ?? 'desc';

            if (in_array($sortBy, ['id', 'name', 'email', 'role', 'status', 'created_at'])) {
                $query->orderBy($sortBy, $sortDir);
            }

            // Paginate results
            $perPage = $request->per_page ?? 15;
            $users = $query->paginate($perPage);

            return response()->json([
                'users' => $users
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching users: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error fetching users',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Get a specific user with their bookings
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUser($id)
    {
        try {
            $user = User::with(['bookings' => function($query) {
                $query->orderBy('date', 'desc')
                      ->orderBy('time', 'desc')
                      ->select('id', 'user_id', 'date', 'time', 'guests', 'status', 'created_at');
            }])->find($id);

            if (!$user) {
                return response()->json([
                    'message' => 'User not found'
                ], 404);
            }

            return response()->json([
                'user' => $user
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching user: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error fetching user',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Update user role
     *
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateUserRole($id, Request $request)
    {
        try {
            $admin = $request->user();
            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'message' => 'User not found'
                ], 404);
            }

            // Validate the request
            $validator = Validator::make($request->all(), [
                'role' => 'required|string|in:user,admin',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Prevent admin from changing their own role to prevent being locked out
            if ($admin->id === $user->id) {
                return response()->json([
                    'message' => 'You cannot change your own role'
                ], 403);
            }

            // Update the user's role
            $user->role = $request->role;
            $user->save();

            // Log the role change
            Log::info("Admin {$admin->id} ({$admin->email}) changed user {$user->id} ({$user->email}) role to {$request->role}");

            return response()->json([
                'message' => 'User role updated successfully',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'updated_at' => $user->updated_at
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error updating user role: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error updating user role',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Update user status (active/inactive)
     *
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateUserStatus($id, Request $request)
    {
        try {
            $admin = $request->user();
            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'message' => 'User not found'
                ], 404);
            }

            // Validate the request
            $validator = Validator::make($request->all(), [
                'status' => 'required|string|in:active,inactive',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Prevent admin from deactivating themselves
            if ($admin->id === $user->id) {
                return response()->json([
                    'message' => 'You cannot change your own status'
                ], 403);
            }

            // Update the user's status
            $user->status = $request->status;
            $user->save();

            // Log the status change
            Log::info("Admin {$admin->id} ({$admin->email}) changed user {$user->id} ({$user->email}) status to {$request->status}");

            return response()->json([
                'message' => 'User status updated successfully',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'status' => $user->status,
                    'updated_at' => $user->updated_at
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error updating user status: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error updating user status',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Create a new user (admin only)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createUser(Request $request)
    {
        try {
            // Validate the incoming request
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8',
                'role' => 'required|string|in:user,admin',
                'status' => 'required|string|in:active,inactive',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Create the user
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => $request->role,
                'status' => $request->status,
                'email_verified_at' => Carbon::now(), // Admin created accounts are pre-verified
            ]);

            // Log the user creation
            Log::info("Admin {$request->user()->id} ({$request->user()->email}) created new user {$user->id} ({$user->email}) with role {$user->role}");

            return response()->json([
                'message' => 'User created successfully',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'status' => $user->status,
                    'created_at' => $user->created_at
                ]
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating user: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error creating user',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Update a user's details (admin only)
     *
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateUser($id, Request $request)
    {
        try {
            $admin = $request->user();
            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'message' => 'User not found'
                ], 404);
            }

            // Validate the request
            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255',
                'email' => 'sometimes|string|email|max:255|unique:users,email,' . $user->id,
                'password' => 'sometimes|string|min:8',
                'role' => 'sometimes|string|in:user,admin',
                'status' => 'sometimes|string|in:active,inactive',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Prevent changing own role or status
            if ($admin->id === $user->id) {
                if ($request->has('role') && $request->role !== $user->role) {
                    return response()->json([
                        'message' => 'You cannot change your own role'
                    ], 403);
                }

                if ($request->has('status') && $request->status !== $user->status) {
                    return response()->json([
                        'message' => 'You cannot change your own status'
                    ], 403);
                }
            }

            // Update the user
            if ($request->has('name')) {
                $user->name = $request->name;
            }
            
            if ($request->has('email')) {
                $user->email = $request->email;
            }
            
            if ($request->has('password')) {
                $user->password = Hash::make($request->password);
            }
            
            if ($request->has('role')) {
                $user->role = $request->role;
            }
            
            if ($request->has('status')) {
                $user->status = $request->status;
            }

            $user->save();

            // Log the user update
            Log::info("Admin {$admin->id} ({$admin->email}) updated user {$user->id} ({$user->email})");

            return response()->json([
                'message' => 'User updated successfully',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'status' => $user->status,
                    'updated_at' => $user->updated_at
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error updating user: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error updating user',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Delete a user (admin only)
     *
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteUser($id, Request $request)
    {
        try {
            $admin = $request->user();
            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'message' => 'User not found'
                ], 404);
            }

            // Prevent admin from deleting themselves
            if ($admin->id === $user->id) {
                return response()->json([
                    'message' => 'You cannot delete your own account through this endpoint'
                ], 403);
            }

            // Check if user has bookings and handle them
            $hasBookings = Booking::where('user_id', $user->id)->exists();
            
            if ($hasBookings) {
                // Option 1: Prevent deletion if user has bookings
                // return response()->json([
                //     'message' => 'Cannot delete user with existing bookings'
                // ], 409);

                // Option 2: Cancel all user's bookings
                Booking::where('user_id', $user->id)
                       ->where('status', '!=', 'completed')
                       ->where('status', '!=', 'cancelled')
                       ->update(['status' => 'cancelled']);
            }

            // Delete user's tokens
            $user->tokens()->delete();
            
            // Log the user deletion
            $userName = $user->name;
            $userEmail = $user->email;
            
            // Delete the user
            $user->delete();

            Log::info("Admin {$admin->id} ({$admin->email}) deleted user ID {$id} ({$userEmail})");

            return response()->json([
                'message' => 'User deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error deleting user: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error deleting user',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Get user activity logs (admin only)
     *
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserActivity($id, Request $request)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'message' => 'User not found'
                ], 404);
            }

            // Get bookings activity
            $bookings = Booking::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->select('id', 'date', 'time', 'guests', 'status', 'created_at', 'updated_at')
                ->get();

            // Get login activity (would require a separate table in a real implementation)
            // This is a placeholder for demonstration
            $loginActivity = [];

            return response()->json([
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'bookings_activity' => $bookings,
                'login_activity' => $loginActivity
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching user activity: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error fetching user activity',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Get system permissions and roles (admin only)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPermissionsAndRoles()
    {
        try {
            // In a real system with more complex permissions,
            // this would be fetched from a database
            $roles = [
                [
                    'name' => 'user',
                    'description' => 'Regular user with restricted access',
                    'permissions' => [
                        'create_booking', 'update_own_booking', 'cancel_own_booking', 
                        'view_own_profile', 'update_own_profile'
                    ]
                ],
                [
                    'name' => 'admin',
                    'description' => 'Administrator with full system access',
                    'permissions' => [
                        'create_booking', 'update_any_booking', 'cancel_any_booking',
                        'view_any_profile', 'update_any_profile', 'delete_users',
                        'manage_menu', 'manage_bookings', 'view_statistics',
                        'manage_roles', 'manage_system_settings'
                    ]
                ]
            ];

            $permissions = [
                ['name' => 'create_booking', 'description' => 'Can create bookings'],
                ['name' => 'update_own_booking', 'description' => 'Can update own bookings'],
                ['name' => 'update_any_booking', 'description' => 'Can update any booking'],
                ['name' => 'cancel_own_booking', 'description' => 'Can cancel own bookings'],
                ['name' => 'cancel_any_booking', 'description' => 'Can cancel any booking'],
                ['name' => 'view_own_profile', 'description' => 'Can view own profile'],
                ['name' => 'view_any_profile', 'description' => 'Can view any profile'],
                ['name' => 'update_own_profile', 'description' => 'Can update own profile'],
                ['name' => 'update_any_profile', 'description' => 'Can update any profile'],
                ['name' => 'delete_users', 'description' => 'Can delete users'],
                ['name' => 'manage_menu', 'description' => 'Can manage menu items'],
                ['name' => 'manage_bookings', 'description' => 'Can manage all bookings'],
                ['name' => 'view_statistics', 'description' => 'Can view system statistics'],
                ['name' => 'manage_roles', 'description' => 'Can manage user roles'],
                ['name' => 'manage_system_settings', 'description' => 'Can manage system settings']
            ];

            return response()->json([
                'roles' => $roles,
                'permissions' => $permissions
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching permissions and roles: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error fetching permissions and roles',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }
}