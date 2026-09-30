# Warung Baleganjur

**A restaurant point-of-sale and QR-based ordering system built for Warung Baleganjur.**

Warung Baleganjur helps restaurant staff manage tables, orders, kitchen progress, payments, and daily sales. Guests can scan a table QR code to browse the menu and place or follow an order from their own device.

## Features

- **QR table ordering** — guests can view menu categories, choose menu items and add-ons, and submit an order without creating an account.
- **Order tracking** — guests can check the status of an order and, while it is still eligible, edit or cancel it.
- **Table and waiting-list management** — manage table QR codes, seating capacity, occupied seats, and waiting-list bookings.
- **Kitchen workflow** — staff can move orders through the preparation workflow and mark them ready for payment.
- **Point of sale and payments** — review orders, accept cash, bank transfer, or QRIS, record payment references, and issue receipts.
- **Sales dashboard and PDF reports** — review sales totals and payment summaries for a selected date range, with popular menu items and add-ons.
- **Menu administration** — manage categories, menu items, bilingual names and descriptions, availability, images, and add-ons.
- **Restaurant settings** — configure discounts and taxes, and manage users, roles, and permissions.
- **Authentication and account security** — includes email verification, password reset, login throttling, and optional two-factor authentication.
- **Indonesian and English interface** — menu content supports Indonesian and English, with a language selector in the application.

## Built with

- PHP 8.2+
- Laravel 12
- Livewire 3, Volt, and Flux
- Laravel Fortify for authentication
- Spatie Laravel Permission for roles and permissions
- Tailwind CSS 4 and Vite
- Dompdf for PDF output
- BaconQrCode for QR code generation

## Requirements

- PHP 8.2 or later with Composer
- Node.js and npm
- SQLite for the default local setup, or another database supported by Laravel
- PHP GD extension if you want to generate WebP thumbnails for uploaded menu images

## Run locally

1. Clone the repository and enter the project directory:

   ```bash
   git clone <repository-url>
   cd warung_baleganjur
   ```

2. Create the SQLite database file used by the example configuration:

   ```bash
   php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
   ```

   If you use MySQL or another database instead, configure its connection in `.env` and skip this step.

3. Install dependencies and prepare the application:

   ```bash
   composer setup
   ```

   The setup script installs PHP and JavaScript dependencies, creates `.env` if needed, generates an application key, runs migrations, and builds the frontend assets.

4. Set the administrator account values in `.env` before seeding:

   ```dotenv
   SUPER_ADMIN_NAME="Restaurant Admin"
   SUPER_ADMIN_EMAIL=admin@example.com
   SUPER_ADMIN_PASSWORD=choose-a-strong-local-password
   ```

5. Seed the initial administrator, tables, and sample menu data:

   ```bash
   php artisan db:seed
   ```

6. Start the local development services:

   ```bash
   composer run dev
   ```

   Open the URL printed by Laravel, usually `http://127.0.0.1:8000`.

Uploaded menu images use Laravel's `public` storage disk. For local image access, create the storage symlink once:

```bash
php artisan storage:link
```

## Useful commands

```bash
php artisan migrate          # Apply database migrations
php artisan db:seed          # Seed the admin account, tables, and sample menus
npm run dev                  # Run the Vite development server
npm run build                # Build frontend assets for deployment
php artisan menus:thumbnails # Generate WebP menu thumbnails
```

The thumbnail command can be run with `--force` to regenerate existing thumbnails. The `menus:cleanup-images` command reports unreferenced menu images by default; pass `--execute` only when you intend to delete those files.

## Notes

- The sample menu is loaded from `database/seeders/data/seedmenu.csv`.
- The default seeder creates six tables (`A1`–`A6`) with a capacity of four seats each. You can change them in the table management screen after signing in.
- Set `APP_URL` to the address guests can reach before printing or distributing table QR codes.
- Set `APP_LOCALE=id` to use Indonesian as the default interface language. The application timezone defaults to `Asia/Makassar` in `.env.example`.
- Set `SUPER_ADMIN_PASSWORD` before running the seeder in production. Production seeding fails if this value is missing.
- The admin account must verify its email before accessing the dashboard. Use an email address you can access and configure a real mail transport; with the local `log` mailer, check `storage/logs/laravel.log` for the verification message.
- Cash, transfer, and QRIS are recorded as payment methods in the cashier workflow. This repository does not include an online payment gateway integration.

## Project status

This repository contains the application source code. Production deployment settings and infrastructure should be configured for the environment where it will run.
