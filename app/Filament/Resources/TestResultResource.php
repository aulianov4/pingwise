<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TestResultResource\Pages;
use App\Models\Site;
use App\Models\TestResult;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class TestResultResource extends Resource
{
    protected static ?string $model = TestResult::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Результаты тестов';

    protected static ?string $modelLabel = 'Результат теста';

    protected static ?string $pluralModelLabel = 'Результаты тестов';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Select::make('site_id')
                    ->label('Сайт')
                    ->relationship('site', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('test_type')
                    ->label('Тип теста')
                    ->options([
                        'availability' => 'Доступность сайта',
                        'ssl' => 'SSL сертификат',
                        'domain' => 'Регистрация домена',
                    ])
                    ->required(),
                Forms\Components\Select::make('status')
                    ->label('Статус')
                    ->options([
                        'success' => 'Успешно',
                        'failed' => 'Ошибка',
                        'warning' => 'Предупреждение',
                    ])
                    ->required(),
                Forms\Components\Textarea::make('message')
                    ->label('Сообщение')
                    ->rows(3),
                Forms\Components\KeyValue::make('value')
                    ->label('Детальные данные')
                    ->keyLabel('Ключ')
                    ->valueLabel('Значение'),
                Forms\Components\DateTimePicker::make('checked_at')
                    ->label('Время проверки')
                    ->required()
                    ->default(now()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('site.name')
                    ->label('Сайт')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('test_type')
                    ->label('Тип теста')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'availability' => 'Доступность',
                        'ssl' => 'SSL',
                        'domain' => 'Домен',
                        default => $state,
                    })
                    ->color('gray'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'success' => 'Успешно',
                        'failed' => 'Ошибка',
                        'warning' => 'Предупреждение',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'success' => 'success',
                        'failed' => 'danger',
                        'warning' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('message')
                    ->label('Сообщение')
                    ->limit(50)
                    ->tooltip(fn (TestResult $record): ?string => $record->message),
                Tables\Columns\TextColumn::make('checked_at')
                    ->label('Время проверки')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('site_id')
                    ->label('Сайт')
                    ->relationship('site', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('test_type')
                    ->label('Тип теста')
                    ->options([
                        'availability' => 'Доступность сайта',
                        'ssl' => 'SSL сертификат',
                        'domain' => 'Регистрация домена',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Статус')
                    ->options([
                        'success' => 'Успешно',
                        'failed' => 'Ошибка',
                        'warning' => 'Предупреждение',
                    ]),
                Tables\Filters\Filter::make('period')
                    ->label('Период')
                    ->form([
                        Forms\Components\Select::make('period')
                            ->options([
                                'week' => 'Неделя',
                                'month' => 'Месяц',
                                'year' => 'Год',
                            ])
                            ->default('month'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['period'],
                            fn (Builder $query, $period): Builder => $query->forPeriod($period)
                        );
                    }),
            ])
            ->actions([
                ViewAction::make(),
            ])
            ->defaultSort('checked_at', 'desc');
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
            'index' => Pages\ListTestResults::route('/'),
            'view' => Pages\ViewTestResult::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('site', function (Builder $query) {
                $query->where('user_id', Auth::id());
            });
    }
}
