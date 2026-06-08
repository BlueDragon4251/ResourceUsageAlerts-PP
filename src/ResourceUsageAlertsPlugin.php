<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts;

use App\Contracts\Plugins\HasPluginSettings;
use App\Traits\EnvironmentWriterTrait;
use Filament\Contracts\Plugin;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Crypt;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertSeverity;
use PelicanPlugins\ResourceUsageAlerts\Livewire\OpenAlertBanners;

class ResourceUsageAlertsPlugin implements HasPluginSettings, Plugin
{
    use EnvironmentWriterTrait;

    public function getId(): string
    {
        return 'resourceusagealerts';
    }

    public function register(Panel $panel): void
    {
        $id = str($panel->getId())->title();

        $panel->discoverPages(
            plugin_path($this->getId(), "src/Filament/$id/Pages"),
            "PelicanPlugins\\ResourceUsageAlerts\\Filament\\$id\\Pages"
        );
        $panel->discoverResources(
            plugin_path($this->getId(), "src/Filament/$id/Resources"),
            "PelicanPlugins\\ResourceUsageAlerts\\Filament\\$id\\Resources"
        );
        $panel->discoverWidgets(
            plugin_path($this->getId(), "src/Filament/$id/Widgets"),
            "PelicanPlugins\\ResourceUsageAlerts\\Filament\\$id\\Widgets"
        );

        if (in_array($panel->getId(), ['admin', 'server'], true)) {
            $panel->renderHook(
                PanelsRenderHook::PAGE_START,
                fn (): string => Blade::render(
                    '@livewire($component, ["panelId" => $panelId])',
                    ['component' => OpenAlertBanners::class, 'panelId' => $panel->getId()]
                )
            );
        }
    }

    public function boot(Panel $panel): void {}

    public function getSettingsForm(): array
    {
        return [
            Toggle::make('enabled')
                ->label(trans('resourceusagealerts::strings.settings.enabled'))
                ->default($this->booleanConfig('resourceusagealerts.enabled', true)),
            TextInput::make('poll_interval_minutes')
                ->label(trans('resourceusagealerts::strings.settings.poll_interval'))
                ->numeric()
                ->minValue(1)
                ->maxValue(60)
                ->required()
                ->default((int) config('resourceusagealerts.poll_interval_minutes', 5)),
            TextInput::make('sample_retention_days')
                ->label(trans('resourceusagealerts::strings.settings.sample_retention'))
                ->numeric()
                ->minValue(1)
                ->required()
                ->default((int) config('resourceusagealerts.sample_retention_days', 14)),
            TextInput::make('event_retention_days')
                ->label(trans('resourceusagealerts::strings.settings.event_retention'))
                ->numeric()
                ->minValue(1)
                ->required()
                ->default((int) config('resourceusagealerts.event_retention_days', 90)),
            Toggle::make('allow_user_rules')
                ->label(trans('resourceusagealerts::strings.settings.allow_user_rules'))
                ->default($this->booleanConfig('resourceusagealerts.allow_user_rules', true)),
            Toggle::make('allow_user_channels')
                ->label(trans('resourceusagealerts::strings.settings.allow_user_channels'))
                ->default($this->booleanConfig('resourceusagealerts.allow_user_channels', true)),
            Toggle::make('push_enabled')
                ->label(trans('resourceusagealerts::strings.settings.push_enabled'))
                ->default($this->booleanConfig('resourceusagealerts.push_enabled', true)),
            TextInput::make('vapid_subject')
                ->label(trans('resourceusagealerts::strings.settings.vapid_subject'))
                ->helperText(trans('resourceusagealerts::strings.settings.vapid_subject_help'))
                ->default((string) config('resourceusagealerts.vapid_subject', config('app.url')))
                ->required(),
            TextInput::make('vapid_public_key')
                ->label(trans('resourceusagealerts::strings.settings.vapid_public_key'))
                ->default((string) config('resourceusagealerts.vapid_public_key', ''))
                ->helperText(trans('resourceusagealerts::strings.settings.vapid_keys_help')),
            TextInput::make('vapid_private_key')
                ->label(trans('resourceusagealerts::strings.settings.vapid_private_key'))
                ->password()
                ->revealable()
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->helperText(trans('resourceusagealerts::strings.settings.vapid_private_key_help')),
            TextInput::make('global_discord_webhook')
                ->label(trans('resourceusagealerts::strings.settings.global_discord_webhook'))
                ->password()
                ->revealable()
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->helperText(trans('resourceusagealerts::strings.settings.webhook_secret_help')),
            Select::make('minimum_notification_severity')
                ->label(trans('resourceusagealerts::strings.settings.minimum_severity'))
                ->options([
                    AlertSeverity::INFO->value => 'Info',
                    AlertSeverity::WARNING->value => 'Warning',
                    AlertSeverity::CRITICAL->value => 'Critical',
                ])
                ->default((string) config('resourceusagealerts.minimum_notification_severity', 'info'))
                ->required(),
        ];
    }

    public function saveSettings(array $data): void
    {
        $values = [
            'RESOURCE_USAGE_ALERTS_ENABLED' => (bool) ($data['enabled'] ?? false),
            'RESOURCE_USAGE_ALERTS_POLL_INTERVAL' => max(1, (int) ($data['poll_interval_minutes'] ?? 5)),
            'RESOURCE_USAGE_ALERTS_SAMPLE_RETENTION_DAYS' => max(1, (int) ($data['sample_retention_days'] ?? 14)),
            'RESOURCE_USAGE_ALERTS_EVENT_RETENTION_DAYS' => max(1, (int) ($data['event_retention_days'] ?? 90)),
            'RESOURCE_USAGE_ALERTS_ALLOW_USER_RULES' => (bool) ($data['allow_user_rules'] ?? false),
            'RESOURCE_USAGE_ALERTS_ALLOW_USER_CHANNELS' => (bool) ($data['allow_user_channels'] ?? false),
            'RESOURCE_USAGE_ALERTS_PUSH_ENABLED' => (bool) ($data['push_enabled'] ?? false),
            'RESOURCE_USAGE_ALERTS_VAPID_SUBJECT' => (string) ($data['vapid_subject'] ?? config('app.url')),
            'RESOURCE_USAGE_ALERTS_VAPID_PUBLIC_KEY' => (string) ($data['vapid_public_key'] ?? ''),
            'RESOURCE_USAGE_ALERTS_MINIMUM_SEVERITY' => AlertSeverity::tryFrom((string) ($data['minimum_notification_severity'] ?? 'info'))?->value ?? 'info',
        ];

        if (filled($data['global_discord_webhook'] ?? null)) {
            $values['RESOURCE_USAGE_ALERTS_GLOBAL_DISCORD_WEBHOOK'] = 'encrypted:' . Crypt::encryptString((string) $data['global_discord_webhook']);
        }

        if (filled($data['vapid_private_key'] ?? null)) {
            $values['RESOURCE_USAGE_ALERTS_VAPID_PRIVATE_KEY'] = 'encrypted:' . Crypt::encryptString((string) $data['vapid_private_key']);
        }

        $this->writeToEnvironment($values);
    }

    private function booleanConfig(string $key, bool $default): bool
    {
        return filter_var(config($key, $default), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }
}
