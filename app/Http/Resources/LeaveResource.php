<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveResource extends JsonResource
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
            'type' => $this->type,
            'start_date' => $this->start_date->toDateString(),
            'end_date' => $this->end_date->toDateString(),
            'duration_days' => $this->start_date->diffInDays($this->end_date) + 1,
            'reason' => $this->reason,
            'status' => $this->status,
            'approved_by' => $this->approver ? [
                'id' => $this->approver->id,
                'name' => $this->approver->name,
                'email' => $this->approver->email,
            ] : null,
            'admin_notes' => $this->admin_notes,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}