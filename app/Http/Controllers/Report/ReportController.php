<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Expense;

class ReportController extends Controller
{
    //Monthly total of expense
    public function getExpenseMonthly()
    {
        try {
            // Query to group expenses by month and calculate the total amount for each month
            $monthlyTotals = Expense::select(
                DB::raw('YEAR(date) as year'),
                DB::raw('MONTH(date) as month'),
                DB::raw('SUM(amount) as total_amount')
            )
                ->groupBy('year', 'month')
                ->orderBy('year', 'desc')
                ->orderBy('month', 'desc')
                ->get();

            // Format the response
            $formattedResults = $monthlyTotals->map(function ($item) {
                return [
                    'year' => $item->year,
                    'month' => $item->month,
                    'total_amount' => $item->total_amount,
                ];
            });

            // Return the response as JSON
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Monthly total expenses retrieved successfully.',
                'data' => $formattedResults,
            ]);
        } catch (\Exception $e) {
            // Log the error for debugging purposes
            Log::error('Error in getMonthlyTotal: ' . $e->getMessage());

            // Return a generic error response
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while retrieving monthly total expenses.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }
}
