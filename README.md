# Incline Fitness Enrollment

A Laravel and Filament application for gym membership enrollment and administration.

## Features

- PIN-protected enrollment form at `/enrol`
- Automatic membership end-date calculation
- Member and gym confirmation emails through Gmail SMTP
- Queued email delivery
- Enrollment confirmation PDF downloads
- CSV enrollment exports
- Membership, renewal, and overdue-balance dashboard
- Admin and staff roles
- Staff access to overdue members, mobile numbers, payment values, and balance settlement
- Restricted staff access to other personal and administrative data

## Requirements

- PHP 8.3 or later
- Composer
- Node.js 20 or later
- SQLite, MySQL, or PostgreSQL

## Local setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build
php artisan serve
```

Set these values in `.env` before you seed the first admin account:

```dotenv
TABLET_PIN=your-secure-pin
ADMIN_NAME="Incline Fitness Admin"
ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=use-a-strong-password
```

Then create the admin account:

```bash
php artisan db:seed
```

## Gmail SMTP

Enable two-step verification for the Gmail account. Create a Gmail App Password. Add these values to `.env`:

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=yourgym@gmail.com
MAIL_PASSWORD=your-gmail-app-password
MAIL_FROM_ADDRESS="${MAIL_USERNAME}"
MAIL_FROM_NAME="${APP_NAME}"
GYM_EMAIL="${MAIL_USERNAME}"
QUEUE_CONNECTION=database
```

Run the queue worker:

```bash
php artisan queue:work database --queue=default --tries=3 --timeout=60
```

Use a process manager such as Supervisor or a hosting platform worker service in production.

## Import existing enrollments

Import a Google Forms CSV or ZIP file:

```bash
php artisan enrollments:import /path/to/export.zip --replace
```

The `--replace` option deletes current enrollment rows before import.

## Tests

```bash
php artisan test
```
