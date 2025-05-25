# Budget Tracker – Evozon PHP Internship Hackathon 2025

## Starting from the skeleton

Prerequisites:

- PHP >= 8.1 with the usual extension installed, including PDO.
- [Composer](https://getcomposer.org/download)
- Sqlite3 (or another database tool that allows handling SQLite databases)
- Git
- A good PHP editor: PHPStorm or something similar

About the skeleton:

- The skeleton is built on Slim (`slim/slim : ^4.0`)
- The templating engine of choice is Twig (`slim/twig-view`)
- The dependency injection container of choice is `php-di/php-di`
- The database access layer of choice is plain PDO
- The configuration should be provided in a .env file (`vlucas/phpdotenv`)
- There is logging support by using `monolog/monolog`
- Input validation should be simply done using `webmozart/assert` and throwing Slim dedicated HTTP exceptions

## Step-by-step set-up

Install dependencies:

```
composer install
```

Set up the database:

```
cd database
./apply_migrations.sh
```

Note: be aware that, if you are using WSL2 (Windows Subsystem for Linux), you'll have trouble opening SQLite databases
with a DB management app (PHPStorm, for example) in Windows **when they are stored within the virtualized WSL2 drive**.
The solution is to store the `db.sqlite` file on the Windows drive (`/mnt/c`) and configure the path to the file in the
application config (`.env`):

```
cd database
./apply_migrations.sh /mnt/c/Users/<user>/AppData/Local/Temp/db.sqlite
```

Copy `.env.example` to `.env` and configure as necessary:

```
cp .env.example .env
```

Run the built-in server on http://localhost:8000

```
composer start
```

## Features

## Tasks

### Before you start coding

Make sure you inspect the skeleton and identify the important parts:

- `public/index.php` - the web entry point
- `app/Kernel.php` - DI container and application setup
- classes under `app` - this is where most of your code will go
- templates under `templates` are almost complete, at least in terms of static mark-up; all you need is to make use of
  the Twig syntax to make them dynamic.

### Main tasks — for having a functional application

Start coding: search for `// TODO: ...` and fill in the necessary logic. Don't limit yourself to that; you can do
whatever you want, design it the way you see fit. The TODOs are a starting point that you may choose to use.

### Extra tasks — for extra points

Solve extra requirements for extra points. Some of them you can implement from the start, others we prefer you to attack
after you have a fully functional application, should you have time left. More instructions on this in the assignment.

### Deliver well designed quality code

Before delivering your solution, make sure to:

- format every file and make sure there is no commented code left, and code looks spotless

- run static analysis tools to check for code issues:

```
composer analyze
```

- run unit tests (in case you added any):

```
composer test
```

A solution with passing analysis and unit tests will receive extra points.

## Delivery details

Participant:

- Full name: Szepesi Renata
- Email address: reniisz19@gmail.com

Features fully implemented:

- Register user via /register (GET + POST)
- Login via /login (GET + POST)
- Logout via /logout (GET)
- Expenses – List via /expenses (GET)
- Expenses – Add via /expenses/create (GET + POST)
- Expenses – Edit via /expenses/{id}/edit (GET + POST)
- Expenses – Delete via /expenses/{id}/delete (POST)
- Dashboard via / (GET)
- CSV Import (from /expenses page)

Bonuses :

- Used prepared statements for all database queries.
- Ensured users can only edit/delete their own expenses.
- Ensured `composer analyze` passes with PHPMD and PHPStan success.
- Registered users with secure password hashing using PHP's password_hash.
- Added "password again" input field in the register form to prevent typos.
- Made register form CSRF-protected.
- Verified login using PHP's password_verify.
- Made login form CSRF-protected.
- Moved categories and budget thresholds to `.env` configuration.
- Displayed “1 2 3 .. N” pagination links on the expenses list page.
- Showed flash messages after deleting expenses (success/failure).
- Wrapped CSV import in a single DB transaction with rollback on error.
- Showed flash success message on Expenses List page after CSV import, including imported count.

Other instructions about setting up the application (if any): ...
