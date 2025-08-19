#!/bin/bash
set -euo pipefail

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
BACKUP_DIR="${BACKUP_DIR}"
RETENTION_DAYS="${BACKUP_RETENTION_DAYS}"
WORDPRESS_ADMIN_EMAIL="${WORDPRESS_ADMIN_EMAIL}"

# Variables de base de dades (sense valors per defecte)
DB_HOST="${WORDPRESS_DB_HOST}"
DB_NAME="${WORDPRESS_DB_NAME}"
DB_USER="${WORDPRESS_DB_USER}"
DB_PASSWORD="${WORDPRESS_DB_PASSWORD}"

# Variables generades
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
DB_BACKUP_FILE="$BACKUP_DIR/db_backup_$TIMESTAMP.sql"
UPLOADS_BACKUP_FILE="$BACKUP_DIR/uploads_backup_$TIMESTAMP.tar.gz"
FULL_BACKUP_FILE="$BACKUP_DIR/full_backup_$TIMESTAMP.tar.gz"

# ===== COMPROVACIONS DE VARIABLES OBLIGATÒRIES =====
echo "🔍 Verificant configuració..."

REQUIRED_VARS=(
    "BACKUP_DIR"
    "BACKUP_RETENTION_DAYS"
    "WORDPRESS_ADMIN_EMAIL"
    "WORDPRESS_DB_HOST"
    "WORDPRESS_DB_NAME"
    "WORDPRESS_DB_USER"
    "WORDPRESS_DB_PASSWORD"
)

missing_vars=()
for var in "${REQUIRED_VARS[@]}"; do
    var_value="${!var:-}"
    if [ -z "$var_value" ]; then
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
    exit 1
fi

echo "✅ Configuració verificada"
echo "   - Base de dades: $DB_NAME"
echo "   - Directori backup: $BACKUP_DIR"
echo "   - Retenció: $RETENTION_DAYS dies"

echo "🗄️ Iniciant backup del lloc web..."

# Crear directori de backup si no existeix
mkdir -p "$BACKUP_DIR"

# Backup de la base de dades
echo "📊 Fent backup de la base de dades..."
wp db export "$DB_BACKUP_FILE" --allow-root
echo "✅ Backup de BD completat: $DB_BACKUP_FILE"

# Backup dels uploads
echo "📁 Fent backup dels uploads..."
if [ -d "/var/www/html/wp-content/uploads" ]; then
    tar -czf "$UPLOADS_BACKUP_FILE" -C /var/www/html/wp-content uploads
    echo "✅ Backup d'uploads completat: $UPLOADS_BACKUP_FILE"
else
    echo "⚠️ Directori d'uploads no trobat"
fi

# Backup complet (exclou cache i logs)
echo "📦 Fent backup complet..."
tar -czf "$FULL_BACKUP_FILE" \
    --exclude="wp-content/cache" \
    --exclude="wp-content/logs" \
    --exclude="wp-content/debug.log" \
    -C /var/www/html .

echo "✅ Backup complet completat: $FULL_BACKUP_FILE"

# Neteja de backups antics
echo "🧹 Netejant backups antics (més de $RETENTION_DAYS dies)..."
find "$BACKUP_DIR" -name "*.sql" -type f -mtime +$RETENTION_DAYS -delete
find "$BACKUP_DIR" -name "*.tar.gz" -type f -mtime +$RETENTION_DAYS -delete

# Mostrar informació dels backups
echo "📋 Informació dels backups:"
echo "  - Base de dades: $(du -h "$DB_BACKUP_FILE" | cut -f1)"
if [ -f "$UPLOADS_BACKUP_FILE" ]; then
    echo "  - Uploads: $(du -h "$UPLOADS_BACKUP_FILE" | cut -f1)"
fi
echo "  - Backup complet: $(du -h "$FULL_BACKUP_FILE" | cut -f1)"

echo "✅ Backup completat amb èxit!"

# Opcional: enviar notificació per email
if command -v wp >/dev/null 2>&1; then
    echo "📧 Enviant notificació per email a $WORDPRESS_ADMIN_EMAIL..."
    wp eval "wp_mail('$WORDPRESS_ADMIN_EMAIL', 'Backup completat - Malet Torrent', 'El backup del $TIMESTAMP s\'ha completat correctament.\n\nFitxers generats:\n- Base de dades: $DB_BACKUP_FILE\n- Uploads: $UPLOADS_BACKUP_FILE\n- Backup complet: $FULL_BACKUP_FILE');" --allow-root --path=/var/www/html 2>/dev/null || echo "⚠️ No s'ha pogut enviar l'email de notificació"
else
    echo "⚠️ WP-CLI no disponible, saltant notificació per email"
fi