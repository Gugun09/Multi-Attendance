<?php

namespace App\Filament\Resources\LeaveTypeResource\Pages;

use App\Filament\Resources\LeaveTypeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLeaveType extends CreateRecord
{
    protected static string $resource = LeaveTypeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (auth()->check() && auth()->user()->tenant_id) {
            $data['tenant_id'] = auth()->user()->tenant_id;
        }
        return $data;
    }
}