<?php

namespace App\Http\Controllers\Subscription;

use App\Http\Controllers\Controller;
use App\Models\IndividualPlan;
use Illuminate\Http\Request;

class IndividualPlanController extends Controller
{
    // List all plans of auth user
   public function index()
{
    try {
        $plans = IndividualPlan::orderBy('id', 'asc')->get();

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
                'features' => 'nullable|array',
                'status' => 'required|boolean',
            ]);

            $plan = IndividualPlan::updateOrCreate(
                [
                    'user_id' => auth()->id(),
                    'name' => $request->name,
                ],
                [
                    'billing_cycle' => $request->billing_cycle,
                    'price' => $request->price,
                    'duration' => $request->duration,
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
            $plan = auth()->user()->individualPlans()->findOrFail($id);
            $plan->delete();

            return response()->json([
                'success' => true,
                'message' => 'Individual Plan deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
