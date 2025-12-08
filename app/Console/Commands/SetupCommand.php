<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

class SetupCommand extends Command
{
    protected $signature = 'app:setup';

    protected $description = 'Set up the application with an admin user';

    public function handle(): int
    {
        if ($this->adminEmailExists()) {
            warning('Setup has already been completed. ADMIN_EMAIL is already configured in your .env file.');

            return Command::FAILURE;
        }

        $this->components->info('Welcome to the application setup wizard.');
        $this->newLine();

        $name = text(
            label: 'What is your name?',
            required: true,
        );

        $email = text(
            label: 'What is your email address?',
            required: true,
            validate: fn (string $value) => match (true) {
                ! filter_var($value, FILTER_VALIDATE_EMAIL) => 'Please enter a valid email address.',
                User::where('email', $value)->exists() => 'A user with this email already exists.',
                default => null,
            },
        );

        $password = password(
            label: 'Choose a password',
            required: true,
            validate: fn (string $value) => match (true) {
                strlen($value) < 8 => 'Password must be at least 8 characters.',
                default => null,
            },
        );

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
        ]);

        $this->addAdminEmailToEnv($email);

        $this->newLine();
        $this->components->success("Admin user '{$user->name}' created successfully!");
        $this->components->info("You can now log in at /admin with your email: {$email}");

        return Command::SUCCESS;
    }

    protected function adminEmailExists(): bool
    {
        $envPath = base_path('.env');

        if (! file_exists($envPath)) {
            return false;
        }

        $envContent = file_get_contents($envPath);

        if (preg_match('/^ADMIN_EMAIL=(.+)$/m', $envContent, $matches)) {
            return ! empty(trim($matches[1]));
        }

        return false;
    }

    protected function addAdminEmailToEnv(string $email): void
    {
        $envPath = base_path('.env');
        $envContent = file_get_contents($envPath);

        if (preg_match('/^ADMIN_EMAIL=.*$/m', $envContent)) {
            $envContent = preg_replace(
                '/^ADMIN_EMAIL=.*$/m',
                "ADMIN_EMAIL={$email}",
                $envContent
            );
        } else {
            $envContent .= "\nADMIN_EMAIL={$email}\n";
        }

        file_put_contents($envPath, $envContent);
    }
}
