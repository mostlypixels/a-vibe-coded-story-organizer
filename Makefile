.PHONY: help up down build rebuild logs shell tinker test lint migrate seed fresh ps restart clean

COMPOSE_DEV = docker-compose -f docker-compose.dev.yml
COMPOSE_PROD = docker-compose
APP_EXEC = $(COMPOSE_DEV) exec -u laravel app

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
	@echo ""
	@echo "Database:"
	@echo "  make migrate         - Run database migrations"
	@echo "  make seed            - Seed database"
	@echo "  make fresh           - Fresh database migration and seed"
	@echo ""
	@echo "Utilities:"
	@echo "  make restart         - Restart containers"
	@echo "  make clean           - Remove containers and volumes"
	@echo ""

up:
	$(COMPOSE_DEV) up

down:
	$(COMPOSE_DEV) down

build:
	$(COMPOSE_DEV) build

rebuild:
	$(COMPOSE_DEV) build --no-cache

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

migrate:
	$(APP_EXEC) php artisan migrate

seed:
	$(APP_EXEC) php artisan db:seed

fresh:
	$(APP_EXEC) php artisan migrate:fresh --seed

clean:
	$(COMPOSE_DEV) down -v
	rm -rf database/database.sqlite

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
