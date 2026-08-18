<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Resources\ResourceAlertReportSubscriptions;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Resources\ResourceAlertReportSubscriptions\Pages\ManageResourceAlertReportSubscriptions;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertReportSubscription;

class ResourceAlertReportSubscriptionResource extends Resource
{
    protected static ?string $model = ResourceAlertReportSubscription::class;

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-mail-forward';

    public static function getNavigationGroup(): ?string
    {
        return trans('resourceusagealerts::strings.navigation.group');
    }

    public static function getNavigationLabel(): string
    {
        return trans('resourceusagealerts::strings.reports.title');
    }

    public static function canAccess(): bool
    {
        return (bool) user()?->isRootAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('user_id')->relationship('user', 'username')->searchable(['username', 'email'])->preload()->required(),
            TextInput::make('email')->email()->required(),
            Select::make('frequency')->options(['weekly' => trans('resourceusagealerts::strings.reports.weekly')])->default('weekly')->required(),
            Toggle::make('enabled')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('user.username')->searchable(), TextColumn::make('email'), TextColumn::make('frequency')->badge(),
            TextColumn::make('last_sent_at')->since()->placeholder('-'), IconColumn::make('enabled')->boolean(),
        ])->recordActions([EditAction::make(), DeleteAction::make()])->toolbarActions([CreateAction::make()]);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ManageResourceAlertReportSubscriptions::route('/')];
    }
}
