#!/usr/bin/env bash
#
# install.sh
#
# Documents (and can re-run) the steps used to scaffold this Laravel app.
# Mirrors the setup conventions used in ps-site-manager: Laravel + Breeze
# (Blade stack), SQLite for local dev, a top-level DashboardController.
#
# This script is written to be safe to read as documentation - it is NOT
# meant to be run against an already-scaffolded app (composer create-project
# will refuse to install into a non-empty directory).

set -euo pipefail

# ---------------------------------------------------------------------------
# 1. Scaffold a fresh Laravel app.
#
# Note: at the time this app was created, the local PHP version was 8.2,
# which does not satisfy Laravel 13's `^8.3` requirement. Laravel 12 was
# used instead since it supports PHP 8.2. If your PHP is 8.3+, you can bump
# the constraint below to "^13.0".
# ---------------------------------------------------------------------------
composer create-project laravel/laravel . "^12.0" --no-interaction

# `composer create-project` refuses to run into a non-empty directory, so in
# practice this was scaffolded into a temp sibling folder and then moved in:
#
#   composer create-project laravel/laravel ../imagoldfish-tmp "^12.0" --no-interaction
#   mv ../imagoldfish-tmp/* .   # (excluding any pre-existing files, e.g. .idea)
#   rmdir ../imagoldfish-tmp
#
# create-project already runs `php artisan key:generate`, creates
# database/database.sqlite, and runs the initial migrations for you.

# ---------------------------------------------------------------------------
# 2. Install Laravel Breeze (auth scaffolding) with the Blade stack.
#
# Breeze adds: routes/auth.php, Auth\* controllers, Blade auth views,
# Tailwind CSS + Alpine.js via Vite, and a default /dashboard route.
# ---------------------------------------------------------------------------
composer require laravel/breeze --dev --no-interaction
php artisan breeze:install blade --no-interaction

# breeze:install already runs `npm install` and `npm run build` for you.

# ---------------------------------------------------------------------------
# 3. Migrate the database (creates users, password_reset_tokens, sessions,
# cache, and jobs tables).
# ---------------------------------------------------------------------------
php artisan migrate --no-interaction

# ---------------------------------------------------------------------------
# 4. Seed a default admin user for local development.
#
# See database/seeders/DatabaseSeeder.php - creates:
#   email:    admin@example.com
#   password: password
#
# The login form (resources/views/auth/login.blade.php) prefills these
# credentials automatically when APP_ENV=local.
# ---------------------------------------------------------------------------
php artisan db:seed --no-interaction

# ---------------------------------------------------------------------------
# 5. Sanity checks.
# ---------------------------------------------------------------------------
php artisan route:list   # confirm dashboard, login, register, logout exist
php artisan test          # confirm Breeze's default auth tests pass

# ---------------------------------------------------------------------------
# 6. Run the app locally.
# ---------------------------------------------------------------------------
# php artisan serve   # http://127.0.0.1:8000
# npm run dev         # Vite dev server for hot-reloading assets
