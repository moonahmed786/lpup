<?php

namespace App\Filament\Actions;

use App\Filament\Support\FilamentAccess;
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
        $maxKb = (int) config('product_import.max_upload_kb');

        return Action::make('importProducts')
            ->label('Import products')
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->modalHeading('Import products')
            ->modalSubmitActionLabel('Queue import')
            ->schema([
                FileUpload::make('file')
                    ->label('Spreadsheet')
                    ->helperText('.xlsx, .xls or .csv with header columns: name, sku, quantity or stock, price, description, status')
                    ->disk(config('product_import.disk'))
                    ->directory('imports')
                    ->visibility('private')
                    // Keep the original name for display while the stored file uses a
                    // collision-proof random name on disk.
                    ->storeFileNamesIn('original_filename')
                    // Real-world CSV/XLSX files are detected under several MIME types
                    // (CSVs often arrive as text/plain or octet-stream, XLSX as zip),
                    // so accept the full legitimate set to avoid rejecting valid files.
                    ->acceptedFileTypes([
                        'text/csv',
                        'text/plain',
                        'application/csv',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/zip',
                        'application/octet-stream',
                    ])
                    ->maxSize($maxKb)
                    ->uploadingMessage('Uploading spreadsheet…')
                    ->validationMessages([
                        'max' => 'The spreadsheet may not be larger than '.number_format($maxKb / 1024).' MB.',
                    ])
                    ->required(),
            ])
            ->visible(fn (): bool => FilamentAccess::hasPermission('imports.upload'))
            ->action(function (array $data): void {
                $storedPath = Arr::first(Arr::wrap($data['file']));
                $originalName = Arr::first(Arr::wrap($data['original_filename'] ?? null)) ?: basename($storedPath);

                $import = app(ProductImportService::class)->startImport(
                    storedPath: $storedPath,
                    originalFilename: $originalName,
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
