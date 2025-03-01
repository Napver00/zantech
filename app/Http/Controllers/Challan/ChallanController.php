<?php

namespace App\Http\Controllers\Challan;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Item;
use App\Models\File;
use App\Models\Challan;
use App\Models\Supplier_item_list;
use App\Models\User;
use App\Models\Expense;

class ChallanController extends Controller
{
    public function store(Request $request)
    {
        try {
            // Validate the request data
            $validator = Validator::make($request->all(), [
                'Date' => 'required|date',
                'item_id' => 'required|array',
                'item_id.*' => 'exists:items,id',
                'user_id' => 'required|exists:users,id',
                'delivery_price' => 'required|numeric|min:0',
                'supplier_id' => 'required|exists:suppliers,id',
                'buying' => 'required|array',
                'buying.*' => 'numeric|min:0',
                'quantity' => 'required|array',
                'quantity.*' => 'integer|min:1',
                'invoice' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:4048',
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

            // Calculate the total buying price
            $totalBuyingPrice = 0;
            foreach ($request->item_id as $index => $itemId) {
                $totalBuyingPrice += $request->buying[$index] * $request->quantity[$index];
            }

            // Calculate the total (buying price + delivery price)
            $total = $totalBuyingPrice + $request->delivery_price;

            // Create the challan
            $challan = Challan::create([
                'Date' => $request->Date,
                'user_id' => $request->user_id,
                'total' => $total,
                'delivery_price' => $request->delivery_price,
                'supplier_id' => $request->supplier_id,
            ]);

            // save in expense
            Expense::create([
                'date' => $request->Date,
                'user_id' => $request->user_id,
                'title' => 'Buying equipments',
                'amount' => $total,
                'description' => "Buying Equipment on {$request->date}. Total price is {$total}.",
            ]);

            // Save supplier-item data and update item quantities
            foreach ($request->item_id as $index => $itemId) {
                // Save in Supplier_item_list table
                Supplier_item_list::create([
                    'supplier_id' => $request->supplier_id,
                    'item_id' => $itemId,
                    'price' => $request->buying[$index],
                    'quantity' => $request->quantity[$index],
                    'challan_id' => $challan->id,
                ]);

                // Update the item quantity in the items table
                $item = Item::find($itemId);
                $item->quantity += $request->quantity[$index];
                $item->save();
            }

            // Save the invoice image
            if ($request->hasFile('invoice')) {
                $invoice = $request->file('invoice');
                $path = $invoice->store('public/challan');

                // Save the image path in the File table
                File::create([
                    'relatable_id' => $challan->id,
                    'type' => 'challan',
                    'path' => $path,
                ]);
            }

            // Return success response
            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => 'Challan created successfully.',
                'data' => $challan,
                'errors' => null,
            ], 201);
        } catch (\Exception $e) {
            // Handle any exceptions
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while creating the challan.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // Show single challenge
    public function show($id)
    {
        try {
            // Find the challan by ID with relationships
            $challan = Challan::with(['supplier', 'user', 'supplierItems.item', 'invoice'])->find($id);

            // Check if the challan exists
            if (!$challan) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Challan not found.',
                    'data' => null,
                    'errors' => 'Invalid challan ID.',
                ], 404);
            }

            // Format the response
            $response = [
                'id' => $challan->id,
                'Date' => $challan->Date,
                'total' => $challan->total,
                'delivery_price' => $challan->delivery_price,
                'supplier' => [
                    'name' => $challan->supplier->name,
                    'phone' => $challan->supplier->phone,
                    'address' => $challan->supplier->address,
                ],
                'user' => [
                    'name' => $challan->user->name,
                ],
                'items' => $challan->supplierItems->map(function ($item) {
                    return [
                        'item_id' => $item->item_id,
                        'item_name' => $item->item->name,
                        'price' => $item->price,
                        'quantity' => $item->quantity,
                    ];
                }),
                'invoice' => $challan->invoice ? [
                    'id' => $challan->invoice->id,
                    'path' => asset('storage/' . str_replace('public/', '', $challan->invoice->path)),
                ] : null,
            ];

            // Return success response
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Challan retrieved successfully.',
                'data' => $response,
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            // Handle any exceptions
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while retrieving the challan.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // show all challenges
    public function index(Request $request)
    {
        try {
            // Get 'limit', 'page', and 'search' from request
            $perPage = $request->input('limit');
            $currentPage = $request->input('page');
            $search = $request->input('search');

            // Base query to fetch challans with supplier information, ordered by descending order
            $query = Challan::with('supplier')->orderBy('id', 'desc');

            // Apply search filter if 'search' parameter is provided
            if ($search) {
                $query->whereHas('supplier', function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                        ->orWhere('phone', 'like', '%' . $search . '%');
                });
            }

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
                $challans = $query->paginate($perPage, ['*'], 'page', $currentPage);

                // Format the paginated response
                $formattedChallans = $challans->map(function ($challan) {
                    return [
                        'id' => $challan->id,
                        'Date' => $challan->Date,
                        'total' => $challan->total,
                        'delivery_price' => $challan->delivery_price,
                        'supplier' => [
                            'name' => $challan->supplier->name,
                            'phone' => $challan->supplier->phone,
                            'address' => $challan->supplier->address,
                        ],
                    ];
                });

                // Return response with pagination data
                return response()->json([
                    'success' => true,
                    'status' => 200,
                    'message' => 'Challans retrieved successfully.',
                    'data' => $formattedChallans,
                    'pagination' => [
                        'total_rows' => $challans->total(),
                        'current_page' => $challans->currentPage(),
                        'per_page' => $challans->perPage(),
                        'total_pages' => $challans->lastPage(),
                        'has_more_pages' => $challans->hasMorePages(),
                    ]
                ], 200);
            }

            // If no pagination parameters, fetch all records without pagination
            $challans = $query->get();

            // Format the response
            $formattedChallans = $challans->map(function ($challan) {
                return [
                    'id' => $challan->id,
                    'Date' => $challan->Date,
                    'total' => $challan->total,
                    'delivery_price' => $challan->delivery_price,
                    'supplier' => [
                        'name' => $challan->supplier->name,
                        'phone' => $challan->supplier->phone,
                        'address' => $challan->supplier->address,
                    ],
                ];
            });

            // Return response without pagination links
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Challans retrieved successfully.',
                'data' => $formattedChallans
            ], 200);
        } catch (\Exception $e) {
            // Handle any exceptions
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while retrieving challans.',
                'data' => null,
                'errors' => $e->getMessage()
            ], 500);
        }
    }
}
