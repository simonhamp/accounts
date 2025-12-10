<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\Person;
use Filament\Resources\Pages\CreateRecord;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set status to Reviewed for manual invoices (ready to be finalized)
        $data['status'] = InvoiceStatus::Reviewed->value;

        // Generate invoice number from the selected person
        if (! empty($data['person_id'])) {
            $person = Person::find($data['person_id']);
            if ($person) {
                $data['invoice_number'] = $person->getNextInvoiceNumber();
            }
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        // Increment the person's invoice number counter after successful creation
        if ($this->record->person_id) {
            $this->record->person->incrementInvoiceNumber();
        }

        // Recalculate total from line items
        $this->record->recalculateTotal();
    }
}
