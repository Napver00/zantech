<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\SocialLink;

class CompanyController extends Controller
{
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

    // Create
    public function store(Request $request)
    {
        $request->validate([
            'company_id' => 'required|exists:companies,id',
            'platform' => 'required|string',
            'url' => 'required|url',
        ]);

        $social = SocialLink::create($request->only('company_id', 'platform', 'url'));

        return response()->json([
            'success' => true,
            'status' => 201,
            'message' => 'Social link added.',
            'data' => $social
        ]);
    }

    //  Show All for a Company
    public function index($company_id)
    {
        $links = SocialLink::where('company_id', $company_id)->get();

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Social links fetched.',
            'data' => $links
        ]);
    }

    //  Update
    public function updatesocial(Request $request, $id)
    {
        $social = SocialLink::findOrFail($id);

        $request->validate([
            'platform' => 'sometimes|string',
            'url' => 'sometimes|url',
        ]);

        $social->update($request->only('platform', 'url'));

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Social link updated.',
            'data' => $social
        ]);
    }

    // Delete
    public function destroy($id)
    {
        $social = SocialLink::findOrFail($id);
        $social->delete();

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Social link deleted.'
        ]);
    }
}
