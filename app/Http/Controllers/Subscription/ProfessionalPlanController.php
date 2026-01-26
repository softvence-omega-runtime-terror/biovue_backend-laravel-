<?php

namespace App\Http\Controllers\Subscription;

use App\Http\Controllers\Controller;
use App\Models\ProfessionalPlan;
use Illuminate\Http\Request;

class ProfessionalPlanController extends Controller
{
    public function index()
    {
        $plans = ProfessionalPlan::latest()->get();
        return response()->json($plans);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:professional_plans,name',
            'billing_cycle' => 'required|in:days,monthly,annual,custom',
            'price' => 'nullable|numeric',
            'duration' => 'nullable|integer',
            'member_limit' => 'nullable|integer',
            'features' => 'nullable|array',
            'status' => 'required|boolean',
        ]);

        $plan = ProfessionalPlan::create([
            'name' => $request->name,
            'billing_cycle' => $request->billing_cycle,
            'price' => $request->price,
            'duration' => $request->duration,
            'member_limit' => $request->member_limit,
            'features' => json_encode($request->features),
            'status' => $request->status,
        ]);

        return response()->json($plan, 201);
    }

    public function show($id)
    {
        $plan = ProfessionalPlan::findOrFail($id);
        return response()->json($plan);
    }

    public function update(Request $request, $id)
    {
        $plan = ProfessionalPlan::findOrFail($id);

        $request->validate([
            'name' => 'required|string|unique:professional_plans,name,' . $plan->id,
            'billing_cycle' => 'required|in:days,monthly,annual,custom',
            'price' => 'nullable|numeric',
            'duration' => 'nullable|integer',
            'member_limit' => 'nullable|integer',
            'features' => 'nullable|array',
            'status' => 'required|boolean',
        ]);

        $plan->update([
            'name' => $request->name,
            'billing_cycle' => $request->billing_cycle,
            'price' => $request->price,
            'duration' => $request->duration,
            'member_limit' => $request->member_limit,
            'features' => json_encode($request->features),
            'status' => $request->status,
        ]);

        return response()->json($plan);
    }

    public function destroy($id)
    {
        $plan = ProfessionalPlan::findOrFail($id);
        $plan->delete();
        return response()->json(['message' => 'Professional Plan deleted successfully']);
    }
}
