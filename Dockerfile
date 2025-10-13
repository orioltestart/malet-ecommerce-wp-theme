FROM wordpress:latest

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

# Copiar tema personalitzat
RUN mkdir -p /var/www/html/wp-content/themes/malet-torrent
COPY . /var/www/html/wp-content/themes/malet-torrent

# Permisos
RUN chown -R www-data:www-data /var/www/html && chmod -R 755 /var/www/html

# Script per fixar permisos (executat a cada arrencada)
RUN cat > /usr/local/bin/fix-volume-permissions.sh << 'EOF'
#!/bin/bash
set -e
echo "🔧 Configurant permisos dels volums..."
mkdir -p /var/www/html/wp-content/{plugins,uploads,upgrade,cache}
chown -R www-data:www-data /var/www/html/wp-content
chmod -R 775 /var/www/html/wp-content
EOF

RUN chmod +x /usr/local/bin/fix-volume-permissions.sh

# Wrapper d'entrypoint
RUN cat > /usr/local/bin/custom-entrypoint.sh << 'EOF'
#!/bin/bash
set -e
/usr/local/bin/fix-volume-permissions.sh
exec /usr/local/bin/docker-entrypoint.sh "$@"
EOF

RUN chmod +x /usr/local/bin/custom-entrypoint.sh

ENTRYPOINT ["/usr/local/bin/custom-entrypoint.sh"]
CMD ["apache2-foreground"]
