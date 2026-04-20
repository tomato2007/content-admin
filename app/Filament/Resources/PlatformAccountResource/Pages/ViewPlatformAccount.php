<?php

declare(strict_types=1);

namespace App\Filament\Resources\PlatformAccountResource\Pages;

use App\Filament\Resources\PlatformAccountResource;
use App\Filament\Resources\PostingPlanResource;
use App\Models\PostingPlan;
use App\Models\PlannedPost;
use App\Services\Publishing\PublishingService;
use App\Services\Publishing\TelegramConfigValidator;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewPlatformAccount extends ViewRecord
{
    protected static string $resource = PlatformAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\Action::make('validateTelegramConfig')
                ->label('Validate Telegram config')
                ->icon('heroicon-o-shield-check')
                ->color('gray')
                ->action(function (): void {
                    try {
                        $result = app(TelegramConfigValidator::class)->validate($this->record->loadMissing('platform'));

                        Notification::make()
                            ->title('Telegram config is valid')
                            ->body(trim(implode("\n", [
                                'Channel key: '.($result['channel_key'] ?? '—'),
                                'Target: '.($result['target'] ?? '—'),
                                'Publish mode: '.($result['publish_mode'] ?? '—'),
                                'Style mode: '.($result['style_mode'] ?? '—'),
                                'Quiet hours: '.($result['quiet_hours_start'] ?? '—').' → '.($result['quiet_hours_end'] ?? '—'),
                            ])))
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Telegram config is invalid')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Actions\Action::make('validateAndDryRun')
                ->label('Validate + Dry-run')
                ->icon('heroicon-o-play-circle')
                ->color('success')
                ->requiresConfirmation()
                ->action(function (): void {
                    try {
                        $record = $this->record->loadMissing('platform');
                        $config = app(TelegramConfigValidator::class)->validate($record);

                        $post = PlannedPost::query()
                            ->where('platform_account_id', $record->getKey())
                            ->latest('id')
                            ->first();

                        if ($post === null) {
                            Notification::make()
                                ->title('Dry-run unavailable')
                                ->body('No planned posts found for this platform account yet.')
                                ->warning()
                                ->send();

                            return;
                        }

                        $dryRun = app(PublishingService::class)->dryRun($post);

                        Notification::make()
                            ->title($dryRun->eligible ? 'Validate + Dry-run succeeded' : 'Dry-run blocked')
                            ->body(trim(implode("\n", array_filter([
                                'Channel key: '.($config['channel_key'] ?? '—'),
                                'Target: '.($config['target'] ?? '—'),
                                'Post ID: '.$post->getKey(),
                                'Mode: '.$dryRun->mode,
                                $dryRun->reason ? 'Reason: '.$dryRun->reason : null,
                                $dryRun->cleanedText !== '' ? 'Text: '.$dryRun->cleanedText : null,
                            ]))))
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Validate + Dry-run failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Actions\Action::make('plan')
                ->label('Open plan')
                ->icon('heroicon-o-calendar-days')
                ->url(function (): string {
                    /** @var PostingPlan $plan */
                    $plan = $this->record->postingPlan;

                    if (auth()->user()?->can('update', $plan)) {
                        return PostingPlanResource::getUrl('edit', ['record' => $plan]);
                    }

                    return PostingPlanResource::getUrl('view', ['record' => $plan]);
                })
                ->visible(fn (): bool => $this->record->postingPlan !== null),
        ];
    }
}
