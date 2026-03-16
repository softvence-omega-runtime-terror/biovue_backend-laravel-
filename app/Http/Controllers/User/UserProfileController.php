<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserMedicalHistory;
use App\Models\UserProfile;
use App\Notifications\InsightNotification;
use Illuminate\Http\Request;

class UserProfileController extends Controller
{
    public function index()
    {
        $profiles = UserProfile::with('user', 'user.medicalHistory')->get();
        return response()->json($profiles);
    }

    public function storeAndUpdate(Request $request)
    {
        $validated = $request->validate([
            'user_id'         => 'required|exists:users,id',
            'user_type'       => 'required|in:individual,professional',
            'profession_type' => 'nullable|string|in:trainer_coach,nutritionist,supplement_supplier',

            'age'              => 'nullable|integer',
            'sex'              => 'nullable|string|max:20',
            'image'            => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
            'height'           => 'nullable|integer',
            'weight'           => 'nullable|integer',
            'location'         => 'nullable|string|max:255',
            'bio'              => 'nullable|string',
            'specialties'      => 'nullable|array',
            'services'         => 'nullable|array',
            'experience_years' => 'nullable|integer',

            'diabetes'            => 'nullable|boolean',
            'high_blood_pressure' => 'nullable|boolean',
            'high_cholesterol'    => 'nullable|boolean',
            'heart_disease'       => 'nullable|boolean',
            'asthma'              => 'nullable|boolean',
            'athritis'            => 'nullable|boolean',
            'depression'          => 'nullable|boolean',
            'anxiety'             => 'nullable|boolean',
            'sleep_apnea'         => 'nullable|boolean',
            'thyroid_issue'       => 'nullable|boolean',
            'current_medication'  => 'nullable|string',
            'smoking_status'       => 'nullable|boolean',
            'alcohol_consumption' => 'nullable|boolean',
            'stress_level'         => 'nullable|string',
            'daily_step'          => 'nullable|integer',
            'sleep_hour'          => 'nullable|numeric',
            'water_consumption_week'   => 'nullable|numeric',
            'overall_diet_quality' => 'nullable|string',
            'fast_food_frequency' => 'nullable|string',
            'strength_training_week' => 'nullable|string',
            'workout_week' => 'nullable|string',
            'is_athletic' => 'nullable|boolean',
            'notes' => 'nullable|string',
            'curvy_fit' => 'nullable|boolean',
            'muscular' => 'nullable|boolean',
            'lean' => 'nullable|boolean',
            'toned' => 'nullable|boolean',
        ]);

        $user = User::find($validated['user_id']);
        $user->update([
            'user_type'       => $validated['user_type'],
            'profession_type' => $validated['profession_type'] ?? $user->profession_type,
        ]);

        if ($request->hasFile('image')) {
            $oldProfile = UserProfile::where('user_id', $validated['user_id'])->first();
            if ($oldProfile && $oldProfile->image) {
                \Storage::disk('public')->delete($oldProfile->image);
            }
            $validated['image'] = $request->file('image')->store('profiles', 'public');
        }

        $medicalFields = [
            'diabetes', 'high_blood_pressure', 'high_cholesterol', 'heart_disease',
            'asthma', 'athritis', 'depression', 'anxiety', 'sleep_apnea',
            'thyroid_issue', 'current_medication'
        ];
        $medicalData = collect($validated)->only($medicalFields)->toArray();

        $profileData = collect($validated)->except(array_merge(['user_type', 'profession_type'], $medicalFields))->toArray();

        UserProfile::updateOrCreate(['user_id' => $validated['user_id']], $profileData);

        UserMedicalHistory::updateOrCreate(['user_id' => $validated['user_id']], $medicalData);

        $fullUser = User::with(['profile', 'medicalHistory'])->find($user->id);

        $fullImageUrl = $fullUser->profile && $fullUser->profile->image
            ? asset('storage/' . $fullUser->profile->image)
            : null;

        $user->notify(new InsightNotification('AI Insight', 'New AI Insight available','insight_msg'));


        return response()->json([
            'success' => true,
            'message' => 'Profile and Medical History updated successfully',
            'data' => [
                'user' => [
                    'id'              => $fullUser->id,
                    'name'            => $fullUser->name,
                    'email'           => $fullUser->email,
                    'user_type'       => $fullUser->user_type,
                    'profession_type' => $fullUser->profession_type,
                ],
                'profile'         => $fullUser->profile,
                'medical_history' => $fullUser->medicalHistory
            ]
        ]);
    }

   public function showByUserId($userId)
{
    $user = \App\Models\User::with('profile')->findOrFail($userId);

    return response()->json([
        'success' => true,
        'data' => $user
    ]);
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
