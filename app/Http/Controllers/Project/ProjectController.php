<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Project;
use App\Models\Technology;
use Illuminate\Support\Facades\Validator;

class ProjectController extends Controller
{

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string',
            'description' => 'nullable|string',
            'image' => 'nullable|image',
            'technologies' => 'required|array',
            'technologies.*' => 'required|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'status' => 422,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $filename = time() . '_' . $image->getClientOriginalName();
            $image->move(public_path('project'), $filename);
            $imagePath = 'project/' . $filename;
        }

        $app = Project::create([
            'title'    => $request->title,
            'description'   => $request->description,
            'image'   => $imagePath,
        ]);

        // Add technologies
        foreach ($request->technologies as $techName) {
            Technology::create([
                'name' => $techName,
                'project_id' => $app->id
            ]);
        }

        return response()->json([
            'success' => true,
            'status' => 201,
            'message' => 'Project created successfully.',
            'data' => $app
        ], 201);
    }

    // update project
    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'title' => 'nullable|string',
                'description' => 'nullable|string',
                'status' => 'nullable|string',
                'image' => 'nullable|image',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'status' => 422,
                    'message' => 'Validation failed.',
                    'data' => null,
                    'errors' => $validator->errors(),
                ], 422);
            }

            $Project = Project::findOrFail($id);

            if (!$Project) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Project not found.',
                    'data' => null,
                    'errors' => 'Invalid Project ID.',
                ], 404);
            }

            // Handle image update
            if ($request->hasFile('image')) {
                // Delete old image from storage if exists
                if ($Project->image && file_exists(public_path($Project->image))) {
                    unlink(public_path($Project->image));
                }

                // Upload new image
                $image = $request->file('image');
                $filename = time() . '_' . $image->getClientOriginalName();
                $image->move(public_path('project'), $filename);
                $Project->image = 'project/' . $filename;
            }

            // Update other fields
            $Project->update($request->except('image'));

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Project updated.',
                'data' => $Project
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while updating the Project.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }



    // GET ALL PROJECTS
    public function index()
    {
        $projects = Project::with('technologies')->get();

        // Attach full image URL
        foreach ($projects as $app) {
            $app->image_url = $app->image ? url('public/' . $app->image) : null;
        }

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Project list fetched.',
            'data' => $projects
        ]);
    }

    // get all active projects
    public function getallactiveproject()
    {
        $projects = Project::with('technologies')
            ->where('status', 'active')
            ->get();

        // Attach full image URL
        foreach ($projects as $app) {
            $app->image_url = $app->image ? url('public/' . $app->image) : null;
        }

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Active project list fetched.',
            'data' => $projects
        ]);
    }



    // DELETE PROJECT
    public function destroy($id)
    {
        $project = Project::findOrFail($id);

        if ($project->image) {
            $fullPath = public_path($project->image);
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
        }

        // Delete related tech
        Technology::where('project_id', $project->id)->delete();

        $project->delete();

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Project and image deleted successfully.',
            'data' => null
        ]);
    }


    // add new technologies in project
    public function addTechnologies(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'project_id' => 'required|exists:projects,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'status' => 422,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        Technology::create([
            'name' => $request->name,
            'project_id' => $request->project_id
        ]);

        return response()->json([
            'success' => true,
            'status' => 201,
            'message' => 'Technology added successfully.',
            'data' => ''
        ], 201);
    }

    // delete technologies from project
    public function deleteTechnologies($id)
    {
        $tech = Technology::find($id);

        if (!$tech) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Technology not found.'
            ], 404);
        }

        $tech->delete();

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Technology deleted successfully.'
        ]);
    }
}
