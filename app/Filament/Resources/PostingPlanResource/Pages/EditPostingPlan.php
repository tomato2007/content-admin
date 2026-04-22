<?php

declare(strict_types=1);

namespace App\Filament\Resources\PostingPlanResource\Pages;

use App\Filament\Resources\PostingPlanResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPostingPlan extends EditRecord
{
    protected static string $resource = PostingPlanResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return array_merge($data, $this->record->structuredRulesFormData());
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['rules'] = $this->record->mergeStructuredRulesFromFormData($data);

        unset(
            $data['rules_content_mix'],
            $data['rules_max_posts_per_day'],
            $data['rules_approval_mode'],
            $data['rules_storage_note'],
        );

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
        ];
    }
}
