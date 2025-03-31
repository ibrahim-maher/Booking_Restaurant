<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
   public function index()
   {
      return response()->json([
         'data' => User::all()
      ], 200);
   }

   /**
    * Show the form for creating a new resource.
    */
   public function createUser(Request $request)
   {
      $request->validate([
         'name' => 'required|string|max:255',
         'email' => 'required|email|unique:users,email',
         'password' => 'required|string|min:8|confirmed',
         'role' => 'required|in:user,admin', // Only allow 'user' or 'admin'
      ]);

      try {
         $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role, // Admin can assign role
         ]);

         return response()->json([
            'message' => 'User created successfully',
            'user' => $user,
         ], 201);
      } catch (\Exception $e) {
         return response()->json([
            'message' => 'User creation failed: ' . $e->getMessage(),
         ], 500);
      }
   }


   /**
    * Store a newly created resource in storage.
    */
   public function store(Request $request)
   {
      //
   }

   /**
    * Display the specified resource.
    */
   public function show(string $id)
   {
      //
   }

   /**
    * Show the form for editing the specified resource.
    */
   public function edit(string $id)
   {
      //
   }

   /**
    * Update the specified resource in storage.
    */
   public function updateUserRole(Request $request, $id)
   {
      $request->validate([
         'role' => 'required|in:user,admin', // Ensure only valid roles
      ]);

      $user = User::find($id);

      if (!$user) {
         return response()->json([
            'message' => 'User not found',
         ], 404);
      }

      try {
         $user->role = $request->role;
         $user->save();

         return response()->json([
            'message' => 'User role updated successfully',
            'user' => $user,
         ], 200);
      } catch (\Exception $e) {
         return response()->json([
            'message' => 'Failed to update user role: ' . $e->getMessage(),
         ], 500);
      }
   }


   /**
    * Remove the specified resource from storage.
    */
   public function destroy(string $id)
   {
      //
   }
   public function deleteUser($id)
   {
      $user = User::find($id);

      if (!$user) {
         return response()->json([
            'message' => 'User not found',
         ], 404);
      }

      $user->tokens()->delete();
      $user->delete();

      return response()->json([
         'message' => 'User deleted Successfully',
      ], 200);
   }
}
