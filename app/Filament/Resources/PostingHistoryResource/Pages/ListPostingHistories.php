<?php

declare(strict_types=1);

namespace App\Filament\Resources\PostingHistoryResource\Pages;

use App\Filament\Resources\PostingHistoryResource;
use Filament\Resources\Pages\ListRecords;

class ListPostingHistories extends ListRecords
{
    protected static string $resource = PostingHistoryResource::class;
}
