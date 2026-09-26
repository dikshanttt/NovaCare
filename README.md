# NovaCare

NovaCare is a role-based healthcare management web application that connects patients, doctors, hospitals, and administrators in a single system. The platform allows patients to find verified doctors, view hospital information, and book appointments; doctors to manage availability and patient visits; and admins to verify providers, approve schedules, and oversee hospital operations.

## Project Overview

NovaCare is designed as a healthcare coordination platform with secure access control and PostgreSQL-backed data management. It includes:

- Patient registration and login
- Doctor registration with verification workflow
- Admin approval of doctor applications and schedules
- Hospital management and doctor-hospital affiliations
- Appointment booking and status tracking
- Role-aware dashboards for patient, doctor, and admin users
- CSRF protection, session security, and database constraints for system integrity

## Tech Stack

- PHP 8+
- PostgreSQL
- Composer
- PHPMailer
- Dotenv
- HTML, CSS, and vanilla JavaScript

## Core Structure

- `index.php` – public landing page with featured doctors and hospital statistics
- `login.php` – unified login portal for all user roles
- `registration/` – patient and doctor sign-up flows
- `patient/` – patient dashboard and appointment booking features
- `doctor/` – doctor dashboard and schedule management
- `admin/` – admin verification, schedule approval, and hospital management
- `auth/auth.php` – session and role-based access control
- `database/db.sql` – database schema, views, indexes, and seed data
- `database/db.php` – PDO database connection setup
- `config/config.php` – environment variable loader
- `include/function.php` – helper utilities, sanitization, token generation, and uploads
- `assets/` – stylesheet and frontend assets

## Requirements

Before running the project, make sure you have:

- PHP 8 or newer
- Composer
- PostgreSQL server
- Local PHP server or Apache/Nginx setup

## Setup

1. Install dependencies:

   ```bash
   composer install
   ```

2. Create a `.env` file in the project root with your PostgreSQL credentials:

   ```env
   DB_HOST=localhost
   DB_PORT=5432
   DB_NAME=novacare
   DB_USER=postgres
   DB_PASSWORD=your_password
   ```

3. Create the database and import the schema:

   ```bash
   psql -U postgres -d novacare -f database/db.sql
   ```

4. Start the PHP development server:

   ```bash
   php -S localhost:8000
   ```

5. Open the app in your browser at:

   ```text
   http://localhost:8000
   ```

## Default Admin Account

The seeded admin account in the database is:

- Email: `admin@novacre.com`
- Password: `novacare_admin`

## Notes

- The app expects PostgreSQL and reads database settings from `.env`.
- Role validation is enforced on the server side before accessing protected pages.
- Doctor accounts remain pending until an admin verifies them and approves their schedules.
- Appointment tokens and generated IDs are created automatically for tracking and reporting.

## License

This project is intended for academic, educational, and internal project use unless stated otherwise by the repository owner.
