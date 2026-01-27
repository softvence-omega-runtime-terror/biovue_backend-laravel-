<?php

namespace App\Http\Controllers\Subscription;

use App\Http\Controllers\Controller;
use App\Models\ProfessionalPlan;
use Illuminate\Http\Request;

class ProfessionalPlanController extends Controller
{
   

// List all plans of auth user
   public function index()
{
    try {
        $plans = ProfessionalPlan::orderBy('id', 'asc')->get();

        return response()->json([
            'success' => true,
            'data' => $plans
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ], 500);
    }
}

    // Create or Update (one route)
    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string',
                'billing_cycle' => 'required|in:days,monthly,annual,custom',
                'price' => 'nullable|numeric',
                'duration' => 'nullable|integer',
                'member_limit' => 'nullable|integer',
                'features' => 'nullable|array',
                'status' => 'required|boolean',
            ]);

            $plan = ProfessionalPlan::updateOrCreate(
                [
                    'user_id' => auth()->id(),
                    'name' => $request->name,
                ],
                [
                    'billing_cycle' => $request->billing_cycle,
                    'price' => $request->price,
                    'duration' => $request->duration,
                    'member_limit' => $request->member_limit,
                    'features' => $request->features,
                    'status' => $request->status,
                ]
            );

            return response()->json([
                'success' => true,
                'data' => $plan
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // Delete plan
    public function destroy($id)
    {
        try {
            $plan = auth()->user()->professionalPlans()->findOrFail($id);
            $plan->delete();

            return response()->json([
                'success' => true,
                'message' => 'Professional Plan deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
