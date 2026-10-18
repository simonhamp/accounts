<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Mail\Mailer;
use Illuminate\Support\Facades\Mail;

/**
 * A business that trades under the issuing Person, e.g. Laradevs, Estilistas
 * or Clickback. It decides who invoice emails come from and which mail
 * server sends them.
 */
class Brand extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email_invoices',
        'invoice_sender_name',
        'invoice_from_address',
        'invoice_reply_to',
        'invoice_bcc',
        'invoice_language',
        'smtp_host',
        'smtp_port',
        'smtp_scheme',
        'smtp_username',
        'smtp_password',
    ];

    /**
     * The SMTP password never leaves the model when it is serialized, so it
     * can't reach a form, a log line or a JSON response.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'smtp_password',
    ];

    protected function casts(): array
    {
        return [
            'email_invoices' => 'boolean',
            'smtp_port' => 'integer',
            'smtp_password' => 'encrypted',
        ];
    }

    public function stripeAccounts(): HasMany
    {
        return $this->hasMany(StripeAccount::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Everything needed to send an invoice email: who it is from and how to
     * reach the mail server.
     */
    public function hasCompleteMailSettings(): bool
    {
        return filled($this->invoice_sender_name)
            && filled($this->invoice_from_address)
            && filled($this->smtp_host)
            && filled($this->smtp_port)
            && filled($this->smtp_username)
            && filled($this->smtp_password);
    }

    /**
     * 'en' or 'es': the language of the invoice email and its PDF.
     */
    public function invoiceLanguage(): string
    {
        return $this->invoice_language === 'es' ? 'es' : 'en';
    }

    /**
     * The on-demand mailer config for this brand's SMTP server. An empty
     * scheme is left to Laravel, which uses smtps on port 465 and smtp with
     * STARTTLS otherwise.
     *
     * @return array{name: string, transport: string, scheme: ?string, host: ?string, port: ?int, username: ?string, password: ?string, timeout: int, local_domain: ?string}
     */
    public function mailerConfig(): array
    {
        return [
            'name' => "brand-{$this->id}",
            'transport' => 'smtp',
            'scheme' => $this->smtp_scheme ?: null,
            'host' => $this->smtp_host,
            'port' => $this->smtp_port,
            'username' => $this->smtp_username,
            'password' => $this->smtp_password,
            'timeout' => 30,
            'local_domain' => config('mail.mailers.smtp.local_domain'),
        ];
    }

    /**
     * A mailer for this brand's own SMTP server, built from the saved
     * settings each time it is asked for.
     */
    public function mailer(): Mailer
    {
        return Mail::build($this->mailerConfig());
    }

    /**
     * Take the SMTP password, and its base64 form as it travels in an AUTH
     * command, out of a message from the mail server or transport.
     */
    public function redactSecrets(string $message): string
    {
        $password = $this->smtp_password;

        if (blank($password)) {
            return $message;
        }

        return str_replace([$password, base64_encode($password)], '********', $message);
    }
}
