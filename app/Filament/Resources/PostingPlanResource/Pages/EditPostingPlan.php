<?php

declare(strict_types=1);

namespace App\Filament\Resources\PostingPlanResource\Pages;

use App\Filament\Resources\PostingPlanResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPostingPlan extends EditRecord
{
    protected static string $resource = PostingPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
        ];
    }
}
