<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Models\Item;
use App\Models\File;
use App\Models\Tag;
use App\Models\Cetagory_Product_list;

class ProductController extends Controller
{
    // Store the product
    public function store(Request $request)
    {
        try {
            // Validate the request data
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'short_description' => 'nullable|string',
                'quantity' => 'required|integer|min:0',
                'price' => 'required|numeric|min:0',
                'discount' => 'nullable|numeric|min:0|max:100',
                'categories' => 'required|array',
                'categories.*' => 'exists:cetagories,id',
                'tags' => 'nullable|array',
                'tags.*' => 'string|max:255',
                'images' => 'nullable|array',
                'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:4048',
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

            // Create the product
            $product = Item::create([
                'name' => $request->name,
                'description' => $request->description,
                'short_description' => $request->short_description,
                'status' => 1,
                'quantity' => $request->quantity,
                'price' => $request->price,
                'discount' => $request->discount,
            ]);

            // Save categories
            if ($request->has('categories')) {
                foreach ($request->categories as $categoryId) {
                    Cetagory_Product_list::create([
                        'category_id' => $categoryId,
                        'item_id' => $product->id,
                    ]);
                }
            }

            // Save tags
            if ($request->has('tags')) {
                foreach ($request->tags as $tag) {
                    Tag::create([
                        'item_id' => $product->id,
                        'tag' => $tag,
                    ]);
                }
            }

            // Save images
            if ($request->has('images')) {
                foreach ($request->file('images') as $image) {
                    // Store the image in the storage folder
                    $path = $image->store('public/product_image');

                    // Save the image path in the File table
                    File::create([
                        'relatable_id' => $product->id,
                        'type' => 'product',
                        'path' => $path,
                    ]);
                }
            }

            // Return success response
            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => 'Product created successfully.',
                'data' => $product,
                'errors' => null,
            ], 201);
        } catch (\Exception $e) {
            // Handle any exceptions
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while creating the product.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // shwo single products
    public function show($id)
    {
        try {
            // Fetch the product by ID with its relationships
            $product = Item::with([
                'categories.category',
                'tags',
                'images'
            ])->find($id);

            // Check if the product exists
            if (!$product) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Product not found.',
                    'data' => null,
                    'errors' => 'Invalid product ID.',
                ], 404);
            }

            // Format the response
            $formattedProduct = [
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description,
                'short_description' => $product->short_description,
                'status' => $product->status,
                'quantity' => $product->quantity,
                'price' => $product->price,
                'discount' => $product->discount,
                'categories' => $product->categories->map(function ($category) {
                    return [
                        'id' => $category->category->id,
                        'name' => $category->category->name,
                    ];
                }),
                'tags' => $product->tags->map(function ($tag) {
                    return [
                        'id' => $tag->id, // Include tag ID
                        'tag' => $tag->tag,
                    ];
                }),
                'images' => $product->images->map(function ($image) {
                    return [
                        'id' => $image->id,
                        'path' => asset('storage/' . str_replace('public/', '', $image->path)),
                    ];
                }),
            ];

            // Return success response
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Product retrieved successfully.',
                'data' => $formattedProduct,
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            // Handle any exceptions
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while retrieving the product.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // Show the all product
    public function index(Request $request)
    {
        try {
            // Get 'limit' and 'page' from request
            $perPage = $request->input('limit');
            $currentPage = $request->input('page');

            // Base query to fetch products with one image
            $query = Item::with(['images' => function ($query) {
                $query->select('relatable_id', 'path')->take(1); // Fetch only one image
            }]);

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
                $products = $query->paginate($perPage, ['*'], 'page', $currentPage);

                // Format the response with pagination data
                $formattedProducts = $products->map(function ($product) {
                    return [
                        'id' => $product->id,
                        'name' => $product->name,
                        'description' => $product->description,
                        'short_description' => $product->short_description,
                        'status' => $product->status,
                        'quantity' => $product->quantity,
                        'price' => $product->price,
                        'discount' => $product->discount,
                        'image_path' => $product->images->isNotEmpty()
                            ? asset('storage/' . str_replace('public/', '', $product->images->first()->path))
                            : null,
                    ];
                });

                // Return response with pagination data
                return response()->json([
                    'success' => true,
                    'status' => 200,
                    'message' => 'Products retrieved successfully.',
                    'data' => $formattedProducts,
                    'pagination' => [
                        'total_rows' => $products->total(),
                        'current_page' => $products->currentPage(),
                        'per_page' => $products->perPage(),
                        'total_pages' => $products->lastPage(),
                        'has_more_pages' => $products->hasMorePages(),
                    ]
                ], 200);
            }

            // If no pagination parameters, fetch all records without pagination
            $products = $query->get();

            // Format the response
            $formattedProducts = $products->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'description' => $product->description,
                    'short_description' => $product->short_description,
                    'status' => $product->status,
                    'quantity' => $product->quantity,
                    'price' => $product->price,
                    'discount' => $product->discount,
                    'image_path' => $product->images->isNotEmpty()
                        ? asset('storage/' . str_replace('public/', '', $product->images->first()->path))
                        : null,
                ];
            });

            // Return response without pagination links
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Products retrieved successfully.',
                'data' => $formattedProducts
            ], 200);
        } catch (\Exception $e) {
            // Handle any exceptions
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while retrieving products.',
                'data' => null,
                'errors' => $e->getMessage()
            ], 500);
        }
    }

    // Toggles the product status
    public function toggleStatus($product_id)
    {
        try {
            // Find the product
            $product = Item::find($product_id);

            // Check if the product exists
            if (!$product) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Product not found.',
                    'data' => null,
                    'errors' => 'Invalid product ID.',
                ], 404);
            }

            // Toggle the status
            $product->status = $product->status == 1 ? 0 : 1;
            $product->save();

            // Return success response with the updated product
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Product status toggled successfully.',
                'data' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'status' => $product->status,
                ],
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            // Handle any exceptions
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while toggling the product status.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // update product
    public function updateProduct(Request $request, $product_id)
    {
        try {
            // Validate the request data
            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255',
                'description' => 'sometimes|string',
                'short_description' => 'sometimes|string',
                'quantity' => 'sometimes|integer|min:0',
                'price' => 'sometimes|numeric|min:0',
                'discount' => 'sometimes|numeric',
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

            // Find the product
            $product = Item::find($product_id);

            // Check if the product exists
            if (!$product) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Product not found.',
                    'data' => null,
                    'errors' => 'Invalid product ID.',
                ], 404);
            }

            // Update the product with validated data
            $product->update($request->only([
                'name',
                'description',
                'short_description',
                'quantity',
                'price',
                'discount',
            ]));

            // Return success response with the updated product
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Product updated successfully.',
                'data' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'description' => $product->description,
                    'short_description' => $product->short_description,
                    'status' => $product->status,
                    'quantity' => $product->quantity,
                    'price' => $product->price,
                    'discount' => $product->discount,
                ],
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            // Handle any exceptions
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while updating the product.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // Delete a product
    public function deleteProduct($product_id)
    {
        try {
            // Find the product
            $product = Item::find($product_id);

            // Check if the product exists
            if (!$product) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Product not found.',
                    'data' => null,
                    'errors' => 'Invalid product ID.',
                ], 404);
            }

            // Fetch related images
            $images = File::where('relatable_id', $product_id)
                ->where('type', 'product')
                ->get();

            // Delete image files from storage
            foreach ($images as $image) {
                if (Storage::exists($image->path)) {
                    Storage::delete($image->path);
                }
            }

            // Delete related records from Cetagory_Product_list, Tag, and File tables
            Cetagory_Product_list::where('item_id', $product_id)->delete();
            Tag::where('item_id', $product_id)->delete();
            File::where('relatable_id', $product_id)->where('type', 'product')->delete();

            // Delete the product
            $product->delete();

            // Return success response
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Product and related data deleted successfully.',
                'data' => null,
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            // Handle any exceptions
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while deleting the product and related data.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }
}
