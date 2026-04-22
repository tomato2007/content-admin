<?php

declare(strict_types=1);

namespace App\Filament\Resources\PlatformAccountResource\RelationManagers;

use App\Enums\PlatformAccountRole;
use App\Features\PlatformAccounts\Application\Actions\AttachPlatformAccountAdministratorAction;
use App\Features\PlatformAccounts\Application\Actions\ChangePlatformAccountAdministratorRoleAction;
use App\Features\PlatformAccounts\Application\Actions\DetachPlatformAccountAdministratorAction;
use App\Models\PlatformAccount;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use RuntimeException;

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
                Tables\Actions\Action::make('addAdministrator')
                    ->label('Add administrator')
                    ->icon('heroicon-o-user-plus')
                    ->form([
                        Forms\Components\Select::make('user_id')
                            ->label('User')
                            ->searchable()
                            ->preload()
                            ->options(fn (): array => User::query()
                                ->whereNotIn('id', $this->getPlatformAccount()->users()->pluck('users.id'))
                                ->orderBy('name')
                                ->orderBy('email')
                                ->get()
                                ->mapWithKeys(fn (User $user): array => [$user->getKey() => trim($user->name.' <'.$user->email.'>')])
                                ->all())
                            ->required(),
                        Forms\Components\Select::make('role')
                            ->options(PlatformAccountRole::options())
                            ->default(PlatformAccountRole::Admin->value)
                            ->required(),
                    ])
                    ->visible(fn (): bool => $this->canManageAdministrators())
                    ->action(function (array $data): void {
                        $user = User::query()->findOrFail($data['user_id']);

                        try {
                            app(AttachPlatformAccountAdministratorAction::class)->execute(
                                $this->getPlatformAccount(),
                                $user,
                                PlatformAccountRole::from((string) $data['role']),
                                $this->getActor(),
                            );

                            Notification::make()
                                ->title('Administrator added')
                                ->success()
                                ->send();
                        } catch (RuntimeException $exception) {
                            Notification::make()
                                ->title('Cannot add administrator')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
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
                        try {
                            app(ChangePlatformAccountAdministratorRoleAction::class)->execute(
                                $this->getPlatformAccount(),
                                $record,
                                PlatformAccountRole::from((string) $data['role']),
                                $this->getActor(),
                            );

                            Notification::make()
                                ->title('Administrator role updated')
                                ->success()
                                ->send();
                        } catch (RuntimeException $exception) {
                            Notification::make()
                                ->title('Cannot change administrator role')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(fn (): bool => $this->canManageAdministrators()),
                Tables\Actions\DetachAction::make()
                    ->label('Remove')
                    ->visible(fn (User $record): bool => $this->canManageAdministrators() && $record->getKey() !== auth()->id())
                    ->action(function (User $record): void {
                        try {
                            app(DetachPlatformAccountAdministratorAction::class)->execute(
                                $this->getPlatformAccount(),
                                $record,
                                $this->getActor(),
                            );

                            Notification::make()
                                ->title('Administrator removed')
                                ->success()
                                ->send();
                        } catch (RuntimeException $exception) {
                            Notification::make()
                                ->title('Cannot remove administrator')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
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

    private function getActor(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
