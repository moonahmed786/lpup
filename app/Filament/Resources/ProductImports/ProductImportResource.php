<?php

namespace App\Filament\Resources\ProductImports;

use App\Filament\Resources\ProductImports\Pages\ListProductImports;
use App\Filament\Resources\ProductImports\Tables\ProductImportsTable;
use App\Models\ProductImport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProductImportResource extends Resource
{
    protected static ?string $model = ProductImport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static ?string $navigationLabel = 'Product Imports';

    protected static ?int $navigationSort = 3;

    public static function table(Table $table): Table
    {
        return ProductImportsTable::configure($table);
    }

    /**
     * Imports are created via the "Import products" action, not a form.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductImports::route('/'),
        ];
    }
}
