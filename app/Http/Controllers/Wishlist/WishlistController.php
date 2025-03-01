<?php

namespace App\Http\Controllers\Wishlist;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Wishlist;
use App\Models\Item;
use App\Models\File;
use Illuminate\Support\Facades\Validator;

class WishlistController extends Controller
{
    //store the wishlist
    public function store(Request $request)
    {
        try {
            // Validate the input
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|exists:users,id',
                'product_id' => 'required|exists:items,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'status' => 400,
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors(),
                ], 400);
            }

            // Check if the item is already in the wishlist
            $existingWishlistItem = Wishlist::where('user_id', $request->user_id)
                ->where('product_id', $request->product_id)
                ->exists();

            if ($existingWishlistItem) {
                return response()->json([
                    'success' => false,
                    'status' => 409,
                    'message' => 'This product is already in the wishlist.',
                    'data' => null,
                    'errors' => 'This product is already in the wishlist.',
                ], 409);
            }

            // Create the wishlist item
            $wishlistItem = Wishlist::create([
                'user_id' => $request->user_id,
                'product_id' => $request->product_id,
            ]);

            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => 'Wishlist item added successfully.',
                'data' => $wishlistItem,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }



    // Show wishlist items for a specific user
    public function show(Request $request, $user_id)
    {
        // Get 'limit' and 'page' from request, default to null
        $perPage = $request->input('limit');
        $currentPage = $request->input('page');

        // Fetch wishlist items for the user with product details
        $wishlistQuery = Wishlist::where('user_id', $user_id)->with('product.images');

        // Check if pagination parameters are provided
        if ($perPage && $currentPage) {
            $wishlistItems = $wishlistQuery->paginate($perPage);
        } else {
            $wishlistItems = $wishlistQuery->get(); // Fetch all data if no pagination
        }

        // Check if wishlist is empty
        if ($wishlistItems->isEmpty()) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'No wishlist items found for this user.',
                'data' => [],
                'errors' => 'No wishlist items found for this user.',
            ], 404);
        }

        // Format response
        $wishlistData = $wishlistItems->map(function ($wishlist) {
            return [
                'product_id' => $wishlist->product->id,
                'name' => $wishlist->product->name,
                'price' => $wishlist->product->price,
                'discount' => $wishlist->product->discount,
                'image' => asset('storage/' . str_replace('public/', '', $wishlist->product->images->first()?->path)),
            ];
        });

        // Pagination details (only if paginated)
        $pagination = $perPage ? [
            'total_rows' => $wishlistItems->total(),
            'current_page' => $wishlistItems->currentPage(),
            'per_page' => $wishlistItems->perPage(),
            'total_pages' => $wishlistItems->lastPage(),
            'has_more_pages' => $wishlistItems->hasMorePages(),
        ] : null;

        // Return JSON response
        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Wishlist items retrieved successfully.',
            'data' => $wishlistData,
            'pagination' => $pagination,
            'error' => null,
        ], 200);
    }

    // remove items from wishlist
    public function destroy($wishlist_id)
    {
        // Find the wishlist item
        $wishlistItem = Wishlist::find($wishlist_id);

        // Check if the wishlist item exists
        if (!$wishlistItem) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Wishlist item not found.',
                'errors' => 'Wishlist item not found.',
            ], 404);
        }

        // Delete the wishlist item
        $wishlistItem->delete();

        // Return JSON response
        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Wishlist item deleted successfully.',
            'errors' => null,
        ], 200);
    }
}
