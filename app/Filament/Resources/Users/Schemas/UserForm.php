<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('User Details')
                    ->components([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        TextInput::make('password')
                            ->password()
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn ($record) => $record === null)
                            ->rule(Password::default())
                            ->helperText(fn ($record) => $record ? 'Leave blank to keep current password' : null),
                    ])
                    ->columns(2),

                Section::make('Permissions')
                    ->components([
                        Toggle::make('is_admin')
                            ->label('Administrator')
                            ->helperText('Administrators can access the admin panel and manage all data. Non-admins can only view the records area.')
                            ->default(false),
                    ]),
            ]);
    }
}
