<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\PlatformAccountResource\Pages;
use App\Filament\Resources\PlatformAccountResource\RelationManagers\AdminAuditLogsRelationManager;
use App\Filament\Resources\PlatformAccountResource\RelationManagers\PlannedPostsRelationManager;
use App\Filament\Resources\PlatformAccountResource\RelationManagers\PostingHistoryRelationManager;
use App\Filament\Resources\PlatformAccountResource\RelationManagers\UsersRelationManager;
use App\Models\Platform;
use App\Models\PlatformAccount;
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

class PlatformAccountResource extends Resource
{
    protected static ?string $model = PlatformAccount::class;

    protected static ?string $navigationIcon = 'heroicon-o-folder';

    protected static ?string $navigationGroup = 'Projects';

    protected static ?string $modelLabel = 'platform account';

    protected static ?string $pluralModelLabel = 'platform accounts';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Account')
                ->description('Core platform and channel/account settings')
                ->schema([
                    Forms\Components\Select::make('platform_id')
                        ->relationship('platform', 'name')
                        ->required()
                        ->preload()
                        ->searchable(),
                    Forms\Components\TextInput::make('title')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('external_id')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('handle')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('credentials_ref')
                        ->label('Credentials reference')
                        ->helperText('Alias or external secret reference, not the raw token')
                        ->maxLength(255),
                    Forms\Components\Toggle::make('is_enabled')
                        ->label('Enabled')
                        ->default(true),
                ])
                ->columns(2),
            Forms\Components\Section::make('Telegram publishing')
                ->description('Telegram-specific publishing configuration for this platform account')
                ->visible(fn (Forms\Get $get): bool => static::selectedPlatformDriver($get) === 'telegram')
                ->schema([
                    Forms\Components\TextInput::make('settings.channel_key')
                        ->label('Channel key')
                        ->helperText('Key from the configured Telegram runtime channel config table, for example humor-story-mems-v2')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('settings.target_chat_id')
                        ->label('Target chat / channel')
                        ->helperText('Telegram target like @anecdots_mems, overrides config target when set')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('settings.source_chat')
                        ->label('Default source chat')
                        ->helperText('Optional default source chat for media lookup when planned post snapshot does not include it')
                        ->maxLength(255),
                    Forms\Components\Toggle::make('settings.force_publish')
                        ->label('Allow force publish outside quiet hours')
                        ->default(false),
                ])
                ->columns(2),
            Forms\Components\Section::make('VK publishing')
                ->description('VK-specific publishing configuration for this platform account')
                ->visible(fn (Forms\Get $get): bool => static::selectedPlatformDriver($get) === 'vk')
                ->schema([
                    Forms\Components\TextInput::make('settings.community_id')
                        ->label('Community ID')
                        ->helperText('Numeric VK owner/group identifier, for example -123456789')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('settings.screen_name')
                        ->label('Screen name')
                        ->helperText('Optional VK screen name for operator context')
                        ->maxLength(255),
                    Forms\Components\Toggle::make('settings.force_publish')
                        ->label('Allow force publish outside quiet hours')
                        ->default(false),
                ])
                ->columns(2),
            Forms\Components\Section::make('X publishing')
                ->description('X-specific publishing configuration for this platform account')
                ->visible(fn (Forms\Get $get): bool => static::selectedPlatformDriver($get) === 'x')
                ->schema([
                    Forms\Components\TextInput::make('settings.account_username')
                        ->label('Account username')
                        ->helperText('X handle, with or without @')
                        ->maxLength(255),
                    Forms\Components\Select::make('settings.thread_mode')
                        ->label('Thread mode')
                        ->options([
                            'single' => 'Single post',
                            'thread' => 'Thread',
                        ])
                        ->placeholder('Single post'),
                    Forms\Components\Toggle::make('settings.force_publish')
                        ->label('Allow force publish outside quiet hours')
                        ->default(false),
                ])
                ->columns(2),
            Forms\Components\Section::make('Advanced settings')
                ->description('Raw platform-specific settings bag for non-standard options')
                ->schema([
                    Forms\Components\KeyValue::make('settings')
                        ->label('Platform settings')
                        ->keyLabel('Key')
                        ->valueLabel('Value')
                        ->reorderable(),
                ]),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Overview')
                ->schema([
                    TextEntry::make('platform.name')->label('Platform'),
                    TextEntry::make('title'),
                    TextEntry::make('external_id')->label('External ID'),
                    TextEntry::make('handle')->placeholder('—'),
                    TextEntry::make('credentials_ref')->label('Credentials reference')->placeholder('—'),
                    IconEntry::make('is_enabled')->label('Enabled')->boolean(),
                    TextEntry::make('owners')
                        ->label('Owners')
                        ->state(fn (PlatformAccount $record): string => $record->ownerNameList()),
                    TextEntry::make('admins_count')
                        ->label('Administrators')
                        ->state(fn (PlatformAccount $record): int => $record->administratorsCount()),
                ])
                ->columns(2),
            Section::make('Publishing plan')
                ->schema([
                    TextEntry::make('postingPlan.timezone')->label('Timezone')->placeholder('—'),
                    IconEntry::make('postingPlan.is_active')->label('Plan active')->boolean(),
                    TextEntry::make('postingPlan.quiet_hours_from')->label('Quiet hours from')->placeholder('—'),
                    TextEntry::make('postingPlan.quiet_hours_to')->label('Quiet hours to')->placeholder('—'),
                    TextEntry::make('postingPlan.postingSlots_count')
                        ->label('Active slots')
                        ->state(fn (PlatformAccount $record): int => $record->postingPlan?->postingSlots()->where('is_enabled', true)->count() ?? 0),
                    TextEntry::make('postingPlan_upcoming_slots')
                        ->label('Upcoming schedule preview')
                        ->state(fn (PlatformAccount $record): string => $record->postingPlan?->upcomingSlotsPreview() ?? 'No plan available')
                        ->html()
                        ->columnSpanFull(),
                ])
                ->columns(2),
            Section::make('Telegram publishing config')
                ->visible(fn (PlatformAccount $record): bool => $record->platform?->driver === 'telegram')
                ->schema([
                    TextEntry::make('settings.channel_key')
                        ->label('Channel key')
                        ->placeholder('—'),
                    TextEntry::make('settings.target_chat_id')
                        ->label('Target chat / channel')
                        ->placeholder('—'),
                    TextEntry::make('settings.source_chat')
                        ->label('Default source chat')
                        ->placeholder('—'),
                    IconEntry::make('telegram_force_publish')
                        ->label('Force publish outside quiet hours')
                        ->state(fn (PlatformAccount $record): bool => (bool) ($record->settings['force_publish'] ?? false))
                        ->boolean(),
                    TextEntry::make('telegram_bot_display_name')
                        ->label('Connected bot')
                        ->state(fn (PlatformAccount $record): string => $record->telegramBotDisplayName()),
                    TextEntry::make('telegram_bot_connected_at')
                        ->label('Bot connected at')
                        ->dateTime()
                        ->placeholder('—'),
                ])
                ->columns(2),
            Section::make('VK publishing config')
                ->visible(fn (PlatformAccount $record): bool => $record->platform?->driver === 'vk')
                ->schema([
                    TextEntry::make('settings.community_id')
                        ->label('Community ID')
                        ->placeholder('—'),
                    TextEntry::make('settings.screen_name')
                        ->label('Screen name')
                        ->placeholder('—'),
                    IconEntry::make('vk_force_publish')
                        ->label('Force publish outside quiet hours')
                        ->state(fn (PlatformAccount $record): bool => (bool) ($record->settings['force_publish'] ?? false))
                        ->boolean(),
                ])
                ->columns(2),
            Section::make('X publishing config')
                ->visible(fn (PlatformAccount $record): bool => $record->platform?->driver === 'x')
                ->schema([
                    TextEntry::make('settings.account_username')
                        ->label('Account username')
                        ->placeholder('—'),
                    TextEntry::make('settings.thread_mode')
                        ->label('Thread mode')
                        ->placeholder('single'),
                    IconEntry::make('x_force_publish')
                        ->label('Force publish outside quiet hours')
                        ->state(fn (PlatformAccount $record): bool => (bool) ($record->settings['force_publish'] ?? false))
                        ->boolean(),
                ])
                ->columns(2),
            Section::make('Moderation queue')
                ->schema([
                    TextEntry::make('planned_posts_total')
                        ->label('Planned posts')
                        ->state(fn (PlatformAccount $record): int => $record->plannedPosts()->count()),
                    TextEntry::make('planned_posts_pending')
                        ->label('Pending review')
                        ->state(fn (PlatformAccount $record): int => $record->plannedPosts()->where('moderation_status', 'pending_review')->count()),
                    TextEntry::make('planned_posts_delete_requested')
                        ->label('Delete requested')
                        ->state(fn (PlatformAccount $record): int => $record->plannedPosts()->where('moderation_status', 'delete_requested')->count()),
                ])
                ->columns(3),
            Section::make('Metadata')
                ->schema([
                    TextEntry::make('created_at')->dateTime(),
                    TextEntry::make('updated_at')->dateTime(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('platform.name')
                    ->label('Platform')
                    ->searchable(),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('external_id')
                    ->label('External ID')
                    ->searchable(),
                Tables\Columns\TextColumn::make('handle')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_enabled')
                    ->label('Enabled')
                    ->boolean(),
                Tables\Columns\TextColumn::make('users_count')
                    ->counts('users')
                    ->label('Admins'),
                Tables\Columns\TextColumn::make('postingPlan.timezone')
                    ->label('Timezone')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('plan')
                    ->label('Plan')
                    ->icon('heroicon-o-calendar-days')
                    ->url(function (PlatformAccount $record): string {
                        $plan = $record->postingPlan;

                        if ($plan === null) {
                            return '#';
                        }

                        if (auth()->user()?->can('update', $plan)) {
                            return PostingPlanResource::getUrl('edit', ['record' => $plan]);
                        }

                        return PostingPlanResource::getUrl('view', ['record' => $plan]);
                    })
                    ->visible(fn (PlatformAccount $record): bool => $record->postingPlan !== null),
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
            PlannedPostsRelationManager::class,
            UsersRelationManager::class,
            PostingHistoryRelationManager::class,
            AdminAuditLogsRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['platform', 'postingPlan']);
        $user = auth()->user();

        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('users', function (Builder $builder) use ($user): void {
            $builder->whereKey($user->getKey());
        });
    }

    public static function resolveRecordRouteBinding(int|string $key): ?Model
    {
        return static::getModel()::query()
            ->with(['platform', 'postingPlan'])
            ->find($key);
    }

    /**
     * @return array<string, string>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlatformAccounts::route('/'),
            'create' => Pages\CreatePlatformAccount::route('/create'),
            'view' => Pages\ViewPlatformAccount::route('/{record}'),
            'edit' => Pages\EditPlatformAccount::route('/{record}/edit'),
        ];
    }

    private static function selectedPlatformDriver(Forms\Get $get): ?string
    {
        $platformId = $get('platform_id');

        if (! is_numeric($platformId)) {
            return null;
        }

        return Platform::query()
            ->whereKey((int) $platformId)
            ->value('driver');
    }
}
