<?php

namespace App\Http\Controllers\ProgramsSet;

use App\Http\Controllers\Controller;
use App\Models\ProgramSet;
use Illuminate\Http\Request;
use App\Notifications\ProgramAssignedNotification;
use Illuminate\Support\Facades\DB;

class ProgramsSetController extends Controller
{
    /**
     * List programs (only logged-in trainer's programs)
     */
    public function index()
    {
        try {
            $programs = ProgramSet::where('profession_id', auth()->id())
                ->latest()
                ->get();

            return response()->json([
                'status' => true,
                'data' => $programs
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch program sets. Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store program
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
            'description' => 'nullable|string',
            'notes' => 'nullable|string',
            'weekly_targets' => 'nullable|array',
        ]);

        try {
            $programSet = ProgramSet::create([
                'profession_id' => auth()->id(), // ✅ FIXED
                'name' => $request->name,
                'duration' => $request->duration,
                'primary_goal' => $request->primary_goal,
                'target_intensity' => $request->target_intensity,
                'habit_focus_areas' => $request->habit_focus_areas,
                'program_focus' => $request->program_focus,
                'focus_areas' => $request->focus_areas,
                'habit_focus' => $request->habit_focus,
                'calories' => $request->calories,
                'protein' => $request->protein,
                'carbs' => $request->carbs,
                'fat' => $request->fat,
                'supplement_recommendation' => $request->supplement_recommendation,
                'supplement' => $request->supplement,
                'description' => $request->description,
                'notes' => $request->notes,
                'weekly_targets' => $request->weekly_targets,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Program Set created successfully.',
                'data' => $programSet
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to create Program Set. Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show single program
     */
    public function show($id)
    {
        try {
            $program = ProgramSet::where('id', $id)
                ->where('profession_id', auth()->id())
                ->firstOrFail();

            return response()->json([
                'status' => true,
                'data' => $program
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Program not found.'
            ], 404);
        }
    }

    /**
     * Update program
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
            'description' => 'nullable|string',
            'notes' => 'nullable|string',
            'weekly_targets' => 'nullable|array',
        ]);

        try {
            $program = ProgramSet::where('id', $id)
                ->where('profession_id', auth()->id())
                ->firstOrFail();

            $program->update($request->only([
                'name','duration','primary_goal','target_intensity',
                'habit_focus_areas','program_focus','focus_areas','habit_focus',
                'calories','protein','carbs','fat',
                'supplement_recommendation','supplement',
                'description','notes','weekly_targets'
            ]));

            return response()->json([
                'status' => true,
                'message' => 'Program updated successfully.',
                'data' => $program
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Update failed. Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete program
     */
    public function destroy($id)
    {
        try {
            $program = ProgramSet::where('id', $id)
                ->where('profession_id', auth()->id())
                ->firstOrFail();

            $program->delete();

            return response()->json([
                'status' => true,
                'message' => 'Program deleted successfully.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Delete failed.'
            ], 500);
        }
    }

    
    /**
 * Assign users to a program set with profession_id in pivot
 */
public function assignUsers(Request $request)
{
    $request->validate([
        'program_set_id' => 'required|integer',
        'user_ids' => 'required|array',
        'user_ids.*' => 'integer|exists:users,id',
    ]);

    try {
        // Ensure the logged-in user owns this program
        $program = ProgramSet::where('id', $request->program_set_id)
            ->where('profession_id', auth()->id())
            ->firstOrFail();

        // Prepare pivot data for each user
        $pivotData = [];
        foreach ($request->user_ids as $userId) {
            $pivotData[$userId] = ['profession_id' => auth()->id()];
        }

        // Sync users with pivot data
        $program->users()->sync($pivotData);

        // Fetch assigned users
        $users = $program->users()->select('users.id','users.name','users.email')->get();

        // Send notifications
        foreach ($users as $user) {
            $user->notify(new ProgramAssignedNotification($program));
        }

        return response()->json([
            'status' => true,
            'message' => 'Users assigned successfully.',
            'data' => $users
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Assignment failed. Error: '.$e->getMessage()
        ], 500);
    }
}
    /**
     * Unread notifications
     */
    public function unread(Request $request)
    {
        return response()->json([
            'status' => true,
            'data' => $request->user()->unreadNotifications
        ]);
    }

    /**
     * Mark notification as read
     */
    public function markRead(Request $request, $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json(['status' => true]);
    }
}