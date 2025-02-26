<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Register a new user.
     */
    public function register(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'phone' => 'required|string|size:11|unique:users',
                'type' => 'required|string',
                'password' => 'required|string|min:6',
            ]);

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'type' => $request->type,
                'password' => Hash::make($request->password),
            ]);

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => 'User registered successfully.',
                'data' => [
                    'user' => $user,
                    'token' => $token,
                ],
                'errors' => null,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to register user. Please try again later.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Login user and return token.
     */
    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required',
            ]);

            $user = User::where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'status' => 401,
                    'message' => 'Invalid credentials.',
                    'data' => null,
                    'errors' => 'Invalid credentials.',
                ], 401);
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Login successful.',
                'data' => [
                    'user' => $user,
                    'token' => $token,
                ],
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to process login. Please try again later.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Logout user (delete token).
     */
    public function logout(Request $request)
    {
        try {
            $request->user()->tokens()->delete();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Logged out successfully.',
                'data' => null,
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to log out. Please try again later.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Get authenticated user details.
     */
    public function profile(Request $request)
    {
        try {
            $user = $request->user();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'User profile retrieved successfully.',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'type' => $user->type,
                    'address' => $user->address,
                    'role_id' => $user->role_id,
                    'status' => $user->status,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ],
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to retrieve user profile. Please try again later.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }


    // User registration
    public function Userregister(Request $request)
    {
        try {
            // Validate the request data
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8',
                'phone' => 'required|string|size:11',
            ]);

            // If validation fails, return error response
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'status' => 422,
                    'message' => 'Validation failed.',
                    'data' => null,
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Create the user
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'phone' => $request->phone,
                'type' => 'user',
                'status' => 1,
            ]);

            // Return success response
            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => 'User registered successfully.',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'type' => $user->type,
                    'status' => $user->status,
                ],
                'errors' => null,
            ], 201);
        } catch (\Exception $e) {
            // Handle any exceptions
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while registering the user.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // user login
    public function Userlogin(Request $request)
    {
        try {
            // Validate the request data
            $validator = Validator::make($request->all(), [
                'email' => 'required|string|email',
                'password' => 'required|string',
            ]);

            // If validation fails, return error response
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'status' => 422,
                    'message' => 'Validation failed.',
                    'data' => null,
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Attempt to authenticate the user
            if (Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
                $user = Auth::user();

                // Check if the user type is 'user'
                if ($user->type !== 'user') {
                    return response()->json([
                        'success' => false,
                        'status' => 403,
                        'message' => 'Access denied. Only users can log in.',
                        'data' => null,
                        'errors' => null,
                    ], 403);
                }

                // Generate a token for the user
                $token = $user->createToken('auth_token')->plainTextToken;

                // Return success response with token
                return response()->json([
                    'success' => true,
                    'status' => 200,
                    'message' => 'User logged in successfully.',
                    'data' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'type' => $user->type,
                        'status' => $user->status,
                        'token' => $token,
                    ],
                    'errors' => null,
                ], 200);
            }

            // If authentication fails, return error response
            return response()->json([
                'success' => false,
                'status' => 401,
                'message' => 'Invalid email or password.',
                'data' => null,
                'errors' => null,
            ], 401);
        } catch (\Exception $e) {
            // Handle any exceptions
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while logging in.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }
}
