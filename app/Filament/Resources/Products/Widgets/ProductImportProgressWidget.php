<?php

namespace App\Filament\Resources\Products\Widgets;

use App\Filament\Resources\ProductImports\Tables\ProductImportsTable;
use App\Models\ProductImport;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class ProductImportProgressWidget extends TableWidget
{
    public function table(Table $table): Table
    {
        return ProductImportsTable::configure($table)
            ->heading('Import progress')
            ->query(fn (): Builder => ProductImport::query()->latest('id'))
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5);
    }
}
