<?php

declare(strict_types=1);

namespace App\Filament\Resources\PostingPlanResource\RelationManagers;

use App\Models\PostingPlan;
use App\Models\PostingSlot;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PostingSlotsRelationManager extends RelationManager
{
    protected static string $relationship = 'postingSlots';

    protected static ?string $title = 'Posting slots';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('weekday')
                ->options([
                    1 => 'Monday',
                    2 => 'Tuesday',
                    3 => 'Wednesday',
                    4 => 'Thursday',
                    5 => 'Friday',
                    6 => 'Saturday',
                    7 => 'Sunday',
                ])
                ->required(),
            Forms\Components\TimePicker::make('time_local')
                ->label('Local time')
                ->seconds(false)
                ->required(),
            Forms\Components\Toggle::make('is_enabled')
                ->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('time_local')
            ->columns([
                Tables\Columns\TextColumn::make('weekday')
                    ->formatStateUsing(fn (int $state, PostingSlot $record): string => $record->weekdayLabel())
                    ->sortable(),
                Tables\Columns\TextColumn::make('time_local')
                    ->label('Local time')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_enabled')
                    ->label('Enabled')
                    ->boolean(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add slot')
                    ->visible(fn (): bool => $this->canManageSlots()),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn (): bool => $this->canManageSlots()),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (): bool => $this->canManageSlots()),
            ])
            ->bulkActions([])
            ->defaultSort('weekday');
    }

    public function isReadOnly(): bool
    {
        return ! $this->canManageSlots();
    }

    private function canManageSlots(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->canManagePostingPlan($this->getPostingPlan()->platformAccount);
    }

    private function getPostingPlan(): PostingPlan
    {
        /** @var PostingPlan $record */
        $record = $this->getOwnerRecord();

        return $record;
    }
}
