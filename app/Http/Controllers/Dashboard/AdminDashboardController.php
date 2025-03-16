<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Transition;
use Illuminate\Support\Facades\Log;

class AdminDashboardController extends Controller
{
    //Admin dashboard overview
    public function adminDashboard()
    {
        try {
            // Get the total order count
            $totalOrderCount = Order::count();

            // Get the count of new orders (status = 0)
            $newOrderCount = Order::where('status', 0)->count();

            // Get the total revenue amount from the transitions table
            $totalRevenue = Transition::sum('amount');

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Dashboard data retrieved successfully.',
                'data' => [
                    'total_order_count' => $totalOrderCount,
                    'new_order_count' => $newOrderCount,
                    'total_revenue' => $totalRevenue,
                ],
            ]);
        } catch (\Exception $e) {
            // Log the error for debugging purposes
            Log::error('Error in adminDashboard: ' . $e->getMessage());

            // Return a generic error response
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while retrieving dashboard data.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }
}
