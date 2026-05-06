<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use App\Models\Person;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = InvoiceStatus::Reviewed->value;

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data) {
            if (! empty($data['person_id'])) {
                $person = Person::find($data['person_id']);
                if ($person) {
                    $data['invoice_number'] = $person->allocateNextInvoiceNumber();
                }
            }

            return Invoice::create($data);
        });
    }

    protected function afterCreate(): void
    {
        $this->record->recalculateTotal();
    }
}
