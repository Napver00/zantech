<?php

namespace App\Http\Controllers\Coupon;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Coupon;
use App\Models\Activity;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CouponController extends Controller
{
    // Method to store a coupon
    public function store(Request $request)
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'code' => 'required|string|unique:coupons,code|max:255',
                'amount' => 'required|numeric|min:0',
            ]);

            // If validation fails, return error response
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'status' => 400,
                    'message' => 'Validation failed.',
                    'data' => null,
                    'errors' => "The code has already been taken."
                ], 400);
            }

            // Create the coupon
            $coupon = Coupon::create([
                'code' => $request->code,
                'amount' => $request->amount,
            ]);

            // Save activity
            Activity::create([
                'relatable_id' => $coupon->id,
                'type' => 'coupon',
                'user_id' => Auth::id(),
                'description' => 'Coupon created: ' . $coupon->code . ' with amount ' . $coupon->amount,
            ]);

            // Return the response in the specified format
            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => 'Coupon created successfully.',
                'data' => $coupon,
                'errors' => null,
            ], 201);
        } catch (\Exception $e) {
            // Handle errors and return a consistent error response
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to create coupon.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // Method to update a coupon
    public function update(Request $request, $id)
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'code' => 'sometimes|string|unique:coupons,code,' . $id . '|max:255',
                'amount' => 'sometimes|numeric|min:0',
            ]);

            // If validation fails, return error response
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'status' => 400,
                    'message' => 'Validation failed.',
                    'data' => null,
                    'errors' => "The code has already been taken."
                ], 400);
            }

            // Find the coupon by ID
            $coupon = Coupon::find($id);

            // If the coupon doesn't exist, return a 404 response
            if (!$coupon) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Coupon not found.',
                    'data' => null,
                    'errors' => 'Coupon not found.',
                ], 404);
            }

            // Track changes
            $changes = [];
            foreach ($request->all() as $key => $value) {
                if ($coupon->$key != $value) {
                    $changes[] = "$key changed from {$coupon->$key} to $value";
                }
            }

            // Update the coupon
            $coupon->update($request->all());

            // Save activity if changes were made
            if (!empty($changes)) {
                Activity::create([
                    'relatable_id' => $coupon->id,
                    'type' => 'coupon',
                    'user_id' => Auth::id(),
                    'description' => 'Coupon updated: ' . implode(', ', $changes),
                ]);
            }

            // Return the response in the specified format
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Coupon updated successfully.',
                'data' => $coupon,
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            // Handle errors and return a consistent error response
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to update coupon.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // Method to delete a coupon
    public function destroy($id)
    {
        try {
            // Find the coupon by ID
            $coupon = Coupon::find($id);

            // If the coupon doesn't exist, return a 404 response
            if (!$coupon) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Coupon not found.',
                    'data' => null,
                    'errors' => 'Coupon not found.',
                ], 404);
            }

            // Save activity before deleting
            Activity::create([
                'relatable_id' => $coupon->id,
                'type' => 'coupon',
                'user_id' => Auth::id(),
                'description' => 'Coupon deleted: ' . $coupon->code,
            ]);

            // Delete the coupon
            $coupon->delete();

            // Return the response in the specified format
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Coupon deleted successfully.',
                'data' => null,
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            // Handle errors and return a consistent error response
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to delete coupon.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // Method to fetch all coupons
    public function index()
    {
        try {
            // Fetch all coupons
            $coupons = Coupon::all();

            // Return the response in the specified format
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Coupons retrieved successfully.',
                'data' => $coupons,
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            // Handle errors and return a consistent error response
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to retrieve coupons.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }
}
