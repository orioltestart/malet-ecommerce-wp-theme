#!/bin/bash
# Script per fer backup de la base de dades WordPress

# Data actual per nom del fitxer
DATE=$(date +%Y%m%d-%H%M%S)
BACKUP_DIR="./backups"
BACKUP_FILE="malet-wp-backup-${DATE}.sql"

# Crear directori de backups si no existeix
mkdir -p $BACKUP_DIR

echo "🗄️ Fent backup de la base de dades..."

# Fer backup de la base de dades
if docker-compose exec -T wordpress wp db export - --allow-root > "$BACKUP_DIR/$BACKUP_FILE"; then
    echo "✅ Backup creat a: $BACKUP_DIR/$BACKUP_FILE"

    # Comprimir backup
    gzip "$BACKUP_DIR/$BACKUP_FILE"
    echo "🗜️ Backup comprimit: $BACKUP_DIR/$BACKUP_FILE.gz"

    # Mostrar informació del backup
    ls -lah "$BACKUP_DIR/$BACKUP_FILE.gz"
else
    echo "❌ Error creant el backup"
    exit 1
fi

echo "✅ Backup completat!"