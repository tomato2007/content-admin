<?php

declare(strict_types=1);

namespace App\Filament\Resources\PlatformAccountResource\RelationManagers;

use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AdminAuditLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'adminAuditLogs';

    protected static ?string $title = 'Audit log';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('action')
            ->columns([
                Tables\Columns\TextColumn::make('action')
                    ->badge()
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Actor')
                    ->placeholder('system')
                    ->searchable(),
                Tables\Columns\TextColumn::make('entity_type')
                    ->label('Entity')
                    ->formatStateUsing(fn (string $state): string => class_basename($state)),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('before')
                    ->label('Before')
                    ->formatStateUsing(static fn ($state): string => $state === null ? '—' : json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
                    ->limit(60)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('after')
                    ->label('After')
                    ->formatStateUsing(static fn ($state): string => $state === null ? '—' : json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
                    ->limit(60)
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->options(fn (): array => $this->getOwnerRecord()->adminAuditLogs()
                        ->orderBy('action')
                        ->pluck('action', 'action')
                        ->all()),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
