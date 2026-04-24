<?php

namespace App\Filament\Resources;

use App\Enums\ProjectRole;
use App\Filament\Resources\SiteResource\Pages;
use App\Models\Project;
use App\Models\Site;
use App\Models\TelegramChat;
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

        $canEditTelegram = $user->isSuperadmin()
            || $user->projects()->wherePivot('role', ProjectRole::Admin->value)->exists();

        return $schema
            ->schema([
                Grid::make(3)
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
                        Forms\Components\Toggle::make('is_active')
                            ->label('Активен')
                            ->default(true),
                    ]),
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
                        function () {
                            return function (string $attribute, mixed $value, \Closure $fail) {
                                $project = Project::find($value);
                                if ($project && $project->hasReachedSiteLimit()) {
                                    $fail("В проекте \"{$project->name}\" достигнут лимит сайтов ({$project->max_sites}).");
                                }
                            };
                        },
                    ])
                    ->columnSpanFull(),
                Section::make('Настройки тестов')
                    ->schema([
                        Forms\Components\Repeater::make('siteTests')
                            ->relationship('siteTests')
                            ->schema([
                                Forms\Components\Select::make('test_type')
                                    ->label('Тип теста')
                                    ->options(fn () => collect(app(TestService::class)->getAllTests())
                                        ->mapWithKeys(fn ($test, $key) => [$key => $test->getName()])
                                        ->toArray())
                                    ->disabled()
                                    ->dehydrated(),
                                Forms\Components\Toggle::make('is_enabled')
                                    ->label('Включен')
                                    ->default(true),
                                Forms\Components\TextInput::make('settings.interval_minutes')
                                    ->label('Интервал (минуты)')
                                    ->numeric()
                                    ->required()
                                    ->default(fn ($record, $get) => app(TestService::class)->getTest($get('test_type'))?->getDefaultInterval() ?? 60
                                    ),
                                Section::make('Настройки Sitemap')
                                    ->schema([
                                        Forms\Components\TextInput::make('settings.max_crawl_pages')
                                            ->label('Макс. страниц для обхода')
                                            ->numeric()
                                            ->default(5000)
                                            ->minValue(1)
                                            ->maxValue(50000)
                                            ->helperText('Максимальное количество страниц при BFS-обходе сайта'),
                                        Forms\Components\TextInput::make('settings.crawl_timeout_seconds')
                                            ->label('Таймаут обхода (секунды)')
                                            ->numeric()
                                            ->default(300)
                                            ->minValue(10)
                                            ->maxValue(600)
                                            ->helperText('Максимальное время на обход сайта'),
                                        Forms\Components\TextInput::make('settings.sitemap_url')
                                            ->label('Путь к sitemap')
                                            ->default('/sitemap.xml')
                                            ->helperText('Относительный путь от корня сайта'),
                                        Forms\Components\TextInput::make('settings.check_concurrency')
                                            ->label('Параллельные запросы')
                                            ->numeric()
                                            ->default(10)
                                            ->minValue(1)
                                            ->maxValue(50)
                                            ->helperText('Количество одновременных HEAD-запросов при проверке URL'),
                                    ])
                                    ->visible(fn (Get $get): bool => $get('test_type') === 'sitemap')
                                    ->columns(2)
                                    ->compact(),
                            ])
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
                Section::make('Telegram-уведомления')
                    ->schema([
                        Forms\Components\Select::make('telegram_chat_id')
                            ->label('Telegram-группа')
                            ->options(fn () => TelegramChat::pluck('title', 'id'))
                            ->placeholder('Не выбрана')
                            ->searchable()
                            ->helperText('Добавьте бота @pingwise_bot в группу и в течение 5 минут он появится в этом списке'),
                        Forms\Components\Toggle::make('notification_settings.alerts_enabled')
                            ->label('Алерты при смене статуса')
                            ->helperText('Отправлять уведомление при изменении статуса теста')
                            ->default(false),
                        Forms\Components\Toggle::make('notification_settings.summary_enabled')
                            ->label('Ежесуточное саммари')
                            ->helperText('Отправлять сводку по всем тестам каждый день в 09:00')
                            ->default(false),
                    ])
                    ->visible($canEditTelegram)
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
                            if ($record->siteTests()->count() === 0) {
                                $testService->initializeTestsForSite($record);
                                $record->refresh();
                            }

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

        $query = parent::getEloquentQuery()->with('latestTestResult', 'project');

        if ($user->isSuperadmin()) {
            return $query;
        }

        $projectIds = $user->accessibleProjectIds();

        return $query->whereIn('project_id', $projectIds);
    }
}
