<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),

                TextInput::make('password')
                    ->password()
                    ->revealable()
                    // Required when creating; optional (leave blank to keep) when editing.
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->dehydrateStateUsing(fn (string $state): string => Hash::make($state))
                    ->maxLength(255),

                Select::make('roles')
                    ->relationship('roles', 'name')
                    ->modifyQueryUsing(fn (Builder $query): Builder => auth()->user()?->hasRole('SuperAdmin')
                        ? $query
                        : $query->where('name', '!=', 'SuperAdmin'))
                    ->multiple()
                    ->preload()
                    ->helperText('Roles resolve on the api guard (see RolePermissionSeeder).'),
            ]);
    }
}
