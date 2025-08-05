<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Project;
use App\Models\Technology;
use Illuminate\Support\Facades\Validator;

class ProjectController extends Controller
{
    //
    // CREATE PROJECT
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'company_id' => 'required|exists:companies,id',
            'title' => 'required|string',
            'description' => 'nullable|string',
            'status' => 'nullable|boolean',
            'technologies' => 'array',
            'technologies.*' => 'string',
            'image' => 'nullable|image'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'status' => 422, 'errors' => $validator->errors()], 422);
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $filename = time() . '_' . $image->getClientOriginalName();
            $image->move(public_path('project'), $filename);
            $imagePath = 'project/' . $filename;
        }

        $project = Project::create([
            'company_id' => $request->company_id,
            'title' => $request->title,
            'description' => $request->description,
            'image' => $imagePath,
            'status' => $request->status ?? 1,
        ]);

        // Create or attach technologies
        $techIds = [];
        foreach ($request->technologies as $techName) {
            $tech = Technology::firstOrCreate(['name' => $techName]);
            $techIds[] = $tech->id;
        }

        $project->technologies()->sync($techIds);

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Project created successfully.',
            'data' => $project->load('technologies')
        ]);
    }

    // SHOW ALL PROJECTS
    public function index()
    {
        $projects = Project::with('technologies')->get()->map(function ($p) {
            $p->image = $p->image ? url('public/' . $p->image) : null;
            return $p;
        });

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'All projects fetched.',
            'data' => $projects
        ]);
    }

    // UPDATE PROJECT
    public function update(Request $request, $id)
    {
        $project = Project::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => 'nullable|string',
            'description' => 'nullable|string',
            'status' => 'nullable|boolean',
            'technologies' => 'array',
            'technologies.*' => 'string',
            'image' => 'nullable|image'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'status' => 422, 'errors' => $validator->errors()], 422);
        }

        if ($request->hasFile('image')) {
            // Delete old image
            if ($project->image && file_exists(public_path($project->image))) {
                unlink(public_path($project->image));
            }

            // Save new image
            $image = $request->file('image');
            $filename = time() . '_' . $image->getClientOriginalName();
            $image->move(public_path('project'), $filename);
            $project->image = 'project/' . $filename;
        }

        $project->update($request->only(['title', 'description', 'status']));

        if ($request->has('technologies')) {
            $techIds = [];
            foreach ($request->technologies as $techName) {
                $tech = Technology::firstOrCreate(['name' => $techName]);
                $techIds[] = $tech->id;
            }
            $project->technologies()->sync($techIds);
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

        // Delete image if exists
        if ($project->image && file_exists(public_path($project->image))) {
            unlink(public_path($project->image));
        }

        $project->technologies()->detach();
        $project->delete();

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Application and image deleted successfully.',
            'data' => null
        ]);
    }
}
