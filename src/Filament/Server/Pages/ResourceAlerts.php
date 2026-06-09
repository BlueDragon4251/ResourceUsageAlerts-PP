<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Filament\Server\Pages;

use App\Models\Server;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Schema;
use Filament\Schemas\Components\Utilities\Get;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertChannelType;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertMetric;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertOperator;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertScope;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertSeverity;
use PelicanPlugins\ResourceUsageAlerts\Filament\Server\Widgets\ServerAlertChannelsTable;
use PelicanPlugins\ResourceUsageAlerts\Filament\Server\Widgets\ServerAlertHistoryTable;
use PelicanPlugins\ResourceUsageAlerts\Filament\Server\Widgets\ServerAlertRulesTable;
use PelicanPlugins\ResourceUsageAlerts\Filament\Server\Widgets\ServerAlertStats;
use PelicanPlugins\ResourceUsageAlerts\Filament\Server\Widgets\ServerOpenAlertsTable;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertChannel;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertRule;
use PelicanPlugins\ResourceUsageAlerts\Services\PermissionService;

class ResourceAlerts extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'tabler-bell-ringing';

    protected static ?int $navigationSort = 8;

    protected string $view = 'resourceusagealerts::filament.server.pages.resource-alerts';

    public static function canAccess(): bool
    {
        $server = Filament::getTenant();

        return Schema::hasTable('resource_alert_events')
            && Schema::hasTable('resource_alert_rules')
            && Schema::hasTable('resource_alert_channels')
            && $server instanceof Server
            && user() !== null
            && app(PermissionService::class)->canViewServerAlerts(user(), $server);
    }

    protected function authorizeAccess(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public static function getNavigationLabel(): string
    {
        return trans('resourceusagealerts::strings.server.title');
    }

    public function getTitle(): string
    {
        return trans('resourceusagealerts::strings.server.title');
    }

    protected function getHeaderActions(): array
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        return [
            Action::make('create_rule')
                ->label(trans('resourceusagealerts::strings.server.create_rule'))
                ->icon('tabler-plus')
                ->visible(fn () => user() !== null && app(PermissionService::class)->canCreateServerRule(user(), $server))
                ->schema($this->ruleSchema())
                ->action(function (array $data) use ($server): void {
                    ResourceAlertRule::query()->create($data + [
                        'scope' => AlertScope::SERVER,
                        'server_id' => $server->id,
                        'created_by' => user()?->id,
                    ]);
                }),
            Action::make('create_channel')
                ->label(trans('resourceusagealerts::strings.server.create_channel'))
                ->icon('tabler-webhook')
                ->visible(fn () => (filter_var(
                    config('resourceusagealerts.allow_user_channels', true),
                    FILTER_VALIDATE_BOOLEAN,
                    FILTER_NULL_ON_FAILURE
                ) ?? true)
                    && ($server->owner_id === user()?->id || user()?->can('alerts.channels', $server)))
                ->schema([
                    TextInput::make('name')->required()->maxLength(255),
                    Select::make('type')
                        ->options([
                            AlertChannelType::PANEL->value => 'Panel',
                            AlertChannelType::DISCORD->value => 'Discord',
                            AlertChannelType::EMAIL->value => 'Email',
                            AlertChannelType::TELEGRAM->value => 'Telegram',
                            AlertChannelType::SLACK->value => 'Slack',
                        ])
                        ->required()
                        ->live(),
                    TextInput::make('webhook_url')
                        ->url()
                        ->password()
                        ->revealable()
                        ->required(fn (Get $get) => in_array($get('type'), [AlertChannelType::DISCORD->value, AlertChannelType::SLACK->value], true))
                        ->visible(fn (Get $get) => in_array($get('type'), [AlertChannelType::DISCORD->value, AlertChannelType::SLACK->value], true)),
                    TextInput::make('bot_token')
                        ->label('Telegram bot token')
                        ->password()
                        ->revealable()
                        ->required(fn (Get $get) => $get('type') === AlertChannelType::TELEGRAM->value)
                        ->visible(fn (Get $get) => $get('type') === AlertChannelType::TELEGRAM->value),
                    TextInput::make('chat_id')
                        ->label('Telegram chat ID')
                        ->required(fn (Get $get) => $get('type') === AlertChannelType::TELEGRAM->value)
                        ->visible(fn (Get $get) => $get('type') === AlertChannelType::TELEGRAM->value),
                    TextInput::make('email')
                        ->email()
                        ->required(fn (Get $get) => $get('type') === AlertChannelType::EMAIL->value)
                        ->visible(fn (Get $get) => $get('type') === AlertChannelType::EMAIL->value),
                    Toggle::make('enabled')->default(true),
                ])
                ->action(function (array $data): void {
                    ResourceAlertChannel::query()->create([
                        'user_id' => user()?->id,
                        'name' => $data['name'],
                        'type' => $data['type'],
                        'config' => [
                            'webhook_url' => $data['webhook_url'] ?? null,
                            'email' => $data['email'] ?? null,
                            'bot_token' => $data['bot_token'] ?? null,
                            'chat_id' => $data['chat_id'] ?? null,
                        ],
                        'enabled' => $data['enabled'] ?? true,
                    ]);
                }),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ServerAlertStats::class,
            ServerOpenAlertsTable::class,
            ServerAlertHistoryTable::class,
            ServerAlertRulesTable::class,
            ServerAlertChannelsTable::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    private function ruleSchema(): array
    {
        return [
            TextInput::make('name')->required()->maxLength(255),
            Select::make('metric')
                ->options(collect(AlertMetric::cases())
                    ->filter(fn (AlertMetric $metric) => $metric !== AlertMetric::NODE_OFFLINE)
                    ->mapWithKeys(fn (AlertMetric $metric) => [$metric->value => $metric->label()])
                    ->all())
                ->required()
                ->live(),
            Select::make('operator')
                ->options(collect(AlertOperator::cases())->mapWithKeys(fn (AlertOperator $operator) => [$operator->value => $operator->value])->all())
                ->default(AlertOperator::GTE->value)
                ->required()
                ->visible(fn (Get $get) => !AlertMetric::tryFrom((string) $get('metric'))?->isBoolean()),
            TextInput::make('threshold')
                ->numeric()
                ->minValue(0)
                ->maxValue(100)
                ->suffix('%')
                ->required(fn (Get $get) => !AlertMetric::tryFrom((string) $get('metric'))?->isBoolean())
                ->visible(fn (Get $get) => !AlertMetric::tryFrom((string) $get('metric'))?->isBoolean()),
            TextInput::make('duration_minutes')->numeric()->minValue(0)->required()->default(5),
            TextInput::make('cooldown_minutes')->numeric()->minValue(0)->required()->default(30),
            Select::make('severity')
                ->options(collect(AlertSeverity::cases())->mapWithKeys(fn (AlertSeverity $severity) => [$severity->value => ucfirst($severity->value)])->all())
                ->default(AlertSeverity::WARNING->value)
                ->required(),
            CheckboxList::make('channels')
                ->options([
                    AlertChannelType::PANEL->value => 'Panel',
                    AlertChannelType::DISCORD->value => 'Discord',
                    AlertChannelType::EMAIL->value => 'Email',
                    AlertChannelType::TELEGRAM->value => 'Telegram',
                    AlertChannelType::SLACK->value => 'Slack',
                    AlertChannelType::PUSH->value => 'Browser Push',
                ])
                ->default([AlertChannelType::PANEL->value, AlertChannelType::PUSH->value]),
            Toggle::make('enabled')->default(true),
        ];
    }
}
