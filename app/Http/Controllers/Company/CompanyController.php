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
}
