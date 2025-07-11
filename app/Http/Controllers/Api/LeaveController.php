<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Leave;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LeaveController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $perPage = $request->get('per_page', 15);
        $status = $request->get('status');
        $year = $request->get('year', now()->year);

        $query = Leave::where('user_id', $user->id)
            ->with(['approver'])
            ->whereYear('start_date', $year)
            ->orderBy('created_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        $leaves = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'leaves' => $leaves->items(),
                'pagination' => [
                    'current_page' => $leaves->currentPage(),
                    'last_page' => $leaves->lastPage(),
                    'per_page' => $leaves->perPage(),
                    'total' => $leaves->total(),
                ]
            ]
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:annual,sick,personal,maternity,paternity,unpaid',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        // Check for overlapping leaves
        $overlapping = Leave::where('user_id', $user->id)
            ->where('status', '!=', 'rejected')
            ->where(function ($query) use ($request) {
                $query->whereBetween('start_date', [$request->start_date, $request->end_date])
                      ->orWhereBetween('end_date', [$request->start_date, $request->end_date])
                      ->orWhere(function ($q) use ($request) {
                          $q->where('start_date', '<=', $request->start_date)
                            ->where('end_date', '>=', $request->end_date);
                      });
            })
            ->exists();

        if ($overlapping) {
            return response()->json([
                'success' => false,
                'message' => 'You already have a leave request for this date range'
            ], 400);
        }

        $leave = Leave::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'type' => $request->type,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'reason' => $request->reason,
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Leave request submitted successfully',
            'data' => [
                'leave' => $leave->load('approver')
            ]
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        
        $leave = Leave::where('user_id', $user->id)
            ->with(['approver'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'leave' => $leave
            ]
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();
        
        $leave = Leave::where('user_id', $user->id)
            ->where('status', 'pending')
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'type' => 'sometimes|required|in:annual,sick,personal,maternity,paternity,unpaid',
            'start_date' => 'sometimes|required|date|after_or_equal:today',
            'end_date' => 'sometimes|required|date|after_or_equal:start_date',
            'reason' => 'sometimes|required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $leave->update($request->only(['type', 'start_date', 'end_date', 'reason']));

        return response()->json([
            'success' => true,
            'message' => 'Leave request updated successfully',
            'data' => [
                'leave' => $leave->fresh()->load('approver')
            ]
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        
        $leave = Leave::where('user_id', $user->id)
            ->where('status', 'pending')
            ->findOrFail($id);

        $leave->delete();

        return response()->json([
            'success' => true,
            'message' => 'Leave request cancelled successfully'
        ]);
    }

    public function summary(Request $request)
    {
        $user = $request->user();
        $year = $request->get('year', now()->year);

        $leaves = Leave::where('user_id', $user->id)
            ->whereYear('start_date', $year)
            ->get();

        $summary = [
            'year' => $year,
            'total_requests' => $leaves->count(),
            'pending' => $leaves->where('status', 'pending')->count(),
            'approved' => $leaves->where('status', 'approved')->count(),
            'rejected' => $leaves->where('status', 'rejected')->count(),
            'by_type' => [],
            'total_days_taken' => 0,
        ];

        // Group by type
        $leaveTypes = ['annual', 'sick', 'personal', 'maternity', 'paternity', 'unpaid'];
        foreach ($leaveTypes as $type) {
            $typeLeaves = $leaves->where('type', $type)->where('status', 'approved');
            $totalDays = $typeLeaves->sum(function ($leave) {
                return $leave->start_date->diffInDays($leave->end_date) + 1;
            });
            
            $summary['by_type'][$type] = [
                'count' => $typeLeaves->count(),
                'days' => $totalDays,
            ];
            
            $summary['total_days_taken'] += $totalDays;
        }

        return response()->json([
            'success' => true,
            'data' => $summary
        ]);
    }
}