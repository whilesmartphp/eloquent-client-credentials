.PHONY: help up down install test pint pint-test lint build serve clean prepare restart logs shell

help:
	@echo 'Usage: make [target]'
	@echo ''
	@echo 'Targets:'
	@egrep '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-15s\033[0m %s\n", $$1, $$2}'

up: ## Start Docker containers
	docker compose up -d

down: ## Stop Docker containers
	docker compose down

restart: ## Restart Docker containers
	docker compose restart

logs: ## Show container logs
	docker compose logs -f

shell: ## Open shell in app container
	docker compose exec app bash

install: up ## Install dependencies
	docker compose exec app composer install

test: ## Run tests
	docker compose exec app ./vendor/bin/testbench package:test

pint: ## Run Pint code formatter
	docker compose exec app composer pint

pint-test: ## Check code formatting
	docker compose exec app composer pint:test

lint: pint ## Alias for pint

build: ## Build testbench
	docker compose exec app composer build

serve: ## Serve application
	docker compose exec app composer serve

clean: ## Clean testbench skeleton
	docker compose exec app composer clear

prepare: ## Prepare package discovery
	docker compose exec app composer prepare

autoload: ## Dump autoload
	docker compose exec app composer dump-autoload

fresh: down up install ## Fresh environment setup
	@echo "Environment is ready!"

setup: fresh test ## Full setup with tests
	@echo "Setup complete!"

check: pint-test test ## Run all checks
	@echo "All checks passed!"
