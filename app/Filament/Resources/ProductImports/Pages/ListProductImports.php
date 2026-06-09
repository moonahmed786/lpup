<?php

namespace App\Filament\Resources\ProductImports\Pages;

use App\Filament\Resources\ProductImports\ProductImportResource;
use App\Services\ProductImportService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListProductImports extends ListRecords
{
    protected static string $resource = ProductImportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')
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
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                            'text/csv',
                            'text/plain',
                        ])
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $path = $data['file'];

                    $import = app(ProductImportService::class)->startImport(
                        storedPath: $path,
                        originalFilename: basename($path),
                        userId: auth()->id(),
                    );

                    Notification::make()
                        ->title('Import queued')
                        ->body("\"{$import->filename}\" is being processed — progress updates below.")
                        ->success()
                        ->send();
                }),
        ];
    }
}
