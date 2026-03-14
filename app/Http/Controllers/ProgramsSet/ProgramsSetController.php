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
            'description' => 'nullable|string',
            'notes' => 'nullable|string',
            'weekly_targets' => 'nullable|array',
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
     * Update only description, notes and weekly_targets.
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




// public function assignUsers(Request $request)
// {
//     $request->validate([
//         'program_set_id' => 'required|integer',
//         'user_ids' => 'required|array',
//         'user_ids.*' => 'integer|exists:users,id',
//     ]);

//     try {
//         $programSet = ProgramSet::find($request->program_set_id);

//         if (!$programSet) {
//             return response()->json([
//                 'status' => false,
//                 'message' => 'Program not found.'
//             ], 404);
//         }

//         // যদি user_ids খালি হয়
//         if (empty($request->user_ids)) {
//             return response()->json([
//                 'status' => false,
//                 'message' => 'No users found to assign for this program.'
//             ], 404);
//         }

//         $programSet->users()->sync($request->user_ids);

//         $users = $programSet->users()
//             ->select('users.id', 'users.name', 'users.email')
//             ->get();

//         return response()->json([
//             'status' => true,
//             'message' => 'Users assigned successfully.',
//             'data' => $users
//         ], 200);

//     } catch (\Exception $e) {
//         return response()->json([
//             'status' => false,
//             'message' => 'Failed to assign users. Error: '.$e->getMessage()
//         ], 500);
//     }
// }
   



public function assignUsers(Request $request)
{
    $request->validate([
        'program_set_id' => 'required|integer',
        'user_ids' => 'required|array',
        'user_ids.*' => 'integer|exists:users,id',
    ]);

    try {
        $programSet = ProgramSet::find($request->program_set_id);

        if (!$programSet) {
            return response()->json([
                'status' => false,
                'message' => 'Program not found.'
            ], 404);
        }

        // যদি user_ids খালি হয়
        if (empty($request->user_ids)) {
            return response()->json([
                'status' => false,
                'message' => 'No users found to assign for this program.'
            ], 404);
        }

        // Assign users to the program
        $programSet->users()->sync($request->user_ids);

        // Fetch assigned users
        $users = $programSet->users()
            ->select('users.id', 'users.name', 'users.email')
            ->get();

        // Send notification to each assigned user
        foreach ($users as $user) {
            $user->notify(new ProgramAssignedNotification($programSet));
        }

        return response()->json([
            'status' => true,
            'message' => 'Users assigned successfully and notifications sent.',
            'data' => $users
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Failed to assign users. Error: '.$e->getMessage()
        ], 500);
    }
}




  /**
     * Get all unread notifications for logged-in user
     */
    public function unread(Request $request)
    {
        $notifications = $request->user()->unreadNotifications()->get()->map(function($n) {
            return [
                'id' => $n->id,
                'message' => $n->data['message'],
                'program_id' => $n->data['program_id'],
                'program_name' => $n->data['program_name'],
                'read_at' => $n->read_at,
                'created_at' => $n->created_at,
            ];
        });

        return response()->json([
            'status' => true,
            'data' => $notifications
        ]);
    }

    /**
     * Mark a notification as read
     */
    public function markRead(Request $request, $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json(['status' => true]);
    }

    public function getProgramContext(Request $request, $userId = null)
    {
        try {
            $loggedInUser = auth()->user();

            $targetUserId = $userId ?: $loggedInUser->id;

            if ($targetUserId != $loggedInUser->id) {
                $isConnected = \DB::table('connect_user_proffesions')
                    ->where('profession_id', $loggedInUser->id)
                    ->where('user_id', $targetUserId)
                    ->exists();

                if (!$isConnected && $loggedInUser->user_type !== 'admin') {
                    return response()->json([
                        'success' => false, 
                        'message' => 'Unauthorized: This client is not connected to you.'
                    ], 403);
                }
            }

            $programData = \DB::table('connect_to_professions')
                ->where('connect_to_professions.user_id', $targetUserId)
                ->join('programs_sets', 'connect_to_professions.program_set_id', '=', 'programs_sets.id')
                ->select(
                    'programs_sets.name as program_name',
                    'programs_sets.duration',
                    'programs_sets.primary_goal',
                    'programs_sets.target_intensity as intensity'
                )
                ->latest('connect_to_professions.created_at')
                ->first();

            if (!$programData) {
                return response()->json([
                    'success' => false,
                    'message' => 'No program found for this user.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'program_name' => $programData->program_name ?? 'N/A',
                    'duration'     => ($programData->duration ?? 0) . ' weeks',
                    'primary_goal' => $programData->primary_goal ?? 'N/A',
                    'intensity'    => $programData->intensity ?? 'Moderate'
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function getUsersInProgram($programSetId)
    {
        try {
            $loggedInUser = auth()->user();

            $program = DB::table('programs_sets')->where('id', $programSetId)->first();
            if (!$program) {
                return response()->json(['success' => false, 'message' => 'Program not found'], 404);
            }

            $connectedUsers = DB::table('connect_to_professions')
                ->where('program_set_id', $programSetId)
                ->join('users', 'connect_to_professions.user_id', '=', 'users.id')
                ->leftJoin('user_profiles as profiles', 'users.id', '=', 'profiles.user_id')
                ->select(
                    'users.id', 
                    'users.name', 
                    'users.email', 
                    'profiles.image as profile_image',
                    'connect_to_professions.created_at as enrolled_at'
                )
                ->get();

            return response()->json([
                'success' => true,
                'program_name' => $program->name,
                'total_users' => $connectedUsers->count(),
                'users' => $connectedUsers
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false, 
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function getUserPrograms($userId = null)
    {
        try {
            $loggedInUser = auth()->user();
            
            $targetUserId = $userId ?: $loggedInUser->id;

            if ($targetUserId != $loggedInUser->id) {
                $isConnected = DB::table('connect_user_proffesions')
                    ->where('profession_id', $loggedInUser->id)
                    ->where('user_id', $targetUserId)
                    ->exists();

                if (!$isConnected && $loggedInUser->user_type !== 'admin') {
                    return response()->json(['success' => false, 'message' => 'Unauthorized access'], 403);
                }
            }

            $userPrograms = DB::table('connect_to_professions')
                ->where('connect_to_professions.user_id', $targetUserId)
                ->join('programs_sets', 'connect_to_professions.program_set_id', '=', 'programs_sets.id')
                ->select(
                    'programs_sets.id as program_id',
                    'programs_sets.name',
                    'programs_sets.duration',
                    'programs_sets.primary_goal',
                    'programs_sets.target_intensity',
                    'connect_to_professions.created_at as assigned_date'
                )
                ->get();

            return response()->json([
                'success' => true,
                'total_connected_programs' => $userPrograms->count(),
                'data' => $userPrograms
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}