<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TestResultResource\Pages;
use App\Models\Site;
use App\Models\TestResult;
use App\Models\User;
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
                Forms\Components\TextInput::make('site.name')
                    ->label('Сайт')
                    ->disabled(),
                Forms\Components\TextInput::make('test_type')
                    ->label('Тип теста')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'availability' => 'Доступность сайта',
                        'ssl' => 'SSL сертификат',
                        'domain' => 'Регистрация домена',
                        'sitemap' => 'Аудит карты сайта',
                        default => $state,
                    })
                    ->disabled(),
                Forms\Components\TextInput::make('status')
                    ->label('Статус')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'success' => 'Успешно',
                        'failed' => 'Ошибка',
                        'warning' => 'Предупреждение',
                        default => $state,
                    })
                    ->disabled(),
                Forms\Components\Textarea::make('message')
                    ->label('Сообщение')
                    ->rows(3)
                    ->disabled(),
                Forms\Components\KeyValue::make('value')
                    ->label('Детальные данные')
                    ->keyLabel('Ключ')
                    ->valueLabel('Значение')
                    ->disabled(),
                Forms\Components\DateTimePicker::make('checked_at')
                    ->label('Время проверки')
                    ->disabled(),
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
                    ->options(function () {
                        /** @var User $user */
                        $user = Auth::user();

                        if ($user->isSuperadmin()) {
                            return Site::query()->orderBy('name')->pluck('name', 'id')->toArray();
                        }

                        return Site::query()
                            ->whereIn('project_id', $user->accessibleProjectIds())
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->toArray();
                    })
                    ->searchable()
                    ->preload()
                    ->multiple(),
                Tables\Filters\SelectFilter::make('test_type')
                    ->label('Тип теста')
                    ->options([
                        'availability' => 'Доступность сайта',
                        'ssl' => 'SSL сертификат',
                        'domain' => 'Регистрация домена',
                        'sitemap' => 'Аудит карты сайта',
                    ])
                    ->multiple(),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Статус')
                    ->options([
                        'success' => 'Успешно',
                        'failed' => 'Ошибка',
                        'warning' => 'Предупреждение',
                    ])
                    ->multiple(),
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
        /** @var User $user */
        $user = Auth::user();

        $query = parent::getEloquentQuery();

        if ($user->isSuperadmin()) {
            return $query;
        }

        return $query->whereHas('site', function (Builder $query) use ($user) {
            $query->whereIn('project_id', $user->accessibleProjectIds());
        });
    }
}
