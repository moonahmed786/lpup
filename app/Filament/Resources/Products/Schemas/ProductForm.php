<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Enums\ProductStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('sku')
                    ->label('SKU')
                    ->required(),
                TextInput::make('quantity')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('price')
                    ->numeric()
                    ->prefix('$')
                    ->nullable(),
                \Filament\Forms\Components\Textarea::make('description')
                    ->columnSpanFull()
                    ->nullable(),
                Select::make('status')
                    ->options(ProductStatus::class)
                    ->default('draft')
                    ->required(),
            ]);
    }
}
