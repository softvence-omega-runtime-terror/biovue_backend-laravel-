<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class TrainerController extends Controller
{
    public function indexProfessionals($id)
    {
        try {
            $trainer = User::whereIn('user_type', ['professional'])
                ->with('profile') 
                ->find($id);

            if (!$trainer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Trainer not found'
                ], 404);
            }

            $profile = $trainer->profile;

            return response()->json([
                'success' => true,
                'data' => [
                    'id'               => $trainer->id,
                    'name'             => $trainer->name,
                    'email'            => $trainer->email,
                    'user_type'        => $trainer->user_type,
                    'bio'              => $profile?->bio ?? null,
                    'experience'       => ($profile?->experience_years ?? 0) . " years",
                    'specialties'      => $profile?->specialties ?? [], 
                    'services'         => $profile?->services ?? [],
                    'profile_image'    => $profile?->image ? asset('storage/' . $profile->image) : null,
                    'created_at'       => $trainer->created_at->format('Y-m-d')
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
