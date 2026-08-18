<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Pages;

use App\Traits\EnvironmentWriterTrait;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Enums\IconSize;
use Illuminate\Support\Facades\Crypt;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertSeverity;
use UnitEnum;

/**
 * Standalone admin page for the plugin settings.
 *
 * Pelican does not have an App\Models\Setting model. Older versions of this
 * page tried to read/write settings through that model, which caused 500s as
 * soon as the page was opened. The plugin settings are env/config based, just
 * like the normal Pelican plugin settings form.
 *
 * @property Schema $form
 */
class ResourceAlertSettings extends Page implements HasSchemas
{
    use EnvironmentWriterTrait;
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'tabler-settings';

    protected static UnitEnum|string|null $navigationGroup = 'Resource Alerts';

    protected static ?string $slug = 'resource-alert-settings';

    protected static ?int $navigationSort = 99;

    protected string $view = 'filament.pages.settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill($this->defaults());
    }

    protected function getFormStatePath(): ?string
    {
        return 'data';
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return trans('resourceusagealerts::strings.navigation.group');
    }

    public static function getNavigationLabel(): string
    {
        return trans('resourceusagealerts::strings.settings.title');
    }

    public function getTitle(): string
    {
        return trans('resourceusagealerts::strings.settings.title');
    }

    protected function getFormSchema(): array
    {
        $defaults = $this->defaults();

        return [
            Section::make(trans('resourceusagealerts::strings.settings.general'))
                ->schema([
                    Group::make()->columns(2)->schema([
                        Toggle::make('enabled')
                            ->label(trans('resourceusagealerts::strings.settings.enabled'))
                            ->default($defaults['enabled']),
                        TextInput::make('poll_interval_minutes')
                            ->label(trans('resourceusagealerts::strings.settings.poll_interval'))
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(60)
                            ->required()
                            ->default($defaults['poll_interval_minutes']),
                        TextInput::make('stale_metric_grace_minutes')
                            ->label(trans('resourceusagealerts::strings.settings.stale_metric_grace_minutes'))
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(60)
                            ->required()
                            ->default($defaults['stale_metric_grace_minutes']),
                        TextInput::make('sample_retention_days')
                            ->label(trans('resourceusagealerts::strings.settings.sample_retention'))
                            ->numeric()
                            ->minValue(1)
                            ->required()
                            ->default($defaults['sample_retention_days']),
                        TextInput::make('event_retention_days')
                            ->label(trans('resourceusagealerts::strings.settings.event_retention'))
                            ->numeric()
                            ->minValue(1)
                            ->required()
                            ->default($defaults['event_retention_days']),
                        TextInput::make('backup_stale_days')
                            ->label(trans('resourceusagealerts::strings.settings.backup_stale_days'))
                            ->numeric()->minValue(1)->required()->default($defaults['backup_stale_days']),
                        TextInput::make('minimum_wings_version')
                            ->label(trans('resourceusagealerts::strings.settings.minimum_wings_version'))
                            ->default($defaults['minimum_wings_version']),
                        Toggle::make('status_page_enabled')
                            ->label(trans('resourceusagealerts::strings.settings.status_page_enabled'))
                            ->default($defaults['status_page_enabled']),
                        TextInput::make('status_page_token')
                            ->label(trans('resourceusagealerts::strings.settings.status_page_token'))
                            ->password()->revealable()
                            ->helperText(trans('resourceusagealerts::strings.settings.status_page_token_help'))
                            ->dehydrated(fn (?string $state): bool => filled($state)),
                        Toggle::make('auto_restart_enabled')
                            ->label(trans('resourceusagealerts::strings.settings.auto_restart_enabled'))
                            ->default($defaults['auto_restart_enabled']),
                        TextInput::make('auto_restart_max_attempts')
                            ->label(trans('resourceusagealerts::strings.settings.auto_restart_max_attempts'))
                            ->numeric()->minValue(1)->maxValue(5)->required()->default($defaults['auto_restart_max_attempts']),
                        TextInput::make('auto_restart_cooldown_minutes')
                            ->label(trans('resourceusagealerts::strings.settings.auto_restart_cooldown'))
                            ->numeric()->minValue(5)->required()->default($defaults['auto_restart_cooldown_minutes']),
                    ]),
                ]),
            Section::make(trans('resourceusagealerts::strings.settings.permissions'))
                ->schema([
                    Group::make()->columns(2)->schema([
                        Toggle::make('allow_user_rules')
                            ->label(trans('resourceusagealerts::strings.settings.allow_user_rules'))
                            ->default($defaults['allow_user_rules']),
                        Toggle::make('allow_user_channels')
                            ->label(trans('resourceusagealerts::strings.settings.allow_user_channels'))
                            ->default($defaults['allow_user_channels']),
                        Toggle::make('block_private_webhook_ips')
                            ->label(trans('resourceusagealerts::strings.settings.block_private_webhook_ips'))
                            ->default($defaults['block_private_webhook_ips']),
                        TextInput::make('custom_webhook_allowed_domains')
                            ->label(trans('resourceusagealerts::strings.settings.custom_webhook_allowed_domains'))
                            ->helperText(trans('resourceusagealerts::strings.settings.custom_webhook_allowed_domains_help'))
                            ->default($defaults['custom_webhook_allowed_domains']),
                    ]),
                ]),
            Section::make(trans('resourceusagealerts::strings.settings.notifications'))
                ->schema([
                    Group::make()->columns(2)->schema([
                        Select::make('minimum_notification_severity')
                            ->label(trans('resourceusagealerts::strings.settings.minimum_severity'))
                            ->options([
                                AlertSeverity::INFO->value => trans('resourceusagealerts::strings.severity.info'),
                                AlertSeverity::WARNING->value => trans('resourceusagealerts::strings.severity.warning'),
                                AlertSeverity::CRITICAL->value => trans('resourceusagealerts::strings.severity.critical'),
                            ])
                            ->default($defaults['minimum_notification_severity'])
                            ->required(),
                        TextInput::make('global_discord_webhook')
                            ->label(trans('resourceusagealerts::strings.settings.global_discord_webhook'))
                            ->password()
                            ->revealable()
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->helperText(trans('resourceusagealerts::strings.settings.webhook_secret_help')),
                        TextInput::make('global_slack_webhook')
                            ->label(trans('resourceusagealerts::strings.settings.global_slack_webhook'))
                            ->password()
                            ->revealable()
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->helperText(trans('resourceusagealerts::strings.settings.webhook_secret_help')),
                        TextInput::make('global_telegram_bot_token')
                            ->label(trans('resourceusagealerts::strings.settings.global_telegram_bot_token'))
                            ->password()
                            ->revealable()
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->helperText(trans('resourceusagealerts::strings.settings.telegram_secret_help')),
                        TextInput::make('global_telegram_chat_id')
                            ->label(trans('resourceusagealerts::strings.settings.global_telegram_chat_id'))
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->helperText(trans('resourceusagealerts::strings.settings.telegram_secret_help')),
                    ]),
                ]),
            Section::make(trans('resourceusagealerts::strings.settings.browser_push'))
                ->schema([
                    Toggle::make('push_enabled')
                        ->label(trans('resourceusagealerts::strings.settings.push_enabled'))
                        ->default($defaults['push_enabled']),
                    TextInput::make('max_push_subscriptions_per_user')
                        ->label(trans('resourceusagealerts::strings.settings.max_push_subscriptions_per_user'))
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(100)
                        ->required()
                        ->default($defaults['max_push_subscriptions_per_user']),
                    TextInput::make('vapid_subject')
                        ->label(trans('resourceusagealerts::strings.settings.vapid_subject'))
                        ->helperText(trans('resourceusagealerts::strings.settings.vapid_subject_help'))
                        ->default($defaults['vapid_subject'])
                        ->required(),
                    Group::make()->columns(2)->schema([
                        TextInput::make('vapid_public_key')
                            ->label(trans('resourceusagealerts::strings.settings.vapid_public_key'))
                            ->default($defaults['vapid_public_key'])
                            ->helperText(trans('resourceusagealerts::strings.settings.vapid_keys_help')),
                        TextInput::make('vapid_private_key')
                            ->label(trans('resourceusagealerts::strings.settings.vapid_private_key'))
                            ->password()
                            ->revealable()
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->helperText(trans('resourceusagealerts::strings.settings.vapid_private_key_help')),
                    ]),
                ]),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label(trans('resourceusagealerts::strings.actions.save'))
                ->iconButton()
                ->iconSize(IconSize::ExtraLarge)
                ->icon('tabler-device-floppy')
                ->action('save')
                ->keyBindings(['mod+s']),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $values = [
            'RESOURCE_USAGE_ALERTS_ENABLED' => (bool) ($data['enabled'] ?? false),
            'RESOURCE_USAGE_ALERTS_POLL_INTERVAL' => max(1, (int) ($data['poll_interval_minutes'] ?? 5)),
            'RESOURCE_USAGE_ALERTS_STALE_METRIC_GRACE' => max(0, (int) ($data['stale_metric_grace_minutes'] ?? 2)),
            'RESOURCE_USAGE_ALERTS_SAMPLE_RETENTION_DAYS' => max(1, (int) ($data['sample_retention_days'] ?? 14)),
            'RESOURCE_USAGE_ALERTS_EVENT_RETENTION_DAYS' => max(1, (int) ($data['event_retention_days'] ?? 90)),
            'RESOURCE_USAGE_ALERTS_BACKUP_STALE_DAYS' => max(1, (int) ($data['backup_stale_days'] ?? 7)),
            'RESOURCE_USAGE_ALERTS_MINIMUM_WINGS_VERSION' => trim((string) ($data['minimum_wings_version'] ?? '')),
            'RESOURCE_USAGE_ALERTS_STATUS_PAGE_ENABLED' => (bool) ($data['status_page_enabled'] ?? false),
            'RESOURCE_USAGE_ALERTS_AUTO_RESTART_ENABLED' => (bool) ($data['auto_restart_enabled'] ?? false),
            'RESOURCE_USAGE_ALERTS_AUTO_RESTART_MAX_ATTEMPTS' => max(1, min(5, (int) ($data['auto_restart_max_attempts'] ?? 2))),
            'RESOURCE_USAGE_ALERTS_AUTO_RESTART_COOLDOWN' => max(5, (int) ($data['auto_restart_cooldown_minutes'] ?? 30)),
            'RESOURCE_USAGE_ALERTS_ALLOW_USER_RULES' => (bool) ($data['allow_user_rules'] ?? false),
            'RESOURCE_USAGE_ALERTS_ALLOW_USER_CHANNELS' => (bool) ($data['allow_user_channels'] ?? false),
            'RESOURCE_USAGE_ALERTS_BLOCK_PRIVATE_WEBHOOK_IPS' => (bool) ($data['block_private_webhook_ips'] ?? true),
            'RESOURCE_USAGE_ALERTS_CUSTOM_WEBHOOK_ALLOWED_DOMAINS' => implode(',', array_values(array_filter(array_map(
                'trim',
                explode(',', (string) ($data['custom_webhook_allowed_domains'] ?? ''))
            )))),
            'RESOURCE_USAGE_ALERTS_PUSH_ENABLED' => (bool) ($data['push_enabled'] ?? false),
            'RESOURCE_USAGE_ALERTS_MAX_PUSH_SUBSCRIPTIONS_PER_USER' => max(1, (int) ($data['max_push_subscriptions_per_user'] ?? 10)),
            'RESOURCE_USAGE_ALERTS_VAPID_SUBJECT' => (string) ($data['vapid_subject'] ?? config('app.url')),
            'RESOURCE_USAGE_ALERTS_VAPID_PUBLIC_KEY' => (string) ($data['vapid_public_key'] ?? ''),
            'RESOURCE_USAGE_ALERTS_MINIMUM_SEVERITY' => AlertSeverity::tryFrom((string) ($data['minimum_notification_severity'] ?? 'info'))?->value ?? 'info',
        ];

        if (filled($data['global_discord_webhook'] ?? null)) {
            $values['RESOURCE_USAGE_ALERTS_GLOBAL_DISCORD_WEBHOOK'] = 'encrypted:'.Crypt::encryptString((string) $data['global_discord_webhook']);
        }

        if (filled($data['global_slack_webhook'] ?? null)) {
            $values['RESOURCE_USAGE_ALERTS_GLOBAL_SLACK_WEBHOOK'] = 'encrypted:'.Crypt::encryptString((string) $data['global_slack_webhook']);
        }

        if (filled($data['global_telegram_bot_token'] ?? null)) {
            $values['RESOURCE_USAGE_ALERTS_GLOBAL_TELEGRAM_BOT_TOKEN'] = 'encrypted:'.Crypt::encryptString((string) $data['global_telegram_bot_token']);
        }

        if (filled($data['global_telegram_chat_id'] ?? null)) {
            $values['RESOURCE_USAGE_ALERTS_GLOBAL_TELEGRAM_CHAT_ID'] = 'encrypted:'.Crypt::encryptString((string) $data['global_telegram_chat_id']);
        }

        if (filled($data['vapid_private_key'] ?? null)) {
            $values['RESOURCE_USAGE_ALERTS_VAPID_PRIVATE_KEY'] = 'encrypted:'.Crypt::encryptString((string) $data['vapid_private_key']);
        }

        if (filled($data['status_page_token'] ?? null)) {
            $values['RESOURCE_USAGE_ALERTS_STATUS_PAGE_TOKEN'] = (string) $data['status_page_token'];
        }

        $this->writeToEnvironment($values);

        Notification::make()
            ->title(trans('resourceusagealerts::strings.actions.saved'))
            ->success()
            ->send();
    }

    private function defaults(): array
    {
        return [
            'enabled' => $this->booleanConfig('resourceusagealerts.enabled', true),
            'poll_interval_minutes' => (int) config('resourceusagealerts.poll_interval_minutes', 5),
            'stale_metric_grace_minutes' => (int) config('resourceusagealerts.stale_metric_grace_minutes', 2),
            'sample_retention_days' => (int) config('resourceusagealerts.sample_retention_days', 14),
            'event_retention_days' => (int) config('resourceusagealerts.event_retention_days', 90),
            'backup_stale_days' => (int) config('resourceusagealerts.backup_stale_days', 7),
            'minimum_wings_version' => (string) config('resourceusagealerts.minimum_wings_version', ''),
            'status_page_enabled' => $this->booleanConfig('resourceusagealerts.status_page_enabled', false),
            'auto_restart_enabled' => $this->booleanConfig('resourceusagealerts.auto_restart_enabled', false),
            'auto_restart_max_attempts' => (int) config('resourceusagealerts.auto_restart_max_attempts', 2),
            'auto_restart_cooldown_minutes' => (int) config('resourceusagealerts.auto_restart_cooldown_minutes', 30),
            'allow_user_rules' => $this->booleanConfig('resourceusagealerts.allow_user_rules', true),
            'allow_user_channels' => $this->booleanConfig('resourceusagealerts.allow_user_channels', true),
            'block_private_webhook_ips' => $this->booleanConfig('resourceusagealerts.block_private_webhook_ips', true),
            'custom_webhook_allowed_domains' => implode(',', (array) config('resourceusagealerts.custom_webhook_allowed_domains', [])),
            'push_enabled' => $this->booleanConfig('resourceusagealerts.push_enabled', true),
            'max_push_subscriptions_per_user' => (int) config('resourceusagealerts.max_push_subscriptions_per_user', 10),
            'vapid_subject' => (string) config('resourceusagealerts.vapid_subject', config('app.url')),
            'vapid_public_key' => (string) config('resourceusagealerts.vapid_public_key', ''),
            'minimum_notification_severity' => (string) config('resourceusagealerts.minimum_notification_severity', 'info'),
        ];
    }

    private function booleanConfig(string $key, bool $default): bool
    {
        return filter_var(config($key, $default), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }
}
