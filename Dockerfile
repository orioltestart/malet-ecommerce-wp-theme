FROM wordpress:latest

# Instal·lar dependències mínimes
RUN apt-get update && apt-get install -y \
    curl \
    default-mysql-client \
    less \
    nano \
    && rm -rf /var/lib/apt/lists/*

# Configuració PHP optimitzada
RUN { \
    echo "memory_limit = 256M"; \
    echo "upload_max_filesize = 64M"; \
    echo "post_max_size = 64M"; \
    echo "max_execution_time = 300"; \
} > /usr/local/etc/php/conf.d/custom.ini

# Habilitar mod_rewrite per als permalinks
RUN a2enmod rewrite

# Copiar el tema personalitzat
RUN mkdir -p /var/www/html/wp-content/themes/malet-torrent
COPY *.php /var/www/html/wp-content/themes/malet-torrent/
COPY style.css /var/www/html/wp-content/themes/malet-torrent/
COPY assets/ /var/www/html/wp-content/themes/malet-torrent/assets/
COPY inc/ /var/www/html/wp-content/themes/malet-torrent/inc/

# Ajustar permisos
RUN chown -R www-data:www-data /var/www/html && chmod -R 755 /var/www/html

# Script per fixar permisos dels volums (s’executa en l’inici)
RUN cat > /usr/local/bin/fix-volume-permissions.sh << 'EOF'
#!/bin/bash
set -e
echo "🔧 Configurant permisos dels volums persistents..."
mkdir -p /var/www/html/wp-content/{plugins,uploads,upgrade,cache}
chown -R www-data:www-data /var/www/html/wp-content
chmod -R 775 /var/www/html/wp-content
echo "✅ Permisos configurats correctament"
EOF

RUN chmod +x /usr/local/bin/fix-volume-permissions.sh

# Wrapper de l'entrypoint oficial (afegeix la fixació de permisos)
RUN cat > /usr/local/bin/docker-entrypoint-with-permissions.sh << 'EOF'
#!/bin/bash
set -e

# Fixar permisos dels volums abans d’arrencar
/usr/local/bin/fix-volume-permissions.sh

# Executar l'entrypoint original amb els paràmetres rebuts
exec docker-entrypoint.sh "$@"
EOF

RUN chmod +x /usr/local/bin/docker-entrypoint-with-permissions.sh

# Utilitzar el wrapper com a entrypoint
ENTRYPOINT ["/usr/local/bin/docker-entrypoint-with-permissions.sh"]

# Arrencar Apache (el CMD és el mateix que a la imatge base)
CMD ["apache2-foreground"]
