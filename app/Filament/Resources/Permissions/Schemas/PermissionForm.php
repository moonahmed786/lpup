<?php

namespace App\Filament\Resources\Permissions\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PermissionForm
{
    private const GUARD = 'api';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),

                TextInput::make('guard_name')
                    ->default(self::GUARD)
                    ->disabled()
                    ->dehydrated()
                    ->required()
                    ->maxLength(255),
            ]);
    }
}
