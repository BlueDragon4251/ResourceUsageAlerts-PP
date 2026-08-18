<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Resources\ResourceAlertMaintenanceWindows;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Resources\ResourceAlertMaintenanceWindows\Pages\ManageResourceAlertMaintenanceWindows;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertMaintenanceWindow;

class ResourceAlertMaintenanceWindowResource extends Resource
{
    protected static ?string $model = ResourceAlertMaintenanceWindow::class;

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-calendar-pause';

    public static function getNavigationGroup(): ?string
    {
        return trans('resourceusagealerts::strings.navigation.group');
    }

    public static function getNavigationLabel(): string
    {
        return trans('resourceusagealerts::strings.maintenance.title');
    }

    public static function canAccess(): bool
    {
        return (bool) user()?->isRootAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                TextInput::make('name')->required()->maxLength(255),
                Select::make('scope')->options([
                    'global' => trans('resourceusagealerts::strings.maintenance.global'),
                    'server' => trans('resourceusagealerts::strings.maintenance.server'),
                    'node' => trans('resourceusagealerts::strings.maintenance.node'),
                    'user' => trans('resourceusagealerts::strings.maintenance.user'),
                ])->required()->live(),
                Select::make('server_id')->relationship('server', 'name')->searchable()->visible(fn (Get $get) => $get('scope') === 'server')->required(fn (Get $get) => $get('scope') === 'server'),
                Select::make('node_id')->relationship('node', 'name')->searchable()->visible(fn (Get $get) => $get('scope') === 'node')->required(fn (Get $get) => $get('scope') === 'node'),
                Select::make('user_id')->relationship('user', 'username')->searchable()->visible(fn (Get $get) => $get('scope') === 'user')->required(fn (Get $get) => $get('scope') === 'user'),
                DateTimePicker::make('starts_at')->required(),
                DateTimePicker::make('ends_at')->required()->after('starts_at'),
                Select::make('recurrence.type')->options(['weekly' => trans('resourceusagealerts::strings.maintenance.weekly')])->nullable()->live(),
                Select::make('recurrence.days')->multiple()->options(collect(range(1, 7))->mapWithKeys(fn (int $day): array => [$day => trans('resourceusagealerts::strings.weekdays.'.$day)])->all())->visible(fn (Get $get) => $get('recurrence.type') === 'weekly'),
                TextInput::make('recurrence.start')->type('time')->visible(fn (Get $get) => $get('recurrence.type') === 'weekly'),
                TextInput::make('recurrence.end')->type('time')->visible(fn (Get $get) => $get('recurrence.type') === 'weekly'),
                TextInput::make('recurrence.timezone')->default(config('app.timezone', 'UTC'))->visible(fn (Get $get) => $get('recurrence.type') === 'weekly'),
                Toggle::make('enabled')->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable(), TextColumn::make('scope')->badge(),
            TextColumn::make('starts_at')->dateTime(), TextColumn::make('ends_at')->dateTime(), IconColumn::make('enabled')->boolean(),
        ])->recordActions([EditAction::make(), DeleteAction::make()])->toolbarActions([CreateAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageResourceAlertMaintenanceWindows::route('/')];
    }
}
