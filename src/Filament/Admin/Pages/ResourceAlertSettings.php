<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Pages;

use App\Enums\Migrations\FilamentStatus;
use App\Models\Setting;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertSeverity;

class ResourceAlertSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-settings';

    protected static ?string $navigationGroup = 'Resource Alerts';

    protected static ?string $navigationLabel = 'Settings';

    protected static ?string $title = 'Resource Alert Settings';

    protected static string $view = 'filament.resources.pages.resource-alert-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'alert_settings_enabled' => Setting::get('alert_settings.enabled', true),
            'alert_settings_poll_interval' => Setting::get('alert_settings.poll_interval_minutes', 5),
            'alert_settings_sample_retention' => Setting::get('alert_settings.sample_retention_days', 7),
            'alert_settings_event_retention' => Setting::get('alert_settings.resolved_event_retention_days', 14),
            'alert_settings_allow_user_rules' => Setting::get('alert_settings.allow_user_rules', false),
            'alert_settings_allow_user_channels' => Setting::get('alert_settings.allow_user_channels', false),
            'alert_settings_push_enabled' => Setting::get('alert_settings.push_enabled', false),
            'alert_settings_minimum_severity' => Setting::get('alert_settings.minimum_severity', 'warning'),
            'alert_settings_global_discord_webhook' => Setting::get('alert_settings.global_discord_webhook'),
            'alert_settings_vapid_subject' => Setting::get('alert_settings.vapid_subject'),
            'alert_settings_vapid_public_key' => Setting::get('alert_settings.vapid_public_key'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('General')
                    ->schema([
                        Toggle::make('alert_settings_enabled')
                            ->label(trans('resourceusagealerts::strings.settings.enabled'))
                            ->default(true),
                        TextInput::make('alert_settings_poll_interval')
                            ->label(trans('resourceusagealerts::strings.settings.poll_interval'))
                            ->numeric()
                            ->default(5)
                            ->minValue(1)
                            ->maxValue(60),
                        TextInput::make('alert_settings_sample_retention')
                            ->label(trans('resourceusagealerts::strings.settings.sample_retention'))
                            ->numeric()
                            ->default(7)
                            ->minValue(1),
                        TextInput::make('alert_settings_event_retention')
                            ->label(trans('resourceusagealerts::strings.settings.event_retention'))
                            ->numeric()
                            ->default(14)
                            ->minValue(1),
                    ])->columns(2),

                Section::make('Permissions')
                    ->schema([
                        Toggle::make('alert_settings_allow_user_rules')
                            ->label(trans('resourceusagealerts::strings.settings.allow_user_rules')),
                        Toggle::make('alert_settings_allow_user_channels')
                            ->label(trans('resourceusagealerts::strings.settings.allow_user_channels')),
                    ])->columns(2),

                Section::make('Notifications')
                    ->schema([
                        Select::make('alert_settings_minimum_severity')
                            ->label(trans('resourceusagealerts::strings.settings.minimum_severity'))
                            ->options(collect(AlertSeverity::cases())->mapWithKeys(fn (AlertSeverity $s) => [$s->value => str($s->value)->title()->toString()])->all())
                            ->default('warning'),
                        TextInput::make('alert_settings_global_discord_webhook')
                            ->label(trans('resourceusagealerts::strings.settings.global_discord_webhook'))
                            ->url()
                            ->nullable()
                            ->helperText(trans('resourceusagealerts::strings.settings.webhook_secret_help')),
                    ]),

                Section::make('Browser Push (VAPID)')
                    ->schema([
                        Toggle::make('alert_settings_push_enabled')
                            ->label(trans('resourceusagealerts::strings.settings.push_enabled')),
                        TextInput::make('alert_settings_vapid_subject')
                            ->label(trans('resourceusagealerts::strings.settings.vapid_subject'))
                            ->helperText(trans('resourceusagealerts::strings.settings.vapid_subject_help')),
                        Grid::make(2)->schema([
                            TextInput::make('alert_settings_vapid_public_key')
                                ->label(trans('resourceusagealerts::strings.settings.vapid_public_key'))
                                ->helperText(trans('resourceusagealerts::strings.settings.vapid_keys_help')),
                            TextInput::make('alert_settings_vapid_private_key')
                                ->label(trans('resourceusagealerts::strings.settings.vapid_private_key'))
                                ->helperText(trans('resourceusagealerts::strings.settings.vapid_private_key_help')),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            Setting::set($key, $value);
        }

        $this->notify('success', trans('resourceusagealerts::strings.actions.saved'));
    }
}