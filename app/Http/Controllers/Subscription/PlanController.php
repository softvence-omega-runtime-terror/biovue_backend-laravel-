<?php

namespace App\Http\Controllers\Subscription;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PlanController extends Controller
{
    public function index()
    {
        $plans = Plan::latest()->get();
        return response()->json([
            'success' => true,
            'data' => $plans
        ], 200);
    }

    public function store(Request $request)
    {
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'plan_type' => 'required|in:individual,professional',
            'billing_cycle' => 'required|in:days,monthly,annual,custom',
            'price' => 'required|numeric|min:0',
            'duration' => 'nullable|integer',
            'member_limit' => 'nullable|integer',
            'features' => 'nullable|array',
            'status' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $plan = Plan::create([
            'name' => $request->name,
            'plan_type' => $request->plan_type,
            'user_id' => $request->user()->id, // ক্রিয়েটর অ্যাডমিন আইডি
            'billing_cycle' => $request->billing_cycle,
            'price' => $request->price,
            'duration' => $request->duration,
            'member_limit' => $request->plan_type === 'professional' ? $request->member_limit : null,
            'features' => $request->features,
            'status' => $request->status ?? true,
        ]);

        return response()->json(['success' => true, 'message' => 'Plan created successfully', 'data' => $plan], 201);
    }

    public function show($id)
    {
        $plan = Plan::find($id);
        if (!$plan) {
            return response()->json(['success' => false, 'message' => 'Plan not found'], 404);
        }
        return response()->json(['success' => true, 'data' => $plan], 200);
    }

    public function update(Request $request, $id)
    {
        $plan = Plan::find($id);
        if (!$plan) return response()->json(['success' => false, 'message' => 'Plan not found'], 404);

        $plan->update($request->all());

        return response()->json(['success' => true, 'message' => 'Plan updated successfully', 'data' => $plan], 200);
    }

    public function destroy($id)
    {
        $plan = Plan::find($id);
        if (!$plan) return response()->json(['success' => false, 'message' => 'Plan not found'], 404);

        $plan->delete();
        return response()->json(['success' => true, 'message' => 'Plan deleted successfully'], 200);
    }

    public function toggleStatus($id)
    {
        $plan = Plan::find($id);
        if (!$plan) return response()->json(['success' => false, 'message' => 'Plan not found'], 404);

        $plan->status = !$plan->status;
        $plan->save();

        return response()->json(['success' => true, 'message' => 'Plan status updated successfully', 'data' => $plan], 200);
    }

    public function getPlansByType($type)
    {
        if (!in_array($type, ['individual', 'professional'])) {
            return response()->json(['success' => false, 'message' => 'Invalid plan type'], 400);
        }

        $plans = Plan::where('plan_type', $type)->where('status', true)->get();
        return response()->json(['success' => true, 'data' => $plans], 200);
    }
}