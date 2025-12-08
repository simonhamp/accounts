# Accounts

A Laravel application for managing invoices, tracking Stripe transactions, and generating PDF invoices in multiple languages.

## Features

- **Invoice Management**: Create, edit, and manage invoices with line items
- **Batch Import**: Upload multiple PDF invoices for automatic data extraction using AI
- **Dual Language PDFs**: Generate invoices in both Spanish and English
- **Stripe Integration**: Track Stripe accounts and transactions
- **Person Management**: Manage people/entities who issue invoices
- **User Management**: Admin-only user management (restricted to super admin)

## Requirements

- PHP 8.4+
- Composer
- Node.js & NPM
- SQLite (default) or MySQL/PostgreSQL
- OpenAI API key (for invoice data extraction)

## Installation

1. **Clone the repository**

   ```bash
   git clone <repository-url>
   cd accounts
   ```

2. **Install PHP dependencies**

   ```bash
   composer install
   ```

3. **Install Node dependencies**

   ```bash
   npm install
   ```

4. **Copy environment file**

   ```bash
   cp .env.example .env
   ```

5. **Generate application key**

   ```bash
   php artisan key:generate
   ```

6. **Configure environment variables**

   Edit `.env` and set the following:

   ```env
   APP_NAME=Accounts
   APP_URL=http://your-domain.test

   # OpenAI API key for invoice extraction
   OPENAI_API_KEY=sk-...
   ```

   > Note: `ADMIN_EMAIL` will be automatically set when you run the setup wizard.

7. **Run database migrations**

   ```bash
   php artisan migrate
   ```

8. **Build frontend assets**

   ```bash
   npm run build
   ```

9. **Run the setup wizard**

   ```bash
   php artisan app:setup
   ```

   This will prompt you for your name, email, and password, then create your admin user and automatically configure the `ADMIN_EMAIL` in your `.env` file.

   > Note: This command can only be run once. If `ADMIN_EMAIL` is already set, it will exit with a warning.

## Running the Application

### With Laravel Herd (recommended)

The application will be automatically available at `https://accounts.test` (or your configured domain).

### With Artisan

```bash
php artisan serve
```

### Queue Worker

For batch invoice imports to work, you need to run the queue worker:

```bash
php artisan queue:work
```

## Usage

### Accessing the Admin Panel

Navigate to `/admin` (or simply `/` which redirects to admin). Log in with your credentials.

### Importing Invoices

1. Go to "Batch Import Invoices" in the navigation
2. Upload one or more PDF invoices
3. The system will extract data using AI and create invoice records
4. Review extracted data in the Invoices section
5. Assign a person, verify details, and mark as reviewed
6. Finalize to generate the official PDF invoices

### Managing Users

Only the user whose email matches `ADMIN_EMAIL` can access the Users section under Settings. This user can create, edit, and delete other user accounts.

## Configuration

### Admin Email

Set `ADMIN_EMAIL` in your `.env` file to specify which user has super admin privileges (user management access).

### OpenAI

The application uses OpenAI's API for extracting invoice data from PDFs. Set your API key in `OPENAI_API_KEY`.

## Testing

```bash
php artisan test
```

## Code Style

This project uses Laravel Pint for code formatting:

```bash
vendor/bin/pint
```

## License

This project is proprietary software.
