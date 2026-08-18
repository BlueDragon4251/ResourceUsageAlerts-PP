<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Resources\ResourceAlertMetricTokens;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertMetric;
use PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Resources\ResourceAlertMetricTokens\Pages\ManageResourceAlertMetricTokens;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertMetricToken;

class ResourceAlertMetricTokenResource extends Resource
{
    protected static ?string $model = ResourceAlertMetricToken::class;

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-api';

    public static function getNavigationGroup(): ?string
    {
        return trans('resourceusagealerts::strings.navigation.group');
    }

    public static function getNavigationLabel(): string
    {
        return trans('resourceusagealerts::strings.metric_tokens.title');
    }

    public static function canAccess(): bool
    {
        return (bool) user()?->isRootAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([Section::make()->schema([
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('plain_token')->label(trans('resourceusagealerts::strings.metric_tokens.token'))->password()->revealable()->minLength(32)->required(fn (?ResourceAlertMetricToken $record) => $record === null)->dehydrated(fn (?string $state) => filled($state)),
            Select::make('server_id')->relationship('server', 'name')->searchable()->nullable(),
            Select::make('node_id')->relationship('node', 'name')->searchable()->nullable(),
            Select::make('allowed_metrics')->multiple()->options(collect(AlertMetric::cases())->mapWithKeys(fn (AlertMetric $metric) => [$metric->value => $metric->label()]))->helperText(trans('resourceusagealerts::strings.metric_tokens.metrics_help')),
            DateTimePicker::make('expires_at'), Toggle::make('enabled')->default(true),
        ])->columns(2)]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')->searchable(), TextColumn::make('server_id'), TextColumn::make('node_id'), TextColumn::make('last_used_at')->since()->placeholder('-'), TextColumn::make('expires_at')->dateTime()->placeholder('-'), IconColumn::make('enabled')->boolean()])
            ->recordActions([EditAction::make(), DeleteAction::make()])->toolbarActions([CreateAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageResourceAlertMetricTokens::route('/')];
    }
}
