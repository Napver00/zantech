<?php

namespace App\Http\Controllers\HeroSection;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\File;
use Illuminate\Support\Facades\Storage;

class HeroSectionController extends Controller
{
    //stor here image
    public function store(Request $request)
    {
        try {
            // Validate the request
            $request->validate([
                'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            ]);

            $imagePath = $request->file('image')->store('public/herosection');

            // Save the file path in the files table
            $file = File::create([
                'relatable_id' => 0,
                'type' => 'hero',
                'path' => str_replace('public/', '', $imagePath),
            ]);

            // Return the response in the specified format
            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => 'Hero image uploaded successfully.',
                'data' => $file,
                'errors' => null,
            ], 201);
        } catch (\Exception $e) {
            // Handle errors and return a consistent error response
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to upload hero image.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // shwo all hero images
    public function index()
    {
        try {
            // Fetch all files where type is 'hero'
            $heroImages = File::where('type', 'hero')->get();

            $heroImages->transform(function ($image) {
                $image->path = asset('storage/' . $image->path);
                return $image;
            });

            // Return the response in the specified format
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Hero images retrieved successfully.',
                'data' => $heroImages,
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            // Handle errors and return a consistent error response
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to retrieve hero images.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // delete hero image
    public function destroy($id)
    {
        try {
            // Find the image by ID
            $image = File::where('type', 'hero')->find($id);

            // If the image doesn't exist, return a 404 response
            if (!$image) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Hero image not found.',
                    'data' => null,
                    'errors' => null,
                ], 404);
            }

            // Delete the image from storage
            Storage::delete('public/' . $image->path);

            // Delete the image from the database
            $image->delete();

            // Return the response in the specified format
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Hero image deleted successfully.',
                'data' => null,
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            // Handle errors and return a consistent error response
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to delete hero image.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }
}
