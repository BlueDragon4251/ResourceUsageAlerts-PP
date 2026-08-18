<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Resources\ResourceAlertRules;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertChannelType;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertMetric;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertOperator;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertScope;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertSeverity;
use PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Resources\ResourceAlertRules\Pages\CreateResourceAlertRule;
use PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Resources\ResourceAlertRules\Pages\EditResourceAlertRule;
use PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Resources\ResourceAlertRules\Pages\ListResourceAlertRules;
use PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Resources\ResourceAlertRules\Pages\ViewResourceAlertRule;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertNotificationGroup;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertRule;
use PelicanPlugins\ResourceUsageAlerts\Services\AlertRuleEvaluator;

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
                TextInput::make('config.custom_metric_name')
                    ->label(trans('resourceusagealerts::strings.rules.custom_metric_name'))
                    ->required(fn (Get $get) => $get('metric') === AlertMetric::CUSTOM->value)
                    ->visible(fn (Get $get) => $get('metric') === AlertMetric::CUSTOM->value),
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
                    ->visible(fn (Get $get) => ! self::isBooleanMetric($get('metric'))),
                TextInput::make('threshold')
                    ->numeric()
                    ->minValue(0)
                    ->suffix(fn (Get $get) => in_array($get('metric'), ['cpu_percent', 'ram_percent', 'disk_percent', 'swap_percent', 'inode_percent'], true) ? '%' : null)
                    ->required(fn (Get $get) => ! self::isBooleanMetric($get('metric')))
                    ->visible(fn (Get $get) => ! self::isBooleanMetric($get('metric'))),
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
            Section::make(trans('resourceusagealerts::strings.rules.advanced'))->schema([
                Select::make('config.condition_logic')
                    ->label(trans('resourceusagealerts::strings.rules.condition_logic'))
                    ->options(['and' => 'AND', 'or' => 'OR'])
                    ->default('and'),
                Repeater::make('config.conditions')
                    ->label(trans('resourceusagealerts::strings.rules.additional_conditions'))
                    ->schema([
                        Select::make('metric')->options(self::metricOptions())->required(),
                        Select::make('operator')->options(self::enumOptions(AlertOperator::cases()))->required(),
                        TextInput::make('threshold')->numeric()->required(),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
                Toggle::make('config.anomaly.enabled')
                    ->label(trans('resourceusagealerts::strings.rules.anomaly_enabled'))
                    ->live(),
                TextInput::make('config.anomaly.window_minutes')
                    ->label(trans('resourceusagealerts::strings.rules.anomaly_window'))
                    ->numeric()->minValue(15)->default(60)
                    ->visible(fn (Get $get) => (bool) $get('config.anomaly.enabled')),
                TextInput::make('config.anomaly.minimum_samples')
                    ->label(trans('resourceusagealerts::strings.rules.anomaly_samples'))
                    ->numeric()->minValue(3)->default(6)
                    ->visible(fn (Get $get) => (bool) $get('config.anomaly.enabled')),
                TextInput::make('config.anomaly.standard_deviations')
                    ->label(trans('resourceusagealerts::strings.rules.anomaly_deviations'))
                    ->numeric()->minValue(0.5)->default(3)
                    ->visible(fn (Get $get) => (bool) $get('config.anomaly.enabled')),
                TextInput::make('config.escalation_minutes')
                    ->label(trans('resourceusagealerts::strings.rules.escalation_minutes'))
                    ->numeric()->minValue(0)->default(0),
                Select::make('config.escalation_severity')
                    ->label(trans('resourceusagealerts::strings.rules.escalation_severity'))
                    ->options(self::enumOptions(AlertSeverity::cases())),
                CheckboxList::make('config.escalation_channels')
                    ->label(trans('resourceusagealerts::strings.rules.escalation_channels'))
                    ->options(self::enumOptions(AlertChannelType::cases()))
                    ->columns(4)
                    ->columnSpanFull(),
                TextInput::make('config.per_channel_cooldown_minutes')
                    ->label(trans('resourceusagealerts::strings.rules.per_channel_cooldown'))
                    ->numeric()->minValue(0)->default(0),
                Select::make('config.notification_group_ids')
                    ->label(trans('resourceusagealerts::strings.rules.notification_groups'))
                    ->multiple()
                    ->options(fn () => ResourceAlertNotificationGroup::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->columnSpanFull(),
                Select::make('config.on_call_rotation.users')
                    ->label(trans('resourceusagealerts::strings.rules.on_call_users'))
                    ->multiple()
                    ->options(fn () => User::query()->orderBy('username')->pluck('username', 'id'))
                    ->searchable()
                    ->columnSpan(2),
                TextInput::make('config.on_call_rotation.rotation_minutes')
                    ->label(trans('resourceusagealerts::strings.rules.rotation_minutes'))
                    ->numeric()->minValue(1)->default(480),
                TextInput::make('config.push.sound')
                    ->label(trans('resourceusagealerts::strings.rules.push_sound')),
                TextInput::make('config.push.action_url')
                    ->label(trans('resourceusagealerts::strings.rules.push_action_url'))
                    ->url(),
                Toggle::make('config.auto_restart.enabled')
                    ->label(trans('resourceusagealerts::strings.rules.auto_restart'))
                    ->visible(fn (Get $get) => $get('metric') === AlertMetric::SERVER_CRASHED->value),
                TextInput::make('config.auto_restart.max_attempts')
                    ->label(trans('resourceusagealerts::strings.rules.auto_restart_attempts'))
                    ->numeric()->minValue(1)->maxValue(5)->default(2)
                    ->visible(fn (Get $get) => $get('metric') === AlertMetric::SERVER_CRASHED->value && (bool) $get('config.auto_restart.enabled')),
            ])->columns(3)->collapsible(),
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
                ViewColumn::make('recent_values')->label(trans('resourceusagealerts::strings.rules.recent_values'))->view('resourceusagealerts::tables.columns.rule-sparkline'),
                IconColumn::make('enabled')->boolean(),
                TextColumn::make('last_checked_at')->dateTime()->since()->placeholder(trans('resourceusagealerts::strings.common.never')),
            ])
            ->filters([
                SelectFilter::make('scope')->options(self::enumOptions(AlertScope::cases())),
                SelectFilter::make('metric')->options(self::metricOptions()),
                SelectFilter::make('severity')->options(self::enumOptions(AlertSeverity::cases())),
                TernaryFilter::make('enabled'),
            ])
            ->recordActions([
                Action::make('dry_run')
                    ->label(trans('resourceusagealerts::strings.rules.dry_run'))
                    ->icon('tabler-flask')
                    ->action(function (ResourceAlertRule $record): void {
                        $results = app(AlertRuleEvaluator::class)->dryRun($record);
                        $triggered = collect($results)->where('would_trigger', true)->count();
                        Notification::make()->title(trans('resourceusagealerts::strings.rules.dry_run_result', ['triggered' => $triggered, 'total' => count($results)]))->info()->send();
                    }),
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
                    BulkAction::make('warning')
                        ->label(trans('resourceusagealerts::strings.rules.set_warning'))
                        ->action(fn (Collection $records) => $records->each->update(['severity' => AlertSeverity::WARNING])),
                    BulkAction::make('critical')
                        ->label(trans('resourceusagealerts::strings.rules.set_critical'))
                        ->action(fn (Collection $records) => $records->each->update(['severity' => AlertSeverity::CRITICAL])),
                    BulkAction::make('clone')
                        ->label(trans('resourceusagealerts::strings.rules.clone'))
                        ->action(fn (Collection $records) => $records->each(function (ResourceAlertRule $rule): void {
                            $copy = $rule->replicate(['last_checked_at']);
                            $copy->name = $rule->name.' ('.trans('resourceusagealerts::strings.rules.copy').')';
                            $copy->enabled = false;
                            $copy->save();
                        })),
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
            TextEntry::make('last_checked_at')->dateTime()->placeholder(trans('resourceusagealerts::strings.common.never')),
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
