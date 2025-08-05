<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Company;

class CompanyController extends Controller
{
    //
    // Create
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'hero_title' => 'nullable|string',
            'hero_subtitle' => 'nullable|string',
            'hero_description' => 'nullable|string',
            'about_title' => 'nullable|string',
            'about_description1' => 'nullable|string',
            'about_description2' => 'nullable|string',
            'email' => 'nullable|email',
            'phone' => 'nullable|string',
            'location' => 'nullable|string',
            'footer_text' => 'nullable|string'
        ]);

        $company = Company::create($request->all());

        return response()->json([
            'success' => true,
            'status' => 201,
            'message' => 'Company created.',
            'data' => $company
        ]);
    }

    // Show (first one for now)
    public function show()
    {
        $company = Company::with('socialLinks')->first();

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Company info retrieved.',
            'data' => $company
        ]);
    }

    // Update
    public function update(Request $request, $id)
    {
        $company = Company::findOrFail($id);

        $company->update($request->all());

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Company updated.',
            'data' => $company
        ]);
    }
}
