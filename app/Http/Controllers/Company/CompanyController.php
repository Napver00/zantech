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
        \Log::info('Request Data:', $request->all());

        $company = Company::find($id);

        if (!$company) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Company not found'
            ], 404);
        }

        $company->update($request->only([
            'name',
            'hero_title',
            'hero_subtitle',
            'hero_description',
            'about_title',
            'about_description1',
            'about_description2',
            'email',
            'phone',
            'location',
            'footer_text'
        ]));

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Company updated.',
            'data' => $company
        ]);
    }
}
