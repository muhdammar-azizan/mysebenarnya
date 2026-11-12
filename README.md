<p align="center">
  <img src="public/images/logo.png" alt="MySebenarnya" width="120">
</p>

# MySebenarnya

A fake-news verification platform for **MCMC** (Malaysian Communications and Multimedia Commission). Members of the public submit inquiries about suspicious news, MCMC staff triage them, relevant government agencies investigate, and the verified outcome is published back to the public.

> Built as a university project (UMPSA) based on the "Appendix 6 – Project Requirements" brief.

## Features

The system has three roles and four modules.

| Role | What they do |
|---|---|
| **Public user** | Register, submit inquiries with supporting evidence, track progress, browse verified public inquiries |
| **MCMC staff** | Triage new inquiries, assign them to agencies, manage agencies and users, view reports |
| **Agency staff** | Review assigned inquiries, request clarification, give a verification result, manage their organisation |

**Modules**
1. **Manage User** – registration, email verification, staff login by username, forced password change on first login, profile photos, agency and staff management.
2. **Manage Inquiry Form Submission** – inquiry form with mandatory evidence (file or source link), categories, "My Inquiries" list and detail view.
3. **Inquiry Assignment** – MCMC triage, assignment to the right agency, reassignment, rejection or discard with reasons, MCMC-mediated clarification threads between agencies.
4. **Inquiry Progress Tracking** – status lifecycle (Submitted → Under Investigation → Verified True / Identified Fake / Rejected / Discarded), activity log, notifications, public results.

**Also included**
- Reports with date-range, category and agency filters, monthly/yearly views and trend charts, exportable to **PDF** and **Excel**.
- In-app and email notifications with per-user preferences.
- Responsive layout (phone, tablet, desktop) with a collapsible sidebar drawer.
- Automated test suite (267 feature tests).

## Tech stack

- **Backend:** Laravel 12, PHP 8.2+
- **Frontend:** Livewire 3 + Volt (single-file components), Blade, Alpine.js, Tailwind CSS 3, Vite
- **Database:** SQLite by default (any Laravel-supported database works)
- **Exports:** barryvdh/laravel-dompdf, maatwebsite/excel
- **Testing:** PHPUnit

## Getting started

Requirements: PHP 8.2+, Composer, Node.js 18+.

```bash
git clone https://github.com/muhdammar-azizan/mysebenarnya.git
cd mysebenarnya

composer install
npm install

cp .env.example .env
php artisan key:generate

# create the SQLite file, then migrate and seed demo data
touch database/database.sqlite        # Windows: type nul > database\database.sqlite
php artisan migrate --seed

php artisan storage:link

# run the app (two terminals)
php artisan serve
npm run dev
```

Open http://localhost:8000.

### Demo accounts

`php artisan migrate --seed` creates demo MCMC, agency and public accounts (see [database/seeders/UserSeeder.php](database/seeders/UserSeeder.php)). MCMC and agency staff sign in with a **username**; public users sign in with an **email**.

> These accounts are for local development only. **Do not run the seeder on a public deployment**, or change the seeded passwords first.

### Email

Set the `MAIL_*` values in `.env` to send real emails (credentials for new agencies and staff, password resets, notifications). For local testing, `MAIL_MAILER=log` writes emails to `storage/logs`.

### Running tests

```bash
php artisan test
```

### Production build

```bash
npm run build
php artisan config:cache route:cache view:cache
```

## Project structure

```
app/
  Enums/            Inquiry status, categories, user roles
  Models/           Inquiry, User, Agency, activity logs, clarifications
  Notifications/    Email and in-app notifications
  Livewire/         Form objects and shared components
resources/views/
  layouts/          Role-based layouts (public, MCMC, agency, auth)
  livewire/         Volt pages grouped by role: public/, mcmc/, agency/
database/           Migrations, factories, seeders
tests/Feature/      Feature tests per module
```

## Author

Muhammad Ammar – Universiti Malaysia Pahang Al-Sultan Abdullah (UMPSA)
