<?php

namespace App\Http\Controllers\File;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Item;
use App\Models\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    // Add image in product
    public function addImagesProduct(Request $request, $product_id)
    {
        try {
            // Validate the request data
            $validator = Validator::make($request->all(), [
                'images' => 'required|array',
                'images.*' => 'image|mimes:jpeg,png,jpg,gif,svg|max:4048',
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

            // Find the product
            $product = Item::find($product_id);

            // Check if the product exists
            if (!$product) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Product not found.',
                    'data' => null,
                    'errors' => 'Invalid product ID.',
                ], 404);
            }

            // Save images
            if ($request->has('images')) {
                foreach ($request->file('images') as $image) {
                    // Store the image in the storage folder
                    $path = $image->store('public/product_image');

                    // Save the image path in the File table
                    File::create([
                        'relatable_id' => $product_id,
                        'type' => 'product',
                        'path' => $path,
                    ]);
                }
            }

            // Return success response
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Images added to the product successfully.',
                'data' => null,
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            // Handle any exceptions
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while adding images to the product.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // Remove images from the product
    public function removePeoductImage(Request $request, $product_id)
    {
        try {
            // Validate the request data
            $validator = Validator::make($request->all(), [
                'image_id' => 'required|exists:files,id',
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

            // Find the product
            $product = Item::find($product_id);

            // Check if the product exists
            if (!$product) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Product not found.',
                    'data' => null,
                    'errors' => 'Invalid product ID.',
                ], 404);
            }

            // Find the image
            $image = File::where('id', $request->image_id)
                ->where('relatable_id', $product_id)
                ->where('type', 'product')
                ->first();

            // Check if the image exists
            if (!$image) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Image not found.',
                    'data' => null,
                    'errors' => 'Invalid image ID or image does not belong to the product.',
                ], 404);
            }

            // Delete the image file from storage
            if (Storage::exists($image->path)) {
                Storage::delete($image->path);
            }

            // Delete the image record from the files table
            $image->delete();

            // Return success response
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Image removed from the product successfully.',
                'data' => null,
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            // Handle any exceptions
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while removing the image from the product.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }
}
