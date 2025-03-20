<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ReportController extends Controller
{
    /**
     * @var ReportService
     */
    private ReportService $reportService;

    /**
     * Create a new controller instance.
     *
     * @param ReportService $reportService
     */
    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * Get monthly expense totals.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getExpenseMonthly(Request $request): JsonResponse
    {
        try {
            $monthlyTotals = $this->reportService->getMonthlyExpenseTotals();

            return $this->successResponse(
                'Monthly total expenses retrieved successfully.',
                $monthlyTotals
            );
        } catch (\Exception $e) {
            Log::error('Error in getExpenseMonthly: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);

            return $this->errorResponse(
                'An error occurred while retrieving monthly total expenses.',
                $e->getMessage()
            );
        }
    }

    /**
     * Get monthly transaction totals.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getMonthlyTransition(Request $request): JsonResponse
    {
        try {
            $monthlyTotals = $this->reportService->getMonthlyTransactionTotals();

            return $this->successResponse(
                'Monthly total transitions retrieved successfully.',
                $monthlyTotals
            );
        } catch (\Exception $e) {
            Log::error('Error in getMonthlyTransition: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);

            return $this->errorResponse(
                'An error occurred while retrieving monthly total transitions.',
                $e->getMessage()
            );
        }
    }

    /**
     * Return a success response.
     *
     * @param string $message
     * @param mixed $data
     * @param int $code
     * @return JsonResponse
     */
    private function successResponse(string $message, $data = null, int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'status' => $code,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    /**
     * Return an error response.
     *
     * @param string $message
     * @param string|null $error
     * @param int $code
     * @return JsonResponse
     */
    private function errorResponse(string $message, ?string $error = null, int $code = 500): JsonResponse
    {
        return response()->json([
            'success' => false,
            'status' => $code,
            'message' => $message,
            'data' => null,
            'errors' => $error,
        ], $code);
    }
}
