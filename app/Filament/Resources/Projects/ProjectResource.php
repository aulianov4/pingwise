<?php

namespace App\Filament\Resources\Projects;

use App\Enums\ProjectRole;
use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\Pages\ListProjects;
use App\Models\Project;
use App\Models\User;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Проекты';

    protected static ?string $modelLabel = 'Проект';

    protected static ?string $pluralModelLabel = 'Проекты';

    protected static ?int $navigationSort = 0;

    /**
     * Ресурс доступен только суперадминистраторам.
     */
    public static function canAccess(): bool
    {
        return Auth::user()?->isSuperadmin() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Основное')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Название')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('description')
                            ->label('Описание')
                            ->rows(3)
                            ->maxLength(1000),
                        Forms\Components\TextInput::make('max_sites')
                            ->label('Макс. количество сайтов')
                            ->numeric()
                            ->required()
                            ->default(5)
                            ->minValue(1)
                            ->maxValue(1000)
                            ->helperText('Максимальное количество сайтов в проекте'),
                    ]),
                Section::make('Участники проекта')
                    ->schema([
                        Forms\Components\Repeater::make('projectUsers')
                            ->relationship('users')
                            ->schema([
                                Forms\Components\Select::make('id')
                                    ->label('Пользователь')
                                    ->options(fn () => User::query()
                                        ->whereNull('role')
                                        ->orWhere('role', '!=', 'superadmin')
                                        ->orderBy('name')
                                        ->pluck('name', 'id'))
                                    ->searchable()
                                    ->required(),
                                Forms\Components\Select::make('role')
                                    ->label('Роль в проекте')
                                    ->options(collect(ProjectRole::cases())
                                        ->mapWithKeys(fn (ProjectRole $role) => [$role->value => $role->label()]))
                                    ->required()
                                    ->default(ProjectRole::Observer->value),
                            ])
                            ->columns(2)
                            ->addActionLabel('Добавить участника')
                            ->defaultItems(0)
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
                Tables\Columns\TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Описание')
                    ->limit(60)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('sites_count')
                    ->label('Сайтов')
                    ->counts('sites')
                    ->sortable(),
                Tables\Columns\TextColumn::make('max_sites')
                    ->label('Лимит')
                    ->sortable(),
                Tables\Columns\TextColumn::make('users_count')
                    ->label('Участников')
                    ->counts('users')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Создан')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->actions([
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
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProjects::route('/'),
            'create' => CreateProject::route('/create'),
            'edit' => EditProject::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount(['sites', 'users']);
    }
}
