<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Resources\ResourceAlertRules;

use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use Illuminate\Database\Eloquent\Collection;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertChannelType;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertMetric;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertOperator;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertScope;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertSeverity;
use PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Resources\ResourceAlertRules\Pages\CreateResourceAlertRule;
use PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Resources\ResourceAlertRules\Pages\EditResourceAlertRule;
use PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Resources\ResourceAlertRules\Pages\ListResourceAlertRules;
use PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Resources\ResourceAlertRules\Pages\ViewResourceAlertRule;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertRule;

class ResourceAlertRuleResource extends Resource
{
    protected static ?string $model = ResourceAlertRule::class;

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-bell-ringing';

    protected static ?string $recordTitleAttribute = 'name';


    public static function canAccess(): bool
    {
        return SchemaFacade::hasTable('resource_alert_rules') && parent::canAccess();
    }

    public static function getNavigationGroup(): ?string
    {
        return trans('resourceusagealerts::strings.navigation.group');
    }

    public static function getNavigationLabel(): string
    {
        return trans('resourceusagealerts::strings.rules.title');
    }

    public static function getModelLabel(): string
    {
        return trans('resourceusagealerts::strings.rules.singular');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(trans('resourceusagealerts::strings.rules.details'))->schema([
                TextInput::make('name')->required()->maxLength(255)->columnSpanFull(),
                Select::make('scope')
                    ->options(self::enumOptions(AlertScope::cases()))
                    ->required()
                    ->live()
                    ->default(AlertScope::GLOBAL->value),
                Select::make('metric')
                    ->options(self::metricOptions())
                    ->required()
                    ->live(),
                Select::make('server_id')
                    ->relationship('server', 'name')
                    ->searchable()
                    ->preload()
                    ->required(fn (Get $get) => $get('scope') === AlertScope::SERVER->value)
                    ->visible(fn (Get $get) => $get('scope') === AlertScope::SERVER->value),
                Select::make('node_id')
                    ->relationship('node', 'name')
                    ->searchable()
                    ->preload()
                    ->required(fn (Get $get) => $get('scope') === AlertScope::NODE->value)
                    ->visible(fn (Get $get) => $get('scope') === AlertScope::NODE->value),
                Select::make('user_id')
                    ->relationship('user', 'username')
                    ->searchable(['username', 'email'])
                    ->preload()
                    ->required(fn (Get $get) => $get('scope') === AlertScope::USER->value)
                    ->visible(fn (Get $get) => $get('scope') === AlertScope::USER->value),
                Select::make('operator')
                    ->options(self::enumOptions(AlertOperator::cases()))
                    ->default(AlertOperator::GTE->value)
                    ->required()
                    ->visible(fn (Get $get) => !self::isBooleanMetric($get('metric'))),
                TextInput::make('threshold')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->suffix('%')
                    ->required(fn (Get $get) => !self::isBooleanMetric($get('metric')))
                    ->visible(fn (Get $get) => !self::isBooleanMetric($get('metric'))),
                TextInput::make('duration_minutes')->numeric()->minValue(0)->required()->default(5),
                TextInput::make('cooldown_minutes')->numeric()->minValue(0)->required()->default(30),
                Select::make('severity')
                    ->options(self::enumOptions(AlertSeverity::cases()))
                    ->required()
                    ->default(AlertSeverity::WARNING->value),
                CheckboxList::make('channels')
                    ->options(self::enumOptions(AlertChannelType::cases()))
                    ->default([AlertChannelType::PANEL->value, AlertChannelType::PUSH->value])
                    ->columns(4)
                    ->columnSpanFull(),
                Toggle::make('enabled')->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('scope')->badge()->sortable(),
                TextColumn::make('metric')->badge()->sortable(),
                TextColumn::make('threshold')->suffix('%')->placeholder('N/A'),
                TextColumn::make('severity')
                    ->badge()
                    ->color(fn (AlertSeverity $state) => $state->filamentStatus()),
                IconColumn::make('enabled')->boolean(),
                TextColumn::make('last_checked_at')->dateTime()->since()->placeholder('Never'),
            ])
            ->filters([
                SelectFilter::make('scope')->options(self::enumOptions(AlertScope::cases())),
                SelectFilter::make('metric')->options(self::metricOptions()),
                SelectFilter::make('severity')->options(self::enumOptions(AlertSeverity::cases())),
                TernaryFilter::make('enabled'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                CreateAction::make(),
                BulkActionGroup::make([
                    BulkAction::make('enable')
                        ->icon('tabler-player-play')
                        ->action(fn (Collection $records) => $records->each->update(['enabled' => true])),
                    BulkAction::make('disable')
                        ->icon('tabler-player-pause')
                        ->action(fn (Collection $records) => $records->each->update(['enabled' => false])),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('name'),
            TextEntry::make('scope')->badge(),
            TextEntry::make('metric')->badge(),
            TextEntry::make('operator'),
            TextEntry::make('threshold')->suffix('%')->placeholder('N/A'),
            TextEntry::make('duration_minutes'),
            TextEntry::make('cooldown_minutes'),
            TextEntry::make('severity')->badge(),
            TextEntry::make('channels')->badge(),
            TextEntry::make('last_checked_at')->dateTime()->placeholder('Never'),
        ])->columns(2);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListResourceAlertRules::route('/'),
            'create' => CreateResourceAlertRule::route('/create'),
            'view' => ViewResourceAlertRule::route('/{record}'),
            'edit' => EditResourceAlertRule::route('/{record}/edit'),
        ];
    }

    private static function isBooleanMetric(mixed $metric): bool
    {
        return AlertMetric::tryFrom((string) $metric)?->isBoolean() ?? false;
    }

    /**
     * @param  array<int, \BackedEnum>  $cases
     * @return array<string, string>
     */
    private static function enumOptions(array $cases): array
    {
        return collect($cases)->mapWithKeys(fn (\BackedEnum $case) => [
            $case->value => str((string) $case->value)->replace('_', ' ')->title()->toString(),
        ])->all();
    }

    /**
     * @return array<string, string>
     */
    private static function metricOptions(): array
    {
        return collect(AlertMetric::cases())->mapWithKeys(fn (AlertMetric $metric) => [
            $metric->value => $metric->label(),
        ])->all();
    }
}
