<?php

namespace App\Filament\Resources\NotificationChannels;

use App\Enums\NotificationChannelType;
use App\Enums\ProjectRole;
use App\Filament\Resources\NotificationChannels\Pages\CreateNotificationChannel;
use App\Filament\Resources\NotificationChannels\Pages\EditNotificationChannel;
use App\Filament\Resources\NotificationChannels\Pages\ListNotificationChannels;
use App\Models\NotificationChannel;
use App\Models\Project;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class NotificationChannelResource extends Resource
{
    protected static ?string $model = NotificationChannel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBell;

    protected static ?string $navigationLabel = 'Каналы уведомлений';

    protected static ?string $modelLabel = 'канал уведомлений';

    protected static ?string $pluralModelLabel = 'Каналы уведомлений';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        /** @var User $user */
        $user = Auth::user();

        return $schema
            ->columns(1)
            ->schema([
                Section::make('Настройки')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Название')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('DevOps, Клиенты…'),
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
                        Forms\Components\Select::make('type')
                            ->label('Тип')
                            ->options(collect(NotificationChannelType::cases())
                                ->mapWithKeys(fn (NotificationChannelType $type): array => [$type->value => $type->label()]))
                            ->default(NotificationChannelType::Telegram->value)
                            ->required()
                            ->disabledOn('edit'),
                        Forms\Components\Toggle::make('is_enabled')
                            ->label('Канал включён')
                            ->helperText('Выключенный канал не получает алерты и саммари')
                            ->default(true)
                            ->inline(false),
                    ]),
                Section::make('Привязка Telegram')
                    ->key('telegramBinding')
                    ->description('Добавьте бота в группу и отправьте код — сообщения начнут приходить туда.')
                    ->headerActions([
                        Action::make('regenerate_token')
                            ->label('Новый код')
                            ->icon('heroicon-o-arrow-path')
                            ->outlined()
                            ->visible(fn (?NotificationChannel $record): bool => $record !== null && ! $record->isConnected())
                            ->action(function (NotificationChannel $record): void {
                                $record->issueConnectToken();

                                Notification::make()
                                    ->title('Код обновлён')
                                    ->body('Отправьте новый код командой /connect в Telegram-группе.')
                                    ->success()
                                    ->send();
                            }),
                    ])
                    ->poll('5s')
                    ->schema([
                        View::make('filament.resources.notification-channels.telegram-connect')
                            ->viewData(function (?NotificationChannel $record): array {
                                if ($record !== null && ! $record->isConnected()) {
                                    $record->refresh();
                                }

                                return ['channel' => $record];
                            }),
                    ])
                    ->visibleOn('edit'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('project.name')
                    ->label('Проект')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Тип')
                    ->badge()
                    ->formatStateUsing(fn (NotificationChannelType $state): string => $state->label()),
                Tables\Columns\IconColumn::make('is_enabled')
                    ->label('Включён')
                    ->boolean(),
                Tables\Columns\TextColumn::make('connection')
                    ->label('Подключение')
                    ->badge()
                    ->state(function (NotificationChannel $record): string {
                        if ($record->isConnected()) {
                            return $record->connectedChatTitle() ?? 'Подключено';
                        }

                        return $record->hasActiveConnectToken() ? 'Ожидает /connect' : 'Код истёк';
                    })
                    ->color(fn (NotificationChannel $record): string => $record->isConnected() ? 'success' : 'warning'),
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
            ])
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

    public static function getPages(): array
    {
        return [
            'index' => ListNotificationChannels::route('/'),
            'create' => CreateNotificationChannel::route('/create'),
            'edit' => EditNotificationChannel::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        /** @var User $user */
        $user = Auth::user();

        $query = parent::getEloquentQuery()->with('project');

        if ($user->isSuperadmin()) {
            return $query;
        }

        return $query->whereIn('project_id', $user->accessibleProjectIds());
    }
}
