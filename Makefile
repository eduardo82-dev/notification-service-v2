.PHONY: install up down restart logs test swagger migrate fresh worker

# Full project setup — single command to get everything running
install:
	cp -n .env.example .env || true
	docker compose up -d --build
	docker compose exec app composer install
	docker compose exec app chown -R www-data:www-data storage bootstrap/cache
	docker compose exec app php artisan key:generate
	docker compose exec app php artisan migrate --force
	docker compose exec app php artisan l5-swagger:generate
	@echo ""
	@echo "=== Notification Service is ready ==="
	@echo "API:            http://localhost:8080/api/v1"
	@echo "Swagger UI:     http://localhost:8080/api/documentation"
	@echo "RabbitMQ UI:    http://localhost:15672 (guest/guest)"
	@echo ""

up:
	docker compose up -d

down:
	docker compose down

restart:
	docker compose restart

logs:
	docker compose logs -f app worker

test:
	docker compose exec app php artisan test

swagger:
	docker compose exec app php artisan l5-swagger:generate

migrate:
	docker compose exec app php artisan migrate

fresh:
	docker compose exec app php artisan migrate:fresh

worker:
	docker compose logs -f worker
