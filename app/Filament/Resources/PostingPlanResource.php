<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\PostingPlanResource\Pages;
use App\Filament\Resources\PostingPlanResource\RelationManagers\PostingSlotsRelationManager;
use App\Models\PostingPlan;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PostingPlanResource extends Resource
{
    protected static ?string $model = PostingPlan::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Plan')
                ->description('Core rules and quiet hours for this account')
                ->schema([
                    Forms\Components\Select::make('platform_account_id')
                        ->relationship('platformAccount', 'title')
                        ->label('Platform account')
                        ->disabled()
                        ->dehydrated(false),
                    Forms\Components\TextInput::make('timezone')
                        ->required()
                        ->default('UTC')
                        ->maxLength(255),
                    Forms\Components\TimePicker::make('quiet_hours_from')
                        ->seconds(false),
                    Forms\Components\TimePicker::make('quiet_hours_to')
                        ->seconds(false),
                    Forms\Components\Toggle::make('is_active')
                        ->label('Plan active'),
                ])
                ->columns(2),
            Forms\Components\Section::make('Rules')
                ->description('Expandable rule bag until we replace it with dedicated UI blocks')
                ->schema([
                    Forms\Components\KeyValue::make('rules')
                        ->keyLabel('Rule')
                        ->valueLabel('Value')
                        ->reorderable(),
                ]),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Plan')
                ->schema([
                    TextEntry::make('platformAccount.title')->label('Platform account'),
                    TextEntry::make('timezone'),
                    TextEntry::make('quiet_hours_from')->placeholder('—'),
                    TextEntry::make('quiet_hours_to')->placeholder('—'),
                    IconEntry::make('is_active')->label('Plan active')->boolean(),
                    TextEntry::make('slot_count')
                        ->label('Configured slots')
                        ->state(fn (PostingPlan $record): int => $record->postingSlots()->count()),
                    TextEntry::make('upcoming_slots_preview')
                        ->label('Upcoming schedule preview')
                        ->state(fn (PostingPlan $record): string => $record->upcomingSlotsPreview())
                        ->html()
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
                Tables\Columns\TextColumn::make('timezone')
                    ->searchable(),
                Tables\Columns\TextColumn::make('postingSlots_count')
                    ->counts('postingSlots')
                    ->label('Slots'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('updated_at', 'desc');
    }

    /**
     * @return array<int, class-string>
     */
    public static function getRelations(): array
    {
        return [
            PostingSlotsRelationManager::class,
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['platformAccount', 'postingSlots']);
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
            ->with(['platformAccount', 'postingSlots'])
            ->find($key);
    }

    /**
     * @return array<string, string>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPostingPlans::route('/'),
            'view' => Pages\ViewPostingPlan::route('/{record}'),
            'edit' => Pages\EditPostingPlan::route('/{record}/edit'),
        ];
    }
}
