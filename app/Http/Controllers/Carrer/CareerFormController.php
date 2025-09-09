<?php

namespace App\Http\Controllers\Carrer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CareerForms;
use App\Models\Career;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Exception;

class CareerFormController extends Controller
{
    // Create a career form (upload CV)
    public function store(Request $request, $career_id)
    {
        try {
            // Check if career exists
            $career = Career::find($career_id);
            if (!$career) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Career not found.',
                    'data' => null,
                    'errors' => null,
                ], 404);
            }

            // Validate the request data
            $validator = Validator::make($request->all(), [
                'name'         => 'required|string|max:255',
                'email'        => 'required|email|max:255|unique:career_forms,email,NULL,id,career_id,' . $career_id,
                'phone'        => 'required|string|max:20',
                'cover_letter' => 'nullable|string',
                'cv'           => 'required|file|mimes:pdf|max:8048',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'status' => 400,
                    'message' => 'Validation errors',
                    'errors' => $validator->errors(),
                ], 400);
            }

            // Handle multiple file uploads by looping
            $file = $request->file('cv');
            $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $extension = $file->getClientOriginalExtension();
            $filename = Str::slug($originalName, '_') . '_zantech_' . time() . '.' . $extension;
            $file->move(public_path('cv'), $filename);
            $cvPath = 'cv/' . $filename;


            // Create the CareerForms record
            $careerForm = CareerForms::create([
                'career_id'    => $career->id,
                'name'         => $request->name,
                'email'        => $request->email,
                'phone'        => $request->phone,
                'cover_letter' => $request->cover_letter,
                'cv'           => $cvPath,
            ]);


            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => 'Career form submitted successfully.',
                'data' => $careerForm,
                'errors' => null,
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

    // Get all submissions for a specific career
    public function index($career_id)
    {
        try {
            $career = Career::find($career_id);
            if (!$career) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Career not found.',
                    'data' => null,
                    'errors' => null,
                ], 404);
            }

            $forms = $career->forms()->get()->map(function ($form) use ($career) {
                return [
                    'id' => $form->id,
                    'career_id' => $form->career_id,
                    'career_title' => $career->job_title,
                    'name' => $form->name,
                    'email' => $form->email,
                    'phone' => $form->phone,
                    'created_at' => $form->created_at,
                ];
            });

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Career submissions retrieved successfully.',
                'data' => $forms,
                'errors' => null,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Something went wrong.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // Show single submission under a career
    public function show($career_id, $form_id)
    {
        try {
            // Check if career exists
            $career = Career::find($career_id);
            if (!$career) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Career not found.',
                    'data' => null,
                    'errors' => null,
                ], 404);
            }

            // Find the form under this career
            $form = $career->forms()->where('id', $form_id)->first();

            if (!$form) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Submission not found for this career.',
                    'data' => null,
                    'errors' => null,
                ], 404);
            }

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Submission retrieved successfully.',
                'data' => $form,
                'errors' => null,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Something went wrong.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }
}
