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
        $validator = Validator::make($request->all(), [
            'title' => 'nullable|string',
            'description' => 'nullable|string',
            'image' => 'nullable|image',
            'status' => 'nullable|string|in:active,inactive',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'status' => 422,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }
        $project = Project::findOrFail($id);

        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($project->image) {
                $fullPath = public_path($project->image);
                if (file_exists($fullPath)) {
                    unlink($fullPath);
                }
            }

            $image = $request->file('image');
            $filename = time() . '_' . $image->getClientOriginalName();
            $image->move(public_path('project'), $filename);
            $project->image = 'project/' . $filename;
        }
        // Update other fields
        $project->title = $request->input('title', $project->title);
        $project->description = $request->input('description', $project->description);
        $project->status = $request->input('status', $project->status);
        $project->save();

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Project updated successfully.',
            'data' => $project
        ]);
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

        $tech = Technology::create([
            'name' => $request->name,
            'project_id' => $request->project_id
        ]);

        return response()->json([
            'success' => true,
            'status' => 201,
            'message' => 'Technology added successfully.',
            'data' => $tech
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
