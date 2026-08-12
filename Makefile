# Bizlife CRM — geliştirme kısayolları
#
# Host'a PHP kurulmaz. Tüm php/artisan/composer komutları container içinde
# çalışır. Kullanım örnekleri:
#
#   make up
#   make artisan a="migrate"
#   make artisan a="about"
#   make composer a="require spatie/laravel-permission"
#   make php a="-v"
#   make tinker
#   make test
#
# Host UID/GID'si .env'den okunur; yoksa id -u/id -g kullanılır.

COMPOSE      := docker compose
APP          := app
USER_ID     ?= $(shell id -u)
GROUP_ID    ?= $(shell id -g)

.PHONY: help up down restart build rebuild logs ps shell \
        artisan composer php tinker migrate fresh fresh-test seed seed-demo test \
        minio-bucket clean purge-demo \
        lint lint-fix analyse test test-coverage prod-build prod-preflight

help: ## Bu yardımı göster
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | \
		awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-14s\033[0m %s\n", $$1, $$2}'

build: ## İmajları derle (UID/GID .env'den)
	$(COMPOSE) build --build-arg USER_ID=$(USER_ID) --build-arg GROUP_ID=$(GROUP_ID) app
	$(COMPOSE) build --build-arg USER_ID=$(USER_ID) --build-arg GROUP_ID=$(GROUP_ID) queue scheduler

rebuild: ## İmajları temizleyip yeniden derle
	$(COMPOSE) build --no-cache --build-arg USER_ID=$(USER_ID) --build-arg GROUP_ID=$(GROUP_ID) app
	$(COMPOSE) build --no-cache --build-arg USER_ID=$(USER_ID) --build-arg GROUP_ID=$(GROUP_ID) queue scheduler

up: ## Servisleri ayağa kaldır (arka planda; gerekirse imajı derler)
	$(COMPOSE) up -d --build
	@echo ""
	@echo "Uygulama : http://localhost:$$(grep '^WEB_PORT=' .env 2>/dev/null | cut -d= -f2 || echo 8088)"
	@echo "Mailpit  : http://localhost:$$(grep '^PUBLISH_MAILPIT_WEB_PORT=' .env 2>/dev/null | cut -d= -f2 || echo 8025)"
	@echo "MinIO    : http://localhost:$$(grep '^PUBLISH_MINIO_CONSOLE_PORT=' .env 2>/dev/null | cut -d= -f2 || echo 9001)"

down: ## Servisleri durdur (volumeler kalır)
	$(COMPOSE) down

restart: ## Servisleri yeniden başlat
	$(COMPOSE) restart

logs: ## Canlı log akışı (tüm servisler)
	$(COMPOSE) logs -f --tail=100

ps: ## Servis durumu
	$(COMPOSE) ps

shell: ## app container'ında bash aç
	$(COMPOSE) exec $(APP) bash

# --- PHP / artisan / composer (host'ta PHP yok, container içinde) ---

artisan: ## artisan komutu: make artisan a="migrate"
	@test -n "$(a)" || (echo "Kullanım: make artisan a=\"migrate\"" && exit 1)
	$(COMPOSE) exec $(APP) php artisan $(a)

composer: ## composer komutu: make composer a="require paket/paket"
	@test -n "$(a)" || (echo "Kullanım: make composer a=\"require ...\"" && exit 1)
	$(COMPOSE) run --rm $(APP) composer $(a)

php: ## keyfi php komutu: make php a="-v"  ya da  make php a="-m"
	@test -n "$(a)" || (echo "Kullanım: make php a=\"-v\"" && exit 1)
	$(COMPOSE) exec $(APP) php $(a)

tinker: ## Laravel tinker
	$(COMPOSE) exec $(APP) php artisan tinker

migrate: ## migrate
	$(COMPOSE) exec $(APP) php artisan migrate

test: ## Pest testleri (PostgreSQL test veritabanına karşı)
	$(COMPOSE) run --rm db-init
	$(COMPOSE) exec $(APP) php artisan test

test-coverage: ## Pest kod kapsama raporu (coverage/*.clover)
	$(COMPOSE) run --rm db-init
	$(COMPOSE) exec $(APP) php artisan test --coverage --coverage-html=coverage --min=0

seed: ## Yalnız üretimde güvenli referans/yapılandırma verisini yükle
	$(COMPOSE) exec $(APP) php artisan db:seed --class=ReferenceDataSeeder --force

seed-demo: ## Referans verisiyle birlikte kurgusal demo verisini yükle
	$(COMPOSE) exec $(APP) php artisan db:seed --class=DemoDataSeeder --force

purge-demo: ## Korumalı onayla yalnız demo iş verisini temizle
	$(COMPOSE) exec $(APP) php artisan demo:purge --confirm="DEMO VERİYİ TEMİZLE"

fresh: ## Şemayı sıfırla ve yalnız referans/yapılandırma verisini yükle
	$(COMPOSE) exec $(APP) php artisan migrate:fresh --seed --force

fresh-test: ## Test şemasını güvenli biçimde sıfırla
	$(COMPOSE) run --rm db-init
	$(COMPOSE) exec $(APP) php artisan migrate:fresh --env=testing --seed --force

# --- Kalite / test ---

lint: ## Pint biçim kontrolü (düzeltmez)
	$(COMPOSE) exec $(APP) vendor/bin/pint --test

lint-fix: ## Pint biçim düzeltmesi (dosyaları değiştirir)
	$(COMPOSE) exec $(APP) vendor/bin/pint

analyse: ## Larastan (PHPStan) statik analiz — level 6
	$(COMPOSE) exec $(APP) vendor/bin/phpstan analyse --memory-limit=512M

minio-bucket: ## MinIO bucket'ını elle oluştur (normalde minio-init yapar)
	$(COMPOSE) run --rm minio-init

clean: ## Dangling imajları ve build önbelleğini temizle
	docker image prune -f

prod-preflight: ## Üretim sırlarını ve dışa kapalı ağı doğrula
	./scripts/production-preflight.sh .env.production
	docker compose --env-file .env.production -f compose.prod.yaml config --quiet

prod-build: ## Üretim app, web ve operasyon imajlarını gerçekten derle
	docker compose --env-file .env.production -f compose.prod.yaml build app web backup
