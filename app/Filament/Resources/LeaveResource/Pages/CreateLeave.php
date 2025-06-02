<?php

namespace App\Filament\Resources\LeaveResource\Pages;

use App\Filament\Resources\LeaveResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateLeave extends CreateRecord
{
    protected static string $resource = LeaveResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (auth()->check() && auth()->user()->tenant_id) {
            $data['tenant_id'] = auth()->user()->tenant_id;
            $data['user_id'] = auth()->user()->id;
        }
        return $data;
    }
}
