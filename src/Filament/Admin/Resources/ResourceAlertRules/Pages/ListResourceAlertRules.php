<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Resources\ResourceAlertRules\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Http\UploadedFile;
use PelicanPlugins\ResourceUsageAlerts\Filament\Admin\Resources\ResourceAlertRules\ResourceAlertRuleResource;
use PelicanPlugins\ResourceUsageAlerts\Services\RuleTemplateService;

class ListResourceAlertRules extends ListRecords
{
    protected static string $resource = ResourceAlertRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_rules')->label(trans('resourceusagealerts::strings.rules.export'))->icon('tabler-download')
                ->action(fn () => response()->streamDownload(
                    fn () => print (app(RuleTemplateService::class)->exportRules()),
                    'resource-alert-rules.json',
                    ['Content-Type' => 'application/json']
                )),
            Action::make('import_rules')->label(trans('resourceusagealerts::strings.rules.import'))->icon('tabler-upload')
                ->schema([FileUpload::make('file')->acceptedFileTypes(['application/json', 'text/json'])->storeFiles(false)->required()])
                ->action(function (array $data): void {
                    $file = $data['file'] ?? null;
                    if (is_array($file)) {
                        $file = reset($file) ?: null;
                    }
                    $path = $file instanceof UploadedFile ? $file->getRealPath() : $file;
                    $json = is_string($path) && is_file($path) ? file_get_contents($path) : false;
                    if (! is_string($json)) {
                        Notification::make()->danger()->title(trans('resourceusagealerts::strings.rules.import_failed'))->send();

                        return;
                    }
                    try {
                        $count = app(RuleTemplateService::class)->importRules($json);
                        Notification::make()->success()->title(trans('resourceusagealerts::strings.rules.imported', ['count' => $count]))->send();
                    } catch (\Throwable) {
                        Notification::make()->danger()->title(trans('resourceusagealerts::strings.rules.import_failed'))->send();
                    }
                }),
        ];
    }
}
