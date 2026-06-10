<?php

namespace App\Filament\Resources\Roles\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class RoleForm
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

                Select::make('permissions')
                    ->relationship(
                        'permissions',
                        'name',
                        fn (Builder $query): Builder => $query->where('guard_name', self::GUARD),
                    )
                    ->multiple()
                    ->preload()
                    ->searchable(),
            ]);
    }
}
