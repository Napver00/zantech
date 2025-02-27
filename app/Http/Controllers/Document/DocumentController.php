<?php

namespace App\Http\Controllers\Document;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Document;

class DocumentController extends Controller
{
    //shwo about
    public function showAbout()
    {
        try {
            // Fetch documents where type is 'about'
            $aboutDocuments = Document::where('type', 'about')->get();

            // Return the response in the specified format
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'About documents retrieved successfully.',
                'data' => $aboutDocuments,
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            // Handle errors and return a consistent error response
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to retrieve about documents.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // show tram and conditions
    public function showTrueCondition()
    {
        try {
            $Documents = Document::where('type', 'terms&conditions')->get();

            // Return the response in the specified format
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'About documents retrieved successfully.',
                'data' => $Documents,
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            // Handle errors and return a consistent error response
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to retrieve about documents.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // show privacy policy
    public function showPrivacyPolicy()
    {
        try {
            $Documents = Document::where('type', 'privacy&policy')->get();

            // Return the response in the specified format
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'About documents retrieved successfully.',
                'data' => $Documents,
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            // Handle errors and return a consistent error response
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to retrieve about documents.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // show return policy
    public function showReturnPolicy()
    {
        try {
            $Documents = Document::where('type', 'return&policy')->get();

            // Return the response in the specified format
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'About documents retrieved successfully.',
                'data' => $Documents,
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            // Handle errors and return a consistent error response
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to retrieve about documents.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // update all documents
    public function update(Request $request, $id)
    {
        try {
            // Validate the request
            $request->validate([
                'text' => 'required|string', 
            ]);

            // Find the document by ID
            $document = Document::find($id);

            // If the document doesn't exist, return a 404 response
            if (!$document) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Document not found.',
                    'data' => null,
                    'errors' => null,
                ], 404);
            }

            // Update the text field
            $document->text = $request->text;
            $document->save();

            // Return the response in the specified format
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Document updated successfully.',
                'data' => $document,
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            // Handle errors and return a consistent error response
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to update document.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }
}
