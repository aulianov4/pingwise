<?php

namespace App\Filament\Resources;

use App\Enums\ProjectRole;
use App\Filament\Resources\SiteResource\Pages;
use App\Models\NotificationChannel;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use App\Services\TestService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class SiteResource extends Resource
{
    protected static ?string $model = Site::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?string $navigationLabel = 'Сайты';

    protected static ?string $modelLabel = 'Сайт';

    protected static ?string $pluralModelLabel = 'Сайты';

    public static function form(Schema $schema): Schema
    {
        /** @var User $user */
        $user = Auth::user();

        return $schema
            ->schema([
                Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Название')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('url')
                            ->label('URL')
                            ->required()
                            ->url()
                            ->maxLength(255)
                            ->helperText('Например: https://example.com'),
                    ])
                    ->columnSpanFull(),
                Forms\Components\Toggle::make('is_active')
                    ->label('Активен')
                    ->default(true)
                    ->inline(false)
                    ->columnSpanFull(),
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
                    ->rules([
                        fn (?Site $record): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($record): void {
                            $project = Project::find($value);

                            if (! $project) {
                                return;
                            }

                            // Редактирование сайта в том же проекте не должно упираться в лимит
                            if ($record && (int) $record->project_id === (int) $value) {
                                return;
                            }

                            if ($project->hasReachedSiteLimit()) {
                                $fail("В проекте \"{$project->name}\" достигнут лимит сайтов ({$project->max_sites}).");
                            }
                        },
                    ])
                    ->columnSpanFull(),
                Section::make('Настройки тестов')
                    ->schema([
                        Forms\Components\Repeater::make('siteTests')
                            ->label('Тесты')
                            ->relationship(
                                'siteTests',
                                modifyQueryUsing: fn (Builder $query): Builder => $query->where('test_type', '!=', 'sitemap'),
                            )
                            ->schema([
                                Forms\Components\Hidden::make('test_type')
                                    ->dehydrated(),
                                Forms\Components\Toggle::make('is_enabled')
                                    ->label('Включен')
                                    ->default(true)
                                    ->inline(false),
                                Forms\Components\TextInput::make('settings.interval_minutes')
                                    ->label('Интервал (минуты)')
                                    ->numeric()
                                    ->required()
                                    ->minValue(1)
                                    ->default(fn ($record, $get) => app(TestService::class)->getTest($get('test_type'))?->getDefaultInterval() ?? 60
                                    ),
                                Forms\Components\Repeater::make('notificationChannelAssignments')
                                    ->relationship()
                                    ->label('Уведомления')
                                    ->schema([
                                        Forms\Components\Select::make('notification_channel_id')
                                            ->label('Канал')
                                            ->options(function ($livewire): array {
                                                $site = $livewire->getRecord();

                                                if (! $site instanceof Site || $site->project_id === null) {
                                                    return [];
                                                }

                                                return NotificationChannel::query()
                                                    ->where('project_id', $site->project_id)
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
                                            ->default(false)
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
                                    ->columnSpanFull()
                                    ->defaultItems(0)
                                    ->addActionLabel('Добавить канал')
                                    ->helperText('Пусто — уведомления не отправляются. Каналы добавляются в разделе «Каналы уведомлений».'),
                                Section::make('Настройки Sitemap')
                                    ->schema([
                                        Forms\Components\TextInput::make('settings.max_crawl_pages')
                                            ->label('Макс. страниц для обхода')
                                            ->numeric()
                                            ->minValue(1)
                                            ->maxValue(50000)
                                            ->helperText('Максимальное количество страниц при BFS-обходе сайта')
                                            ->afterStateHydrated(function (Forms\Components\TextInput $component, mixed $state): void {
                                                if (blank($state)) {
                                                    $component->state(5000);
                                                }
                                            }),
                                        Forms\Components\TextInput::make('settings.crawl_timeout_seconds')
                                            ->label('Таймаут обхода (секунды)')
                                            ->numeric()
                                            ->minValue(10)
                                            ->maxValue(600)
                                            ->helperText('Максимальное время на обход сайта')
                                            ->afterStateHydrated(function (Forms\Components\TextInput $component, mixed $state): void {
                                                if (blank($state)) {
                                                    $component->state(300);
                                                }
                                            }),
                                        Forms\Components\TextInput::make('settings.max_crawl_depth')
                                            ->label('Макс. глубина обхода')
                                            ->numeric()
                                            ->minValue(1)
                                            ->maxValue(50)
                                            ->helperText('Максимальная глубина BFS-обхода от главной страницы')
                                            ->afterStateHydrated(function (Forms\Components\TextInput $component, mixed $state): void {
                                                if (blank($state)) {
                                                    $component->state(5);
                                                }
                                            }),
                                        Forms\Components\TextInput::make('settings.sitemap_url')
                                            ->label('Путь к sitemap')
                                            ->helperText('Относительный путь от корня сайта')
                                            ->afterStateHydrated(function (Forms\Components\TextInput $component, mixed $state): void {
                                                if (blank($state)) {
                                                    $component->state('/sitemap.xml');
                                                }
                                            }),
                                        Forms\Components\TextInput::make('settings.check_concurrency')
                                            ->label('Параллельные запросы')
                                            ->numeric()
                                            ->minValue(1)
                                            ->maxValue(50)
                                            ->helperText('Количество одновременных HEAD-запросов при проверке URL')
                                            ->afterStateHydrated(function (Forms\Components\TextInput $component, mixed $state): void {
                                                if (blank($state)) {
                                                    $component->state(10);
                                                }
                                            }),
                                    ])
                                    ->visible(fn (Get $get): bool => $get('test_type') === 'sitemap')
                                    ->columns(2)
                                    ->compact()
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->itemLabel(fn (array $state): ?string => app(TestService::class)->getTest($state['test_type'] ?? '')?->getName()
                            )
                            ->collapsible()
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false),
                    ])
                    ->visibleOn('edit')
                    ->collapsible()
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
                Tables\Columns\TextColumn::make('url')
                    ->label('URL')
                    ->searchable()
                    ->copyable()
                    ->limit(50),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean(),
                Tables\Columns\TextColumn::make('availability_status')
                    ->label('Доступность')
                    ->badge()
                    ->state(function (Site $record): ?string {
                        $result = $record->latestAvailabilityResult;

                        if ($result === null) {
                            return null;
                        }

                        return $result->isAvailabilityIncidentDown() ? 'failed' : 'success';
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'success' => 'Работает',
                        'failed' => 'Недоступен',
                        default => '—',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'success' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('ping')
                    ->label('Пинг')
                    ->state(function (Site $record): ?string {
                        $ms = $record->latestAvailabilityResult?->responseTimeMs();

                        return $ms === null ? null : $ms.' мс';
                    })
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('last_test_status')
                    ->label('Последний статус')
                    ->badge()
                    ->state(function (Site $record): ?string {
                        return $record->latestTestResult?->status;
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'success' => 'success',
                        'failed' => 'danger',
                        'warning' => 'warning',
                        default => 'gray',
                    })
                    ->default('—'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Создан')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                Action::make('run_tests')
                    ->label('Запустить проверки')
                    ->icon('heroicon-o-play')
                    ->visible(fn () => Auth::user()?->isSuperadmin()
                        || Auth::user()?->projects()->wherePivot('role', ProjectRole::Admin->value)->exists()
                    )
                    ->action(function (Site $record) {
                        $rateLimitKey = 'run_tests_site_'.$record->id;

                        if (RateLimiter::tooManyAttempts($rateLimitKey, 1)) {
                            $seconds = RateLimiter::availableIn($rateLimitKey);

                            Notification::make()
                                ->title('Слишком частые запросы')
                                ->body("Повторный запуск будет доступен через {$seconds} сек.")
                                ->warning()
                                ->send();

                            return;
                        }

                        RateLimiter::hit($rateLimitKey, 60);

                        $testService = app(TestService::class);
                        $runCount = 0;
                        $errors = [];

                        try {
                            $testService->initializeTestsForSite($record);
                            $record->refresh();

                            foreach ($testService->getAllTests() as $testType => $test) {
                                if ($record->isTestEnabled($testType)) {
                                    try {
                                        $result = $testService->runTest($record, $testType);
                                        if ($result) {
                                            $runCount++;
                                        }
                                    } catch (\Exception $e) {
                                        $errors[] = "Ошибка при выполнении теста {$testType}: ".$e->getMessage();
                                        Log::error("Error running test {$testType} for site {$record->id}: ".$e->getMessage());
                                    }
                                }
                            }

                            if (count($errors) > 0) {
                                Notification::make()
                                    ->title('Проверки выполнены с ошибками')
                                    ->body('Запущено: '.$runCount.'. Ошибок: '.count($errors))
                                    ->danger()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Проверки запущены')
                                    ->body("Выполнено тестов: {$runCount}")
                                    ->success()
                                    ->send();
                            }
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Ошибка при запуске проверок')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                            Log::error("Error in run_tests action for site {$record->id}: ".$e->getMessage());
                        }
                    })
                    ->requiresConfirmation(),
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn (Site $record) => Auth::user()?->isSuperadmin()
                        || ($record->project_id && Auth::user()?->isAdminOf($record->project_id))
                    ),
                DeleteAction::make()
                    ->visible(fn (Site $record) => Auth::user()?->isSuperadmin()
                        || ($record->project_id && Auth::user()?->isAdminOf($record->project_id))
                    ),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn () => Auth::user()?->isSuperadmin()
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
            'index' => Pages\ListSites::route('/'),
            'create' => Pages\CreateSite::route('/create'),
            'view' => Pages\ViewSite::route('/{record}'),
            'edit' => Pages\EditSite::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        /** @var User $user */
        $user = Auth::user();

        $query = parent::getEloquentQuery()->with('latestTestResult', 'latestAvailabilityResult', 'project');

        if ($user->isSuperadmin()) {
            return $query;
        }

        $projectIds = $user->accessibleProjectIds();

        return $query->whereIn('project_id', $projectIds);
    }
}
