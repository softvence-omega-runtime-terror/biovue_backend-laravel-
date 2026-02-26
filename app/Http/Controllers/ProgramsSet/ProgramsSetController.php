<?php

namespace App\Http\Controllers\ProgramsSet;

use App\Http\Controllers\Controller;
use App\Models\ProgramSet;
use Illuminate\Http\Request;

class ProgramsSetController extends Controller
{
    /**
     * Display a listing of active program sets.
     */
    public function index()
    {
        try {
            $programs = ProgramSet::latest()->get();

            return response()->json([
                'status' => true,
                'data' => $programs
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch program sets. Error: '.$e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'duration' => 'nullable|integer',
            'primary_goal' => 'nullable|string|max:255',
            'target_intensity' => 'nullable|in:Light,Moderate,High',
            'habit_focus_areas' => 'nullable|array',
            'program_focus' => 'nullable|array',
            'focus_areas' => 'nullable|array',
            'habit_focus' => 'nullable|array',
            'calories' => 'nullable|integer',
            'protein' => 'nullable|integer',
            'carbs' => 'nullable|integer',
            'fat' => 'nullable|integer',
            'supplement_recommendation' => 'nullable|array',
            'supplement' => 'nullable|array',
        ]);

        try {
            $programSet = ProgramSet::create($request->all());

            return response()->json([
                'status' => true,
                'message' => 'Program Set created successfully.',
                'data' => $programSet
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to create Program Set. Error: '.$e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        try {
            $programSet = ProgramSet::findOrFail($id);

            return response()->json([
                'status' => true,
                'data' => $programSet
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Program Set not found. Error: '.$e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'nullable|string|max:255',
            'duration' => 'nullable|integer',
            'primary_goal' => 'nullable|string|max:255',
            'target_intensity' => 'nullable|in:Light,Moderate,High',
            'habit_focus_areas' => 'nullable|array',
            'program_focus' => 'nullable|array',
            'focus_areas' => 'nullable|array',
            'habit_focus' => 'nullable|array',
            'calories' => 'nullable|integer',
            'protein' => 'nullable|integer',
            'carbs' => 'nullable|integer',
            'fat' => 'nullable|integer',
            'supplement_recommendation' => 'nullable|array',
            'supplement' => 'nullable|array',
        ]);

        try {
            $programSet = ProgramSet::findOrFail($id);
            $programSet->update($request->all());

            return response()->json([
                'status' => true,
                'message' => 'Program Set updated successfully.',
                'data' => $programSet
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to update Program Set. Error: '.$e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage (soft delete).
     */
    public function destroy($id)
    {
        try {
            $programSet = ProgramSet::findOrFail($id);
            $programSet->delete();

            return response()->json([
                'status' => true,
                'message' => 'Program Set deleted successfully.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete Program Set. Error: '.$e->getMessage()
            ], 500);
        }
    }

    
}