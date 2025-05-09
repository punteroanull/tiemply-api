<?php

namespace App\Filament\Resources\AbsenceRequestResource\Pages;

use App\Filament\Resources\AbsenceRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAbsenceRequest extends EditRecord
{
    protected static string $resource = AbsenceRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
