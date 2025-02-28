<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Transition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    //update payment status
    public function updatePaymentStatus(Request $request, $paymentId)
    {
        DB::beginTransaction();
        try {
            // Validate the request
            $request->validate([
                'status' => 'required|integer|in:0,1,3,4',
            ]);

            // Find the payment
            $payment = Payment::find($paymentId);
            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Payment not found.',
                    'data' => null,
                    'errors' => 'No query results for model [App\Models\Payment] ' . $paymentId,
                ], 404);
            }

            // Update the payment status
            $payment->status = $request->input('status');
            $payment->save();

            // If status is 1 and 3, store data in the Transition table
            if ($payment->status == 1) {
                Transition::create([
                    'payment_id' => $payment->id,
                    'amount' => $payment->amount,
                ]);
            } else if ($payment->status == 3) {
                Transition::create([
                    'payment_id' => $payment->id,
                    'amount' => $payment->padi_amount,
                ]);
            }

            // Commit the transaction
            DB::commit();

            // Return success response
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Payment status updated successfully.',
                'data' => [
                    'payment_id' => $payment->id,
                    'new_status' => $payment->status,
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
                'message' => 'Failed to update payment status.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // update padi amount
    public function updatePadiAmount(Request $request, $paymentId)
    {
        DB::beginTransaction();
        try {
            // Validate the request
            $request->validate([
                'padi_amount' => 'required|numeric|min:0',
            ]);

            // Find the payment
            $payment = Payment::find($paymentId);
            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Payment not found.',
                    'data' => null,
                    'errors' => 'No query results for model [App\Models\Payment] ' . $paymentId,
                ], 404);
            }

            // Ensure padi_amount is not greater than amount
            if ($request->input('padi_amount') > $payment->amount) {
                return response()->json([
                    'success' => false,
                    'status' => 400,
                    'message' => 'padi_amount cannot be greater than the total amount.',
                    'data' => null,
                    'errors' => 'padi_amount exceeds the total amount.',
                ], 400);
            }

            // Update the padi_amount
            $payment->padi_amount = $request->input('padi_amount');
            $payment->save();

            // Commit the transaction
            DB::commit();

            // Return success response
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'padi_amount updated successfully.',
                'data' => [
                    'payment_id' => $payment->id,
                    'new_padi_amount' => $payment->padi_amount,
                    'total_amount' => $payment->amount,
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
                'message' => 'Failed to update padi_amount.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }
}
