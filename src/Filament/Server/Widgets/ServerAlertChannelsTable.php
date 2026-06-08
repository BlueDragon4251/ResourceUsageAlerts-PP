<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Filament\Server\Widgets;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertChannelType;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertChannel;
use Throwable;

class ServerAlertChannelsTable extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return filter_var(
            config('resourceusagealerts.allow_user_channels', true),
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        ) ?? true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(trans('resourceusagealerts::strings.server.channels'))
            ->query(ResourceAlertChannel::query()->where('user_id', user()?->id)->latest())
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('type')->badge(),
                IconColumn::make('enabled')->boolean(),
                TextColumn::make('updated_at')->since(),
            ])
            ->recordActions([
                Action::make('test')
                    ->icon('tabler-send')
                    ->visible(fn (ResourceAlertChannel $record) => $record->type === AlertChannelType::DISCORD && $record->enabled)
                    ->action(function (ResourceAlertChannel $record): void {
                        try {
                            $allowed = RateLimiter::attempt(
                                "resource-alert-channel-test:{$record->id}:" . user()?->id,
                                1,
                                function () use ($record): void {
                                    $url = data_get($record->config, 'webhook_url');
                                    if (!is_string($url) || $url === '') {
                                        throw new \RuntimeException('Missing webhook URL.');
                                    }

                                    Http::connectTimeout(2)
                                        ->timeout((int) config('resourceusagealerts.discord_timeout_seconds', 5))
                                        ->post($url, ['content' => 'Pelican Resource Usage Alerts test notification.'])
                                        ->throw();
                                },
                                60
                            );

                            Notification::make()
                                ->status($allowed ? 'success' : 'warning')
                                ->title($allowed
                                    ? trans('resourceusagealerts::strings.channels.test_sent')
                                    : trans('resourceusagealerts::strings.channels.rate_limited'))
                                ->send();
                        } catch (Throwable $exception) {
                            report($exception);
                            Notification::make()
                                ->danger()
                                ->title(trans('resourceusagealerts::strings.channels.test_failed'))
                                ->send();
                        }
                    }),
                Action::make('toggle')
                    ->action(fn (ResourceAlertChannel $record) => $record->update(['enabled' => !$record->enabled])),
                DeleteAction::make(),
            ]);
    }
}
