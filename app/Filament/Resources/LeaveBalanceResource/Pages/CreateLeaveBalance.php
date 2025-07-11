<?php

namespace App\Filament\Resources\LeaveBalanceResource\Pages;

use App\Filament\Resources\LeaveBalanceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLeaveBalance extends CreateRecord
{
    protected static string $resource = LeaveBalanceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (auth()->check() && auth()->user()->tenant_id) {
            $data['tenant_id'] = auth()->user()->tenant_id;
        }
        
        // Calculate available days
        $data['available_days'] = ($data['entitled_days'] ?? 0) + 
                                 ($data['carried_over_days'] ?? 0) + 
                                 ($data['adjustment_days'] ?? 0) - 
                                 ($data['used_days'] ?? 0) - 
                                 ($data['pending_days'] ?? 0);
        
        return $data;
    }
}