<?php

namespace App\Filament\Pages;

use App\Enums\InvoiceStatus;
use App\Jobs\ProcessInvoiceImport;
use App\Models\Invoice;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class BatchImportInvoices extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): ?string
    {
        return 'Invoices';
    }

    public static function getNavigationLabel(): string
    {
        return 'Batch Import';
    }

    protected string $view = 'filament.pages.batch-import-invoices';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Upload Invoice PDFs')
                    ->description('Upload one or more PDF invoices. They will be queued for extraction and can be reviewed afterward.')
                    ->components([
                        FileUpload::make('pdfs')
                            ->label('Invoice PDFs')
                            ->acceptedFileTypes(['application/pdf'])
                            ->multiple()
                            ->maxFiles(50)
                            ->maxSize(10240)
                            ->required()
                            ->helperText('You can upload up to 50 PDFs at once (max 10MB each)'),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('queue')
                ->label('Queue for Processing')
                ->action('queueInvoices')
                ->color('primary')
                ->icon('heroicon-o-queue-list'),
        ];
    }

    public function queueInvoices(): void
    {
        $data = $this->form->getState();

        if (empty($data['pdfs'])) {
            Notification::make()
                ->danger()
                ->title('Please upload at least one PDF file')
                ->send();

            return;
        }

        $queued = 0;

        foreach ($data['pdfs'] as $pdfPath) {
            $invoice = Invoice::create([
                'status' => InvoiceStatus::Pending,
                'original_file_path' => $pdfPath,
                'currency' => 'EUR',
                'total_amount' => 0,
            ]);

            ProcessInvoiceImport::dispatch($invoice);
            $queued++;
        }

        $this->form->fill();

        Notification::make()
            ->success()
            ->title("{$queued} invoice(s) queued for processing")
            ->body('You can review them once extraction is complete.')
            ->send();
    }
}
