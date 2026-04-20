<?php

declare(strict_types=1);

namespace App\Filament\Resources\PlatformAccountResource\RelationManagers;

use App\Enums\PlatformAccountRole;
use App\Models\AdminAuditLog;
use App\Models\PlatformAccount;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $title = 'Administrators';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('email')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\BadgeColumn::make('pivot.role')
                    ->label('Role')
                    ->colors([
                        'success' => PlatformAccountRole::Owner->value,
                        'warning' => PlatformAccountRole::Admin->value,
                        'gray' => PlatformAccountRole::Viewer->value,
                    ]),
                Tables\Columns\TextColumn::make('pivot.created_at')
                    ->label('Added')
                    ->dateTime()
                    ->sortable(),
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->label('Add administrator')
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['name', 'email'])
                    ->form(fn (Tables\Actions\AttachAction $action): array => [
                        $action->getRecordSelect()
                            ->label('User')
                            ->searchable()
                            ->preload()
                            ->optionsQuery(fn (Builder $query): Builder => $query->orderBy('name')->orderBy('email')),
                        Forms\Components\Select::make('role')
                            ->options(PlatformAccountRole::options())
                            ->default(PlatformAccountRole::Admin->value)
                            ->required(),
                    ])
                    ->visible(fn (): bool => $this->canManageAdministrators())
                    ->after(function (array $data): void {
                        $recordId = $data['recordId'] ?? null;

                        if ($recordId === null) {
                            return;
                        }

                        $user = User::query()->find($recordId);

                        AdminAuditLog::logAction(
                            action: 'administrator_attached',
                            userId: auth()->id(),
                            platformAccountId: $this->getPlatformAccount()->getKey(),
                            entityType: PlatformAccount::class,
                            entityId: $this->getPlatformAccount()->getKey(),
                            before: null,
                            after: [
                                'attached_user_id' => $user?->getKey(),
                                'attached_user_email' => $user?->email,
                                'role' => $data['role'] ?? null,
                            ],
                        );
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('changeRole')
                    ->label('Change role')
                    ->icon('heroicon-o-pencil-square')
                    ->form([
                        Forms\Components\Select::make('role')
                            ->options(PlatformAccountRole::options())
                            ->required(),
                    ])
                    ->fillForm(fn (User $record): array => ['role' => $record->pivot->role])
                    ->action(function (User $record, array $data): void {
                        $before = ['role' => $record->pivot->role];

                        $this->getPlatformAccount()->users()->updateExistingPivot($record->getKey(), [
                            'role' => $data['role'],
                        ]);

                        AdminAuditLog::logAction(
                            action: 'administrator_role_changed',
                            userId: auth()->id(),
                            platformAccountId: $this->getPlatformAccount()->getKey(),
                            entityType: PlatformAccount::class,
                            entityId: $this->getPlatformAccount()->getKey(),
                            before: $before,
                            after: [
                                'user_id' => $record->getKey(),
                                'user_email' => $record->email,
                                'role' => $data['role'],
                            ],
                        );
                    })
                    ->visible(fn (): bool => $this->canManageAdministrators()),
                Tables\Actions\DetachAction::make()
                    ->label('Remove')
                    ->visible(fn (User $record): bool => $this->canManageAdministrators() && $record->getKey() !== auth()->id())
                    ->after(function (User $record): void {
                        AdminAuditLog::logAction(
                            action: 'administrator_detached',
                            userId: auth()->id(),
                            platformAccountId: $this->getPlatformAccount()->getKey(),
                            entityType: PlatformAccount::class,
                            entityId: $this->getPlatformAccount()->getKey(),
                            before: [
                                'detached_user_id' => $record->getKey(),
                                'detached_user_email' => $record->email,
                                'role' => $record->pivot->role,
                            ],
                            after: null,
                        );
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('name');
    }

    public function isReadOnly(): bool
    {
        return ! $this->canManageAdministrators();
    }

    private function getPlatformAccount(): PlatformAccount
    {
        /** @var PlatformAccount $record */
        $record = $this->getOwnerRecord();

        return $record;
    }

    private function canManageAdministrators(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->canManageAdministrators($this->getPlatformAccount());
    }
}
