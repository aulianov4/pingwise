<?php

namespace App\Filament\Resources\Servers;

use App\Enums\ProjectRole;
use App\Filament\Resources\Servers\Pages\CreateServer;
use App\Filament\Resources\Servers\Pages\EditServer;
use App\Filament\Resources\Servers\Pages\ListServers;
use App\Filament\Resources\Servers\Pages\ViewServer;
use App\Models\NotificationChannel;
use App\Models\Project;
use App\Models\Server;
use App\Models\User;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ServerResource extends Resource
{
    protected static ?string $model = Server::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    protected static ?string $navigationLabel = 'Серверы';

    protected static ?string $modelLabel = 'сервер';

    protected static ?string $pluralModelLabel = 'Серверы';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        /** @var User $user */
        $user = Auth::user();
        $defaults = Server::defaultSettings();

        return $schema
            ->schema([
                Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Название')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('project_id')
                            ->label('Проект')
                            ->options(function () use ($user): array {
                                if ($user->isSuperadmin()) {
                                    return Project::query()->orderBy('name')->pluck('name', 'id')->all();
                                }

                                return $user->projects()
                                    ->wherePivot('role', ProjectRole::Admin->value)
                                    ->orderBy('name')
                                    ->pluck('projects.name', 'projects.id')
                                    ->all();
                            })
                            ->required()
                            ->searchable()
                            ->disabledOn('edit'),
                    ])
                    ->columnSpanFull(),
                Forms\Components\Toggle::make('is_active')
                    ->label('Активен')
                    ->default(true)
                    ->inline(false)
                    ->columnSpanFull(),
                Section::make('Пороги и интервалы')
                    ->schema([
                        Forms\Components\TextInput::make('settings.heartbeat_interval_minutes')
                            ->label('Интервал пинга (мин)')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(1440)
                            ->default($defaults['heartbeat_interval_minutes']),
                        Forms\Components\TextInput::make('settings.silence_timeout_minutes')
                            ->label('Тишина до алерта (мин)')
                            ->numeric()
                            ->required()
                            ->minValue(5)
                            ->maxValue(1440)
                            ->default($defaults['silence_timeout_minutes'])
                            ->helperText('Нет пинга столько минут — статус failed.'),
                        Forms\Components\TextInput::make('settings.disk_warning_percent')
                            ->label('Диск warning, %')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(100)
                            ->default($defaults['disk_warning_percent']),
                        Forms\Components\TextInput::make('settings.disk_critical_percent')
                            ->label('Диск critical, %')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(100)
                            ->default($defaults['disk_critical_percent']),
                        Forms\Components\TextInput::make('settings.memory_warning_percent')
                            ->label('RAM warning, % доступно')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(100)
                            ->default($defaults['memory_warning_percent'])
                            ->helperText('Алерт, если MemAvailable не больше этого процента.'),
                        Forms\Components\TextInput::make('settings.memory_critical_percent')
                            ->label('RAM critical, % доступно')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(100)
                            ->default($defaults['memory_critical_percent']),
                        Forms\Components\TagsInput::make('settings.monitored_mounts')
                            ->label('Точки монтирования')
                            ->default($defaults['monitored_mounts'])
                            ->helperText('Пусто — все диски от агента. По умолчанию только /.')
                            ->columnSpanFull(),
                        Forms\Components\Toggle::make('settings.load_alert_enabled')
                            ->label('Алертить по load15')
                            ->default(false)
                            ->inline(false)
                            ->helperText('По умолчанию нагрузка только на графике.'),
                        Forms\Components\TextInput::make('settings.load_warning_per_core')
                            ->label('Load15 на ядро для warning')
                            ->numeric()
                            ->minValue(0.1)
                            ->step(0.1)
                            ->default($defaults['load_warning_per_core'])
                            ->visible(fn (Get $get): bool => (bool) $get('settings.load_alert_enabled')),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Section::make('Уведомления')
                    ->schema([
                        Forms\Components\Repeater::make('notificationChannelAssignments')
                            ->relationship()
                            ->label('Каналы')
                            ->schema([
                                Forms\Components\Select::make('notification_channel_id')
                                    ->label('Канал')
                                    ->options(function ($livewire): array {
                                        $server = $livewire->getRecord();

                                        if (! $server instanceof Server || $server->project_id === null) {
                                            return [];
                                        }

                                        return NotificationChannel::query()
                                            ->where('project_id', $server->project_id)
                                            ->orderBy('name')
                                            ->get()
                                            ->mapWithKeys(fn (NotificationChannel $channel): array => [
                                                $channel->id => $channel->isConnected()
                                                    ? $channel->name
                                                    : $channel->name.' (ожидает /connect)',
                                            ])
                                            ->all();
                                    })
                                    ->required()
                                    ->distinct()
                                    ->searchable()
                                    ->columnSpanFull(),
                                Forms\Components\Toggle::make('alerts')
                                    ->label('Алерт')
                                    ->default(true)
                                    ->inline(false),
                                Forms\Components\Toggle::make('daily_summary')
                                    ->label('Саммари за сутки')
                                    ->default(false)
                                    ->inline(false),
                                Forms\Components\Toggle::make('weekly_summary')
                                    ->label('Саммари за неделю')
                                    ->default(false)
                                    ->inline(false),
                                Forms\Components\Toggle::make('monthly_summary')
                                    ->label('Саммари за месяц')
                                    ->default(false)
                                    ->inline(false),
                            ])
                            ->columns([
                                'default' => 1,
                                'md' => 2,
                                'xl' => 4,
                            ])
                            ->defaultItems(0)
                            ->addActionLabel('Добавить канал')
                            ->helperText('Каналы проекта. На создании сервера список появится после сохранения — добавьте на экране редактирования.'),
                    ])
                    ->visibleOn('edit')
                    ->columnSpanFull(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Установка агента')
                    ->schema([
                        TextEntry::make('install_command')
                            ->label('Команда (один раз)')
                            ->copyable()
                            ->state(function (Server $record): string {
                                $token = session('server_install_token');

                                if (! is_string($token) || $token === '') {
                                    return '';
                                }

                                return $record->installCommand($token);
                            }),
                        TextEntry::make('install_hint')
                            ->hiddenLabel()
                            ->state('Запустите на сервере от root. После ухода со страницы токен больше не показывается — только «Сменить токен».'),
                    ])
                    ->visible(fn (): bool => is_string(session('server_install_token')) && session('server_install_token') !== '')
                    ->columnSpanFull(),
                Section::make('Сервер')
                    ->schema([
                        TextEntry::make('name')->label('Название'),
                        TextEntry::make('project.name')->label('Проект'),
                        TextEntry::make('hostname')->label('Hostname')->placeholder('—'),
                        TextEntry::make('token_prefix')->label('Токен')->placeholder('—'),
                        TextEntry::make('last_status')
                            ->label('Статус')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                'success' => 'OK',
                                'warning' => 'Warning',
                                'failed' => 'Failed',
                                default => 'Ожидает агент',
                            })
                            ->color(fn (?string $state): string => match ($state) {
                                'success' => 'success',
                                'warning' => 'warning',
                                'failed' => 'danger',
                                default => 'gray',
                            }),
                        TextEntry::make('last_seen_at')
                            ->label('Последний пинг')
                            ->dateTime('d.m.Y H:i:s')
                            ->placeholder('ещё не было'),
                        TextEntry::make('last_message')
                            ->label('Сообщение')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('project.name')
                    ->label('Проект')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('hostname')
                    ->label('Hostname')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('last_status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'success' => 'OK',
                        'warning' => 'Warning',
                        'failed' => 'Failed',
                        default => 'Ожидает агент',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'success' => 'success',
                        'warning' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('ram')
                    ->label('RAM доступно')
                    ->state(function (Server $record): ?string {
                        $percent = $record->latestHeartbeat?->memoryAvailablePercent();

                        return $percent === null ? null : $percent.'%';
                    })
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('disk')
                    ->label('Диск')
                    ->state(function (Server $record): ?string {
                        $percent = $record->latestHeartbeat?->worstDiskUsedPercent();

                        return $percent === null ? null : $percent.'%';
                    })
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('load')
                    ->label('Load/ядро')
                    ->state(function (Server $record): ?string {
                        $load = $record->latestHeartbeat?->loadPerCore();

                        return $load === null ? null : (string) $load;
                    })
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('last_seen_at')
                    ->label('Последний пинг')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('project_id')
                    ->label('Проект')
                    ->relationship('project', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Активен')
                    ->placeholder('Все')
                    ->trueLabel('Только активные')
                    ->falseLabel('Только неактивные'),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn (Server $record): bool => Auth::user()?->isSuperadmin()
                        || ($record->project_id && Auth::user()?->isAdminOf($record->project_id))
                    ),
                DeleteAction::make()
                    ->visible(fn (Server $record): bool => Auth::user()?->isSuperadmin()
                        || ($record->project_id && Auth::user()?->isAdminOf($record->project_id))
                    ),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => Auth::user()?->isSuperadmin()
                            || Auth::user()?->projects()->wherePivot('role', ProjectRole::Admin->value)->exists()
                        ),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListServers::route('/'),
            'create' => CreateServer::route('/create'),
            'view' => ViewServer::route('/{record}'),
            'edit' => EditServer::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        /** @var User $user */
        $user = Auth::user();

        $query = parent::getEloquentQuery()->with(['project', 'latestHeartbeat']);

        if ($user->isSuperadmin()) {
            return $query;
        }

        return $query->whereIn('project_id', $user->accessibleProjectIds());
    }
}
