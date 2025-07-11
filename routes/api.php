<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\LeaveController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::prefix('v1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    
    // Health check
    Route::get('/health', function () {
        return response()->json([
            'success' => true,
            'message' => 'API is running',
            'version' => '1.0.0',
            'timestamp' => now()->toISOString(),
        ]);
    });
});

// Protected routes
Route::prefix('v1')->middleware(['auth:sanctum', 'api.response'])->group(function () {
    
    // Authentication
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    
    // Attendance
    Route::prefix('attendance')->group(function () {
        Route::get('/', [AttendanceController::class, 'index']);
        Route::get('/today', [AttendanceController::class, 'todayAttendance']);
        Route::post('/check-in', [AttendanceController::class, 'checkIn']);
        Route::post('/check-out', [AttendanceController::class, 'checkOut']);
        Route::get('/summary', [AttendanceController::class, 'summary']);
    });
    
    // Leave Management
    Route::prefix('leaves')->group(function () {
        Route::get('/', [LeaveController::class, 'index']);
        Route::post('/', [LeaveController::class, 'store']);
        Route::get('/summary', [LeaveController::class, 'summary']);
        Route::get('/{id}', [LeaveController::class, 'show']);
        Route::put('/{id}', [LeaveController::class, 'update']);
        Route::delete('/{id}', [LeaveController::class, 'destroy']);
    });
    
    // System info
    Route::get('/system-info', function (Request $request) {
        $user = $request->user();
        
        return response()->json([
            'success' => true,
            'data' => [
                'app_name' => config('app.name'),
                'app_version' => '1.0.0',
                'api_version' => 'v1',
                'server_time' => now()->toISOString(),
                'user_timezone' => $user->tenant?->settings['timezone'] ?? 'UTC',
                'features' => [
                    'geofencing' => $user->tenant?->enforce_geofencing ?? false,
                    'shifts' => $user->shift ? true : false,
                    'notifications' => true,
                ],
            ]
        ]);
    });
});

// Fallback route for API
Route::fallback(function () {
    return response()->json([
        'success' => false,
        'message' => 'API endpoint not found',
        'timestamp' => now()->toISOString(),
    ], 404);
});