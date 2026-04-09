<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Notifications\AdminNotification;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;
use App\Jobs\SendOtpEmail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;

class SignUpController extends Controller
{
 public function register(Request $request)
{
    try {
        // ✅ Validation with custom messages
        $request->validate([
            'email' => 'required|email',
             'name' => 'required|string|max:255',
            'password' => [
                'required',
                'confirmed',
                Password::min(8)->letters()->mixedCase()->numbers()->symbols()
            ],
            'zipcode' => 'nullable|string|max:20',
            'prof_service_type' => 'nullable|string|in:local, remote, both',
            'role' => 'required|string',
            'terms_accepted' => 'required|accepted',
            'user_type' => 'required|string|in:individual,professional',
            'profession_type' => 'nullable|string|in:trainer_coach,nutritionist,supplement_supplier',
        ], [
            'name.required' => 'Name field is required.',
            'email.required' => 'Email field is required.',
            'email.email' => 'Please enter a valid email address.',
            'password.required' => 'Password is required.',
            'password.confirmed' => 'Password confirmation does not match.',
            'role.required' => 'Role is required.',
            'terms_accepted.required' => 'You must accept the terms and conditions.',
            'terms_accepted.accepted' => 'You must accept the terms and conditions.',
        ]);

        // Check if email already exists
        if (User::where('email', $request->email)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Email is already registered.'
            ], 409);
        }

        // Check if role exists
        if (!\Spatie\Permission\Models\Role::where('name', $request->role)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found.'
            ], 404);
        }

        // Create user
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'terms_accepted' => true,
            'user_type' => $request->user_type,
            'profession_type' => $request->profession_type ?? null,
        ]);

        // Assign role
        $user->assignRole($request->role);

       // Generate OTP (5-digit)
        $otp = random_int(10000, 99999);

        $user->update([
            'otp' => $otp,
            'otp_expire_at' => Carbon::now()->addMinutes(5),
        ]);

        // Send OTP email
        SendOtpEmail::dispatch($user->id, 'verify', $otp);

        $admin = User::find(1);

        $admin->notify(new AdminNotification('New User Registration', "$user->name is onboarded",'registration_message'));


        return response()->json([
            'success' => true,
            'message' => 'Registration successful. OTP sent to your email.',
            'data' => [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
                'role' => $request->role,
                'user_type' => $user->user_type,
                'profession_type' => $user->profession_type,
            ]
        ], 201);

    } catch (\Illuminate\Validation\ValidationException $e) {
        // ✅ Show first validation error as message
        $firstError = collect($e->errors())->flatten()->first();

        return response()->json([
            'success' => false,
            'message' => $firstError,
        ], 422);

    } catch (\Exception $e) {
        \Log::error('Registration Error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Something went wrong. Please try again.',
        ], 500);
    }
}


    /**
     * Verify OTP
     */
    public function verifyOtp(Request $request)
{
    try {
        // ✅ Validation with custom messages
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|digits:5',
        ], [
            'email.required' => 'Email field is required.',
            'email.email' => 'Please enter a valid email address.',
            'otp.required' => 'OTP field is required.',
            'otp.digits' => 'OTP must be 5 digits.',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Email not found.'
            ], 404);
        }

        if (!$user->otp || !$user->otp_expire_at) {
            return response()->json([
                'success' => false,
                'message' => 'No OTP found. Please request a new OTP.'
            ], 400);
        }

        $otpExpire = Carbon::parse($user->otp_expire_at);

        if ($otpExpire->lt(Carbon::now())) {
            return response()->json([
                'success' => false,
                'message' => 'OTP has expired. Please request a new one.'
            ], 400);
        }

        if ((string)$user->otp !== (string)$request->otp) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid OTP. Please try again.'
            ], 400);
        }

        // OTP is valid → verify email and clear OTP
        $user->update([
            'email_verified_at' => $user->email_verified_at ?? Carbon::now(),
            'otp' => null,
            'otp_expire_at' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'OTP verified successfully.',
        ], 200);

    } catch (\Illuminate\Validation\ValidationException $e) {
        // ✅ Show first validation error as message
        $firstError = collect($e->errors())->flatten()->first();

        return response()->json([
            'success' => false,
            'message' => $firstError,
        ], 422);

    } catch (\Exception $e) {
        \Log::error('Verify OTP Error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'OTP verification failed. Please try again.',
        ], 500);
    }
}

}
