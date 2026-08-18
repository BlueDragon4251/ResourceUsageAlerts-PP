<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Filament\Server\Widgets;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertChannelType;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertChannel;
use PelicanPlugins\ResourceUsageAlerts\Services\OutboundEndpointGuard;
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
            ->query(ResourceAlertChannel::query()
                ->where('user_id', user()?->id)
                ->where(function ($query): void {
                    $query->whereNull('server_id')->orWhere('server_id', Filament::getTenant()?->id);
                })
                ->latest())
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('type')->badge(),
                IconColumn::make('enabled')->boolean(),
                TextColumn::make('updated_at')->since(),
            ])
            ->recordActions([
                Action::make('test')
                    ->icon('tabler-send')
                    ->visible(fn (ResourceAlertChannel $record) => ! in_array($record->type, [AlertChannelType::PANEL, AlertChannelType::EMAIL, AlertChannelType::PUSH], true) && $record->enabled)
                    ->action(function (ResourceAlertChannel $record): void {
                        try {
                            $allowed = RateLimiter::attempt(
                                "resource-alert-channel-test:{$record->id}:".user()?->id,
                                1,
                                function () use ($record): void {
                                    match ($record->type) {
                                        AlertChannelType::DISCORD => $this->sendWebhookTest($record, ['content' => 'Pelican Resource Usage Alerts test notification.'], 'discord_timeout_seconds'),
                                        AlertChannelType::SLACK => $this->sendWebhookTest($record, ['text' => 'Pelican Resource Usage Alerts test notification.'], 'slack_timeout_seconds'),
                                        AlertChannelType::TELEGRAM => $this->sendTelegramTest($record),
                                        AlertChannelType::CUSTOM_WEBHOOK => $this->sendCustomWebhookTest($record),
                                        AlertChannelType::NTFY, AlertChannelType::GOTIFY, AlertChannelType::MATRIX => $this->sendSelfHostedTest($record),
                                        default => null,
                                    };
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
                    ->action(fn (ResourceAlertChannel $record) => $record->update(['enabled' => ! $record->enabled])),
                DeleteAction::make(),
            ]);
    }

    private function sendWebhookTest(ResourceAlertChannel $record, array $payload, string $timeoutConfigKey): void
    {
        $url = data_get($record->config, 'webhook_url');
        if (! is_string($url) || $url === '') {
            throw new \RuntimeException('Missing webhook URL.');
        }

        $allowedDomains = $record->type === AlertChannelType::DISCORD
            ? ['discord.com', 'discordapp.com']
            : ['hooks.slack.com'];
        app(OutboundEndpointGuard::class)->assertAllowed($url, $allowedDomains);

        Http::connectTimeout(2)
            ->timeout((int) config("resourceusagealerts.{$timeoutConfigKey}", 5))
            ->post($url, $payload)
            ->throw();
    }

    private function sendCustomWebhookTest(ResourceAlertChannel $record): void
    {
        $url = data_get($record->config, 'webhook_url');
        $secret = data_get($record->config, 'signing_secret');
        if (! is_string($url) || $url === '' || ! is_string($secret) || strlen($secret) < 32) {
            throw new \RuntimeException('Missing custom webhook URL or signing secret.');
        }

        $allowedDomains = array_values(array_filter(array_map(
            'strval',
            (array) config('resourceusagealerts.custom_webhook_allowed_domains', [])
        )));
        app(OutboundEndpointGuard::class)->assertAllowed($url, $allowedDomains);
        $timestamp = (string) now()->timestamp;
        $nonce = bin2hex(random_bytes(16));
        $body = json_encode(['type' => 'test', 'message' => 'Pelican Resource Usage Alerts test notification.'], JSON_THROW_ON_ERROR);
        $signed = $timestamp.'.'.$nonce.'.'.$body;

        Http::connectTimeout(2)
            ->timeout((int) config('resourceusagealerts.custom_webhook_timeout_seconds', 10))
            ->withHeaders([
                'X-Resource-Alerts-Timestamp' => $timestamp,
                'X-Resource-Alerts-Nonce' => $nonce,
                'X-Resource-Alerts-Signature' => 'sha256='.hash_hmac('sha256', $signed, $secret),
            ])
            ->withBody($body, 'application/json')
            ->post($url)
            ->throw();
    }

    private function sendTelegramTest(ResourceAlertChannel $record): void
    {
        $botToken = data_get($record->config, 'bot_token');
        $chatId = data_get($record->config, 'chat_id');

        if (! is_string($botToken) || $botToken === '' || ! is_string($chatId) || $chatId === '') {
            throw new \RuntimeException('Missing Telegram credentials.');
        }

        Http::connectTimeout(2)
            ->timeout((int) config('resourceusagealerts.telegram_timeout_seconds', 5))
            ->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => 'Pelican Resource Usage Alerts test notification.',
                'disable_web_page_preview' => true,
            ])
            ->throw();
    }

    private function sendSelfHostedTest(ResourceAlertChannel $record): void
    {
        $endpoint = rtrim((string) data_get($record->config, 'endpoint_url', ''), '/');
        $token = (string) data_get($record->config, 'api_token', '');
        app(OutboundEndpointGuard::class)->assertAllowed($endpoint);
        $request = Http::connectTimeout(2)->timeout(10);

        match ($record->type) {
            AlertChannelType::NTFY => $request->withToken($token)->withBody('Pelican Resource Usage Alerts test notification.', 'text/plain')->post($endpoint.'/'.rawurlencode((string) data_get($record->config, 'topic', 'pelican-alerts')))->throw(),
            AlertChannelType::GOTIFY => $request->post($endpoint.'/message?token='.rawurlencode($token), ['title' => 'Pelican', 'message' => 'Resource Usage Alerts test notification.', 'priority' => 5])->throw(),
            AlertChannelType::MATRIX => $request->withToken($token)->put($endpoint.'/_matrix/client/v3/rooms/'.rawurlencode((string) data_get($record->config, 'room_id')).'/send/m.room.message/'.bin2hex(random_bytes(8)), ['msgtype' => 'm.text', 'body' => 'Pelican Resource Usage Alerts test notification.'])->throw(),
            default => null,
        };
    }
}
