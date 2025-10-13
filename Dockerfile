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

# Ajustar permisos
RUN chown -R www-data:www-data /var/www/html && chmod -R 755 /var/www/html

# Script per fixar permisos dels volums (s’executa a cada arrencada)
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
# Fixar permisos abans d’arrencar
/usr/local/bin/fix-volume-permissions.sh
# Arrencar Apache / WordPress
exec /usr/local/bin/docker-entrypoint.sh "$@"
EOF

RUN chmod +x /usr/local/bin/custom-entrypoint.sh

# Indicar port intern exposat
EXPOSE 8082

# Definir entrypoint i comanda per defecte
ENTRYPOINT ["/usr/local/bin/custom-entrypoint.sh"]
CMD ["apache2-foreground"]
