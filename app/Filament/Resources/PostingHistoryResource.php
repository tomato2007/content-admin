<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\PostingHistoryStatus;
use App\Filament\Resources\PostingHistoryResource\Pages;
use App\Models\PostingHistory;
use App\Models\User;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PostingHistoryResource extends Resource
{
    protected static ?string $model = PostingHistory::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('History entry')
                ->schema([
                    TextEntry::make('platformAccount.title')->label('Platform account'),
                    TextEntry::make('planned_post_id')->label('Planned post ID')->placeholder('—'),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('attempt_type')->label('Attempt type')->placeholder('—'),
                    TextEntry::make('scheduled_at')->dateTime()->placeholder('—'),
                    TextEntry::make('sent_at')->dateTime()->placeholder('—'),
                    TextEntry::make('provider_message_id')->label('Provider message ID')->placeholder('—'),
                    TextEntry::make('idempotency_key')->label('Idempotency key')->placeholder('—')->columnSpanFull(),
                    TextEntry::make('error')->placeholder('—')->columnSpanFull(),
                    TextEntry::make('payload')
                        ->formatStateUsing(static fn ($state): string => $state === null ? '—' : json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
                        ->columnSpanFull(),
                    TextEntry::make('response')
                        ->formatStateUsing(static fn ($state): string => $state === null ? '—' : json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('platformAccount.title')
                    ->label('Platform account')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge(),
                Tables\Columns\TextColumn::make('attempt_type')
                    ->label('Attempt')
                    ->badge()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('planned_post_id')
                    ->label('Planned post')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('scheduled_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sent_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('provider_message_id')
                    ->label('Provider ID')
                    ->searchable(),
                Tables\Columns\TextColumn::make('error')
                    ->limit(60)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(PostingHistoryStatus::options()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with('platformAccount');
        $user = auth()->user();

        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('platformAccount.users', function (Builder $builder) use ($user): void {
            $builder->whereKey($user->getKey());
        });
    }

    public static function resolveRecordRouteBinding(int|string $key): ?Model
    {
        return static::getModel()::query()
            ->with('platformAccount')
            ->find($key);
    }

    /**
     * @return array<string, string>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPostingHistories::route('/'),
            'view' => Pages\ViewPostingHistory::route('/{record}'),
        ];
    }
}
