<?php

namespace App\Filament\Pages;

use App\Enums\BillStatus;
use App\Jobs\ProcessBillImport;
use App\Models\Bill;
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

class BatchImportBills extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): ?string
    {
        return 'Bills';
    }

    public static function getNavigationLabel(): string
    {
        return 'Import Bills';
    }

    protected string $view = 'filament.pages.batch-import-bills';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Upload Supplier Bills')
                    ->description('Upload one or more PDF bills from suppliers. They will be queued for extraction and can be reviewed afterward.')
                    ->components([
                        FileUpload::make('pdfs')
                            ->label('Bill PDFs')
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
                ->action('queueBills')
                ->color('primary')
                ->icon('heroicon-o-queue-list'),
        ];
    }

    public function queueBills(): void
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
            $bill = Bill::create([
                'status' => BillStatus::Pending,
                'original_file_path' => $pdfPath,
                'currency' => 'EUR',
                'total_amount' => 0,
            ]);

            ProcessBillImport::dispatch($bill);
            $queued++;
        }

        $this->form->fill();

        Notification::make()
            ->success()
            ->title("{$queued} bill(s) queued for processing")
            ->body('You can review them once extraction is complete.')
            ->send();
    }
}
