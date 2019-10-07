#!/bin/bash

SERVICE = web
SERVICE_DB = db
CONTAINER = magento_module
CONTAINER_DB = magento_module_db

.PHONY: up
up:
	docker-compose up -d
	docker exec -it $(CONTAINER) service cron start

.PHONY: stop
stop:
	docker-compose stop
	docker exec -it $(CONTAINER) service cron stop

.PHONY: down
down:
	docker-compose down

.PHONY: rebuild
rebuild: down
	docker-compose up -d --build
	docker exec -it $(CONTAINER) service cron start

.PHONY: bash
bash:
	docker-compose exec -u www-data $(SERVICE) bash

.PHONY: mysql
mysql:
	docker exec -it $(CONTAINER) mysql --user=root --password=root magento

.PHONY: install
install: rebuild
	docker exec -u magendock -it $(CONTAINER) composer install -d ./app/code/PlacetoPay/Payments

.PHONY: install-magento
install-magento:
	docker exec -it $(CONTAINER) install-magento

.PHONY: install-sampledata
install-sampledata:
	docker exec -it $(CONTAINER) install-sampledata