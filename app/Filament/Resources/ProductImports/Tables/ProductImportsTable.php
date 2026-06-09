<?php

namespace App\Filament\Resources\ProductImports\Tables;

use App\Enums\ProductImportStatus;
use App\Models\ProductImport;
use App\Services\ProductImportService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class ProductImportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Live progress: the table refreshes every 3s while imports run.
            ->poll('3s')
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('filename')
                    ->searchable()
                    ->limit(40),
                TextColumn::make('user.name')
                    ->label('By')
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (ProductImportStatus $state): string => $state->label())
                    ->color(fn (ProductImportStatus $state): string => $state->color()),
                TextColumn::make('progress')
                    ->label('Progress')
                    ->badge()
                    ->state(fn (ProductImport $record): string => $record->progress().'%'),
                TextColumn::make('rows')
                    ->label('Processed / Total')
                    ->state(fn (ProductImport $record): string => number_format($record->processed_rows).' / '.number_format($record->total_rows)),
                TextColumn::make('failed_rows')
                    ->label('Failed')
                    ->numeric()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray'),
                TextColumn::make('started_at')
                    ->dateTime()
                    ->since()
                    ->toggleable(),
                TextColumn::make('completed_at')
                    ->dateTime()
                    ->since()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                Action::make('downloadSample')
                    ->label('Download Sample CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function () {
                        $csvContent = "name,sku,quantity,price,description,status\nSample Product,SKU-12345,100,19.99,A sample product description,active";

                        return response()->streamDownload(function () use ($csvContent) {
                            echo $csvContent;
                        }, 'sample_product_import.csv');
                    }),
            ])
            ->recordActions([
                Action::make('start')
                    ->label('Start')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->visible(fn (ProductImport $record): bool => $record->canStart())
                    ->requiresConfirmation()
                    ->modalHeading('Start import')
                    ->modalDescription('This will queue the import to run again from the beginning.')
                    ->action(function (ProductImport $record): void {
                        app(ProductImportService::class)->startExisting($record);

                        Notification::make()
                            ->title('Import queued')
                            ->body("\"{$record->filename}\" will start shortly.")
                            ->success()
                            ->send();
                    }),
                Action::make('stop')
                    ->label('Stop')
                    ->icon('heroicon-o-stop')
                    ->color('danger')
                    ->visible(fn (ProductImport $record): bool => $record->canStop())
                    ->requiresConfirmation()
                    ->modalHeading('Stop import')
                    ->modalDescription('A running import will stop after the current chunk finishes. Pending imports will be stopped before they start.')
                    ->action(function (ProductImport $record): void {
                        $import = app(ProductImportService::class)->requestStop($record);

                        Notification::make()
                            ->title($import->status === ProductImportStatus::Stopped ? 'Import stopped' : 'Stop requested')
                            ->body("\"{$record->filename}\" ".($import->status === ProductImportStatus::Stopped ? 'has been stopped.' : 'will stop after the current chunk finishes.'))
                            ->success()
                            ->send();
                    }),
                Action::make('downloadFailures')
                    ->label('Failures')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('danger')
                    ->visible(fn (ProductImport $record): bool => filled($record->error_log_path)
                        && Storage::disk('local')->exists($record->error_log_path))
                    ->action(fn (ProductImport $record) => Storage::disk('local')
                        ->download($record->error_log_path, "failed_rows_import_{$record->id}.csv")),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
