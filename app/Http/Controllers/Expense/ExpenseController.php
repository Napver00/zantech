<?php

namespace App\Http\Controllers\Expense;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\File;
use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class ExpenseController extends Controller
{
    //store the expense
    public function store(Request $request)
    {
        try {
            // Validate the request data
            $validator = Validator::make($request->all(), [
                'date' => 'required|date',
                'user_id' => 'required|integer|exists:users,id',
                'title' => 'required|string|max:255',
                'amount' => 'required|numeric|min:0',
                'prove' => 'required|file|mimes:jpg,jpeg,png,pdf|max:3048',
                'description' => 'nullable|string',
            ]);

            // If validation fails, return error response
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'status' => 400,
                    'message' => 'Validation errors',
                    'errors' => $validator->errors(),
                ], 400);
            }

            // Handle file upload
            if ($request->hasFile('prove')) {
                $file = $request->file('prove');
                $fileName = time() . '_' . $file->getClientOriginalName();
                $filePath = $file->storeAs('public/expense', $fileName);
            }

            // Create the expense record
            $expense = Expense::create([
                'date' => $request->date,
                'user_id' => $request->user_id,
                'title' => $request->title,
                'amount' => $request->amount,
                'description' => $request->description,
            ]);

            $fileRecord = File::create([
                'relatable_id' => $expense->id,
                'type' => 'expense',
                'path' => $filePath,
            ]);

            // Add file URL to the response
            $expense->prove_url = asset('storage/expense/' . $fileName);

            // Return success response
            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => 'Expense created successfully.',
                'data' =>  $expense,
            ], 201);
        } catch (\Exception $e) {
            // Handle any exceptions
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while creating the expense.',
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // shwo all expense
    public function index(Request $request)
    {
        try {
            // Get 'limit' and 'page' from the request
            $perPage = $request->input('limit');
            $currentPage = $request->input('page');

            // Base query to fetch expenses with their associated prove files
            $query = Expense::with(['proveFile' => function ($query) {
                $query->where('type', 'expense');
            }])->orderBy('created_at', 'desc');

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
                $expenses = $query->paginate($perPage, ['*'], 'page', $currentPage);

                // Format the response with pagination data
                $formattedExpenses = $expenses->map(function ($expense) {
                    return [
                        'id' => $expense->id,
                        'date' => $expense->date,
                        'user_id' => $expense->user_id,
                        'title' => $expense->title,
                        'amount' => $expense->amount,
                        'description' => $expense->description,
                        'prove' => $expense->proveFile ? [
                            'id' => $expense->proveFile->id,
                            'type' => $expense->proveFile->type,
                            'url' => asset('storage/expense/' . $expense->proveFile->path),
                        ] : null,
                    ];
                });

                // Return response with pagination data
                return response()->json([
                    'success' => true,
                    'status' => 200,
                    'message' => 'Expenses retrieved successfully.',
                    'data' => $formattedExpenses,
                    'pagination' => [
                        'total' => $expenses->total(),
                        'per_page' => $expenses->perPage(),
                        'current_page' => $expenses->currentPage(),
                        'last_page' => $expenses->lastPage(),
                        'from' => $expenses->firstItem(),
                        'to' => $expenses->lastItem(),
                    ],
                ], 200);
            }

            // If no pagination parameters, fetch all records without pagination
            $expenses = $query->get();

            // Format the response
            $formattedExpenses = $expenses->map(function ($expense) {
                return [
                    'id' => $expense->id,
                    'date' => $expense->date,
                    'user_id' => $expense->user_id,
                    'title' => $expense->title,
                    'amount' => $expense->amount,
                    'description' => $expense->description,
                    'prove' => $expense->proveFile ? [
                        'id' => $expense->proveFile->id,
                        'type' => $expense->proveFile->type,
                        'url' => asset('storage/expense/' . $expense->proveFile->path),
                    ] : null,
                ];
            });

            // Return response without pagination links
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Expenses retrieved successfully.',
                'data' => $formattedExpenses,
            ], 200);
        } catch (\Exception $e) {
            // Handle any exceptions
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while retrieving expenses.',
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // update the expense
    public function update(Request $request, $id)
    {
        try {
            // Validate the request data
            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|string|max:255',
                'description' => 'sometimes|string',
                'amount' => 'sometimes|numeric|min:0',
                'user_id' => 'required|integer|exists:users,id',
            ]);

            // If validation fails, return error response
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'status' => 400,
                    'message' => 'Validation errors',
                    'errors' => $validator->errors(),
                ], 400);
            }

            // Find the expense by ID
            $expense = Expense::find($id);

            // If expense not found, return error response
            if (!$expense) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Expense not found.',
                    'data' => null,
                    'errors' => 'Expense not found.',
                ], 404);
            }

            // Store the old values for comparison
            $oldValues = $expense->getAttributes();

            // Update the expense with new values
            $expense->update($request->only(['title', 'description', 'amount', 'user_id']));

            // Store the new values for comparison
            $newValues = $expense->getAttributes();

            // Generate a description of changes
            $changes = [];
            foreach ($request->all() as $key => $value) {
                if (array_key_exists($key, $oldValues) && $oldValues[$key] != $newValues[$key]) {
                    $changes[] = "{$key} changed from '{$oldValues[$key]}' to '{$newValues[$key]}'";
                }
            }

            // Log the changes in the Activity table
            if (!empty($changes)) {
                Activity::create([
                    'relatable_id' => $expense->id,
                    'type' => 'expense',
                    'user_id' => $request->user_id ?? $expense->user_id,
                    'description' => 'Expense updated: ' . implode(', ', $changes),
                ]);
            }

            // Return success response
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Expense updated successfully.',
                'data' => $expense,
            ], 200);
        } catch (\Exception $e) {
            // Handle any exceptions
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while updating the expense.',
                'errors' => $e->getMessage(),
            ], 500);
        }
    }
}
