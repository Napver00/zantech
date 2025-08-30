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
        $validated = $request->validate([
            'code' => 'required|string|unique:coupons,code',
            'amount' => 'required|numeric|min:0',
            'type' => 'required|in:flat,percent',
            'is_global' => 'required|boolean',
            'max_usage' => 'nullable|integer|min:1',
            'max_usage_per_user' => 'nullable|integer|min:1',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'item_ids' => 'array',      // optional list of items
            'item_ids.*' => 'exists:items,id',
        ]);

        // Create coupon
        $coupon = Coupon::create($validated);

        // Attach items if not global
        if (!$validated['is_global'] && !empty($validated['item_ids'])) {
            $coupon->items()->attach($validated['item_ids']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Coupon created successfully',
            'data' => $coupon->load('items')
        ], 201);
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
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('limit');
            $currentPage = $request->input('page');
            $search = $request->input('search');

            // Base query with related items
            $couponsQuery = Coupon::with('items')->orderBy('created_at', 'desc');

            // Apply search filter (by code only, since no 'name' column exists)
            if ($search) {
                $couponsQuery->where('code', 'like', '%' . $search . '%');
            }

            // If pagination parameters are provided
            if ($perPage && $currentPage) {
                $coupons = $couponsQuery->paginate($perPage, ['*'], 'page', $currentPage);

                return response()->json([
                    'success' => true,
                    'status' => 200,
                    'message' => 'Coupons retrieved successfully.',
                    'data' => $coupons->items(),
                    'pagination' => [
                        'total_rows' => $coupons->total(),
                        'current_page' => $coupons->currentPage(),
                        'per_page' => $coupons->perPage(),
                        'total_pages' => $coupons->lastPage(),
                        'has_more_pages' => $coupons->hasMorePages(),
                    ],
                    'errors' => null,
                ], 200);
            }

            // If no pagination, return all coupons with related items
            $coupons = $couponsQuery->get();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Coupons retrieved successfully.',
                'data' => $coupons,
                'pagination' => null,
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
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
