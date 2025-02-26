<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Supplier;

class SupplierController extends Controller
{
    public function store(Request $request)
    {
        try {
            // Validate the request data
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'phone' => 'required|string|max:20',
                'phone2' => 'nullable|string|max:20',
                'address' => 'required|string',
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

            // Create the supplier
            $supplier = Supplier::create($request->all());

            // Return success response
            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => 'Supplier created successfully.',
                'data' => $supplier,
                'errors' => null,
            ], 201);
        } catch (\Exception $e) {
            // Handle any exceptions
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while creating the supplier.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // shwo all supplier
    public function index(Request $request)
    {
        try {
            // Get 'limit' and 'page' from request
            $perPage = $request->input('limit');
            $currentPage = $request->input('page');

            // Base query to fetch suppliers with descending order by 'created_at'
            $query = Supplier::query()->orderBy('created_at', 'desc');

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
                $suppliers = $query->paginate($perPage, ['*'], 'page', $currentPage);

                // Return response with pagination data
                return response()->json([
                    'success' => true,
                    'status' => 200,
                    'message' => 'Suppliers retrieved successfully.',
                    'data' => $suppliers->items(),
                    'pagination' => [
                        'total_rows' => $suppliers->total(),
                        'current_page' => $suppliers->currentPage(),
                        'per_page' => $suppliers->perPage(),
                        'total_pages' => $suppliers->lastPage(),
                        'has_more_pages' => $suppliers->hasMorePages(),
                    ]
                ], 200);
            }

            // If no pagination parameters, fetch all records without pagination
            $suppliers = $query->get();

            // Return response without pagination links
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Suppliers retrieved successfully.',
                'data' => $suppliers
            ], 200);
        } catch (\Exception $e) {
            // Handle any exceptions
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while retrieving suppliers.',
                'data' => null,
                'errors' => $e->getMessage()
            ], 500);
        }
    }

    // shwo single supplier
    public function show($id)
    {
        try {
            // Find the supplier by ID
            $supplier = Supplier::find($id);

            // Check if the supplier exists
            if (!$supplier) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Supplier not found.',
                    'data' => null,
                    'errors' => 'Invalid supplier ID.',
                ], 404);
            }

            // Return success response
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Supplier retrieved successfully.',
                'data' => $supplier,
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            // Handle any exceptions
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while retrieving the supplier.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // Update the supplier
    public function update(Request $request, $id)
    {
        try {
            // Validate the request data
            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255',
                'phone' => 'sometimes|string|max:20',
                'phone2' => 'nullable|string|max:20',
                'address' => 'nullable|string',
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

            // Find the supplier by ID
            $supplier = Supplier::find($id);

            // Check if the supplier exists
            if (!$supplier) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Supplier not found.',
                    'data' => null,
                    'errors' => 'Invalid supplier ID.',
                ], 404);
            }

            // Update the supplier with validated data
            $supplier->update($request->all());

            // Return success response
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Supplier updated successfully.',
                'data' => $supplier,
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            // Handle any exceptions
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while updating the supplier.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // Delete the supplier
    public function delete($id)
    {
        try {
            // Find the supplier by ID
            $supplier = Supplier::find($id);

            // Check if the supplier exists
            if (!$supplier) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Supplier not found.',
                    'data' => null,
                    'errors' => 'Invalid supplier ID.',
                ], 404);
            }

            // Delete the supplier
            $supplier->delete();

            // Return success response
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Supplier deleted successfully.',
                'data' => null,
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            // Handle any exceptions
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while deleting the supplier.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }
}
