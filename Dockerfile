FROM wordpress:6.8.3-php8.3-apache

# Instal·lar paquets mínims
RUN apt-get update && apt-get install -y \
    curl default-mysql-client less nano \
    && rm -rf /var/lib/apt/lists/*

# Configuració PHP optimitzada
RUN { \
    echo "memory_limit = 256M"; \
    echo "upload_max_filesize = 64M"; \
    echo "post_max_size = 64M"; \
    echo "max_execution_time = 300"; \
} > /usr/local/etc/php/conf.d/custom.ini

# Habilitar mod_rewrite
RUN a2enmod rewrite

# Crear directori del tema i copiar només els fitxers del tema
RUN mkdir -p /var/www/html/wp-content/themes/malet-torrent
COPY . /var/www/html/wp-content/themes/malet-torrent

# Ajustar permisos generals de WordPress
RUN chown -R www-data:www-data /var/www/html && chmod -R 755 /var/www/html

# Script per fixar permisos dels volums persistents
RUN cat > /usr/local/bin/fix-volume-permissions.sh << 'EOF'
#!/bin/bash
set -e
echo "🔧 Configurant permisos dels volums..."
mkdir -p /var/www/html/wp-content/{plugins,uploads,upgrade,cache}
chown -R www-data:www-data /var/www/html/wp-content
chmod -R 775 /var/www/html/wp-content
EOF

RUN chmod +x /usr/local/bin/fix-volume-permissions.sh

# Entrypoint personalitzat
RUN cat > /usr/local/bin/custom-entrypoint.sh << 'EOF'
#!/bin/bash
set -e

# Fixar permisos abans d'arrencar
/usr/local/bin/fix-volume-permissions.sh

# Configurar WP_DEBUG via variables d'entorn
if [ -f /var/www/html/wp-config.php ]; then
    echo "🔧 Configurant WP_DEBUG des de variables d'entorn..."

    # WP_DEBUG (per defecte: false)
    WP_DEBUG_VALUE="${WP_DEBUG:-false}"
    if grep -q "define.*'WP_DEBUG'" /var/www/html/wp-config.php; then
        sed -i "s/define( *'WP_DEBUG'.*/define( 'WP_DEBUG', ${WP_DEBUG_VALUE} );/" /var/www/html/wp-config.php
    else
        sed -i "/\/\* That's all, stop editing/i define( 'WP_DEBUG', ${WP_DEBUG_VALUE} );" /var/www/html/wp-config.php
    fi

    # WP_DEBUG_DISPLAY (per defecte: false)
    WP_DEBUG_DISPLAY_VALUE="${WP_DEBUG_DISPLAY:-false}"
    if grep -q "define.*'WP_DEBUG_DISPLAY'" /var/www/html/wp-config.php; then
        sed -i "s/define( *'WP_DEBUG_DISPLAY'.*/define( 'WP_DEBUG_DISPLAY', ${WP_DEBUG_DISPLAY_VALUE} );/" /var/www/html/wp-config.php
    else
        sed -i "/\/\* That's all, stop editing/i define( 'WP_DEBUG_DISPLAY', ${WP_DEBUG_DISPLAY_VALUE} );" /var/www/html/wp-config.php
    fi

    # WP_DEBUG_LOG (per defecte: false)
    WP_DEBUG_LOG_VALUE="${WP_DEBUG_LOG:-false}"
    if grep -q "define.*'WP_DEBUG_LOG'" /var/www/html/wp-config.php; then
        sed -i "s/define( *'WP_DEBUG_LOG'.*/define( 'WP_DEBUG_LOG', ${WP_DEBUG_LOG_VALUE} );/" /var/www/html/wp-config.php
    else
        sed -i "/\/\* That's all, stop editing/i define( 'WP_DEBUG_LOG', ${WP_DEBUG_LOG_VALUE} );" /var/www/html/wp-config.php
    fi

    echo "✅ WP_DEBUG=${WP_DEBUG_VALUE}, WP_DEBUG_DISPLAY=${WP_DEBUG_DISPLAY_VALUE}, WP_DEBUG_LOG=${WP_DEBUG_LOG_VALUE}"
fi

# Configurar Redis Object Cache via variables d'entorn
if [ -f /var/www/html/wp-config.php ]; then
    echo "🔧 Configurant Redis Object Cache des de variables d'entorn..."

    # Configuració de Redis
    REDIS_HOST_VALUE="${REDIS_HOST:-redis}"
    REDIS_PORT_VALUE="${REDIS_PORT:-6379}"
    REDIS_DATABASE_VALUE="${REDIS_DATABASE:-0}"

    # Eliminar configuracions antigues de Redis si existeixen
    sed -i "/define( *'WP_REDIS_HOST'/d" /var/www/html/wp-config.php
    sed -i "/define( *'WP_REDIS_PORT'/d" /var/www/html/wp-config.php
    sed -i "/define( *'WP_REDIS_DATABASE'/d" /var/www/html/wp-config.php
    sed -i "/define( *'WP_REDIS_PASSWORD'/d" /var/www/html/wp-config.php
    sed -i "/define( *'WP_CACHE'/d" /var/www/html/wp-config.php

    # Afegir noves configuracions abans de "That's all, stop editing"
    sed -i "/\/\* That's all, stop editing/i define( 'WP_REDIS_HOST', '${REDIS_HOST_VALUE}' );" /var/www/html/wp-config.php
    sed -i "/\/\* That's all, stop editing/i define( 'WP_REDIS_PORT', ${REDIS_PORT_VALUE} );" /var/www/html/wp-config.php
    sed -i "/\/\* That's all, stop editing/i define( 'WP_REDIS_DATABASE', ${REDIS_DATABASE_VALUE} );" /var/www/html/wp-config.php

    # Afegir password només si està definit
    if [ ! -z "${REDIS_PASSWORD}" ]; then
        sed -i "/\/\* That's all, stop editing/i define( 'WP_REDIS_PASSWORD', '${REDIS_PASSWORD}' );" /var/www/html/wp-config.php
    fi

    # Activar WP_CACHE per Redis Object Cache
    sed -i "/\/\* That's all, stop editing/i define( 'WP_CACHE', true );" /var/www/html/wp-config.php

    echo "✅ Redis configurat: ${REDIS_HOST_VALUE}:${REDIS_PORT_VALUE} (DB: ${REDIS_DATABASE_VALUE})"
fi

# Arrencar Apache / WordPress
exec /usr/local/bin/docker-entrypoint.sh "$@"
EOF

RUN chmod +x /usr/local/bin/custom-entrypoint.sh

# Exposar port intern del contenidor
EXPOSE 80

# Definir entrypoint i comanda per defecte
ENTRYPOINT ["/usr/local/bin/custom-entrypoint.sh"]
CMD ["apache2-foreground"]
