<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use App\Mail\VerifyEmail;

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
            // Extract only the main error message
            $errorMessage = $e->getMessage();

            // Check if it's a SQL Integrity Constraint Violation
            if (str_contains($errorMessage, 'Integrity constraint violation')) {
                preg_match("/Duplicate entry '(.+?)' for key '(.+?)'/", $errorMessage, $matches);
                if (!empty($matches)) {
                    $errorMessage = "Duplicate entry '{$matches[1]}' for key '{$matches[2]}'";
                }
            }
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to register user. Please try again later.',
                'data' => null,
                'errors' => $errorMessage,
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
            // Extract only the main error message
            $errorMessage = $e->getMessage();

            // Check if it's a SQL Integrity Constraint Violation
            if (str_contains($errorMessage, 'Integrity constraint violation')) {
                preg_match("/Duplicate entry '(.+?)' for key '(.+?)'/", $errorMessage, $matches);
                if (!empty($matches)) {
                    $errorMessage = "Duplicate entry '{$matches[1]}' for key '{$matches[2]}'";
                }
            }
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to process login. Please try again later.',
                'data' => null,
                'errors' => $errorMessage,
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
            // Extract only the main error message
            $errorMessage = $e->getMessage();

            // Check if it's a SQL Integrity Constraint Violation
            if (str_contains($errorMessage, 'Integrity constraint violation')) {
                preg_match("/Duplicate entry '(.+?)' for key '(.+?)'/", $errorMessage, $matches);
                if (!empty($matches)) {
                    $errorMessage = "Duplicate entry '{$matches[1]}' for key '{$matches[2]}'";
                }
            }
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to log out. Please try again later.',
                'data' => null,
                'errors' => $errorMessage,
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
            // Extract only the main error message
            $errorMessage = $e->getMessage();

            // Check if it's a SQL Integrity Constraint Violation
            if (str_contains($errorMessage, 'Integrity constraint violation')) {
                preg_match("/Duplicate entry '(.+?)' for key '(.+?)'/", $errorMessage, $matches);
                if (!empty($matches)) {
                    $errorMessage = "Duplicate entry '{$matches[1]}' for key '{$matches[2]}'";
                }
            }
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to retrieve user profile. Please try again later.',
                'data' => null,
                'errors' => $errorMessage,
            ], 500);
        }
    }


    // User registration
    // public function Userregister(Request $request)
    // {
    //     try {
    //         // Validate the request data
    //         $validator = Validator::make($request->all(), [
    //             'name' => 'required|string|max:255',
    //             'email' => 'required|string|email|max:255|unique:users',
    //             'password' => 'required|string|min:8',
    //             'phone' => 'required|string|size:11',
    //         ]);

    //         // If validation fails, return error response
    //         if ($validator->fails()) {
    //             // Get the first error message
    //             $errorMessage = $validator->errors()->first();

    //             return response()->json([
    //                 'success' => false,
    //                 'status' => 422,
    //                 'message' => 'Validation failed.',
    //                 'data' => null,
    //                 'errors' => $errorMessage,
    //             ], 422);
    //         }

    //         // Create the user
    //         $user = User::create([
    //             'name' => $request->name,
    //             'email' => $request->email,
    //             'password' => Hash::make($request->password),
    //             'phone' => $request->phone,
    //             'type' => 'user',
    //             'status' => 1,
    //         ]);

    //         // Send the verification email
    //         // Mail::to($user->email)->send(new VerifyEmail($user));

    //         // Return success response
    //         return response()->json([
    //             'success' => true,
    //             'status' => 201,
    //             'message' => 'User registered successfully. Please check your email to verify your account.',
    //             'data' => [
    //                 'id' => $user->id,
    //                 'name' => $user->name,
    //                 'email' => $user->email,
    //                 'phone' => $user->phone,
    //                 'type' => $user->type,
    //                 'status' => $user->status,
    //             ],
    //             'errors' => null,
    //         ], 201);
    //     } catch (\Exception $e) {
    //         // Extract only the main error message
    //         $errorMessage = $e->getMessage();

    //         // // Check if it's a SQL Integrity Constraint Violation
    //         // if (str_contains($errorMessage, 'Integrity constraint violation')) {
    //         //     preg_match("/Duplicate entry '(.+?)' for key '(.+?)'/", $errorMessage, $matches);
    //         //     if (!empty($matches)) {
    //         //         $errorMessage = "Duplicate entry '{$matches[1]}' for key '{$matches[2]}'";
    //         //     }
    //         // }
    //         // Handle any exceptions
    //         return response()->json([
    //             'success' => false,
    //             'status' => 500,
    //             'message' => 'An error occurred while registering the user.',
    //             'data' => null,
    //             'errors' => $errorMessage,
    //         ], 500);
    //     }
    // }

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
                // Get the first error message
                $errorMessage = $validator->errors()->first();

                return response()->json([
                    'success' => false,
                    'status' => 422,
                    'message' => 'Validation failed.',
                    'data' => null,
                    'errors' => $errorMessage,
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
            // Extract only the main error message
            $errorMessage = $e->getMessage();

            // Handle any exceptions
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while registering the user.',
                'data' => null,
                'errors' => $errorMessage,
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
                    'errors' => $validator->errors()->first(), // Return the first error message as a string
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
                        'errors' => 'Access denied. Only users can log in.',
                    ], 403);
                }

                // Check if the user's email is verified
                // if (!$user->hasVerifiedEmail()) {
                //     return response()->json([
                //         'success' => false,
                //         'status' => 403,
                //         'message' => 'Email not verified. Please verify your email to log in.',
                //         'data' => null,
                //         'errors' => 'Email not verified.',
                //     ], 403);
                // }

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
                'errors' => 'Invalid email or password.',
            ], 401);
        } catch (\Exception $e) {
            // Extract only the main error message
            $errorMessage = $e->getMessage();

            // Check if it's a SQL Integrity Constraint Violation
            // if (str_contains($errorMessage, 'Integrity constraint violation')) {
            //     preg_match("/Duplicate entry '(.+?)' for key '(.+?)'/", $errorMessage, $matches);
            //     if (!empty($matches)) {
            //         $errorMessage = "Duplicate entry '{$matches[1]}' for key '{$matches[2]}'";
            //     }
            // }
            // Handle any exceptions
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while logging in.',
                'data' => null,
                'errors' => $errorMessage,
            ], 500);
        }
    }

    // Change password
    public function changePassword(Request $request)
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:8|confirmed',
            ]);

            // If validation fails, return error response
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'status' => 400,
                    'message' => 'Validation failed.',
                    'data' => null,
                    'errors' => $validator->errors(),
                ], 400);
            }

            // Get the authenticated user
            $user = Auth::user();

            // Check if the current password matches
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'status' => 400,
                    'message' => 'Current password is incorrect.',
                    'data' => null,
                    'errors' => 'Current password is incorrect.',
                ], 400);
            }

            // Update the password
            $user->password = Hash::make($request->new_password);
            $user->save();

            // Return the response in the specified format
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Password changed successfully.',
                'data' => null,
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            // Extract only the main error message
            $errorMessage = $e->getMessage();

            // Check if it's a SQL Integrity Constraint Violation
            if (str_contains($errorMessage, 'Integrity constraint violation')) {
                preg_match("/Duplicate entry '(.+?)' for key '(.+?)'/", $errorMessage, $matches);
                if (!empty($matches)) {
                    $errorMessage = "Duplicate entry '{$matches[1]}' for key '{$matches[2]}'";
                }
            }
            // Handle errors and return a consistent error response
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to change password.',
                'data' => null,
                'errors' => $errorMessage,
            ], 500);
        }
    }

    // update user information
    public function updateUserInfo(Request $request)
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255',
                'email' => 'sometimes|string|email|max:255|unique:users,email,' . Auth::id(),
                'phone' => 'sometimes|string|max:20',
                'address' => 'sometimes|string|max:255',
            ]);

            // If validation fails, return error response
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'status' => 400,
                    'message' => 'Validation failed.',
                    'data' => null,
                    'errors' => $validator->errors(),
                ], 400);
            }

            // Get the authenticated user
            $user = Auth::user();

            // Update the user info
            $user->update($request->only(['name', 'email', 'phone', 'address']));

            // Return the response in the specified format
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'User info updated successfully.',
                'data' => $user,
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            // Extract only the main error message
            $errorMessage = $e->getMessage();

            // Check if it's a SQL Integrity Constraint Violation
            if (str_contains($errorMessage, 'Integrity constraint violation')) {
                preg_match("/Duplicate entry '(.+?)' for key '(.+?)'/", $errorMessage, $matches);
                if (!empty($matches)) {
                    $errorMessage = "Duplicate entry '{$matches[1]}' for key '{$matches[2]}'";
                }
            }
            // Handle errors and return a consistent error response
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to update user info.',
                'data' => null,
                'errors' => $errorMessage,
            ], 500);
        }
    }

    // email varifications
    public function verifyEmail(EmailVerificationRequest $request)
    {
        $request->fulfill();

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Email verified successfully.',
            'errors' => null,
        ], 200);
    }
}
