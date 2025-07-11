<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ],
            'check_in_at' => $this->check_in_at?->toISOString(),
            'check_out_at' => $this->check_out_at?->toISOString(),
            'check_in_location' => $this->check_in_location,
            'check_out_location' => $this->check_out_location,
            'check_in_coordinates' => [
                'latitude' => $this->check_in_latitude,
                'longitude' => $this->check_in_longitude,
            ],
            'check_out_coordinates' => [
                'latitude' => $this->check_out_latitude,
                'longitude' => $this->check_out_longitude,
            ],
            'is_within_geofence' => $this->is_within_geofence,
            'distance_from_office' => $this->distance_from_office,
            'actual_work_hours' => $this->actual_work_hours,
            'is_late' => $this->is_late,
            'late_minutes' => $this->late_minutes,
            'status' => $this->status,
            'notes' => $this->notes,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}