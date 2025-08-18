FROM wordpress:latest

# Configuració PHP bàsica
RUN echo "memory_limit = 256M" > /usr/local/etc/php/conf.d/custom.ini && \
    echo "upload_max_filesize = 64M" >> /usr/local/etc/php/conf.d/custom.ini && \
    echo "post_max_size = 64M" >> /usr/local/etc/php/conf.d/custom.ini

# Habilitar mod_rewrite
RUN a2enmod rewrite

# Crear directori del tema després que WordPress s'inicialitzi
RUN mkdir -p /usr/src/themes/malet-torrent

# Copiar fitxers del tema
COPY *.php /usr/src/themes/malet-torrent/
COPY style.css /usr/src/themes/malet-torrent/
COPY assets/ /usr/src/themes/malet-torrent/assets/
COPY inc/ /usr/src/themes/malet-torrent/inc/

# Script per copiar el tema després de la inicialització de WordPress
RUN echo '#!/bin/bash' > /usr/local/bin/setup-theme.sh && \
    echo 'cp -r /usr/src/themes/malet-torrent /var/www/html/wp-content/themes/' >> /usr/local/bin/setup-theme.sh && \
    echo 'chown -R www-data:www-data /var/www/html/wp-content/themes/malet-torrent' >> /usr/local/bin/setup-theme.sh && \
    chmod +x /usr/local/bin/setup-theme.sh

# Modificar entrypoint per incloure setup del tema
RUN echo '#!/bin/bash' > /usr/local/bin/custom-entrypoint.sh && \
    echo 'set -euo pipefail' >> /usr/local/bin/custom-entrypoint.sh && \
    echo '/usr/local/bin/setup-theme.sh' >> /usr/local/bin/custom-entrypoint.sh && \
    echo 'exec docker-entrypoint.sh "$@"' >> /usr/local/bin/custom-entrypoint.sh && \
    chmod +x /usr/local/bin/custom-entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/custom-entrypoint.sh"]
CMD ["apache2-foreground"]