<?php

namespace Database\Factories;

use App\Models\Brand;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Brand>
 */
class BrandFactory extends Factory
{
    protected $model = Brand::class;

    /**
     * A brand with invoice emails off and no mail server.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'email_invoices' => false,
            'invoice_language' => 'en',
        ];
    }

    /**
     * Invoice emails on, with a complete sender and SMTP setup.
     */
    public function emailingInvoices(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_invoices' => true,
            'invoice_sender_name' => $attributes['name'],
            'invoice_from_address' => 'invoices@'.fake()->unique()->domainName(),
            'smtp_host' => 'smtp.'.fake()->domainName(),
            'smtp_port' => 587,
            'smtp_username' => fake()->userName(),
            'smtp_password' => fake()->password(16, 24),
        ]);
    }
}
