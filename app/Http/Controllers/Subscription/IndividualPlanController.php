<?php

namespace App\Http\Controllers\Subscription;

use App\Http\Controllers\Controller;
use App\Models\IndividualPlan;
use Illuminate\Http\Request;

class IndividualPlanController extends Controller
{
    public function index()
    {
        $plans = IndividualPlan::latest()->get();
        return response()->json($plans);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:individual_plans,name',
            'billing_cycle' => 'required|in:days,monthly,annual,custom',
            'price' => 'nullable|numeric',
            'duration' => 'nullable|integer',
            'user_limit' => 'nullable|integer',
            'features' => 'nullable|array',
            'status' => 'required|boolean',
        ]);

        $plan = IndividualPlan::create([
            'name' => $request->name,
            'billing_cycle' => $request->billing_cycle,
            'price' => $request->price,
            'duration' => $request->duration,
            'user_limit' => $request->user_limit,
            'features' => json_encode($request->features),
            'status' => $request->status,
        ]);

        return response()->json($plan, 201);
    }

    public function show($id)
    {
        $plan = IndividualPlan::findOrFail($id);
        return response()->json($plan);
    }

    public function update(Request $request, $id)
    {
        $plan = IndividualPlan::findOrFail($id);

        $request->validate([
            'name' => 'required|string|unique:individual_plans,name,' . $plan->id,
            'billing_cycle' => 'required|in:days,monthly,annual,custom',
            'price' => 'nullable|numeric',
            'duration' => 'nullable|integer',
            'user_limit' => 'nullable|integer',
            'features' => 'nullable|array',
            'status' => 'required|boolean',
        ]);

        $plan->update([
            'name' => $request->name,
            'billing_cycle' => $request->billing_cycle,
            'price' => $request->price,
            'duration' => $request->duration,
            'user_limit' => $request->user_limit,
            'features' => json_encode($request->features),
            'status' => $request->status,
        ]);

        return response()->json($plan);
    }

    public function destroy($id)
    {
        $plan = IndividualPlan::findOrFail($id);
        $plan->delete();
        return response()->json(['message' => 'Individual Plan deleted successfully']);
    }
}
