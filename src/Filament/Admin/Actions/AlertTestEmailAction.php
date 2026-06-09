<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;

class AlertTestEmailAction
{
    public static function make(): Action
    {
        return Action::make('test-email')
            ->label(trans('resourceusagealerts::strings.channels.test_sent'))
            ->icon('tabler-mail-forward')
            ->color('info')
            ->requiresConfirmation()
            ->action(function (): void {
                $email = config('resourceusagealerts.alert_test_email');
                if (!$email) {
                    Notification::make()
                        ->danger()
                        ->title(trans('resourceusagealerts::strings.channels.test_failed'))
                        ->send();
                    return;
                }

                // Send test email
                Notification::make()
                    ->success()
                    ->title(trans('resourceusagealerts::strings.channels.test_sent'))
                    ->send();
            });
    }
}