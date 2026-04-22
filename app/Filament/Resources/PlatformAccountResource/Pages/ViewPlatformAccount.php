<?php

declare(strict_types=1);

namespace App\Filament\Resources\PlatformAccountResource\Pages;

use App\Features\PlatformAccounts\Application\Actions\ConnectTelegramBotAction;
use App\Features\Publishing\Application\Actions\DryRunPlannedPostAction;
use App\Filament\Resources\PlatformAccountResource;
use App\Filament\Resources\PostingPlanResource;
use App\Models\PlannedPost;
use App\Models\PostingPlan;
use App\Models\User;
use App\Services\Publishing\PlatformAwarePublishingConfigValidator;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewPlatformAccount extends ViewRecord
{
    protected static string $resource = PlatformAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\Action::make('connectTelegramBot')
                ->label(fn (): string => $this->record->hasConnectedTelegramBot() ? 'Reconnect Telegram bot' : 'Connect Telegram bot')
                ->icon('heroicon-o-key')
                ->color('warning')
                ->visible(fn (): bool => $this->record->platform?->driver === 'telegram' && $this->getActor()?->can('update', $this->record) === true)
                ->form([
                    Forms\Components\TextInput::make('bot_token')
                        ->label('Bot token')
                        ->password()
                        ->revealable()
                        ->required()
                        ->helperText('Paste the token from BotFather. The token is validated with Telegram Bot API and stored encrypted.'),
                ])
                ->action(function (array $data): void {
                    try {
                        $account = app(ConnectTelegramBotAction::class)->execute(
                            $this->record,
                            (string) $data['bot_token'],
                            $this->getActor(),
                        );

                        $this->record = $account;

                        Notification::make()
                            ->title('Telegram bot connected')
                            ->body(trim(implode("\n", array_filter([
                                'Bot: '.$account->telegramBotDisplayName(),
                                'Credentials ref: '.($account->credentials_ref ?? '—'),
                            ]))))
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Telegram bot connection failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Actions\Action::make('validatePublishingConfig')
                ->label('Validate publishing config')
                ->icon('heroicon-o-shield-check')
                ->color('gray')
                ->action(function (): void {
                    try {
                        $result = app(PlatformAwarePublishingConfigValidator::class)->validate($this->record->loadMissing('platform'));

                        Notification::make()
                            ->title('Publishing config is valid')
                            ->body(trim(implode("\n", $result['summary_lines'] ?? [])))
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Publishing config is invalid')
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
                        $config = app(PlatformAwarePublishingConfigValidator::class)->validate($record);

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

                        $dryRun = app(DryRunPlannedPostAction::class)->execute($post);

                        Notification::make()
                            ->title($dryRun->eligible ? 'Validate + Dry-run succeeded' : 'Dry-run blocked')
                            ->body(trim(implode("\n", array_filter([
                                ...($config['summary_lines'] ?? []),
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

    private function getActor(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
