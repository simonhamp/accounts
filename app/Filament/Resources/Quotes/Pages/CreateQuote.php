<?php

namespace App\Filament\Resources\Quotes\Pages;

use App\Enums\QuoteStatus;
use App\Filament\Resources\Quotes\QuoteResource;
use App\Models\Person;
use App\Models\Quote;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateQuote extends CreateRecord
{
    protected static string $resource = QuoteResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = QuoteStatus::Draft->value;

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data) {
            if (! empty($data['person_id'])) {
                $person = Person::find($data['person_id']);
                if ($person) {
                    $data['quote_number'] = $person->allocateNextQuoteNumber();
                }
            }

            return Quote::create($data);
        });
    }

    protected function afterCreate(): void
    {
        $this->record->recalculateTotal();
    }
}
