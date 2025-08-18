#!/bin/bash
set -euo pipefail

echo "🚀 Iniciant desplegament de producció..."

# Verificar que existeix el fitxer .env
if [ ! -f ".env" ]; then
    echo "❌ Error: Fitxer .env no trobat!"
    echo "Copia .env.example a .env i configura les variables"
    exit 1
fi

# Verificar variables crítiques
source .env
REQUIRED_VARS=("DB_NAME" "DB_USER" "DB_PASSWORD" "WP_SITEURL" "WORDPRESS_AUTH_KEY")
for var in "${REQUIRED_VARS[@]}"; do
    if [ -z "${!var:-}" ]; then
        echo "❌ Error: Variable $var no definida al fitxer .env"
        exit 1
    fi
done

echo "✅ Configuració verificada"

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
if curl -s -o /dev/null -w "%{http_code}" "${WP_SITEURL}/nginx-health" | grep -q "200"; then
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
echo "🌐 El vostre lloc web està disponible a: $WP_SITEURL"
echo ""
echo "📋 Comandes útils:"
echo "  - Logs: docker-compose logs -f"
echo "  - WP-CLI: docker-compose exec wp-cli wp --info"
echo "  - Backup: docker-compose exec wordpress /scripts/backup.sh"
echo "  - Aturar: docker-compose down"