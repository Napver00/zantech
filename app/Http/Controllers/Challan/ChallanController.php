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
use App\Models\Challan_item;
use Illuminate\Support\Facades\Storage;

class ChallanController extends Controller
{
    public function store(Request $request)
    {
        try {
            // Get the authenticated user
            $user = auth()->user();

            // Validate the request data
            $validator = Validator::make($request->all(), [
                'Date' => 'required|date',
                'item_id' => 'required|array',
                'item_id.*' => 'exists:items,id',
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

            // Calculate the total (buying + delivery)
            $total = $totalBuyingPrice + $request->delivery_price;

            // Create the challan
            $challan = Challan::create([
                'Date' => $request->Date,
                'user_id' => $user->id,
                'total' => $total,
                'delivery_price' => $request->delivery_price,
                'supplier_id' => $request->supplier_id,
            ]);

            // Save in expense
            Expense::create([
                'date' => $request->Date,
                'user_id' => $user->id,
                'title' => 'Buying equipments',
                'amount' => $total,
                'description' => "Buying Equipment on {$request->Date}. Total price is {$total}.",
            ]);

            // Process each item
            foreach ($request->item_id as $index => $itemId) {
                // Find item details
                $item = Item::find($itemId);

                // Save to Supplier_item_list
                Supplier_item_list::create([
                    'supplier_id' => $request->supplier_id,
                    'item_id' => $itemId,
                    'price' => $request->buying[$index],
                    'quantity' => $request->quantity[$index],
                    'challan_id' => $challan->id,
                ]);

                // Save to Challan_item with item_name
                Challan_item::create([
                    'challan_id' => $challan->id,
                    'item_id' => $itemId,
                    'item_name' => $item->name,
                    'quantity' => $request->quantity[$index],
                    'buying_price' => $request->buying[$index],
                ]);

                // Update item quantity
                $item->quantity += $request->quantity[$index];
                $item->save();
            }

            // Save the invoice image
            if ($request->hasFile('invoice')) {
                $invoice = $request->file('invoice');
                $path = $invoice->store('public/challan');

                File::create([
                    'relatable_id' => $challan->id,
                    'type' => 'challan',
                    'path' => $path,
                ]);
            }

            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => 'Challan created successfully.',
                'data' => $challan,
                'errors' => null,
            ], 201);
        } catch (\Exception $e) {
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
            $challan = Challan::with(['supplier', 'user', 'challanItems', 'invoices'])->find($id);

            if (!$challan) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Challan not found.',
                    'data' => null,
                    'errors' => 'Invalid challan ID.',
                ], 404);
            }

            $response = [
                'id' => $challan->id,
                'Date' => $challan->Date,
                'total' => $challan->total,
                'delivery_price' => $challan->delivery_price,
                'supplier' => $challan->supplier ? [
                    'name' => $challan->supplier->name,
                    'phone' => $challan->supplier->phone,
                    'address' => $challan->supplier->address,
                ] : null,
                'user' => [
                    'name' => $challan->user->name,
                ],
                'items' => $challan->challanItems->map(function ($item) {
                    return [
                        'challan_item_id' => $item->id,
                        'item_id' => $item->item_id,
                        'item_name' => $item->item_name,
                        'buying_price' => $item->buying_price,
                        'quantity' => $item->quantity,
                    ];
                }),
                'invoices' => $challan->invoices->map(function ($file) {
                    return [
                        'id' => $file->id,
                        'path' => asset('storage/' . str_replace('public/', '', $file->path)),
                    ];
                }),
            ];

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Challan retrieved successfully.',
                'data' => $response,
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
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
                        'supplier' => $challan->supplier ? [
                            'name' => $challan->supplier->name,
                            'phone' => $challan->supplier->phone,
                            'address' => $challan->supplier->address,
                        ] : null,
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

    // update challenge
    public function update(Request $request, $id)
    {
        try {
            // Find the challan by ID
            $challan = Challan::find($id);

            if (!$challan) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Challan not found.',
                    'data' => null,
                    'errors' => 'Invalid challan ID.',
                ], 404);
            }

            // Validate the request data
            $validator = Validator::make($request->all(), [
                'Date' => 'required|date',
                'delivery_price' => 'required|numeric|min:0',
                'supplier_id' => 'required|exists:suppliers,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'status' => 422,
                    'message' => 'Validation failed.',
                    'data' => null,
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Recalculate the total if delivery_price changes
            $oldDeliveryPrice = $challan->delivery_price;
            $newDeliveryPrice = $request->delivery_price;

            $priceDifference = $newDeliveryPrice - $oldDeliveryPrice;
            $updatedTotal = $challan->total + $priceDifference;

            // Update challan with new values
            $challan->update([
                'Date' => $request->Date,
                'delivery_price' => $newDeliveryPrice,
                'supplier_id' => $request->supplier_id,
                'total' => $updatedTotal,
            ]);

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Challan updated successfully.',
                'data' => $challan,
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while updating the challan.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // add item to challenge
    public function addItemsToChallan(Request $request, $challanId)
    {
        try {
            // Find the challan
            $challan = Challan::find($challanId);

            if (!$challan) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Challan not found.',
                    'data' => null,
                    'errors' => 'Invalid challan ID.',
                ], 404);
            }

            // Validate the request
            $validator = Validator::make($request->all(), [
                'item_id' => 'required|array',
                'item_id.*' => 'exists:items,id',
                'buying' => 'required|array',
                'buying.*' => 'numeric|min:0',
                'quantity' => 'required|array',
                'quantity.*' => 'integer|min:1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'status' => 422,
                    'message' => 'Validation failed.',
                    'data' => null,
                    'errors' => $validator->errors(),
                ], 422);
            }

            $totalAdded = 0;

            foreach ($request->item_id as $index => $itemId) {
                $buyingPrice = $request->buying[$index];
                $quantity = $request->quantity[$index];

                $item = Item::find($itemId);

                if (!$item) {
                    continue;
                }

                $subtotal = $buyingPrice * $quantity;
                $totalAdded += $subtotal;

                // Save to Challan_item
                Challan_item::create([
                    'challan_id' => $challan->id,
                    'item_id' => $itemId,
                    'item_name' => $item->name,
                    'quantity' => $quantity,
                    'buying_price' => $buyingPrice,
                ]);

                // Update item quantity
                $item->quantity += $quantity;
                $item->save();
            }

            // Update challan total
            $challan->total += $totalAdded;
            $challan->save();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Items added and challan updated successfully.',
                'data' => $challan,
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while adding items to the challan.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // items quantity update in challen items table
    public function updateChallanItemQuantity(Request $request, $challanItemId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'quantity' => 'required|integer|min:1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'status' => 422,
                    'message' => 'Validation failed.',
                    'data' => null,
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Find the Challan Item
            $challanItem = Challan_item::find($challanItemId);
            if (!$challanItem) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Challan item not found.',
                    'data' => null,
                    'errors' => 'Invalid challan item ID.',
                ], 404);
            }

            // Find the related item
            $item = Item::find($challanItem->item_id);
            if (!$item) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Related item not found.',
                    'data' => null,
                    'errors' => 'Invalid item ID.',
                ], 404);
            }

            // Save old quantity and subtotal
            $oldQuantity = $challanItem->quantity;
            $oldSubtotal = $challanItem->buying_price * $oldQuantity;

            // Calculate new subtotal
            $newQuantity = $request->quantity;
            $newSubtotal = $challanItem->buying_price * $newQuantity;

            // Update item stock
            $quantityDifference = $newQuantity - $oldQuantity; // +ve if increased, -ve if decreased
            $item->quantity += $quantityDifference;
            $item->save();

            // Update challan item
            $challanItem->quantity = $newQuantity;
            $challanItem->save();

            // Update challan total
            $challan = Challan::find($challanItem->challan_id);
            $challan->total = $challan->total - $oldSubtotal + $newSubtotal;
            $challan->save();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Challan item quantity and total updated successfully.',
                'data' => [
                    'challan_item' => $challanItem,
                    'updated_item' => $item,
                    'updated_challan' => $challan,
                ],
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while updating.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // delete challenge item
    public function deleteChallanItem($challanItemId)
    {
        try {
            // Find the challan item
            $challanItem = Challan_item::find($challanItemId);

            if (!$challanItem) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Challan item not found.',
                    'data' => null,
                    'errors' => 'Invalid challan item ID.',
                ], 404);
            }

            // Get related item and challan
            $item = Item::find($challanItem->item_id);
            $challan = Challan::find($challanItem->challan_id);

            if (!$item || !$challan) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Related item or challan not found.',
                    'data' => null,
                    'errors' => 'Invalid related data.',
                ], 404);
            }

            // Calculate subtotal to subtract from challan total
            $subtotal = $challanItem->buying_price * $challanItem->quantity;

            // Decrease item quantity
            $item->quantity -= $challanItem->quantity;
            if ($item->quantity < 0) {
                $item->quantity = 0;
            }
            $item->save();

            // Update challan total
            $challan->total -= $subtotal;
            if ($challan->total < 0) {
                $challan->total = 0;
            }
            $challan->save();

            // Delete challan item
            $challanItem->delete();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Challan item deleted, item quantity and challan total updated.',
                'data' => [
                    'updated_item' => $item,
                    'updated_challan' => $challan,
                ],
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while deleting the challan item.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // add new invoice image
    public function uploadInvoiceImage(Request $request, $challanId)
    {
        try {
            // Validate challan existence
            $challan = Challan::find($challanId);
            if (!$challan) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Challan not found.',
                    'data' => null,
                    'errors' => 'Invalid challan ID.',
                ], 404);
            }

            // Validate the uploaded file
            $validator = Validator::make($request->all(), [
                'invoice' => 'required|file|mimes:jpg,jpeg,png,pdf|max:2048',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'status' => 422,
                    'message' => 'Validation failed.',
                    'data' => null,
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Store the file
            $invoice = $request->file('invoice');
            $path = $invoice->store('public/challan');

            // Save file record
            $file = File::create([
                'relatable_id' => $challan->id,
                'type' => 'challan',
                'path' => $path,
            ]);

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Invoice image uploaded successfully.',
                'data' => $file,
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while uploading the invoice image.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // delete invoice image
    public function destroyInvoice($id)
    {
        try {
            // Find the file record
            $file = File::find($id);

            if (!$file) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Invoice file not found.',
                    'data' => null,
                    'errors' => 'Invalid file ID.',
                ], 404);
            }

            // Check and delete file from storage
            if (Storage::exists($file->path)) {
                Storage::delete($file->path);
            }

            // Delete the database record
            $file->delete();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Invoice file deleted successfully.',
                'data' => null,
                'errors' => null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while deleting the invoice file.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }
}
