<?php

namespace App\Filament\Pages;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Person;
use App\Services\InvoiceExtractionService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;

class ImportInvoice extends Page implements HasForms
{
    use InteractsWithForms;

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.import-invoice';

    public ?array $data = [];

    public bool $showExtractedData = false;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Upload Invoice PDF')
                    ->components([
                        FileUpload::make('pdf')
                            ->label('Invoice PDF')
                            ->acceptedFileTypes(['application/pdf'])
                            ->required()
                            ->maxSize(10240)
                            ->visibility($this->showExtractedData ? 'hidden' : 'visible'),
                    ])
                    ->visible(! $this->showExtractedData),

                Section::make('Extracted Invoice Data')
                    ->components([
                        Select::make('person_id')
                            ->label('Person')
                            ->options(Person::all()->pluck('name', 'id'))
                            ->required()
                            ->helperText('Select which person this invoice belongs to'),

                        TextInput::make('invoice_number')
                            ->required(),

                        DatePicker::make('invoice_date')
                            ->required()
                            ->native(false),

                        TextInput::make('customer_name')
                            ->required(),

                        TextInput::make('customer_email')
                            ->email(),

                        Select::make('selected_address')
                            ->label('Select Customer Address')
                            ->options(fn () => $this->data['all_addresses'] ?? [])
                            ->required()
                            ->helperText('Choose which address belongs to the customer')
                            ->reactive()
                            ->afterStateUpdated(function ($state, $set) {
                                $set('customer_address', $state);
                            }),

                        Textarea::make('customer_address')
                            ->label('Customer Address (Selected)')
                            ->rows(3)
                            ->disabled()
                            ->dehydrated(),

                        TextInput::make('customer_tax_id'),

                        Hidden::make('all_addresses'),

                        TextInput::make('currency')
                            ->required()
                            ->default('EUR'),

                        Repeater::make('items')
                            ->components([
                                TextInput::make('description')
                                    ->required(),
                                TextInput::make('quantity')
                                    ->numeric()
                                    ->required()
                                    ->default(1),
                                TextInput::make('unit_price')
                                    ->numeric()
                                    ->required()
                                    ->helperText('Amount in cents'),
                                TextInput::make('total')
                                    ->numeric()
                                    ->required()
                                    ->helperText('Amount in cents'),
                            ])
                            ->columns(4)
                            ->required()
                            ->minItems(1),

                        TextInput::make('total_amount')
                            ->numeric()
                            ->required()
                            ->helperText('Total amount in cents'),

                        Textarea::make('notes')
                            ->rows(3),

                        Hidden::make('original_pdf_path'),
                    ])
                    ->visible($this->showExtractedData),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('extract')
                ->label('Extract Data from PDF')
                ->action('extractData')
                ->visible(! $this->showExtractedData),

            Action::make('create')
                ->label('Create Invoice')
                ->action('createInvoice')
                ->color('success')
                ->visible($this->showExtractedData),

            Action::make('cancel')
                ->label('Start Over')
                ->action('startOver')
                ->color('gray')
                ->visible($this->showExtractedData),
        ];
    }

    public function extractData(): void
    {
        $data = $this->form->getState();

        if (empty($data['pdf'])) {
            Notification::make()
                ->danger()
                ->title('Please upload a PDF file')
                ->send();

            return;
        }

        try {
            $pdfPath = Storage::disk('local')->path($data['pdf']);

            $extractionService = app(InvoiceExtractionService::class);
            $extracted = $extractionService->extractFromPdf($pdfPath);

            // Prepare addresses for dropdown (use address as both key and value)
            $addresses = $extracted['all_addresses'] ?? [];
            $addressOptions = array_combine($addresses, $addresses);

            // Fill form with extracted data
            $this->form->fill([
                'invoice_number' => $extracted['invoice_number'] ?? null,
                'invoice_date' => $extracted['invoice_date'] ?? now()->format('Y-m-d'),
                'customer_name' => $extracted['customer_name'] ?? null,
                'customer_email' => $extracted['customer_email'] ?? null,
                'all_addresses' => $addressOptions,
                'selected_address' => null,
                'customer_address' => null,
                'customer_tax_id' => $extracted['customer_tax_id'] ?? null,
                'currency' => $extracted['currency'] ?? 'EUR',
                'items' => $extracted['items'] ?? [],
                'total_amount' => $extracted['total_amount'] ?? 0,
                'notes' => $extracted['notes'] ?? null,
                'original_pdf_path' => $data['pdf'],
            ]);

            $this->showExtractedData = true;

            Notification::make()
                ->success()
                ->title('Data extracted successfully')
                ->body('Please review and edit the extracted data before creating the invoice.')
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Failed to extract data')
                ->body($e->getMessage())
                ->send();
        }
    }

    public function createInvoice(): void
    {
        $data = $this->form->getState();

        try {
            $invoiceDate = \Carbon\Carbon::parse($data['invoice_date']);

            $invoice = Invoice::create([
                'person_id' => $data['person_id'],
                'invoice_number' => $data['invoice_number'],
                'invoice_date' => $data['invoice_date'],
                'period_month' => $invoiceDate->month,
                'period_year' => $invoiceDate->year,
                'customer_name' => $data['customer_name'],
                'customer_address' => $data['customer_address'],
                'customer_tax_id' => $data['customer_tax_id'],
                'currency' => $data['currency'],
                'total_amount' => $data['total_amount'],
                'status' => InvoiceStatus::Finalized,
            ]);

            foreach ($data['items'] as $item) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'stripe_transaction_id' => null,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total' => $item['total'],
                ]);
            }

            Notification::make()
                ->success()
                ->title('Invoice created successfully')
                ->body("Invoice {$invoice->invoice_number} has been imported.")
                ->send();

            $this->redirect(route('filament.admin.resources.invoices.index'));
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Failed to create invoice')
                ->body($e->getMessage())
                ->send();
        }
    }

    public function startOver(): void
    {
        $this->showExtractedData = false;
        $this->form->fill();
    }
}
