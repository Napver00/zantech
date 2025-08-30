<?php

namespace App\Http\Controllers\Coupon;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Coupon;
use App\Models\Activity;
use App\Models\Coupon_Product;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

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
            'min_pur'=>'nullable|integer',
            'max_usage_per_user' => 'nullable|integer|min:1',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'item_ids' => 'sometimes|array', // Use 'sometimes' so it's not required
            'item_ids.*' => 'exists:items,id',
        ]);

        // Use a database transaction to ensure data integrity
        try {
            DB::beginTransaction();

            // Create the coupon
            $coupon = Coupon::create($validated);

            // Attach items if the coupon is not global and item_ids are provided
            if (!$validated['is_global'] && !empty($validated['item_ids'])) {
                // This is the only part you need. It saves records to the 'coupon_item' pivot table.
                $coupon->items()->attach($validated['item_ids']);
            }

            // If everything is successful, commit the transaction
            DB::commit();

            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => 'Coupon created successfully',
                'data' => null
            ], 201);
        } catch (\Exception $e) {
            // If an error occurs, roll back the transaction
            DB::rollBack();

            // Return a proper error response for debugging
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to create coupon.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Method to update a coupon
    public function update(Request $request, $id)
    {
        try {
            $coupon = Coupon::findOrFail($id);

            // Validate request
            $validated = $request->validate([
                'code' => [
                    'required',
                    'string',
                    Rule::unique('coupons', 'code')->ignore($coupon->id),
                ],
                'amount' => [
                    'required',
                    'numeric',
                    'min:0',
                    Rule::when($request->input('type') === 'percent', ['max:100']),
                ],
                'type' => 'required|in:flat,percent',
                'is_global' => 'required|boolean',
                'max_usage' => 'nullable|integer|min:1',
                'max_usage_per_user' => 'nullable|integer|min:1',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
            ]);

            // Update coupon
            $coupon->update($validated);

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Coupon updated successfully.',
                'data' => $coupon
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Coupon not found.',
                'data' => null,
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to update coupon.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Method to delete a coupon
    public function destroy($id)
    {
        try {
            // Start transaction to ensure data integrity
            DB::beginTransaction();

            $coupon = Coupon::findOrFail($id);

            // Delete related entries from pivot table manually
            Coupon_Product::where('coupon_id', $coupon->id)->delete();

            // Delete the coupon
            $coupon->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Coupon and related products deleted successfully.',
                'data' => null,
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Coupon not found.',
                'data' => null,
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to delete coupon.',
                'error' => $e->getMessage(),
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

            // Load only id & name for items
            $couponsQuery = Coupon::with(['items:id,name'])->orderBy('created_at', 'desc');

            if ($search) {
                $couponsQuery->where('code', 'like', '%' . $search . '%');
            }

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

    // toggle status of coupons
    public function toggleStatus($id)
    {
        try {
            $coupon = Coupon::findOrFail($id);

            // Toggle the status (if null, default to 0 first)
            $coupon->status = $coupon->status == 1 ? 0 : 1;
            $coupon->save();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Coupon status updated successfully.',
                'data' => [
                    'id' => $coupon->id,
                    'status' => $coupon->status
                ]
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Coupon not found.',
                'data' => null,
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to update coupon status.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function addItems(Request $request, $id)
    {
        $request->validate([
            'item_ids' => 'required|array',
            'item_ids.*' => 'exists:items,id',
        ]);

        try {
            $coupon = Coupon::findOrFail($id);

            DB::beginTransaction();

            foreach ($request->item_ids as $itemId) {
                // Avoid duplicates
                Coupon_Product::firstOrCreate([
                    'coupon_id' => $coupon->id,
                    'item_id' => $itemId,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Items added to coupon successfully.',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to add items.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function removeItem(Request $request, $id)
    {
        $request->validate([
            'item_id' => 'required|exists:items,id',
        ]);

        try {
            $coupon = Coupon::findOrFail($id);

            DB::beginTransaction();

            Coupon_Product::where('coupon_id', $coupon->id)
                ->where('item_id', $request->item_id)
                ->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Item removed from coupon successfully.',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to remove item.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
