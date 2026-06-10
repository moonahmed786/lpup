<?php

namespace App\Filament\Actions;

use App\Filament\Support\FilamentAccess;
use App\Services\ProductImportService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
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
                TextInput::make('file')
                    ->type('file')
                    ->label('Spreadsheet')
                    ->helperText('.xlsx or .csv with header columns: name, sku, quantity or stock, price, description, status')
                    ->extraInputAttributes([
                        'accept' => '.csv,.xlsx,text/csv,text/x-csv,application/csv,application/x-csv,text/plain,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel',
                    ])
                    ->rules([
                        'file',
                        'max:'.config('product_import.max_upload_kb'),
                        'mimetypes:text/csv,text/x-csv,application/csv,application/x-csv,text/comma-separated-values,text/x-comma-separated-values,text/plain,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel',
                    ])
                    ->required(),
            ])
            ->visible(fn (): bool => FilamentAccess::hasPermission('imports.upload'))
            ->action(function (array $data): void {
                $uploadedFile = Arr::first(Arr::wrap($data['file']));

                $import = app(ProductImportService::class)->startUploadedImport(
                    uploadedFile: $uploadedFile,
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
