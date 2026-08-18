<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Resources\ResourceAlertNotificationGroups;

use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Resources\ResourceAlertNotificationGroups\Pages\ManageResourceAlertNotificationGroups;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertChannel;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertNotificationGroup;

class ResourceAlertNotificationGroupResource extends Resource
{
    protected static ?string $model = ResourceAlertNotificationGroup::class;

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-users-group';

    public static function getNavigationGroup(): ?string
    {
        return trans('resourceusagealerts::strings.navigation.group');
    }

    public static function getNavigationLabel(): string
    {
        return trans('resourceusagealerts::strings.groups.title');
    }

    public static function canAccess(): bool
    {
        return (bool) user()?->isRootAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([Section::make()->schema([
            TextInput::make('name')->required()->maxLength(255),
            Select::make('owner_user_id')->label(trans('resourceusagealerts::strings.groups.owner'))->options(fn () => User::query()->orderBy('username')->pluck('username', 'id'))->searchable(),
            Select::make('channel_ids')->label(trans('resourceusagealerts::strings.groups.channels'))->multiple()->required()->options(fn () => ResourceAlertChannel::query()->orderBy('name')->pluck('name', 'id'))->searchable(),
            Select::make('recipient_user_ids')->label(trans('resourceusagealerts::strings.groups.recipients'))->multiple()->options(fn () => User::query()->orderBy('username')->pluck('username', 'id'))->searchable(),
            Toggle::make('shared')->label(trans('resourceusagealerts::strings.groups.shared'))->default(false),
        ])->columns(2)]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable(), TextColumn::make('owner_user_id'),
            TextColumn::make('channel_ids')->formatStateUsing(fn (array $state) => count($state)),
            TextColumn::make('recipient_user_ids')->formatStateUsing(fn (?array $state) => count($state ?? [])),
            IconColumn::make('shared')->boolean(),
        ])->recordActions([EditAction::make(), DeleteAction::make()])->toolbarActions([CreateAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageResourceAlertNotificationGroups::route('/')];
    }
}
