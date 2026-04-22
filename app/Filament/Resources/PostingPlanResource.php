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
                        ->rule('timezone:all')
                        ->helperText('Use a valid PHP timezone identifier, for example UTC or Europe/Budapest.')
                        ->maxLength(255),
                    Forms\Components\TimePicker::make('quiet_hours_from')
                        ->label('Quiet hours from')
                        ->seconds(false),
                    Forms\Components\TimePicker::make('quiet_hours_to')
                        ->label('Quiet hours to')
                        ->seconds(false),
                    Forms\Components\Toggle::make('is_active')
                        ->label('Plan active'),
                ])
                ->columns(2),
            Forms\Components\Section::make('Rules')
                ->description('Structured publishing rules stored internally inside the rules JSON bag')
                ->schema([
                    Forms\Components\TagsInput::make('rules_content_mix')
                        ->label('Content mix')
                        ->placeholder('memes')
                        ->helperText('Examples: memes, stories, reposts. Stored internally as rules.content_mix')
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('rules_max_posts_per_day')
                        ->label('Max posts per day')
                        ->numeric()
                        ->minValue(1),
                    Forms\Components\Select::make('rules_approval_mode')
                        ->label('Approval mode')
                        ->options([
                            'manual' => 'Manual',
                            'assisted' => 'Assisted',
                            'auto' => 'Auto',
                        ])
                        ->placeholder('Not set'),
                    Forms\Components\Placeholder::make('rules_storage_note')
                        ->label('JSON storage')
                        ->content('Unknown rule keys remain preserved in JSON storage even though they are no longer edited as raw key/value pairs.')
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Overview')
                ->schema([
                    TextEntry::make('platformAccount.title')->label('Platform account'),
                    TextEntry::make('timezone'),
                    TextEntry::make('plan_status_summary')
                        ->label('Plan status')
                        ->state(fn (PostingPlan $record): string => $record->planStatusSummary()),
                    IconEntry::make('is_active')->label('Plan active')->boolean(),
                    TextEntry::make('next_active_slot')
                        ->label('Next active slot')
                        ->state(fn (PostingPlan $record): string => $record->nextActiveSlotLabel()),
                    TextEntry::make('weekly_cadence_summary')
                        ->label('Weekly cadence')
                        ->state(fn (PostingPlan $record): string => $record->weeklyCadenceSummary())
                        ->columnSpanFull(),
                    TextEntry::make('quiet_hours_summary')
                        ->label('Quiet hours')
                        ->state(fn (PostingPlan $record): string => $record->quietHoursSummary()),
                    TextEntry::make('publishing_rules_summary')
                        ->label('Publishing rules')
                        ->state(fn (PostingPlan $record): string => $record->publishingRulesSummary()),
                    TextEntry::make('slot_count')
                        ->label('Configured slots')
                        ->state(fn (PostingPlan $record): int => $record->postingSlots()->count()),
                    TextEntry::make('content_mix_summary')
                        ->label('Content mix')
                        ->state(fn (PostingPlan $record): string => $record->contentMixSummary()),
                    TextEntry::make('additional_rules_count')
                        ->label('Additional hidden rule keys')
                        ->state(fn (PostingPlan $record): int => $record->additionalRulesCount()),
                ])
                ->columns(2),
            Section::make('Schedule Preview')
                ->schema([
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
