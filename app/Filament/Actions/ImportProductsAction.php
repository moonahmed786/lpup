<?php

namespace App\Filament\Actions;

use App\Services\ProductImportService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;

class ImportProductsAction
{
    public static function make(): Action
    {
        return Action::make('importProducts')
            ->label('Import products')
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->modalHeading('Import products')
            ->modalSubmitActionLabel('Queue import')
            ->schema([
                FileUpload::make('file')
                    ->label('Spreadsheet')
                    ->helperText('.xlsx or .csv with header columns: name, sku, quantity, status')
                    ->disk('local')
                    ->directory('imports')
                    ->preserveFilenames()
                    ->maxSize(102400)
                    ->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-excel',
                        'text/csv',
                        'text/plain',
                    ])
                    ->required(),
            ])
            ->action(function (array $data): void {
                $path = Arr::first(Arr::wrap($data['file']));

                $import = app(ProductImportService::class)->startImport(
                    storedPath: $path,
                    originalFilename: basename($path),
                    userId: auth()->id(),
                );

                Notification::make()
                    ->title('Import queued')
                    ->body("\"{$import->filename}\" is being processed. Progress updates below.")
                    ->success()
                    ->send();
            });
    }
}
