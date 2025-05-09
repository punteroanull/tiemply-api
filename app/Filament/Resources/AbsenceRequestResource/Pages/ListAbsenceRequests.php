<?php

namespace App\Filament\Resources\AbsenceRequestResource\Pages;

use App\Filament\Resources\AbsenceRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAbsenceRequests extends ListRecords
{
    protected static string $resource = AbsenceRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
