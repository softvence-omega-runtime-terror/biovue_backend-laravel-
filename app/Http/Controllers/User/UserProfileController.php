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
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'user_type' => 'required|in:individual,professional',
            'profession_type' => 'nullable|string|in:trainer_coach,nutritionist,supplement_supplier',
            'age' => 'nullable|integer',
            'sex' => 'nullable|string|max:20',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
            'height' => 'nullable|integer',
            'weight' => 'nullable|integer',
            'location' => 'nullable|string|max:255',
            'bio' => 'nullable|string',
            'specialties' => 'nullable|array',
            'services' => 'nullable|array',
            'experience_years' => 'nullable|integer',
        ]);

        $user = \App\Models\User::find($validated['user_id']);
        $user->update([
            'user_type' => $validated['user_type'],
            'profession_type' => $validated['profession_type'] ?? $user->profession_type,
        ]);

        if ($request->hasFile('image')) {
            $oldProfile = \App\Models\UserProfile::where('user_id', $validated['user_id'])->first();
            if ($oldProfile && $oldProfile->image) {
                \Storage::disk('public')->delete($oldProfile->image);
            }
            $validated['image'] = $request->file('image')->store('profiles', 'public');
        }

        $profileData = collect($validated)->except(['user_type', 'profession_type'])->toArray();

        $profile = \App\Models\UserProfile::updateOrCreate(
            ['user_id' => $validated['user_id']], 
            $profileData 
        );

        $fullUser = \App\Models\User::with('profile')->find($user->id);

        $fullImageUrl = $fullUser->profile && $fullUser->profile->image 
            ? asset('storage/' . $fullUser->profile->image) 
            : null;

        return response()->json([
            'success' => true,
            'message' => 'Profile and account information updated successfully',
            'data' => [
                'user' => [
                    'id' => $fullUser->id,
                    'name' => $fullUser->name,
                    'email' => $fullUser->email,
                    'user_type' => $fullUser->user_type,
                    'profession_type' => $fullUser->profession_type,
                    'status' => $fullUser->status,
                    'plan_id' => $fullUser->plan_id,
                ],
                'profile' => $fullUser->profile ? array_merge($fullUser->profile->toArray(), [
                    'image' => $fullImageUrl 
                ]) : null
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
