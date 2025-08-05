<?php

namespace App\Http\Controllers\Order;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Order_list;
use App\Models\Item;
use App\Models\BundleItem;
use App\Models\Payment;
use App\Models\Coupon;
use App\Models\ShippingAddress;
use App\Models\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Carbon;
use App\Models\Activity;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Mail\OrderPlacedMail;
use App\Events\OrderPlaced;
use Illuminate\Support\Facades\Log;
use App\Jobs\SendOrderEmailsJob;


class OrderController extends Controller
{
    //plase order by user
    public function placeOrder(Request $request)
    {
        DB::beginTransaction();

        try {
            // Validate the request
            $validator = $this->validateOrderRequest($request);
            if ($validator->fails()) {
                return $this->validationErrorResponse($validator);
            }

            // Check product availability and update quantities
            $this->updateProductQuantities($request->products);

            // Generate invoice code
            $invoiceCode = $this->generateInvoiceCode();

            // Create the order
            $order = $this->createOrder($request, $invoiceCode);

            // Save order items
            $this->saveOrderItems($order, $request->products);

            // Create payment record
            $this->createPayment($order, $request);

            DB::commit();

            // Dispatch the job to send emails asynchronously
            // dispatch(new SendOrderEmailsJob($order));

            // Return success response
            return $this->successResponse($order, 'Order placed successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse($e->getMessage());
        }
    }

    // Validate the order request
    private function validateOrderRequest(Request $request)
    {
        return Validator::make($request->all(), [
            'coupon_id' => 'nullable|exists:coupons,id',
            'user_id' => 'nullable|exists:users,id',
            'shipping_id' => 'nullable|exists:shipping_addresses,id',
            'shipping_charge' => 'nullable|numeric|min:0',
            'product_subtotal' => 'required|numeric|min:0',
            'total' => 'required|numeric|min:0',
            'paymentphone' => 'nullable|string|max:20',
            'products' => 'required|array',
            'products.*.product_id' => 'required|exists:items,id',
            'products.*.quantity' => 'required|integer|min:1',
            'payment_type' => 'required|integer|in:1,2', // 1 = Cash on Delivery, 2 = Bkash
            'trxed' => 'nullable|string|max:255',
            'user_name' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'userphone' => 'nullable|string|max:20',
        ]);
    }

    // Update product quantities
    private function updateProductQuantities($products)
    {
        foreach ($products as $product) {
            $item = Item::find($product['product_id']);
            if ($item->quantity < $product['quantity']) {
                throw new \Exception('Insufficient quantity for product: ' . $item->name);
            }
            $item->quantity -= $product['quantity'];
            $item->save();
        }
    }

    // Generate invoice code
    private function generateInvoiceCode()
    {
        $lastOrder = Order::latest()->first();
        return $lastOrder ? 'ZT' . (intval(substr($lastOrder->invoice_code, 2)) + 1) : 'ZT1000';
    }

    // Create the order
    private function createOrder(Request $request, $invoiceCode)
    {
        return Order::create([
            'invoice_code' => $invoiceCode,
            'user_id' => $request->user_id,
            'shipping_id' => $request->shipping_id,
            'status' => '0',
            'item_subtotal' => $request->product_subtotal,
            'shipping_chaege' => $request->shipping_charge,
            'total_amount' => $request->total,
            'coupons_id' => $request->coupon_id,
            'discount' => $request->discount ?? 0,
            'user_name' => $request->user_name,
            'phone' => $request->userphone,
            'address' => $request->address,
        ]);
    }

    // Save order items
    private function saveOrderItems($order, $products)
    {
        foreach ($products as $product) {
            // Fetch the item details from the Item table
            $item = Item::find($product['product_id']);

            if (!$item) {
                throw new \Exception('Product not found: ' . $product['product_id']);
            }

            // Calculate the total price for the product
            $Price = $item->price;

            // Create the order item
            $orderItem = Order_list::create([
                'order_id' => $order->id,
                'product_id' => $product['product_id'],
                'Bundle_product_id' => null,
                'quantity' => $product['quantity'],
                'price' => $Price,
            ]);

            // Handle bundle products if any
            $this->handleBundleProducts($order, $product);
        }
    }

    // Handle bundle products
    private function handleBundleProducts($order, $product)
    {
        // Find the item (bundle product)
        $item = Item::find($product['product_id']);

        if ($item->is_bundle) {
            // Fetch all bundle items related to the bundle product
            $bundleItems = BundleItem::where('bundle_item_id', $item->id)->get();

            // Store the item_id and quantity of items in the bundle
            $itemsInBundle = [];
            foreach ($bundleItems as $bundleItem) {
                $itemsInBundle[] = [
                    'item_id' => $bundleItem->item_id,
                    'quantity' => $bundleItem->bundle_quantity * $product['quantity'],
                ];
            }

            // Process each item in the bundle
            foreach ($itemsInBundle as $itemInBundle) {
                // Fetch the item from the items table
                $bundleProduct = Item::find($itemInBundle['item_id']);

                // Reduce the quantity of the item
                $bundleProduct->quantity -= $itemInBundle['quantity'];
                $bundleProduct->save();
            }

            // Create the order item for the bundle product
            Order_list::create([
                'order_id' => $order->id,
                'quantity' => $product['quantity'],
                'price' => $item->price * $product['quantity'],
                'product_id' => $product['product_id'],
            ]);
        }
    }

    // Create payment record
    private function createPayment($order, $request)
    {
        $paymentStatus = $request->payment_type == 2 ? 4 : 0; // 1 = Paid (Bkash), 0 = Unpaid (Cash on Delivery)
        Payment::create([
            'order_id' => $order->id,
            'status' => $paymentStatus,
            'amount' => $request->total,
            'padi_amount' => 0,
            'payment_type' => $request->payment_type,
            'trxed' => $request->trxed,
            'phone' => $request->paymentphone
        ]);;
    }

    // Return validation error response
    private function validationErrorResponse($validator)
    {
        return response()->json([
            'success' => false,
            'status' => 400,
            'message' => 'Validation failed.',
            'data' => null,
            'errors' => $validator->errors(),
        ], 400);
    }

    // Return success response
    private function successResponse($data, $message)
    {
        return response()->json([
            'success' => true,
            'status' => 201,
            'message' => $message,
            'data' => $data,
            'errors' => null,
        ], 201);
    }

    // Return error response
    private function errorResponse($errorMessage)
    {
        return response()->json([
            'success' => false,
            'status' => 500,
            'message' => 'Failed to place order.',
            'data' => null,
            'errors' => $errorMessage,
        ], 500);
    }

    // Resend order emails
    public function sendOrderEmails(Request $request, $order_Id)
    {
        // Fetch the order by ID
        $order = Order::find($order_Id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Order not found',
                'data' => null,
                'errors' => 'Order not found',
            ], 404);
        }

