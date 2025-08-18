#!/bin/bash
set -euo pipefail

# Configuració
BACKUP_DIR="/backups"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
DB_BACKUP_FILE="$BACKUP_DIR/db_backup_$TIMESTAMP.sql"
UPLOADS_BACKUP_FILE="$BACKUP_DIR/uploads_backup_$TIMESTAMP.tar.gz"
FULL_BACKUP_FILE="$BACKUP_DIR/full_backup_$TIMESTAMP.tar.gz"
RETENTION_DAYS=${BACKUP_RETENTION_DAYS:-30}

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

# Opcional: enviar notificació (descomenta si tens configuració SMTP)
# wp eval "wp_mail('admin@your-domain.com', 'Backup completat', 'El backup del $TIMESTAMP s\'ha completat correctament.');" --allow-root