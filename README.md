# ChezaCoop

Sacco Digital Wallet Application

---

# Requirements

Make sure you have the following installed:

- PHP 8.4+
- Composer
- PostgreSQL
- Git

---

# Clone the Project

```bash
git clone <repository-url>
cd <project-folder>
```

---

# Install Dependencies

```bash
composer install
npm install
```

---

# Environment Setup

Copy the example environment file:

```bash
cp .env.example .env
```

Generate the application key:

```bash
php artisan key:generate
```

---

# Configure PostgreSQL

Copy the .env.example to .env file and update database, mpesa and express sms credentials in the .env file:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=your_database_name
DB_USERNAME=your_postgres_username
DB_PASSWORD=your_postgres_password

EXPRESS_SMS_API_KEY=api_key_here


MPESA_CONSUMER_KEY=consumer_key
MPESA_CONSUMER_SECRET=secret
MPESA_PASSKEY=passkey
MPESA_ENVIRONMENT=sandbox
MPESA_SHORTCODE=600995
```

---

# Create the Database

Using PostgreSQL:

```sql
CREATE DATABASE your_database_name;
```

Or via terminal:

```bash
createdb your_database_name
```

---

# Run Database Migrations

```bash
php artisan migrate
```

---

# Run the Application

Start the Laravel development server:

```bash
php artisan serve
```

The application will be available at:

```text
http://127.0.0.1:8000
```

---

# Useful Commands

Clear application cache:

```bash
php artisan optimize:clear
```

Check routes:

```bash
php artisan route:list
```

Run tests:

```bash
php artisan test
```

---

# API Authentication (Sanctum)

If using Laravel Sanctum:

Run migrations:

```bash
php artisan migrate
```
---

# Troubleshooting

## Missing PHP Extensions

If you encounter errors related to PHP extensions, enable them in your `php.ini`.

Example:

```ini
extension=fileinfo
extension=pdo_pgsql
extension=pgsql
```

Then restart your terminal/server.

Check loaded extensions:

```bash
php -m
```

---


# TODO:

Due to time constraints, a lot of features have not been implemented.

1. Work on Manual reconciliation
2. Test the payments and SMS
3. Fill and track access logs
4. Handle failed transactions
5. Set flow for National ID and Phone number verification
