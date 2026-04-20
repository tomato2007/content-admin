<?php

declare(strict_types=1);

namespace App\Filament\Resources\PostingPlanResource\Pages;

use App\Filament\Resources\PostingPlanResource;
use Filament\Resources\Pages\ListRecords;

class ListPostingPlans extends ListRecords
{
    protected static string $resource = PostingPlanResource::class;
}
