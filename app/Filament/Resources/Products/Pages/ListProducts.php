<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Actions\ImportProductsAction;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Products\Widgets\ProductImportProgressWidget;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportProductsAction::make(),
            CreateAction::make(),
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            ProductImportProgressWidget::class,
        ];
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return 1;
    }
}
