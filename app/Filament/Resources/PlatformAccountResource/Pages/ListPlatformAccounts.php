<?php

declare(strict_types=1);

namespace App\Filament\Resources\PlatformAccountResource\Pages;

use App\Filament\Resources\PlatformAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPlatformAccounts extends ListRecords
{
    protected static string $resource = PlatformAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Add account'),
        ];
    }
}
