<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\UserProfile;
use Illuminate\Http\Request;

class UserProfileController extends Controller
{
    public function index()
    {
        $profiles = UserProfile::with('user')->get();
        return response()->json($profiles);
    }

    public function storeAndUpdate(Request $request)
    {
        // Validate request data
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'age' => 'nullable|integer',
            'sex' => 'nullable|string|max:20',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120', 
            'height' => 'nullable|integer',
            'weight' => 'nullable|integer',
            'body_fat' => 'nullable|string|max:20',
            'location' => 'nullable|string|max:255',
            'agreed_terms' => 'boolean',
            'smoking_status' => 'boolean',
            'alcohol_consumption' => 'boolean',
            'stress_level' => 'nullable|integer|min:1|max:10',
            'daily_step' => 'nullable|integer',
            'sleep_hour' => 'nullable|numeric',
            'water_consumption_week' => 'nullable|string|max:50',
            'overall_diet_quality' => 'nullable|string|max:50',
            'fast_food_frequency' => 'nullable|string|max:50',
            'strength_training_week' => 'nullable|string|max:50',
            'workout_week' => 'nullable|string|max:50',
            'is_athletic' => 'boolean',
            'toned' => 'boolean',
            'lean' => 'boolean',
            'muscular' => 'boolean',
            'curvy_fit' => 'boolean',
            'notes' => 'nullable|string|max:255',
        ]);

        if ($request->hasFile('image')) 
        {
            $oldProfile = UserProfile::where('user_id', $validated['user_id'])->first();

            if ($oldProfile && $oldProfile->image) {
                \Storage::disk('public')->delete($oldProfile->image);
            }

            $path = $request->file('image')->store('profiles', 'public');
            $validated['image'] = $path; 
        }

        $profile = UserProfile::updateOrCreate(
            ['user_id' => $validated['user_id']], 
            $validated 
        );

        if ($profile->image) {
            $profile->image = asset('storage/' . $profile->image);
        }

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => $profile 
        ]);
    }
    

    public function show($id)
    {
        $profile = UserProfile::with('user')->findOrFail($id);
        return response()->json($profile);
    }

    public function destroy($id)
    {
        $profile = UserProfile::findOrFail($id);
        $profile->delete();

        return response()->json([
            'success' => true,
            'message' => 'Profile deleted successfully'
        ]);
    }
}
