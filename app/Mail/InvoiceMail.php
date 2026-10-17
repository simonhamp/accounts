<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Models\StripeAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * An invoice or credit note emailed to the customer, with its PDF attached.
 * Who it comes from, the reply-to, the BCC and the language all come from
 * the Stripe account the invoice was made from. An invoice without one goes
 * out from the default mail address in English, under the issuer's name.
 */
class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Invoice $invoice)
    {
        $this->locale($this->language());
    }

    /**
     * 'en' or 'es': the language of the attached PDF and of the email itself.
     */
    public function language(): string
    {
        return $this->account()?->invoice_language === 'es' ? 'es' : 'en';
    }

    public function senderName(): string
    {
        return $this->account()?->invoice_sender_name ?: $this->invoice->person->legalName();
    }

    public function envelope(): Envelope
    {
        $account = $this->account();

        return new Envelope(
            from: new Address($account?->invoice_from_address ?: config('mail.from.address'), $this->senderName()),
            replyTo: $account?->invoice_reply_to ? [new Address($account->invoice_reply_to)] : [],
            bcc: $account?->invoice_bcc ? [new Address($account->invoice_bcc)] : [],
            subject: __("invoice_mail.subject.{$this->documentType()}", [
                'number' => $this->invoice->invoice_number,
                'sender' => $this->senderName(),
            ], $this->language()),
        );
    }

    public function content(): Content
    {
        $language = $this->language();
        $document = $this->documentType();
        $person = $this->invoice->person;

        $issuer = $person->legalName();
        if (filled($person->taxIdentifier())) {
            $issuer .= " ({$person->taxIdentifierLabel()} {$person->taxIdentifier()})";
        }

        $intro = __("invoice_mail.intro.{$document}", ['sender' => $this->senderName()], $language);
        if ($parentNumber = $this->invoice->parentInvoice?->invoice_number) {
            $intro .= ' '.__('invoice_mail.corrects', ['number' => $parentNumber], $language);
        }

        return new Content(
            text: 'mail.invoice',
            with: [
                'greeting' => __('invoice_mail.greeting', [], $language),
                'intro' => $intro,
                'details' => [
                    __("invoice_mail.number.{$document}", [], $language) => $this->invoice->invoice_number,
                    __('invoice_mail.date', [], $language) => $this->formattedDate(),
                    __('invoice_mail.total', [], $language) => $this->formattedTotal(),
                ],
                'issuedBy' => __("invoice_mail.issued_by.{$document}", ['issuer' => $issuer], $language),
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $path = $this->language() === 'es' ? $this->invoice->pdf_path : $this->invoice->pdf_path_en;

        return [
            Attachment::fromStorage($path)
                ->as("{$this->invoice->invoice_number}.pdf")
                ->withMime('application/pdf'),
        ];
    }

    protected function account(): ?StripeAccount
    {
        return $this->invoice->sourceStripeAccount();
    }

    protected function documentType(): string
    {
        return $this->invoice->isCreditNote() ? 'credit_note' : 'invoice';
    }

    protected function formattedDate(): string
    {
        return $this->language() === 'es'
            ? $this->invoice->invoice_date->locale('es')->translatedFormat('j \d\e F \d\e Y')
            : $this->invoice->invoice_date->locale('en')->translatedFormat('j F Y');
    }

    protected function formattedTotal(): string
    {
        $amount = $this->invoice->total_amount / 100;

        $formatted = $this->language() === 'es'
            ? number_format($amount, 2, ',', '.')
            : number_format($amount, 2, '.', ',');

        return "{$formatted} {$this->invoice->currency}";
    }
}
