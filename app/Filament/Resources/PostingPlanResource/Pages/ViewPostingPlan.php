<?php

declare(strict_types=1);

namespace App\Filament\Resources\PostingPlanResource\Pages;

use App\Filament\Resources\PostingPlanResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPostingPlan extends ViewRecord
{
    protected static string $resource = PostingPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