        // Get additional email addresses from the request
        $additionalEmails = $request->input('emails', []); // Expecting an array of emails

        dispatch(new SendOrderEmailsJob($order, $additionalEmails));

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Emails are being sent in the background',
            'data' => null,
        ], 200);
    }

    // shwo all orders for admin page
    public function adminindex(Request $request)
    {
        try {
            $perPage = $request->input('limit');
            $currentPage = $request->input('page');
            $search = $request->input('search');
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');

            // Eager load 'user' and 'payment' relationships
            $query = Order::with(['user', 'payment'])->orderBy('created_at', 'desc');

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('invoice_code', 'like', '%' . $search . '%')
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where('name', 'like', '%' . $search . '%')
                                ->orWhere('phone', 'like', '%' . $search . '%')
                                ->orWhere('email', 'like', '%' . $search . '%');
                        });
                });
            }

            if ($startDate && $endDate) {
                $query->whereBetween('created_at', [
                    Carbon::parse($startDate)->startOfDay(),
                    Carbon::parse($endDate)->endOfDay()
                ]);
            }

            $orders = ($perPage && $currentPage)
                ? $query->paginate($perPage, ['*'], 'page', $currentPage)
                : $query->get();

            $formattedOrders = $orders->map(function ($order) {
                $payment = $order->payment; // Assume one-to-one relationship (adjust if multiple payments)

                $paidAmount = $payment?->padi_amount ?? 0;
                $totalAmount = $payment?->amount ?? $order->total_amount ?? 0;
                $dueAmount = $totalAmount - $paidAmount;

                return [
                    'user_name' => $order->user?->name ?? $order->user_name,
                    'user_phone' => $order->user?->phone ?? $order->phone,
                    'user_email' => $order->user?->email ?? null,
                    'order_id' => $order->id,
                    'invoice_code' => $order->invoice_code,
                    'status' => $order->status,
                    'total_amount' => $totalAmount,
                    'paid_amount' => $paidAmount,
                    'due_amount' => $dueAmount,
                    'order_placed_date_time' => $order->created_at->format('Y-m-d H:i:s'),
                ];
            });

            $statusCounts = Order::select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->get()
                ->pluck('total', 'status');

            $response = [
                'success' => true,
                'status' => 200,
                'message' => 'Orders fetched successfully.',
                'data' => $formattedOrders,
                'status_summary' => [
                    'processing' => $statusCounts[0] ?? 0,
                    'completed' => $statusCounts[1] ?? 0,
                    'on_hold' => $statusCounts[2] ?? 0,
                    'cancelled' => $statusCounts[3] ?? 0,
                    'refunded' => $statusCounts[4] ?? 0,
                ],
                'errors' => null,
            ];

            if ($perPage && $currentPage) {
                $response['pagination'] = [
                    'total' => $orders->total(),
                    'per_page' => $orders->perPage(),
                    'current_page' => $orders->currentPage(),
                    'last_page' => $orders->lastPage(),
                    'from' => $orders->firstItem(),
                    'to' => $orders->lastItem(),
                ];
            }

            return response()->json($response, 200);
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();

            if (str_contains($errorMessage, 'Integrity constraint violation')) {
                preg_match("/Duplicate entry '(.+?)' for key '(.+?)'/", $errorMessage, $matches);
                if (!empty($matches)) {
                    $errorMessage = "Duplicate entry '{$matches[1]}' for key '{$matches[2]}'";
                }
            }

            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to fetch orders.',
                'data' => null,
                'errors' => $errorMessage,
            ], 500);
        }
    }





    // shwo all orders for user
    public function userindex(Request $request)
    {
        try {
            // Get 'limit', 'page', 'user_id', and 'search' from request
            $perPage = $request->input('limit');
            $currentPage = $request->input('page');
            $userId = $request->input('user_id');
            $search = $request->input('search');

            // Start with a base query
            $query = Order::with('user')->orderBy('created_at', 'desc');

            // Filter by user_id if provided
            if ($userId) {
                $query->where('user_id', $userId);
            }

            // Apply search filter if 'search' parameter is provided
            if ($search) {
                $query->where('invoice_code', 'like', '%' . $search . '%');
            }

            // Apply pagination only if 'limit' and 'page' are provided
            if ($perPage && $currentPage) {
                $orders = $query->paginate($perPage, ['*'], 'page', $currentPage);
            } else {
                // Fetch all orders if pagination parameters are not provided
                $orders = $query->get();
            }

            // Format the response data
            $formattedOrders = $orders->map(function ($order) {
                return [
                    'user_name' => $order->user->name,
                    'user_phone' => $order->user->phone,
                    'user_email' => $order->user->email,
                    'order_id' => $order->id,
                    'invoice_code' => $order->invoice_code,
                    'status' => $order->status,
                    'total_amount' => $order->total_amount,
                    'order_placed_date_time' => $order->created_at->format('Y-m-d H:i:s'),
                ];
            });

            // Prepare the response
            $response = [
                'success' => true,
                'status' => 200,
                'message' => 'Orders fetched successfully.',
                'data' => $formattedOrders,
                'errors' => null,
            ];

            // Add pagination metadata if pagination is applied
            if ($perPage && $currentPage) {
                $response['pagination'] = [
                    'total' => $orders->total(),
                    'per_page' => $orders->perPage(),
                    'current_page' => $orders->currentPage(),
                    'last_page' => $orders->lastPage(),
                    'from' => $orders->firstItem(),
                    'to' => $orders->lastItem(),
                ];
            }

            // Return the response as JSON
            return response()->json($response, 200);
        } catch (\Exception $e) {
            // Extract only the main error message
            $errorMessage = $e->getMessage();

            // Check if it's a SQL Integrity Constraint Violation
            if (str_contains($errorMessage, 'Integrity constraint violation')) {
                preg_match("/Duplicate entry '(.+?)' for key '(.+?)'/", $errorMessage, $matches);
                if (!empty($matches)) {
                    $errorMessage = "Duplicate entry '{$matches[1]}' for key '{$matches[2]}'";
                }
            }
            // Handle exceptions and return error response
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to fetch orders.',
                'data' => null,
                'errors' => $errorMessage,
            ], 500);
        }
    }

    // shwo single order
    public function show($orderId)
    {
        try {
            // Fetch the order with related data
            $order = Order::with([
                'user',
                'shippingAddress',
                'coupon',
                'orderItems.item.images',
                'payments',
                'orderItems.item.bundleItems.bundleItem.images'
            ])->findOrFail($orderId);

            // Format the response
            $response = [
                'success' => true,
                'status' => 200,
                'message' => 'Order fetched successfully.',
                'data' => $this->formatOrderResponse($order),
                'errors' => null,
            ];

            return response()->json($response, 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to fetch order.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    private function formatOrderResponse($order)
    {
        return [
            'order' => [
                'order_id' => $order->id,
                'user_id' => $order->user_id ?? null,
                'shipping_id' => $order->shipping_id ?? null,
                'user_name' => $order->user_name ?? null,
                'user_phone' => $order->phone ?? null,
                'address' => $order->address ?? null,
                'invoice_code' => $order->invoice_code,
                'status' => $order->status,
                'status_change_desc' => $order->status_chnange_desc,
                'item_subtotal' => $order->item_subtotal,
                'shipping_charge' => $order->shipping_chaege,
                'total_amount' => $order->total_amount,
                'discount' => $order->discount,
                'created_at' => $order->created_at->format('Y-m-d H:i:s'),
            ],
            'user' => $order->user ? [
                'user_id' => $order->user->id,
                'name' => $order->user->name,
                'email' => $order->user->email,
                'phone' => $order->user->phone,
                'address' => $order->user->address,
            ] : null,
            'shipping_address' => $order->shippingAddress ? [
                'shipping_id' => $order->shippingAddress->id,
                'f_name' => $order->shippingAddress->f_name,
                'l_name' => $order->shippingAddress->l_name,
                'phone' => $order->shippingAddress->phone,
                'address' => $order->shippingAddress->address,
                'city' => $order->shippingAddress->city,
                'zip' => $order->shippingAddress->zip,
            ] : null,
            'coupon' => $order->coupon ? [
                'code' => $order->coupon->code,
                'amount' => $order->coupon->amount,
            ] : null,
            'order_items' => $order->orderItems->map(function ($orderItem) {
                $item = $orderItem->item;
                $bundleItems = $item->is_bundle ? $item->bundleItems->map(function ($bundleItem) {
                    return [
                        'name' => $bundleItem->bundleItem->name,
                        'quantity' => $bundleItem->bundle_quantity,
                        'price' => $bundleItem->bundleItem->price,
                        'discount' => $bundleItem->bundleItem->discount,
                        'is_bundle' => $bundleItem->bundleItem->is_bundle,
                        'image' => url('public/'. $bundleItem->bundleItem->images->first()) ? url('public/'. $bundleItem->bundleItem->images->first()->path): null,
                    ];
                }) : null;

                return [
                    'product_id' => $item->id,
                    'name' => $item->name,
                    'quantity' => $orderItem->quantity,
                    'price' => $orderItem->price,
                    'is_bundle' => $item->is_bundle,
                    'bundle_items' => $bundleItems,
                    'image' => url('public/' . $item->images->first()->path) ? url('public/' . $item->images->first()->path) : null,
                ];
            }),
            'payments' => $order->payments->map(function ($payment) {
                return [
                    'payment_id' => $payment->id,
                    'status' => $payment->status,
                    'amount' => $payment->amount,
                    'paid_amount' => $payment->padi_amount,
                    'payment_type' => $payment->payment_type,
                    'transaction_id' => $payment->trxed,
                    'phone' => $payment->phone,
                    'due_amount' => $payment->amount - $payment->padi_amount,
                ];
            }),
        ];
    }

    // update order status
    public function updateStatus(Request $request, $orderId)
    {
        try {
            // Validate the request
            $request->validate([
                'status' => 'required|integer|in:0,1,2,3,4',
            ]);

            // Find the order
            $order = Order::findOrFail($orderId);

            // Get the current status and the new status
            $currentStatus = $order->status;
            $newStatus = $request->input('status');

            // Prepare the status change description
            $statusChangeDesc = "Status changed from {$currentStatus} to {$newStatus} at " . Carbon::now()->format('Y-m-d H:i:s');

            // Update the order status and status change description
            $order->update([
                'status' => $newStatus,
                'status_chnange_desc' => $statusChangeDesc,
            ]);

            // Save activity
            Activity::create([
                'relatable_id' => $orderId,
                'type' => 'order',
                'user_id' => Auth::id(),
                'description' => $statusChangeDesc,
            ]);

            // Return success response
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Order status updated successfully.',
                'data' => [
                    'order_id' => $order->id,
                    'new_status' => $order->status,
                    'status_change_desc' => $order->status_chnange_desc,
                ],
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            // Handle exceptions and return error response
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to update order status.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // Remove products from oder
    public function removeProductFromOrder(Request $request, $orderId, $productId)
    {
        DB::beginTransaction();
        try {
            // Find the order
            $order = Order::findOrFail($orderId);

            // Find the product in the order list
            $orderItem = Order_list::where('order_id', $orderId)
                ->where('product_id', $productId)
                ->firstOrFail();

            // Calculate the amount to be deducted
            $amountToDeduct = $orderItem->quantity * $orderItem->price;

            // Remove the product from the order list
            $orderItem->delete();

            // Update the total_amount in the order table
            $order->total_amount -= $amountToDeduct;
            $order->save();

            // Update the amount in the payment table
            $payment = Payment::where('order_id', $orderId)->first();
            if ($payment) {
                $payment->amount -= $amountToDeduct;
                $payment->save();
            }

            // Save activity
            Activity::create([
                'relatable_id' => $orderId,
                'type' => 'order',
                'user_id' => Auth::id(),
                'description' => 'remove new product in : ' . $orderId,
            ]);

            // Commit the transaction
            DB::commit();

            // Return success response
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Product removed from order successfully.',
                'data' => [
                    'order_id' => $order->id,
                    'new_total_amount' => $order->total_amount,
                    'payment_amount' => $payment ? $payment->amount : null,
                ],
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            // Rollback the transaction in case of error
            DB::rollBack();

            // Handle exceptions and return error response
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to remove product from order.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // update product quantity
    public function updateProductQuantity(Request $request, $orderId, $productId)
    {
        DB::beginTransaction();
        try {
            // Validate the request
            $request->validate([
                'quantity' => 'required|integer|min:1',
                'price' => 'nullable|numeric|min:0',
            ]);

            // Find the order
            $order = Order::find($orderId);
            if (!$order) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Order not found.',
                    'data' => null,
                    'errors' => 'No query results for model [App\Models\Order] ' . $orderId,
                ], 404);
            }

            // Find the product in the order list
            $orderItem = Order_list::where('order_id', $orderId)
                ->where('product_id', $productId)
                ->first();

            if (!$orderItem) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Product not found in the order.',
                    'data' => null,
                    'errors' => 'No query results for model [App\Models\Order_list] with product_id ' . $productId,
                ], 404);
            }

            // Calculate the old total for the product
            $oldTotal = $orderItem->quantity * $orderItem->price;

            // Update the product quantity and price (if provided)
            $orderItem->quantity = $request->input('quantity');
            if ($request->has('price')) {
                $orderItem->price = $request->input('price');
            }
            $orderItem->save();

            // Calculate the new total for the product
            $newTotal = $orderItem->quantity * $orderItem->price;

            // Calculate the difference in total amount
            $amountDifference = $newTotal - $oldTotal;

            // Update the total_amount in the order table
            $order->total_amount += $amountDifference;
            $order->save();

            // Update the amount in the payment table
            $payment = Payment::where('order_id', $orderId)->first();
            if ($payment) {
                $payment->amount += $amountDifference;
                $payment->save();
            }

            // Save activity
            Activity::create([
                'relatable_id' => $orderId,
                'type' => 'order',
                'user_id' => Auth::id(),
                'description' =>  'Update product quantity: ' . $oldTotal . ' to ' . $newTotal,
            ]);

            // Commit the transaction
            DB::commit();

            // Return success response
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Product quantity and price updated successfully.',
                'data' => [
                    'order_id' => $order->id,
                    'product_id' => $productId,
                    'new_quantity' => $orderItem->quantity,
                    'new_price' => $orderItem->price,
                    'new_total_amount' => $order->total_amount,
                    'payment_amount' => $payment ? $payment->amount : null,
                ],
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            // Rollback the transaction in case of error
            DB::rollBack();
            // Extract only the main error message
            $errorMessage = $e->getMessage();

            // Check if it's a SQL Integrity Constraint Violation
            if (str_contains($errorMessage, 'Integrity constraint violation')) {
                preg_match("/Duplicate entry '(.+?)' for key '(.+?)'/", $errorMessage, $matches);
                if (!empty($matches)) {
                    $errorMessage = "Duplicate entry '{$matches[1]}' for key '{$matches[2]}'";
                }
            }
            // Handle exceptions and return error response
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to update product quantity and price.',
                'data' => null,
                'errors' => $errorMessage
            ], 500);
        }
    }

    // add new product to order
    public function addProductToOrder(Request $request, $orderId)
    {
        DB::beginTransaction();
        try {
            // Validate the request
            $request->validate([
                'product_id' => 'required|exists:items,id',
                'quantity' => 'required|integer|min:1',
            ]);

            // Find the order
            $order = Order::find($orderId);
            if (!$order) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Order not found.',
                    'data' => null,
                    'errors' => 'No query results for model [App\Models\Order] ' . $orderId,
                ], 404);
            }

            // Find the product in the items table
            $product = Item::find($request->input('product_id'));
            if (!$product) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Product not found.',
                    'data' => null,
                    'errors' => 'No query results for model [App\Models\Item] ' . $request->input('product_id'),
                ], 404);
            }

            // Use the product's price if no price is provided in the request
            $price = $request->input('price', $product->price);

            // Check if the product already exists in the order
            $orderItem = Order_list::where('order_id', $orderId)
                ->where('product_id', $request->input('product_id'))
                ->first();

            if ($orderItem) {
                // If the product already exists, update the quantity and price
                $oldTotal = $orderItem->quantity * $orderItem->price;
                $orderItem->quantity += $request->input('quantity');
                $orderItem->price = $price;
                $orderItem->save();
                $newTotal = $orderItem->quantity * $orderItem->price;
            } else {
                // If the product does not exist, create a new order item
                $orderItem = Order_list::create([
                    'order_id' => $orderId,
                    'product_id' => $request->input('product_id'),
                    'quantity' => $request->input('quantity'),
                    'price' => $price,
                ]);
                $oldTotal = 0;
                $newTotal = $orderItem->quantity * $orderItem->price;
            }

            // Calculate the difference in total amount
            $amountDifference = $newTotal - $oldTotal;

            // Update the total_amount in the order table
            $order->total_amount += $amountDifference;
            $order->save();

            // Update the amount in the payment table
            $payment = Payment::where('order_id', $orderId)->first();
            if ($payment) {
                $payment->amount += $amountDifference;
                $payment->save();
            }

            // Save activity
            Activity::create([
                'relatable_id' => $orderId,
                'type' => 'order',
                'user_id' => Auth::id(),
                'description' => 'add new product in : ' . $orderId
            ]);

            // Commit the transaction
            DB::commit();

            // Return success response
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Product added to order successfully.',
                'data' => [
                    'order_id' => $order->id,
                    'product_id' => $orderItem->product_id,
                    'quantity' => $orderItem->quantity,
                    'price' => $orderItem->price,
                    'new_total_amount' => $order->total_amount,
                    'payment_amount' => $payment ? $payment->amount : null,
                ],
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            // Rollback the transaction in case of error
            DB::rollBack();

            // Handle exceptions and return error response
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to add product to order.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // shwo due amount of all orders in dashboard
    public function getOrderSummary(Request $request)
    {
        try {
            $query = Order::with(['user', 'payment'])->orderBy('created_at', 'desc');

            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('invoice_code', 'like', "%$search%")
                        ->orWhereHas('user', function ($userQ) use ($search) {
                            $userQ->where('name', 'like', "%$search%")
                                ->orWhere('phone', 'like', "%$search%");
                        });
                });
            }

            if ($request->has(['start_date', 'end_date'])) {
                $query->whereBetween('created_at', [
                    Carbon::parse($request->start_date)->startOfDay(),
                    Carbon::parse($request->end_date)->endOfDay(),
                ]);
            }

            $orders = $query->get();

            $formattedOrders = $orders->map(function ($order) {
                $payment = $order->payment;

                $paidAmount = $payment?->padi_amount ?? 0;
                $totalAmount = $payment?->amount ?? $order->total_amount ?? 0;
                $dueAmount = $totalAmount - $paidAmount;

                if ($dueAmount <= 0) {
                    return null; // filter out fully paid orders
                }

                return [
                    'user_name' => $order->user?->name ?? $order->user_name,
                    'user_phone' => $order->user?->phone ?? $order->phone,
                    'user_email' => $order->user?->email ?? null,
                    'order_id' => $order->id,
                    'invoice_code' => $order->invoice_code,
                    'status' => $order->status,
                    'total_amount' => $totalAmount,
                    'paid_amount' => $paidAmount,
                    'due_amount' => $dueAmount,
                    'order_placed_date_time' => $order->created_at->format('Y-m-d H:i:s'),
                ];
            })->filter();


            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Order summary fetched successfully.',
                'data' => $formattedOrders,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Something went wrong.',
                'errors' => $e->getMessage(),
            ]);
        }
    }

    // updaet customer address
    public function updateCustomerInfo(Request $request, $order_Id)
    {
        $request->validate([
            'user_name' => 'nullable|string',
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
        ]);

        try {
            $order = Order::findOrFail($order_Id);

            // Ensure it's a guest order before updating
            if ($order->user_id !== null || $order->shipping_id !== null) {
                return response()->json([
                    'success' => false,
                    'message' => 'This order belongs to a registered user and cannot be updated this way.',
                ], 400);
            }

            $order->update([
                'user_name' => $request->input('user_name', $order->user_name),
                'user_phone' => $request->input('phone', $order->user_phone),
                'address' => $request->input('address', $order->address),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Order customer info updated successfully!',
                'data' => $order
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong!',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
