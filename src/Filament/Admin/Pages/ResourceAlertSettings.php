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
 * @property \Filament\Schemas\Schema $form
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
                    ]),
                ]),
            Section::make(trans('resourceusagealerts::strings.settings.notifications'))
                ->schema([
                    Group::make()->columns(2)->schema([
                        Select::make('minimum_notification_severity')
                            ->label(trans('resourceusagealerts::strings.settings.minimum_severity'))
                            ->options([
                                AlertSeverity::INFO->value => 'Info',
                                AlertSeverity::WARNING->value => 'Warning',
                                AlertSeverity::CRITICAL->value => 'Critical',
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

        if (filled($data['global_slack_webhook'] ?? null)) {
            $values['RESOURCE_USAGE_ALERTS_GLOBAL_SLACK_WEBHOOK'] = 'encrypted:' . Crypt::encryptString((string) $data['global_slack_webhook']);
        }

        if (filled($data['global_telegram_bot_token'] ?? null)) {
            $values['RESOURCE_USAGE_ALERTS_GLOBAL_TELEGRAM_BOT_TOKEN'] = 'encrypted:' . Crypt::encryptString((string) $data['global_telegram_bot_token']);
        }

        if (filled($data['global_telegram_chat_id'] ?? null)) {
            $values['RESOURCE_USAGE_ALERTS_GLOBAL_TELEGRAM_CHAT_ID'] = 'encrypted:' . Crypt::encryptString((string) $data['global_telegram_chat_id']);
        }

        if (filled($data['vapid_private_key'] ?? null)) {
            $values['RESOURCE_USAGE_ALERTS_VAPID_PRIVATE_KEY'] = 'encrypted:' . Crypt::encryptString((string) $data['vapid_private_key']);
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
            'sample_retention_days' => (int) config('resourceusagealerts.sample_retention_days', 14),
            'event_retention_days' => (int) config('resourceusagealerts.event_retention_days', 90),
            'allow_user_rules' => $this->booleanConfig('resourceusagealerts.allow_user_rules', true),
            'allow_user_channels' => $this->booleanConfig('resourceusagealerts.allow_user_channels', true),
            'push_enabled' => $this->booleanConfig('resourceusagealerts.push_enabled', true),
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
