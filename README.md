# NovaCare

NovaCare is a PHP healthcare appointment management system built for patients, doctors, and administrators. It supports patient registration, doctor verification, hospital management, doctor schedule approvals, and appointment booking workflows.

## Overview

The application includes:

- Patient sign up and login
- Doctor registration and verification flow
- Admin approval for doctor schedules and profiles
- Hospital and department management
- Appointment booking and status tracking
- Role-based access for patients, doctors, and admins
- PostgreSQL database-backed records for healthcare operations

## Tech Stack

- PHP 8+
- PostgreSQL
- Composer
- PHPMailer
- Dotenv

## Project Structure

- `index.php` – public landing page
- `login.php` – authentication page
- `registration/` – user registration flows
- `patient/` – patient dashboard and appointment features
- `doctor/` – doctor schedule and dashboard pages
- `admin/` – admin management screens
- `database/db.sql` – database schema and seed data
- `config/config.php` – environment-based configuration loader
- `include/` – shared helper functions and mail utilities
- `assets/` – frontend CSS and media assets

## Requirements

Before running the project, make sure you have:

- PHP 8 or newer
- Composer
- PostgreSQL database server
- A local web server or PHP built-in server

## Setup

1. Clone or download this project.
2. Install PHP dependencies:

   ```bash
   composer install
   ```

3. Create a `.env` file in the project root with your database settings:

   ```env
   DB_HOST=localhost
   DB_PORT=5432
   DB_NAME=novacare
   DB_USER=postgres
   DB_PASSWORD=your_password
   ```

4. Create the PostgreSQL database and import the schema:

   ```bash
   psql -U postgres -d novacare -f database/db.sql
   ```

5. Start the app locally:

   ```bash
   php -S localhost:8000
   ```

6. Open the app in your browser:

   ```text
   http://localhost:8000
   ```

## Default Admin Account

The database seed script creates a default admin user:

- Email: `admin@example.com`
- Password: `Password123!`

## Notes

- The app expects PostgreSQL and uses the environment variables defined in `.env`.
- The admin approval and doctor scheduling flows are driven by entries in the `users`, `doctors`, `schedules`, and `appointments` tables.
- If the database is not reachable, the app will show a configuration or database error message.

## License

This project is intended for educational and internal project use unless otherwise specified by the repository owner.
