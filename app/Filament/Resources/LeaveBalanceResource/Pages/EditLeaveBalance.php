<?php

namespace App\Filament\Resources\LeaveBalanceResource\Pages;

use App\Filament\Resources\LeaveBalanceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLeaveBalance extends EditRecord
{
    protected static string $resource = LeaveBalanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Recalculate available days
        $data['available_days'] = ($data['entitled_days'] ?? 0) + 
                                 ($data['carried_over_days'] ?? 0) + 
                                 ($data['adjustment_days'] ?? 0) - 
                                 ($data['used_days'] ?? 0) - 
                                 ($data['pending_days'] ?? 0);
        
        return $data;
    }
}