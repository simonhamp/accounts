<?php

namespace App\Filament\Resources\Brands\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BrandsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('email_invoices')
                    ->label('Emails invoices')
                    ->boolean(),
                TextColumn::make('invoice_from_address')
                    ->label('From')
                    ->placeholder('Not set'),
                TextColumn::make('smtp_host')
                    ->label('Mail server')
                    ->placeholder('Not set'),
                TextColumn::make('stripe_accounts_count')
                    ->label('Stripe accounts')
                    ->counts('stripeAccounts'),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
