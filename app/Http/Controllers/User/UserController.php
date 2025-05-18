<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\ShippingAddress;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Activity;
use App\Models\Expense;
use App\Models\Coupon;


class UserController extends Controller
{
    //shwo user info only id, name and email
    public function index(Request $request)
    {
        $query = User::select('id', 'name', 'email', 'phone', 'address');

        // Search by name, email, or phone
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $users = $query->get();

        if ($users->isEmpty()) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'No users found.',
                'data' => [],
                'errors' => 'No users found.',
            ], 404);
        }

        // Prepare enriched user data
        $allUsersData = [];

        foreach ($users as $user) {
            $orders = Order::where('user_id', $user->id)->get();
            $totalOrders = $orders->count();
            $totalAmount = $orders->where('status', 1)->sum('total_amount');

            $payments = Payment::whereHas('order', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })->get();

            $totalDueAmount = $payments->sum(function ($payment) {
                return $payment->amount - $payment->padi_amount;
            });

            $allUsersData[] = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'address' => $user->address,
                'order_summary' => [
                    'total_orders' => $totalOrders,
                    'total_spend' => $totalAmount,
                ],
                'payment_summary' => [
                    'due_amount' => $totalDueAmount,
                ],
            ];
        }

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Users retrieved successfully.',
            'data' => $allUsersData,
            'errors' => null,
        ], 200);
    }



    // shwo user all information
    public function shwoAllInfo(Request $request, $userId)
    {
        $perPage = $request->input('limit');
        $currentPage = $request->input('page');

        $query = User::select('id', 'name', 'email', 'phone', 'created_at', 'address');

        if ($userId) {
            $query->where('id', $userId);
        }

        if ($perPage && $currentPage) {
            $users = $query->paginate($perPage);
        } else {
            $users = $query->get();
        }

        if ($users->isEmpty()) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'No users found.',
                'data' => null,
                'errors' => 'No users found.',
            ], 404);
        }

        $allUsersData = [];

        foreach ($users as $user) {
            $shippingAddresses = ShippingAddress::where('User_id', $user->id)->get();
            $orders = Order::where('user_id', $user->id)->get();
            $totalOrders = $orders->count();
            $totalAmount = $orders->where('status', 1)->sum('total_amount');

            $payments = Payment::whereHas('order', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })->get();

            $totalDueAmount = $payments->sum(function ($payment) {
                return $payment->amount - $payment->padi_amount;
            });

            $userData = [
                'user' => $user,
                'shipping_addresses' => $shippingAddresses,
                'order_summary' => [
                    'total_orders' => $totalOrders,
                    'total_spend' => $totalAmount,
                ],
                'payment_summary' => [
                    'due_amount' => $totalDueAmount,
                ],
            ];

            $allUsersData[] = $userData;
        }

        $pagination = $perPage ? [
            'total_rows' => $users->total(),
            'current_page' => $users->currentPage(),
            'per_page' => $users->perPage(),
            'total_pages' => $users->lastPage(),
            'has_more_pages' => $users->hasMorePages(),
        ] : null;

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'User information retrieved successfully.',
            'data' => $allUsersData,
            'pagination' => $pagination,
            'error' => null,
        ], 200);
    }


    // Shwo all active
    public function getActivities(Request $request)
    {
        try {
            $perPage = $request->input('limit');
            $currentPage = $request->input('page');

            // Base query to fetch activities with eager loading of user
            $query = Activity::with('user')->orderBy('created_at', 'desc');

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
                $activities = $query->paginate($perPage, ['*'], 'page', $currentPage);

                // Return response with pagination data
                return response()->json([
                    'success' => true,
                    'status' => 200,
                    'message' => 'Activities retrieved successfully.',
                    'data' => $activities->items(),
                    'pagination' => [
                        'total_rows' => $activities->total(),
                        'current_page' => $activities->currentPage(),
                        'per_page' => $activities->perPage(),
                        'total_pages' => $activities->lastPage(),
                        'has_more_pages' => $activities->hasMorePages(),
                    ]
                ], 200);
            }

            // If no pagination parameters, fetch all records without pagination
            $activities = $query->get();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Activities retrieved successfully.',
                'data' => $activities
            ], 200);
        } catch (\Exception $e) {
            // Handle any exceptions that occur
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while retrieving activities.',
                'data' => null,
                'errors' => $e->getMessage()
            ], 500);
        }
    }
}
