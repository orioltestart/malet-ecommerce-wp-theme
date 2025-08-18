.PHONY: help build up down restart logs clean backup restore dev prod init

# Variables
COMPOSE_FILE = docker-compose.yml
DEV_COMPOSE_FILE = docker-compose.dev.yml

# Colors
RED := \033[0;31m
GREEN := \033[0;32m
YELLOW := \033[1;33m
BLUE := \033[0;34m
NC := \033[0m # No Color

help: ## Mostra aquesta ajuda
	@echo "$(BLUE)Malet Torrent WordPress - Comandes disponibles:$(NC)"
	@echo ""
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "$(GREEN)%-20s$(NC) %s\n", $$1, $$2}'

build: ## Construeix les imatges Docker
	@echo "$(BLUE)Construint imatges...$(NC)"
	docker-compose build

up: ## Inicia els serveis en mode producció
	@echo "$(BLUE)Iniciant serveis en mode producció...$(NC)"
	docker-compose up -d
	@echo "$(GREEN)✅ Serveis iniciats!$(NC)"
	@echo "$(YELLOW)URL: $$(grep WP_SITEURL .env 2>/dev/null | cut -d'=' -f2 || echo 'http://localhost')$(NC)"

down: ## Atura els serveis
	@echo "$(BLUE)Aturant serveis...$(NC)"
	docker-compose down
	@echo "$(GREEN)✅ Serveis aturats!$(NC)"

restart: down up ## Reinicia els serveis

logs: ## Mostra els logs dels serveis
	docker-compose logs -f

status: ## Mostra l'estat dels serveis
	@echo "$(BLUE)Estat dels serveis:$(NC)"
	docker-compose ps

clean: ## Neteja imatges i volums no utilitzats
	@echo "$(YELLOW)Netejant imatges i volums no utilitzats...$(NC)"
	docker system prune -f
	docker volume prune -f
	@echo "$(GREEN)✅ Neteja completada!$(NC)"

backup: ## Crea un backup complet
	@echo "$(BLUE)Creant backup...$(NC)"
	docker-compose exec -T wordpress /scripts/backup.sh
	@echo "$(GREEN)✅ Backup completat!$(NC)"

init: ## Inicialitza WordPress amb configuració bàsica
	@echo "$(BLUE)Inicialitzant WordPress...$(NC)"
	docker-compose exec -T wordpress /scripts/init.sh
	@echo "$(GREEN)✅ Inicialització completada!$(NC)"

# Comandos de desenvolupament
dev: ## Inicia en mode desenvolupament
	@echo "$(BLUE)Iniciant en mode desenvolupament...$(NC)"
	@if [ ! -f .env ]; then \
		echo "$(YELLOW)Creant fitxer .env per desenvolupament...$(NC)"; \
		cp .env.example .env; \
		sed -i 's/your_secure_password_here/dev_password/g' .env; \
		sed -i 's/your_root_password_here/root/g' .env; \
		sed -i 's/your_redis_password_here/dev_redis/g' .env; \
		sed -i 's/https:\/\/your-domain.com/http:\/\/localhost:8080/g' .env; \
	fi
	docker-compose -f $(COMPOSE_FILE) -f $(DEV_COMPOSE_FILE) up -d
	@echo "$(GREEN)✅ Mode desenvolupament iniciat!$(NC)"
	@echo "$(YELLOW)URL: http://localhost:8080$(NC)"
	@echo "$(YELLOW)PHPMyAdmin: http://localhost:8081$(NC)"
	@echo "$(YELLOW)MailHog: http://localhost:8025$(NC)"

dev-down: ## Atura el mode desenvolupament
	@echo "$(BLUE)Aturant mode desenvolupament...$(NC)"
	docker-compose -f $(COMPOSE_FILE) -f $(DEV_COMPOSE_FILE) down
	@echo "$(GREEN)✅ Mode desenvolupament aturat!$(NC)"

dev-logs: ## Mostra logs en mode desenvolupament
	docker-compose -f $(COMPOSE_FILE) -f $(DEV_COMPOSE_FILE) logs -f

# Comandos de producció
prod: ## Desplega en producció
	@echo "$(BLUE)Desplegant en producció...$(NC)"
	@if [ ! -f .env ]; then \
		echo "$(RED)❌ Error: Fitxer .env no trobat!$(NC)"; \
		echo "$(YELLOW)Copia .env.example a .env i configura les variables$(NC)"; \
		exit 1; \
	fi
	./scripts/deploy.sh
	@echo "$(GREEN)✅ Desplegament de producció completat!$(NC)"

# Comandos de WP-CLI
wp: ## Executa comandes WP-CLI (ús: make wp cmd="core version")
	@if [ -z "$(cmd)" ]; then \
		echo "$(RED)Error: Especifica una comanda amb cmd=\"...\"$(NC)"; \
		echo "$(YELLOW)Exemple: make wp cmd=\"core version\"$(NC)"; \
		exit 1; \
	fi
	docker-compose exec wp-cli wp $(cmd)

wp-dev: ## Executa comandes WP-CLI en mode desenvolupament
	@if [ -z "$(cmd)" ]; then \
		echo "$(RED)Error: Especifica una comanda amb cmd=\"...\"$(NC)"; \
		echo "$(YELLOW)Exemple: make wp-dev cmd=\"core version\"$(NC)"; \
		exit 1; \
	fi
	docker-compose -f $(COMPOSE_FILE) -f $(DEV_COMPOSE_FILE) exec wp-cli wp $(cmd)

# Comandos d'utilitat
shell: ## Obre una shell dins del contenidor WordPress
	docker-compose exec wordpress bash

db-shell: ## Obre una shell MySQL
	docker-compose exec db mysql -u$$(grep DB_USER .env | cut -d'=' -f2) -p$$(grep DB_PASSWORD .env | cut -d'=' -f2) $$(grep DB_NAME .env | cut -d'=' -f2)

redis-shell: ## Obre una shell Redis
	docker-compose exec redis redis-cli

# Setup inicial
setup: ## Configuració inicial del projecte
	@echo "$(BLUE)Configuració inicial del projecte...$(NC)"
	@if [ ! -f .env ]; then \
		echo "$(YELLOW)Creant fitxer .env...$(NC)"; \
		cp .env.example .env; \
		echo "$(GREEN)✅ Fitxer .env creat!$(NC)"; \
		echo "$(YELLOW)⚠️ Configura les variables al fitxer .env abans de continuar$(NC)"; \
	else \
		echo "$(GREEN)✅ Fitxer .env ja existeix$(NC)"; \
	fi
	@mkdir -p backups logs nginx/ssl
	@echo "$(GREEN)✅ Configuració inicial completada!$(NC)"
	@echo "$(YELLOW)Següents passos:$(NC)"
	@echo "  1. Edita el fitxer .env amb la teva configuració"
	@echo "  2. Executa 'make dev' per desenvolupament o 'make prod' per producció"

# Informació del sistema
info: ## Mostra informació del sistema
	@echo "$(BLUE)Informació del sistema:$(NC)"
	@echo "Docker: $$(docker --version)"
	@echo "Docker Compose: $$(docker-compose --version)"
	@echo ""
	@echo "$(BLUE)Volums Docker:$(NC)"
	@docker volume ls --filter name=maletwp
	@echo ""
	@echo "$(BLUE)Imatges Docker:$(NC)"
	@docker images --filter reference=maletwp*