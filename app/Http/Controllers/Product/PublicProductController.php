<?php

namespace App\Http\Controllers\product;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Item;
use App\Models\Cetagory_Product_list;
use App\Models\Cetagory;
use App\Models\Order_list;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PublicProductController extends Controller
{
    //
    // Show the all product
    public function index(Request $request)
    {
        try {
            // Get 'limit', 'page', 'search', 'min_price', and 'max_price' from request
            $perPage = $request->input('limit');
            $currentPage = $request->input('page');
            $search = $request->input('search');
            $minPrice = $request->input('min_price');
            $maxPrice = $request->input('max_price');

            // Base query to fetch products with all related images of type 'product'
            $query = Item::with(['images' => function ($query) {
                $query->where('type', 'product')->orderBy('id', 'asc');
            }])->orderBy('created_at', 'desc');

            // Exclude items with status 0
            $query->where('status', '!=', 0);

            // Apply search filter if 'search' parameter is provided
            if ($search) {
                $query->where('name', 'like', '%' . $search . '%');
            }

            // Apply price range filter if 'min_price' and 'max_price' are provided
            if ($minPrice) {
                $query->where('price', '>=', $minPrice);
            }
            if ($maxPrice) {
                $query->where('price', '<=', $maxPrice);
            }

            // If pagination parameters are provided, apply pagination
            if ($perPage && $currentPage) {
                // Validate pagination parameters
                if (!is_numeric($perPage) || !is_numeric($currentPage) || $perPage <= 0 || $currentPage <= 0) {
                    return response()->json([
                        'success' => false,
                        'status' => 400,
                        'message' => 'Invalid pagination parameters.',
                        'data' => null,
                        'errors' => 'Invalid pagination parameters.',
                    ], 400);
                }

                // Apply pagination
                $products = $query->paginate($perPage, ['*'], 'page', $currentPage);

                // Format the response with pagination data
                $formattedProducts = $products->map(function ($product) {
                    // Collect all image paths for this product
                    $imagePaths = $product->images->map(function ($image) {
                        return url('public/' . $image->path);
                    })->toArray();


                    return [
                        'id' => $product->id,
                        'slug' => $product->slug,
                        'name' => $product->name,
                        'short_description' => $product->short_description,
                        'status' => $product->status,
                        'quantity' => $product->quantity,
                        'price' => $product->price,
                        'discount' => $product->discount,
                        'image_paths' => $imagePaths,
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
                // Collect all image paths for this product
                $imagePaths = $product->images->map(function ($image) {
                    return url('public/' . $image->path);
                })->toArray();


                return [
                    'id' => $product->id,
                    'slug' => $product->slug,
                    'name' => $product->name,
                    'description' => $product->description,
                    'short_description' => $product->short_description,
                    'status' => $product->status,
                    'quantity' => $product->quantity,
                    'price' => $product->price,
                    'discount' => $product->discount,
                    'image_paths' => $imagePaths,
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

    // shwo single products
    public function show($id)
    {
        try {
            // Fetch the product by ID with its relationships
            $product = Item::with([
                'categories.category',
                'tags',
                'images',
                'bundleItems.item.images'
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
                'slug' => $product->slug,
                'name' => $product->name,
                'description' => $product->description,
                'short_description' => $product->short_description,
                'is_bundle' => $product->is_bundle,
                'status' => $product->status,
                'quantity' => $product->quantity,
                'price' => $product->price,
                'discount' => $product->discount,
                'meta_title' => $product->meta_title,
                'meta_keywords' => $product->meta_keywords,
                'meta_description' => $product->meta_description,
                'categories' => $product->categories->map(function ($category) {
                    return [
                        'id' => $category->category->id,
                        'name' => $category->category->name,
                    ];
                }),
                'tags' => $product->tags->map(function ($tag) {
                    return [
                        'id' => $tag->id,
                        'tag' => $tag->tag,
                        'slug' => $tag->slug,
                    ];
                }),
                'images' => $product->images->map(function ($image) {
                    return [
                        'id' => $image->id,
                        'path' => url('public/' . $image->path),
                    ];
                }),
            ];

            // Add bundle items if the product is a bundle
            if ($product->is_bundle == 1) {
                $formattedProduct['bundle_items'] = $product->bundleItems->map(function ($bundleItem) {
                    $item = $bundleItem->item; // Get the related item
                    return [
                        'bundle_id' => $bundleItem->id,
                        'item_id' => $item->id,
                        'name' => $item->name,
                        'price' => $item->price,
                        'discount' => $item->discount,
                        'bundle_quantity' => $bundleItem->bundle_quantity,
                        'image' => $item->images->isNotEmpty()
                            ? url('public/' . $item->images->first()->path)
                            : null,
                    ];
                });
            }

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

    // Showing single products by slug
    public function showSingleProductBySlug($slug)
    {
        try {
            // Fetch product by slug with relations
            $product = Item::with([
                'categories.category',
                'tags',
                'images',
                'bundleItems.item.images'
            ])->where('slug', $slug)->first();

            // Check if product exists
            if (!$product) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Product not found.',
                    'data' => null,
                    'errors' => 'Invalid product slug.',
                ], 404);
            }

            // Format the response (same as before)
            $formattedProduct = [
                'id' => $product->id,
                'slug' => $product->slug,
                'name' => $product->name,
                'description' => $product->description,
                'short_description' => $product->short_description,
                'is_bundle' => $product->is_bundle,
                'status' => $product->status,
                'quantity' => $product->quantity,
                'price' => $product->price,
                'discount' => $product->discount,
                'meta_title' => $product->meta_title,
                'meta_keywords' => $product->meta_keywords,
                'meta_description' => $product->meta_description,
                'categories' => $product->categories->map(function ($category) {
                    return [
                        'id' => $category->category->id,
                        'name' => $category->category->name,
                    ];
                }),
                'tags' => $product->tags->map(function ($tag) {
                    return [
                        'id' => $tag->id,
                        'tag' => $tag->tag,
                        'slug' => $tag->slug,
                    ];
                }),
                'images' => $product->images->map(function ($image) {
                    return [
                        'id' => $image->id,
                        'path' => url('public/' . $image->path),
                    ];
                }),
            ];

            // Add bundle items if the product is a bundle
            if ($product->is_bundle == 1) {
                $formattedProduct['bundle_items'] = $product->bundleItems->map(function ($bundleItem) {
                    $item = $bundleItem->item;
                    return [
                        'bundle_id' => $bundleItem->id,
                        'item_id' => $item->id,
                        'name' => $item->name,
                        'price' => $item->price,
                        'discount' => $item->discount,
                        'bundle_quantity' => $bundleItem->bundle_quantity,
                        'image' => $item->images->isNotEmpty()
                            ? url('public/' . $item->images->first()->path)
                            : null,
                    ];
                });
            }

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Product retrieved successfully.',
                'data' => $formattedProduct,
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while retrieving the product.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // Best selling product
    public function bestSellingProducts(Request $request)
    {
        try {
            $limit = $request->input('limit', 10); // default 10 best-selling
            $query = Order_list::select('product_id', DB::raw('SUM(quantity) as total_sold'))
                ->groupBy('product_id')
                ->orderByDesc('total_sold')
                ->with(['item.images']) // load product + images
                ->take($limit)
                ->get();

            $products = $query->map(function ($orderList) {
                $product = $orderList->item;

                if (!$product) {
                    return null; // skip if no product found
                }

                $imagePaths = $product->images->map(function ($image) {
                    return url('public/' . $image->path);
                })->toArray();

                return [
                    'id' => $product->id,
                    'slug' => $product->slug,
                    'name' => $product->name,
                    'short_description' => $product->short_description,
                    'status' => $product->status,
                    'quantity' => $product->quantity,
                    'price' => $product->price,
                    'discount' => $product->discount,
                    'total_sold' => $orderList->total_sold,
                    'image_paths' => $imagePaths,
                ];
            })->filter(); // remove nulls

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Best-selling products retrieved successfully.',
                'data' => $products,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while fetching best-selling products.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // New product
    public function newProducts(Request $request)
    {
        try {
            $limit = $request->input('limit', 10); // default 10 latest products

            $products = Item::with(['images' => function ($query) {
                $query->where('type', 'product')->orderBy('id', 'asc');
            }])
                ->where('status', '!=', 0) // only active products
                ->orderBy('created_at', 'desc')
                ->take($limit)
                ->get();

            $formattedProducts = $products->map(function ($product) {
                $imagePaths = $product->images->map(function ($image) {
                    return url('public/' . $image->path);
                })->toArray();

                return [
                    'id' => $product->id,
                    'slug' => $product->slug,
                    'name' => $product->name,
                    'short_description' => $product->short_description,
                    'status' => $product->status,
                    'quantity' => $product->quantity,
                    'price' => $product->price,
                    'discount' => $product->discount,
                    'image_paths' => $imagePaths,
                ];
            });

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'New products retrieved successfully.',
                'data' => $formattedProducts,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while fetching new products.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // shwo product by category id
    public function shwoProductCategory($category_id, Request $request)
    {
        try {
            // Get 'limit' and 'page' from request
            $perPage = $request->input('limit');
            $currentPage = $request->input('page');

            // Fetch the category
            $category = Cetagory::find($category_id);

            if (!$category) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Category not found.',
                    'data' => null,
                    'errors' => 'Category not found.',
                ], 404);
            }

            // Fetch product IDs associated with the category
            $productIds = Cetagory_Product_list::where('category_id', $category_id)
                ->pluck('item_id');

            // Fetch products with one image and order by 'created_at' in descending order
            $query = Item::with(['images' => function ($query) {
                $query->select('relatable_id', 'path')->take(1);
            }])->whereIn('id', $productIds)
                ->where('status', '!=', 0)
                ->orderBy('created_at', 'desc');

            // If pagination parameters are provided, apply pagination
            if ($perPage && $currentPage) {
                // Validate pagination parameters
                if (!is_numeric($perPage) || !is_numeric($currentPage) || $perPage <= 0 || $currentPage <= 0) {
                    return response()->json([
                        'success' => false,
                        'status' => 400,
                        'message' => 'Invalid pagination parameters.',
                        'data' => null,
                        'errors' => 'Invalid pagination parameters.',
                    ], 400);
                }

                // Apply pagination
                $products = $query->paginate($perPage, ['*'], 'page', $currentPage);

                // Format the response with pagination data
                $formattedProducts = $products->map(function ($product) {
                    return [
                        'id' => $product->id,
                        'name' => $product->name,
                        'slug' => $product->slug,
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
                    'message' => 'Products retrieved successfully for category: ' . $category->name,
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
                    'slug' => $product->slug,
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
                'message' => 'Products retrieved successfully for category: ' . $category->name,
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
}
