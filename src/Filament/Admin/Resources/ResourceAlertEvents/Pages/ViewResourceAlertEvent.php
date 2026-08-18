<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Resources\ResourceAlertEvents\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Resources\ResourceAlertEvents\ResourceAlertEventResource;

class ViewResourceAlertEvent extends ViewRecord
{
    protected static string $resource = ResourceAlertEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('add_comment')
                ->label(trans('resourceusagealerts::strings.events.add_comment'))
                ->icon('tabler-message-plus')
                ->schema([
                    Textarea::make('body')
                        ->label(trans('resourceusagealerts::strings.events.comment'))
                        ->required()
                        ->minLength(2)
                        ->maxLength(5000),
                ])
                ->action(function (array $data): void {
                    $this->record->comments()->create([
                        'user_id' => user()?->id,
                        'body' => trim((string) $data['body']),
                    ]);
                    $this->record->load('comments.user');
                    Notification::make()->success()->title(trans('resourceusagealerts::strings.events.comment_added'))->send();
                }),
        ];
    }
}
