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
                    ->helperText('.xlsx or .csv with header columns: name, sku, quantity or stock, price, description, status')
                    ->disk(config('product_import.disk'))
                    ->directory('imports')
                    ->preserveFilenames()
                    ->maxSize(config('product_import.max_upload_kb'))
                    ->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-excel',
                        'text/csv',
                        'text/plain',
                    ])
                    ->required(),
            ])
            ->visible(fn (): bool => auth()->user()?->can('imports.upload') ?? false)
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
