<?php

namespace App\Filament\Resources\ReadingPlanResource\Pages;

use App\Filament\Resources\ReadingPlanResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListReadingPlans extends ListRecords
{
    protected static string $resource = ReadingPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
