<?php

namespace App\Filament\Resources\ReadingPlanResource\Pages;

use App\Filament\Resources\ReadingPlanResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditReadingPlan extends EditRecord
{
    protected static string $resource = ReadingPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
