<?php

namespace App\Http\Controllers\Coupon;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Coupon;
use App\Models\Activity;
use App\Models\Coupon_Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\Item;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CouponController extends Controller
{
    /**
     * Store a newly created coupon
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'code' => 'required|string|max:50|unique:coupons,code',
                'amount' => 'required|numeric|min:0',
                'type' => 'required|in:flat,percent',
                'is_global' => 'required|boolean',
                'max_usage' => 'nullable|integer|min:1',
                'max_usage_per_user' => 'nullable|integer|min:1',
                'start_date' => 'nullable|date|after_or_equal:today',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'item_ids' => 'array',
                'item_ids.*' => 'exists:items,id',
            ]);

            // Additional business logic validations
            if ($validated['type'] === 'percent' && $validated['amount'] > 100) {
                return response()->json([
                    'success' => false,
                    'message' => 'Percentage discount cannot exceed 100%',
                    'errors' => ['amount' => ['Percentage discount must be 100% or less']]
                ], 422);
            }

            // If not global, item_ids should be provided
            if (!$validated['is_global'] && empty($validated['item_ids'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item selection is required for non-global coupons',
                    'errors' => ['item_ids' => ['Please select at least one item for this coupon']]
                ], 422);
            }

            // Use database transaction for data consistency
            DB::beginTransaction();

            // Create coupon
            $coupon = Coupon::create($validated);

            // Attach items if not global
            if (!$validated['is_global'] && !empty($validated['item_ids'])) {
                // Verify all items exist before attaching
                $existingItems = Item::whereIn('id', $validated['item_ids'])->pluck('id')->toArray();
                $missingItems = array_diff($validated['item_ids'], $existingItems);

                if (!empty($missingItems)) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Some selected items do not exist',
                        'errors' => ['item_ids' => ['Items with IDs ' . implode(', ', $missingItems) . ' do not exist']]
                    ], 422);
                }

                // Use only the pivot table relationship (recommended approach)
                $coupon->items()->attach($validated['item_ids']);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Coupon created successfully',
                'data' => $coupon->load('items')
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating coupon: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create coupon. Please try again.',
                'errors' => ['general' => ['An unexpected error occurred']]
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
