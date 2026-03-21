<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SiteResource\Pages;
use App\Models\Site;
use App\Models\TelegramChat;
use App\Services\TestService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class SiteResource extends Resource
{
    protected static ?string $model = Site::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?string $navigationLabel = 'Сайты';

    protected static ?string $modelLabel = 'Сайт';

    protected static ?string $pluralModelLabel = 'Сайты';

    public static function form(Schema $schema): Schema
    {
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
                Section::make('Настройки тестов')
                    ->schema([
                        Forms\Components\Repeater::make('siteTests')
                            ->relationship('siteTests')
                            ->schema([
                                Forms\Components\TextInput::make('test_type')
                                    ->label('Тип теста')
                                    ->disabled()
                                    ->dehydrated()
                                    ->formatStateUsing(function ($state) {
                                        $test = app(TestService::class)->getTest($state);

                                        return $test ? $test->getName() : $state;
                                    })
                                    ->afterStateHydrated(function ($component, $state) {
                                        if ($state) {
                                            $component->extraAttributes(['data-original-value' => $state]);
                                        }
                                    }),
                                Forms\Components\Toggle::make('is_enabled')
                                    ->label('Включен')
                                    ->default(true),
                                Forms\Components\TextInput::make('settings.interval_minutes')
                                    ->label('Интервал (минуты)')
                                    ->numeric()
                                    ->required()
                                    ->default(fn ($record, $get) => app(TestService::class)->getTest($get('test_type'))?->getDefaultInterval() ?? 60
                                    ),
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
                    ->visibleOn('edit')
                    ->collapsible()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
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
                        return $record->testResults->first()?->status;
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
                    ->action(function (Site $record) {
                        $rateLimitKey = 'run_tests_site_'.$record->id;

                        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($rateLimitKey, 1)) {
                            $seconds = \Illuminate\Support\Facades\RateLimiter::availableIn($rateLimitKey);

                            \Filament\Notifications\Notification::make()
                                ->title('Слишком частые запросы')
                                ->body("Повторный запуск будет доступен через {$seconds} сек.")
                                ->warning()
                                ->send();

                            return;
                        }

                        \Illuminate\Support\Facades\RateLimiter::hit($rateLimitKey, 60);

                        $testService = app(TestService::class);
                        $runCount = 0;
                        $errors = [];

                        try {
                            // Проверяем, инициализированы ли тесты
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
                                        \Illuminate\Support\Facades\Log::error("Error running test {$testType} for site {$record->id}: ".$e->getMessage());
                                    }
                                }
                            }

                            if (count($errors) > 0) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Проверки выполнены с ошибками')
                                    ->body('Запущено: '.$runCount.'. Ошибок: '.count($errors))
                                    ->danger()
                                    ->send();
                            } else {
                                \Filament\Notifications\Notification::make()
                                    ->title('Проверки запущены')
                                    ->body("Выполнено тестов: {$runCount}")
                                    ->success()
                                    ->send();
                            }
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Ошибка при запуске проверок')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                            \Illuminate\Support\Facades\Log::error("Error in run_tests action for site {$record->id}: ".$e->getMessage());
                        }
                    })
                    ->requiresConfirmation(),
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
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
        return parent::getEloquentQuery()
            ->where('user_id', Auth::id())
            ->with(['testResults' => function ($query) {
                $query->latest('checked_at')->limit(1);
            }]);
    }
}
