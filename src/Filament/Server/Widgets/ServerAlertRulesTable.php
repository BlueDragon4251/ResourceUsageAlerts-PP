<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Filament\Server\Widgets;

use App\Models\Server;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertRule;
use PelicanPlugins\ResourceUsageAlerts\Services\PermissionService;

class ServerAlertRulesTable extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        return $table
            ->heading(trans('resourceusagealerts::strings.server.active_rules'))
            ->query(ResourceAlertRule::query()->where('server_id', $server->id)->latest())
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('metric')->badge(),
                TextColumn::make('operator'),
                TextColumn::make('threshold')->suffix('%')->placeholder('-'),
                TextColumn::make('duration_minutes')->suffix(' min'),
                TextColumn::make('severity')->badge(),
                ViewColumn::make('recent_values')->label(trans('resourceusagealerts::strings.rules.recent_values'))->view('resourceusagealerts::tables.columns.rule-sparkline'),
                IconColumn::make('enabled')->boolean(),
            ])
            ->recordActions([
                Action::make('toggle')
                    ->label(fn (ResourceAlertRule $record) => $record->enabled
                        ? trans('resourceusagealerts::strings.actions.disable')
                        : trans('resourceusagealerts::strings.actions.enable'))
                    ->visible(fn (ResourceAlertRule $record) => user() !== null
                        && app(PermissionService::class)->canUpdateServerRule(user(), $record, $server))
                    ->action(function (ResourceAlertRule $record): void {
                        $record->update(['enabled' => ! $record->enabled]);
                        Notification::make()->success()->title(trans('resourceusagealerts::strings.actions.saved'))->send();
                    }),
                DeleteAction::make()
                    ->visible(fn (ResourceAlertRule $record) => user() !== null
                        && app(PermissionService::class)->canDeleteServerRule(user(), $record, $server)),
            ]);
    }
}
