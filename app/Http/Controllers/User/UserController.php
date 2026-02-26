<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
   public function individualUsers(Request $request)
{
    try {
        $query = User::role('individual'); // Spatie scope

        if ($request->has('email')) {
            $email = $request->email;
            $query->where('email', 'like', "%{$email}%"); // partial match
        } else {
            // if email→ user not found
            return response()->json([
                'status' => false,
                'message' => 'User not found. Please provide an email to search.'
            ], 404);
        }

        $users = $query->select('id', 'name', 'email')->get(); // id added

        if ($users->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No individual users found with this email keyword.'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $users
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Failed to fetch users. Error: '.$e->getMessage()
        ], 500);
    }
}
}