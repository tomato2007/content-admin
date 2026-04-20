<?php

declare(strict_types=1);

namespace App\Filament\Resources\PlatformAccountResource\RelationManagers;

use App\Filament\Resources\PostingHistoryResource;
use App\Models\PostingHistory;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PostingHistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'postingHistory';

    protected static ?string $title = 'Publishing history';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('status')
            ->columns([
                Tables\Columns\TextColumn::make('status')
                    ->badge(),
                Tables\Columns\TextColumn::make('attempt_type')
                    ->label('Attempt')
                    ->badge(),
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
                Tables\Columns\TextColumn::make('idempotency_key')
                    ->label('Idempotency key')
                    ->limit(28)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('error')
                    ->limit(60)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->headerActions([])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->icon('heroicon-o-eye')
                    ->url(fn (PostingHistory $record): string => PostingHistoryResource::getUrl('view', ['record' => $record])),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
