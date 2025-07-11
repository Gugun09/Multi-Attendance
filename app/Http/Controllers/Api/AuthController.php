<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\HasApiTokens;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $credentials = $request->only('email', 'password');

        if (!Auth::attempt($credentials)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        $user = Auth::user();
        $token = $user->createToken('mobile-app')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'tenant' => $user->tenant ? [
                        'id' => $user->tenant->id,
                        'name' => $user->tenant->name,
                        'settings' => $user->tenant->settings,
                    ] : null,
                    'roles' => $user->roles->pluck('name'),
                    'shift' => $user->shift ? [
                        'id' => $user->shift->id,
                        'name' => $user->shift->name,
                        'start_time' => $user->shift->start_time,
                        'end_time' => $user->shift->end_time,
                        'working_days' => $user->shift->working_days,
                    ] : null,
                ],
                'token' => $token,
                'token_type' => 'Bearer'
            ]
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout successful'
        ]);
    }

    public function profile(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'tenant' => $user->tenant ? [
                        'id' => $user->tenant->id,
                        'name' => $user->tenant->name,
                        'office_latitude' => $user->tenant->office_latitude,
                        'office_longitude' => $user->tenant->office_longitude,
                        'geofence_radius_meters' => $user->tenant->geofence_radius_meters,
                        'enforce_geofencing' => $user->tenant->enforce_geofencing,
                        'work_start_time' => $user->tenant->work_start_time,
                        'work_end_time' => $user->tenant->work_end_time,
                        'late_tolerance_minutes' => $user->tenant->late_tolerance_minutes,
                        'working_days' => $user->tenant->working_days,
                    ] : null,
                    'shift' => $user->shift ? [
                        'id' => $user->shift->id,
                        'name' => $user->shift->name,
                        'start_time' => $user->shift->start_time,
                        'end_time' => $user->shift->end_time,
                        'working_days' => $user->shift->working_days,
                        'late_tolerance_minutes' => $user->shift->late_tolerance_minutes,
                    ] : null,
                    'roles' => $user->roles->pluck('name'),
                ]
            ]
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,email,' . $user->id,
            'password' => 'sometimes|required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->only(['name', 'email']);
        
        if ($request->has('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ]
            ]
        ]);
    }
}