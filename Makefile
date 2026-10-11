.PHONY: help up down build rebuild logs shell tinker test lint analyse migrate seed demo fresh fresh-install-onboardable fresh-install-testable ps restart clean db-to-host db-from-native guard-native

# `docker compose` (v2, a subcommand) rather than `docker-compose` (v1, a separate
# Python binary that reached end of life in 2023).
COMPOSE_DEV = docker compose -f docker-compose.dev.yml
COMPOSE_PROD = docker compose
APP_EXEC = $(COMPOSE_DEV) exec -u laravel app

# The native server (scripts/serve-app.sh) publishes the same port, so the PID
# file it records blocks `make up`.
#
# Make tests for the file itself rather than a shell `if`: recipes run through
# cmd.exe or sh depending on how Make was started, and no `if` syntax suits both.
NATIVE_SERVER_PID_FILE := $(wildcard scripts/.serve-app.pid)

help:
	@echo "Imagoldfish Docker Commands"
	@echo ""
	@echo "Development:"
	@echo "  make up              - Start development containers"
	@echo "  make down            - Stop development containers"
	@echo "  make build           - Build development image"
	@echo "  make rebuild         - Rebuild development image"
	@echo "  make logs            - View application logs"
	@echo "  make ps              - Show running containers"
	@echo ""
	@echo "Application:"
	@echo "  make shell           - Open bash in app container"
	@echo "  make tinker          - Open Laravel tinker REPL"
	@echo "  make test            - Run test suite"
	@echo "  make lint            - Run code linting"
	@echo "  make analyse         - Run static analysis"
	@echo ""
	@echo "Database:"
	@echo "  make migrate         - Run database migrations"
	@echo "  make seed            - Seed database (admin, demo, second user)"
	@echo "  make demo            - Install the Melusine demo for the first user"
	@echo "  make fresh           - Fresh database migration and seed"
	@echo "  make fresh-install-onboardable - Fresh DB, admin only, no projects (try onboarding)"
	@echo "  make fresh-install-testable    - Fresh DB with demo + second user"
	@echo "  make db-to-host      - Save a snapshot to storage-docker/app/backups"
	@echo "  make db-from-native  - Replace Docker data with the native database and storage/app"
	@echo ""
	@echo "Utilities:"
	@echo "  make restart         - Restart containers"
	@echo "  make clean           - Remove containers, the database volume and storage-docker/"
	@echo ""

guard-native:
ifneq ($(NATIVE_SERVER_PID_FILE),)
	@echo ERROR: the native dev server holds port 8000.
	@echo Stop it first: bash scripts/stop-app.sh
	@exit 1
endif

up: guard-native
	$(COMPOSE_DEV) up

down:
	$(COMPOSE_DEV) down

build:
	$(COMPOSE_DEV) build

# Use this after changing composer.json/package.json (or pulling a branch that did).
#
# vendor/ and node_modules/ live in ANONYMOUS VOLUMES (see docker-compose.dev.yml)
# so that the host's copies don't shadow the ones installed in the image. Compose
# carries an existing anonymous volume over to the replacement container when it
# recreates one — so `build` alone leaves you running a brand-new image with the
# OLD dependencies still mounted on top of it, which looks exactly like the build
# having silently done nothing. --renew-anon-volumes is what discards them so they
# are repopulated from the freshly built image.
rebuild: guard-native
	$(COMPOSE_DEV) build --no-cache
	$(COMPOSE_DEV) up --force-recreate --renew-anon-volumes

logs:
	$(COMPOSE_DEV) logs -f

ps:
	$(COMPOSE_DEV) ps

restart:
	$(COMPOSE_DEV) restart

shell:
	$(APP_EXEC) bash

tinker:
	$(APP_EXEC) php artisan tinker

test:
	$(APP_EXEC) composer test

lint:
	$(APP_EXEC) composer lint

analyse:
	$(APP_EXEC) composer analyse

migrate:
	$(APP_EXEC) php artisan migrate

# `db:seed` now creates the admin only, so chain the test fixtures (demo + second
# user) to keep the full local dataset one command away.
seed:
	$(APP_EXEC) php artisan db:seed
	$(APP_EXEC) php artisan app:install-test-fixtures

demo:
	$(APP_EXEC) php artisan app:install-demo

# Empty admin, no projects: the app redirects to onboarding, so you can try the
# first-project flow yourself.
fresh-install-onboardable:
	$(APP_EXEC) php artisan migrate:fresh --seed

# Full local dataset: admin plus the demo projects and the second user, for visual
# checks and manual testing.
fresh-install-testable:
	$(APP_EXEC) php artisan migrate:fresh --seed
	$(APP_EXEC) php artisan app:install-test-fixtures

# Kept as the default fresh reset. Same as fresh-install-testable.
fresh: fresh-install-testable

# Resets Docker data only: the dbdata volume and storage-docker/. The native
# database/database.sqlite and storage/ are never touched. A one-off container deletes
# the folder, because `rm` does not exist in cmd.exe and the folder may hold files
# owned by root.
clean:
	$(COMPOSE_DEV) stop
	$(COMPOSE_DEV) run --rm --no-deps --entrypoint rm app -rf /app/storage-docker
	$(COMPOSE_DEV) down -v

# Snapshot of the Docker database in storage-docker/app/backups, which Explorer and
# PhpStorm can open. Safe while the app runs (VACUUM INTO).
db-to-host:
	$(APP_EXEC) php artisan db:backup --name=docker --path=/app/storage-docker/app/backups

# One-time copy of the native data into Docker; it replaces what Docker holds. The
# one-off container runs docker/db-from-native.sh with the stack down. The script
# keeps a snapshot of the old Docker database first. `migrate` runs in the entrypoint.
db-from-native: guard-native
	$(COMPOSE_DEV) down
	$(COMPOSE_DEV) run --rm --no-deps --entrypoint sh app /app/docker/db-from-native.sh
	$(COMPOSE_DEV) up -d

# Production commands
prod-up:
	$(COMPOSE_PROD) up -d

prod-down:
	$(COMPOSE_PROD) down

prod-build:
	$(COMPOSE_PROD) build

prod-logs:
	$(COMPOSE_PROD) logs -f

# Convenience aliases
dev: up
dev-down: down
dev-rebuild: rebuild
dev-logs: logs
dev-shell: shell
dev-test: test
dev-lint: lint
dev-migrate: migrate
dev-seed: seed
dev-fresh: fresh
