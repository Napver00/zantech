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
                'title' => 'required|string|max:255',
                'amount' => 'required|numeric|min:0',
                'prove.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:3048',
                'prove' => 'nullable|array',
                'description' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'status' => 400,
                    'message' => 'Validation errors',
                    'errors' => $validator->errors(),
                ], 400);
            }

            // Create the expense record
            $expense = Expense::create([
                'date' => $request->date,
                'user_id' => $request->user()->id,
                'title' => $request->title,
                'amount' => $request->amount,
                'description' => $request->description,
            ]);

            // Handle multiple file uploads by looping
            if ($request->hasFile('prove')) {
                // $request->file('prove') will be an array of files
                foreach ($request->file('prove') as $file) {
                    // Generate a unique filename to prevent overwrites
                    $filename = time() . '_' . uniqid() . '_' . $file->getClientOriginalName();

                    // Move the file to the public/expense directory
                    $file->move(public_path('expense'), $filename);

                    // Create the relative path to store in the database
                    $relativePath = 'expense/' . $filename;
                    $filePaths[] = $relativePath; // Add path to the array for the response

                    // Create a File record for each uploaded file
                    File::create([
                        'relatable_id' => $expense->id,
                        'type' => 'expense', // Or whatever your polymorphic relation type is
                        'path' => $relativePath,
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => 'Expense created successfully.',
                'data' => [
                    'id' => $expense->id,
                    'date' => $expense->date,
                    'user_id' => $expense->user_id,
                    'title' => $expense->title,
                    'amount' => $expense->amount,
                    'description' => $expense->description,
                ],
            ], 201);
        } catch (\Exception $e) {
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
            $perPage = $request->input('limit');
            $currentPage = $request->input('page');
            $exactDate = $request->input('date');
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');

            $query = Expense::with('proveFiles')->orderBy('created_at', 'desc');

            // Filter by exact date
            if ($exactDate) {
                $query->whereDate('date', $exactDate);
            }

            // Filter by date range
            if ($startDate && $endDate) {
                $query->whereBetween('date', [$startDate, $endDate]);
            }

            if ($perPage && $currentPage) {
                if (!is_numeric($perPage) || !is_numeric($currentPage) || $perPage <= 0 || $currentPage <= 0) {
                    return response()->json([
                        'success' => false,
                        'status' => 400,
                        'message' => 'Invalid pagination parameters.',
                        'data' => null,
                        'errors' => 'Invalid pagination parameters.',
                    ], 400);
                }

                $expenses = $query->paginate($perPage, ['*'], 'page', $currentPage);

                $formattedExpenses = $expenses->map(function ($expense) {
                    return [
                        'id' => $expense->id,
                        'date' => $expense->date,
                        'user_id' => $expense->user_id,
                        'title' => $expense->title,
                        'amount' => $expense->amount,
                        'description' => $expense->description,
                        'proves' => $expense->proveFiles->map(function ($proveFile) {
                            return [
                                'id' => $proveFile->id,
                                'type' => $proveFile->type,
                                'url' => url('public/' . $proveFile->path),
                            ];
                        }),
                    ];
                });

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

            // If no pagination
            $expenses = $query->get();

            $formattedExpenses = $expenses->map(function ($expense) {
                return [
                    'id' => $expense->id,
                    'date' => $expense->date,
                    'user_id' => $expense->user_id,
                    'title' => $expense->title,
                    'amount' => $expense->amount,
                    'description' => $expense->description,
                    'proves' => $expense->proveFiles->map(function ($proveFile) {
                        return [
                            'id' => $proveFile->id,
                            'type' => $proveFile->type,
                            'url' => asset('storage/expense/' . basename($proveFile->path)),
                        ];
                    }),
                ];
            });

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Expenses retrieved successfully.',
                'data' => $formattedExpenses,
            ], 200);
        } catch (\Exception $e) {
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
            // Add debugging to check if files are being received
            \Log::info('Update request files:', ['files' => $request->allFiles()]);
            \Log::info('Has prove files:', ['has_prove' => $request->hasFile('prove')]);

            // Validate the request data
            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|string|max:255',
                'description' => 'sometimes|string',
                'amount' => 'sometimes|numeric|min:0',
                'prove.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:3048',
                'prove' => 'nullable|array',
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

            if (!$expense) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Expense not found.',
                    'data' => null,
                    'errors' => 'Expense not found.',
                ], 404);
            }

            // Store old values for comparison
            $oldValues = $expense->getAttributes();

            // Update fields (remove user_id from update as it shouldn't change)
            $expense->update($request->only(['title', 'description', 'amount']));

            // Compare for change log
            $newValues = $expense->getAttributes();
            $changes = [];
            foreach ($request->only(['title', 'description', 'amount']) as $key => $value) {
                if (array_key_exists($key, $oldValues) && $oldValues[$key] != $newValues[$key]) {
                    $changes[] = "{$key} changed from '{$oldValues[$key]}' to '{$newValues[$key]}'";
                }
            }

            // Initialize file paths array for response
            $filePaths = [];

            // Upload and save multiple prove files if provided
            if ($request->hasFile('prove')) {
                \Log::info('Processing prove files...');

                // Ensure the expense directory exists
                $expenseDir = public_path('expense');
                if (!file_exists($expenseDir)) {
                    mkdir($expenseDir, 0755, true);
                }

                // Handle both single file and array of files
                $files = $request->file('prove');
                if (!is_array($files)) {
                    $files = [$files];
                }

                foreach ($files as $file) {
                    if ($file->isValid()) {
                        // Generate a unique filename to prevent overwrites
                        $filename = time() . '_' . uniqid() . '_' . $file->getClientOriginalName();

                        // Move the file to the public/expense directory
                        $moved = $file->move($expenseDir, $filename);

                        if ($moved) {
                            // Create the relative path to store in the database
                            $relativePath = 'expense/' . $filename;
                            $filePaths[] = $relativePath;

                            // Create a File record for each uploaded file
                            $fileRecord = File::create([
                                'relatable_id' => $expense->id,
                                'type' => 'expense',
                                'path' => $relativePath,
                            ]);

                            \Log::info('File uploaded successfully:', [
                                'filename' => $filename,
                                'path' => $relativePath,
                                'file_record_id' => $fileRecord->id
                            ]);

                            // Add file upload to changes log
                            $changes[] = "File uploaded: {$filename}";
                        } else {
                            \Log::error('Failed to move file:', ['filename' => $file->getClientOriginalName()]);
                        }
                    } else {
                        \Log::error('Invalid file:', ['error' => $file->getError()]);
                    }
                }
            } else {
                \Log::info('No prove files in request');
            }

            // Log changes
            if (!empty($changes)) {
                Activity::create([
                    'relatable_id' => $expense->id,
                    'type' => 'expense',
                    'user_id' => $request->user()->id,
                    'description' => 'Expense updated: ' . implode(', ', $changes),
                ]);
            }

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Expense updated successfully.',
                'data' => $expense->load('proveFiles'),
                'uploaded_files' => $filePaths, // Include uploaded file paths in response
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Update expense error:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while updating the expense.',
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // single expense
    public function show($id)
    {
        try {
            $expense = Expense::with('proveFiles')->find($id);

            if (!$expense) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Expense not found.',
                    'data' => null,
                    'errors' => 'Expense not found.',
                ], 404);
            }

            $formattedExpense = [
                'id' => $expense->id,
                'date' => $expense->date,
                'user_id' => $expense->user_id,
                'title' => $expense->title,
                'amount' => $expense->amount,
                'description' => $expense->description,
                'proves' => $expense->proveFiles->map(function ($proveFile) {
                    return [
                        'id' => $proveFile->id,
                        'type' => $proveFile->type,
                        'url' => url('public/' . $proveFile->path),
                    ];
                }),
            ];

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Expense retrieved successfully.',
                'data' => $formattedExpense,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while retrieving the expense.',
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // delete the expense proves
    public function destroyProve($id)
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

    // delete the expense
    public function destroy($id)
    {
        try {
            // Find the expense
            $expense = Expense::find($id);

            if (!$expense) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Expense not found.',
                    'data' => null,
                ], 404);
            }

            // Get related files
            $files = File::where('relatable_id', $expense->id)->where('type', 'expense')->get();

            // Delete each file from storage and database
            foreach ($files as $file) {
                // Delete from storage
                if (Storage::exists($file->path)) {
                    Storage::delete($file->path);
                }

                // Delete file record
                $file->delete();
            }

            // Delete the expense
            $expense->delete();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Expense and associated files deleted successfully.',
                'data' => null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while deleting the expense.',
                'errors' => $e->getMessage(),
            ], 500);
        }
    }
}
