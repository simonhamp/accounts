<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

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
                            ->label('Email address')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        DateTimePicker::make('email_verified_at')
                            ->label('Email Verified At')
                            ->native(false),
                    ])
                    ->columns(2),

                Section::make('Password')
                    ->components([
                        TextInput::make('password')
                            ->password()
                            ->dehydrateStateUsing(fn (string $state): string => Hash::make($state))
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->confirmed()
                            ->maxLength(255),

                        TextInput::make('password_confirmation')
                            ->password()
                            ->requiredWith('password')
                            ->maxLength(255),
                    ])
                    ->columns(2),
            ]);
    }
}
