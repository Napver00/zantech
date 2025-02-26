<?php

namespace App\Http\Controllers\Rating;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Reating;

class RatingController extends Controller
{
    //store Rating
    public function store(Request $request)
    {
        try {
            // Validate the request data
            $validator = Validator::make($request->all(), [
                'star' => 'required|integer|between:1,5',
                'rating' => 'nullable|string|max:255',
                'user_id' => 'required|exists:users,id',
                'product_id' => 'required|exists:items,id',
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

            // Create the rating
            $rating = Reating::create([
                'User_id' => $request->user_id,
                'status' => 0,
                'star' => $request->star,
                'rating' => $request->reating,
                'product_id' => $request->product_id,
            ]);

            // Return success response
            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => 'Rating created successfully.',
                'data' => $rating,
                'errors' => null,
            ], 201);
        } catch (\Exception $e) {
            // Handle any exceptions
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while creating the rating.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // shwo all rating
    public function index(Request $request)
    {
        try {
            // Get 'limit' and 'page' from request
            $perPage = $request->input('limit');
            $currentPage = $request->input('page');


            $query = Reating::with(['product.images' => function ($query) {
                $query->take(1);
            }])->orderBy('id', 'desc');

            // If pagination parameters are provided, apply pagination
            if ($perPage && $currentPage) {
                // Validate pagination parameters
                if (!is_numeric($perPage) || !is_numeric($currentPage) || $perPage <= 0 || $currentPage <= 0) {
                    return response()->json([
                        'success' => false,
                        'status' => 400,
                        'message' => 'Invalid pagination parameters.',
                        'data' => null
                    ], 400);
                }

                // Apply pagination
                $ratings = $query->paginate($perPage, ['*'], 'page', $currentPage);

                // Format the paginated response
                $formattedRatings = $ratings->map(function ($rating) {
                    return [
                        'id' => $rating->id,
                        'star' => $rating->star,
                        'reating' => $rating->reating,
                        'status' => $rating->status,
                        'product' => [
                            'id' => $rating->product->id,
                            'name' => $rating->product->name,
                            'image' => $rating->product->images->isNotEmpty()
                                ? asset('storage/' . str_replace('public/', '', $rating->product->images->first()->path))
                                : null,
                        ],
                    ];
                });

                // Return response with pagination data
                return response()->json([
                    'success' => true,
                    'status' => 200,
                    'message' => 'Ratings retrieved successfully.',
                    'data' => $formattedRatings,
                    'pagination' => [
                        'total_rows' => $ratings->total(),
                        'current_page' => $ratings->currentPage(),
                        'per_page' => $ratings->perPage(),
                        'total_pages' => $ratings->lastPage(),
                        'has_more_pages' => $ratings->hasMorePages(),
                    ]
                ], 200);
            }

            // If no pagination parameters, fetch all records without pagination
            $ratings = $query->get();

            // Format the response
            $formattedRatings = $ratings->map(function ($rating) {
                return [
                    'id' => $rating->id,
                    'star' => $rating->star,
                    'reating' => $rating->reating,
                    'status' => $rating->status,
                    'product' => [
                        'id' => $rating->product->id,
                        'name' => $rating->product->name,
                        'image' => $rating->product->images->isNotEmpty()
                            ? asset('storage/' . str_replace('public/', '', $rating->product->images->first()->path))
                            : null,
                    ],
                ];
            });

            // Return response without pagination links
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Ratings retrieved successfully.',
                'data' => $formattedRatings
            ], 200);
        } catch (\Exception $e) {
            // Handle any exceptions
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while retrieving ratings.',
                'data' => null,
                'errors' => $e->getMessage()
            ], 500);
        }
    }

    // Toggles the rating
    public function toggleStatus($id)
    {
        try {
            // Find the rating by ID
            $rating = Reating::find($id);

            // Check if the rating exists
            if (!$rating) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Rating not found.',
                    'data' => null,
                    'errors' => 'Invalid rating ID.',
                ], 404);
            }

            // Toggle the status
            $rating->status = $rating->status == 1 ? 0 : 1;
            $rating->save();

            // Return success response with the updated rating
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Rating status toggled successfully.',
                'data' => [
                    'id' => $rating->id,
                    'status' => $rating->status,
                ],
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            // Handle any exceptions
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while toggling the rating status.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }
}
