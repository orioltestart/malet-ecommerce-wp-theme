#!/bin/bash
set -euo pipefail

echo "🚀 Iniciant desplegament de producció..."

# ===== CONFIGURACIÓ I VARIABLES D'ENTORN =====

# Verificar que existeix el fitxer .env
if [ ! -f ".env" ]; then
    echo "❌ Error: Fitxer .env no trobat!"
    echo "Copia .env.example a .env i configura les variables"
    exit 1
fi

# Carregar variables d'entorn
source .env

# Variables d'entorn necessàries (sense valors per defecte)
WORDPRESS_URL="${WORDPRESS_URL}"
WORDPRESS_THEME_NAME="${WORDPRESS_THEME_NAME}"
BACKUP_SCHEDULE="${BACKUP_SCHEDULE}"
WORDPRESS_PORT="${WORDPRESS_PORT}"

# ===== COMPROVACIONS DE VARIABLES OBLIGATÒRIES =====
echo "🔍 Verificant configuració..."

REQUIRED_VARS=(
    "DB_NAME"
    "DB_USER" 
    "DB_PASSWORD"
    "WORDPRESS_URL"
    "WORDPRESS_ADMIN_USER"
    "WORDPRESS_ADMIN_PASSWORD"
    "WORDPRESS_ADMIN_EMAIL"
    "WORDPRESS_THEME_NAME"
    "WORDPRESS_PORT"
    "WORDPRESS_AUTH_KEY"
    "WORDPRESS_SECURE_AUTH_KEY"
    "WORDPRESS_LOGGED_IN_KEY"
    "WORDPRESS_NONCE_KEY"
)

missing_vars=()
for var in "${REQUIRED_VARS[@]}"; do
    if [ -z "${!var:-}" ]; then
        missing_vars+=("$var")
    fi
done

if [ ${#missing_vars[@]} -gt 0 ]; then
    echo "❌ Error: Variables obligatòries no definides al fitxer .env:"
    for var in "${missing_vars[@]}"; do
        echo "   - $var"
    done
    echo ""
    echo "💡 Assegura't que el fitxer .env conté totes les variables necessàries"
    echo "   Pots generar claus de seguretat a: https://api.wordpress.org/secret-key/1.1/salt/"
    exit 1
fi

echo "✅ Configuració verificada"
echo "   - Base de dades: $DB_NAME"
echo "   - URL WordPress: $WORDPRESS_URL"
echo "   - Tema: $WORDPRESS_THEME_NAME"
echo "   - Port: $WORDPRESS_PORT"

# Construir imatges
echo "🔨 Construint imatges Docker..."
docker-compose build --no-cache

# Baixar la base de dades si és necessari
echo "📊 Preparant base de dades..."
docker-compose up -d db redis
sleep 10

# Iniciar WordPress
echo "🌐 Iniciant WordPress..."
docker-compose up -d wordpress

# Esperar que WordPress estigui disponible
echo "⏳ Esperant que WordPress estigui disponible..."
timeout=60
counter=0
while ! docker-compose exec -T wordpress wp core is-installed --allow-root 2>/dev/null; do
    if [ $counter -ge $timeout ]; then
        echo "❌ Timeout esperant WordPress"
        exit 1
    fi
    sleep 2
    ((counter++))
done

# Executar script d'inicialització
echo "⚙️ Executant inicialització..."
docker-compose exec -T wordpress /scripts/init.sh

# Iniciar Nginx
echo "🌍 Iniciant servidor web..."
docker-compose up -d nginx

# Verificar que tot funciona
echo "🔍 Verificant desplegament..."
if curl -s -o /dev/null -w "%{http_code}" "${WORDPRESS_URL}" | grep -q "200\|301\|302"; then
    echo "✅ Servidor web funcionant correctament"
else
    echo "⚠️ Servidor web no respon correctament"
fi

# Mostrar estat dels serveis
echo "📊 Estat dels serveis:"
docker-compose ps

# Configurar backup automàtic (si està configurat)
if [ -n "${BACKUP_SCHEDULE:-}" ]; then
    echo "⏰ Configurant backup automàtic..."
    # Afegir cron job per backup automàtic
    (crontab -l 2>/dev/null || true; echo "${BACKUP_SCHEDULE} cd $(pwd) && docker-compose exec -T wordpress /scripts/backup.sh") | crontab -
fi

echo "🎉 Desplegament completat amb èxit!"
echo "🌐 El vostre lloc web està disponible a: $WORDPRESS_URL"
echo "👤 Admin: $WORDPRESS_ADMIN_USER"
echo "🎨 Tema actiu: $WORDPRESS_THEME_NAME"
echo ""
echo "📋 Comandes útils:"
echo "  - Logs: docker-compose logs -f"
echo "  - WP-CLI: docker-compose exec wordpress wp --info --allow-root"
echo "  - Backup: docker-compose exec wordpress /scripts/backup.sh"
echo "  - Aturar: docker-compose down"
echo ""
echo "🔐 Accés d'administració:"
echo "  - URL: $WORDPRESS_URL/wp-admin/"
echo "  - Usuari: $WORDPRESS_ADMIN_USER"
echo "  - Email: $WORDPRESS_ADMIN_EMAIL"