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

    // UPDATE PROJECT
    public function update(Request $request, $id)
    {
        $project = Project::findOrFail($id);

        $project->title = $request->title ?? $project->title;
        $project->description = $request->description ?? $project->description;
        $project->status = $request->status ?? $project->status;

        // Handle image update
        if ($request->hasFile('image')) {
            if ($project->image && file_exists(public_path($project->image))) {
                unlink(public_path($project->image));
            }

            $image = $request->file('image');
            $filename = time() . '_' . $image->getClientOriginalName();
            $image->move(public_path('project'), $filename);
            $project->image = 'project/' . $filename;
        }

        $project->save();

        // Update technologies (optional: delete old first)
        if ($request->has('technologies')) {
            Technology::where('project_id', $project->id)->delete();

            foreach ($request->technologies as $techName) {
                Technology::create([
                    'name' => $techName,
                    'project_id' => $project->id
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Project updated successfully.',
            'data' => $project->load('technologies')
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
}
