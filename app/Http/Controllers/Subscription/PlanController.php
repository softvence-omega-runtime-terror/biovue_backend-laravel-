<?php

namespace App\Http\Controllers\Subscription;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    /**
     * List plans (with optional type & billing filter)
     */
    public function index(Request $request)
    {
        try {
            $type = $request->query('type');       // individual / professional
            $billing = strtolower($request->query('billing', 'monthly')); // monthly/annual, default monthly

            // Validate billing param
            if (!in_array($billing, ['monthly', 'annual'])) {
                $billing = 'monthly';
            }

            $query = Plan::query()->where('status', true);

            if ($type && in_array($type, ['individual', 'professional'])) {
                $query->where('plan_type', $type);
            }

            $plans = $query->latest()->get();

            $data = $plans->map(function ($plan) use ($billing) {
                return [
                    'id'             => $plan->id,
                    'name'           => $plan->name,
                    'plan_type'      => $plan->plan_type,
                    'billing_cycle'  => $plan->billing_cycle,
                    'duration'       => $plan->duration,
                    'member_limit'   => $plan->member_limit,
                    'features'       => $plan->features,
                    'status'         => $plan->status,
                    'price'          => $billing === 'annual' ? $plan->annual_price : $plan->price,
                    'projection_limit' => $plan->projection_limit,
                ];
            });

            return response()->json([
                'success' => true,
                'data'    => $data
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch plans: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function storeOrUpdatePlan(Request $request)
    {
        $request->validate([
            'id' => 'nullable|integer|exists:plans,id', // optional for update
            'name' => 'required|string|max:255',
            'plan_type' => 'required|in:individual,professional',
            'billing_cycle' => 'required|in:days,monthly,annual,custom',
            'price' => 'required|numeric|min:0',
            'duration' => 'nullable|integer',
            'member_limit' => 'nullable|integer',
            'features' => 'nullable|array',
            'status' => 'boolean',
            'projection_limit' => 'nullable|integer'
        ]);

        try {
            // Use updateOrCreate
            $plan = Plan::updateOrCreate(
                ['id' => $request->id], // if id exists → update, otherwise create
                [
                    'name' => $request->name,
                    'plan_type' => $request->plan_type,
                    'user_id' => $request->user()->id,
                    'billing_cycle' => $request->billing_cycle,
                    'price' => $request->price,
                    'duration' => $request->duration,
                    'member_limit' => $request->plan_type === 'professional' ? $request->member_limit : null,
                    'features' => $request->features,
                    'status' => $request->status ?? true,
                    'projection_limit' => $request->projection_limit ?? null,
                ]
            );

            $message = $request->filled('id') ? 'Plan updated successfully.' : 'Plan created successfully.';

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $plan
            ], $request->filled('id') ? 200 : 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong. Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show single plan
     */
    public function show(Request $request, $id)
    {
        try {
            $plan = Plan::findOrFail($id);

            $billing = strtolower($request->query('billing', 'monthly')); // monthly/annual default
            if (!in_array($billing, ['monthly', 'annual'])) {
                $billing = 'monthly';
            }

            $data = [
                'id'            => $plan->id,
                'name'          => $plan->name,
                'plan_type'     => $plan->plan_type,
                'billing_cycle' => $plan->billing_cycle,
                'duration'      => $plan->duration,
                'member_limit'  => $plan->member_limit,
                'features'      => $plan->features,
                'status'        => $plan->status,
                'price'         => $billing === 'annual' ? $plan->annual_price : $plan->price,
            ];

            return response()->json([
                'success' => true,
                'data'    => $data
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Plan not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch plan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle plan status (active/inactive)
     */
    public function toggleStatus($id)
    {
        try {
            $plan = Plan::findOrFail($id);

            // Prevent toggling fixed plans
            if (in_array($plan->id, [1,2,3,4,5,6,7,8])) {
                return response()->json([
                    'success' => false,
                    'message' => 'This plan is fixed and cannot be modified.'
                ], 403);
            }

            // Toggle status
            $plan->status = !$plan->status;
            $plan->save();

            return response()->json([
                'success' => true,
                'message' => 'Plan status updated successfully.',
                'data' => [
                    'id' => $plan->id,
                    'status' => $plan->status
                ]
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Plan not found.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update plan status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete plan
     */
    public function destroy($id)
    {
        $plan = Plan::find($id);
        if (!$plan) return response()->json(['success' => false, 'message' => 'Plan not found'], 404);

        // Prevent deleting fixed plans
        if (in_array($plan->id, [1,2,3,4,5,6,7,8])) {
            return response()->json([
                'success' => false,
                'message' => 'This plan is fixed and cannot be deleted.'
            ], 403);
        }

        $plan->delete();
        return response()->json(['success' => true, 'message' => 'Plan deleted successfully'], 200);
    }
}