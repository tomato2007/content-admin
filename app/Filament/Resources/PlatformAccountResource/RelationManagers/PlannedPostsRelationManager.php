<?php

declare(strict_types=1);

namespace App\Filament\Resources\PlatformAccountResource\RelationManagers;

use App\Enums\ModerationStatus;
use App\Enums\PlannedPostStatus;
use App\Models\PlannedPost;
use App\Models\PlatformAccount;
use App\Models\User;
use App\Services\PlannedPostWorkflowService;
use App\Services\Publishing\PublishingService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

class PlannedPostsRelationManager extends RelationManager
{
    protected static string $relationship = 'plannedPosts';

    protected static ?string $title = 'Queue / Moderation';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('source_type')
                ->required()
                ->default('manual')
                ->maxLength(255),
            Forms\Components\TextInput::make('source_id')
                ->maxLength(255),
            Forms\Components\DateTimePicker::make('scheduled_at')
                ->seconds(false)
                ->native(false),
            Forms\Components\Textarea::make('content_text')
                ->label('Prepared content')
                ->rows(6)
                ->required()
                ->columnSpanFull(),
            Forms\Components\KeyValue::make('content_snapshot')
                ->label('Content snapshot')
                ->keyLabel('Key')
                ->valueLabel('Value')
                ->reorderable(),
            Forms\Components\Textarea::make('notes')
                ->rows(4)
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('content_text')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['approver', 'replacementOf']))
            ->columns([
                Tables\Columns\TextColumn::make('scheduled_at')
                    ->label('Scheduled')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge(),
                Tables\Columns\TextColumn::make('moderation_status')
                    ->badge()
                    ->label('Moderation'),
                Tables\Columns\TextColumn::make('content_preview')
                    ->label('Content')
                    ->state(fn (PlannedPost $record): string => $record->contentPreview(90))
                    ->wrap(),
                Tables\Columns\TextColumn::make('source_type')
                    ->label('Source'),
                Tables\Columns\TextColumn::make('approver.name')
                    ->label('Approved by')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('replacementOf.id')
                    ->label('Replaces')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(PlannedPostStatus::options()),
                SelectFilter::make('moderation_status')
                    ->options(ModerationStatus::options()),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add planned post')
                    ->visible(fn (): bool => $this->canManageQueue()),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn (): bool => $this->canManageQueue()),
                Tables\Actions\Action::make('dryRun')
                    ->label('Dry run')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->action(function (PlannedPost $record): void {
                        try {
                            $result = app(PublishingService::class)->dryRun($record);

                            Notification::make()
                                ->title($result->eligible ? 'Dry run ready' : 'Dry run blocked')
                                ->body(trim(implode("\n", array_filter([
                                    'Mode: '.$result->mode,
                                    $result->reason ? 'Reason: '.$result->reason : null,
                                    $result->cleanedText !== '' ? 'Text: '.$result->cleanedText : null,
                                ]))))
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Dry run failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(fn (PlannedPost $record): bool => $this->canManageQueue() && ! in_array($record->status, [PlannedPostStatus::Cancelled, PlannedPostStatus::Replaced], true)),
                Tables\Actions\Action::make('publishNow')
                    ->label('Publish now')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Toggle::make('force')
                            ->label('Force publish outside quiet hours')
                            ->default(fn (): bool => (bool) ($this->getPlatformAccount()->settings['force_publish'] ?? false)),
                    ])
                    ->action(function (PlannedPost $record, array $data): void {
                        try {
                            $result = app(PublishingService::class)->publish(
                                $record,
                                $this->getActor()->getKey(),
                                (bool) ($data['force'] ?? false),
                                'manual',
                                'planned-post-'.$record->getKey().'-manual'
                            );

                            Notification::make()
                                ->title($result->success ? 'Post published' : 'Publish failed')
                                ->body($result->success
                                    ? 'Provider message ID: '.($result->providerMessageId ?? '—')
                                    : ($result->error ?? 'Unknown error'))
                                ->success()
                                ->send();
                        } catch (RuntimeException $e) {
                            Notification::make()
                                ->title('Publish blocked')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Publish failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(fn (PlannedPost $record): bool => $this->canManageQueue() && $record->moderation_status === ModerationStatus::Approved && ! in_array($record->status, [PlannedPostStatus::Published, PlannedPostStatus::Cancelled, PlannedPostStatus::Replaced], true)),
                Tables\Actions\Action::make('retryPublish')
                    ->label('Retry publish')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Toggle::make('force')
                            ->label('Force publish outside quiet hours')
                            ->default(fn (): bool => (bool) ($this->getPlatformAccount()->settings['force_publish'] ?? false)),
                    ])
                    ->action(function (PlannedPost $record, array $data): void {
                        try {
                            $result = app(PublishingService::class)->publish(
                                $record,
                                $this->getActor()->getKey(),
                                (bool) ($data['force'] ?? false),
                                'retry',
                                'planned-post-'.$record->getKey().'-retry-'.now()->timestamp
                            );

                            Notification::make()
                                ->title($result->success ? 'Retry published' : 'Retry failed')
                                ->body($result->success
                                    ? 'Provider message ID: '.($result->providerMessageId ?? '—')
                                    : ($result->error ?? 'Unknown error'))
                                ->success()
                                ->send();
                        } catch (RuntimeException $e) {
                            Notification::make()
                                ->title('Retry blocked')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Retry failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(fn (PlannedPost $record): bool => $this->canManageQueue() && $record->moderation_status === ModerationStatus::Approved && $record->status === PlannedPostStatus::Failed),
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(fn (PlannedPost $record) => app(PlannedPostWorkflowService::class)->approve($record, $this->getActor()))
                    ->visible(fn (PlannedPost $record): bool => $this->canManageQueue() && in_array($record->moderation_status, [ModerationStatus::PendingReview, ModerationStatus::NeedsReplacement], true)),
                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->form([
                        Forms\Components\Textarea::make('notes')
                            ->label('Rejection reason')
                            ->rows(3),
                    ])
                    ->action(fn (PlannedPost $record, array $data) => app(PlannedPostWorkflowService::class)->reject($record, $this->getActor(), $data['notes'] ?? null))
                    ->visible(fn (PlannedPost $record): bool => $this->canManageQueue() && $record->status !== PlannedPostStatus::Cancelled),
                Tables\Actions\Action::make('requestDelete')
                    ->label('Request delete')
                    ->icon('heroicon-o-trash')
                    ->form([
                        Forms\Components\Textarea::make('notes')
                            ->label('Reason')
                            ->rows(3),
                    ])
                    ->action(fn (PlannedPost $record, array $data) => app(PlannedPostWorkflowService::class)->requestDelete($record, $this->getActor(), $data['notes'] ?? null))
                    ->visible(fn (PlannedPost $record): bool => $this->canManageQueue() && ! in_array($record->moderation_status, [ModerationStatus::DeleteRequested, ModerationStatus::DeleteConfirmed], true)),
                Tables\Actions\Action::make('confirmDelete')
                    ->label('Confirm delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('notes')
                            ->label('Confirmation note')
                            ->rows(3),
                    ])
                    ->action(fn (PlannedPost $record, array $data) => app(PlannedPostWorkflowService::class)->confirmDelete($record, $this->getActor(), $data['notes'] ?? null))
                    ->visible(fn (PlannedPost $record): bool => $this->canManageQueue() && $record->moderation_status === ModerationStatus::DeleteRequested),
                Tables\Actions\Action::make('replace')
                    ->label('Replace')
                    ->icon('heroicon-o-arrow-path-rounded-square')
                    ->form([
                        Forms\Components\TextInput::make('source_type')
                            ->required()
                            ->default('manual')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('source_id')
                            ->maxLength(255),
                        Forms\Components\DateTimePicker::make('scheduled_at')
                            ->seconds(false)
                            ->native(false),
                        Forms\Components\Textarea::make('content_text')
                            ->label('Replacement content')
                            ->rows(6)
                            ->required()
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('reason')
                            ->label('Replacement reason')
                            ->rows(3),
                        Forms\Components\Textarea::make('notes')
                            ->rows(3),
                    ])
                    ->fillForm(fn (PlannedPost $record): array => [
                        'source_type' => $record->source_type,
                        'source_id' => $record->source_id,
                        'scheduled_at' => $record->scheduled_at,
                        'content_text' => $record->content_text,
                    ])
                    ->action(fn (PlannedPost $record, array $data) => app(PlannedPostWorkflowService::class)->replace($record, $this->getActor(), $data))
                    ->visible(fn (PlannedPost $record): bool => $this->canManageQueue() && ! in_array($record->status, [PlannedPostStatus::Published, PlannedPostStatus::Replaced], true)),
                Tables\Actions\Action::make('reschedule')
                    ->label('Reschedule')
                    ->icon('heroicon-o-calendar')
                    ->form([
                        Forms\Components\DateTimePicker::make('scheduled_at')
                            ->seconds(false)
                            ->native(false)
                            ->required(),
                        Forms\Components\Textarea::make('notes')
                            ->rows(3),
                    ])
                    ->fillForm(fn (PlannedPost $record): array => ['scheduled_at' => $record->scheduled_at])
                    ->action(fn (PlannedPost $record, array $data) => app(PlannedPostWorkflowService::class)->reschedule($record, $this->getActor(), (string) $data['scheduled_at'], $data['notes'] ?? null))
                    ->visible(fn (PlannedPost $record): bool => $this->canManageQueue() && ! in_array($record->status, [PlannedPostStatus::Cancelled, PlannedPostStatus::Published], true)),
            ])
            ->bulkActions([])
            ->defaultSort('scheduled_at', 'desc');
    }

    public function isReadOnly(): bool
    {
        return ! $this->canManageQueue();
    }

    private function canManageQueue(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->canManagePostingPlan($this->getPlatformAccount());
    }

    private function getPlatformAccount(): PlatformAccount
    {
        /** @var PlatformAccount $record */
        $record = $this->getOwnerRecord();

        return $record;
    }

    private function getActor(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
