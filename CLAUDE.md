# Documentació Malet Torrent WordPress Theme

## 📝 Context del Projecte

Aquest és un tema de WordPress customitzat per **Malet Torrent**, una pastisseria tradicional catalana especialitzada en melindros artesans. El projecte inclou:

- **Tema WordPress personalitzat** amb funcionalitats headless
- **Sistema d'actualitzacions automàtiques** via GitHub
- **Desplegament automàtic** amb Docker i Dokploy
- **Integració amb Next.js** per al frontend

## 🏗️ Arquitectura del Sistema

```
GitHub Repository (malet-ecommerce-wp-theme)
    ↓ (webhook automàtic)
Dokploy Deploy System
    ↓
WordPress Container + MariaDB + Redis
    ↓ (API REST)
Next.js Frontend (malet.testart.cat)
```

## 🔧 Configuració Dokploy

### Aplicació Principal
- **Nom**: Malet WP Complete
- **App Name**: malet-wp-theme-complete-9mr0ul
- **URL**: https://wp2.malet.testart.cat/
- **Repository**: git@github.com:orioltestart/malet-ecommerce-wp-theme.git
- **Branch**: main
- **Auto Deploy**: ✅ Activat

### Base de Dades
- **Tipus**: MySQL/MariaDB
- **Nom**: MaletWP DB
- **App Name**: malet-wp-db-vmvyjp
- **Host**: malet-wp-db-vmvyjp:3306
- **Database**: malet_torrent
- **User**: malet_user
- **Password**: MaletSecurePass2024!

### Variables d'Entorn
```bash
WORDPRESS_DB_HOST=malet-wp-db-vmvyjp:3306
WORDPRESS_DB_NAME=malet_torrent
WORDPRESS_DB_USER=malet_user
WORDPRESS_DB_PASSWORD=MaletSecurePass2024!

# Security Keys (auto-generades)
WORDPRESS_AUTH_KEY=5c2d9ef4a7b1e8f3c6d0e9f2a5b8c1d4e7f0a3b6c9d2e5f8
WORDPRESS_SECURE_AUTH_KEY=1a4d7f0c3e6b9d2f5a8c1e4b7f0c3e6b9d2f5a8c1e4b7f0c
# ... (altres claus de seguretat)

WORDPRESS_TABLE_PREFIX=wp_
WORDPRESS_DEBUG=false
```

## 🔑 Credencials d'Accés

### WordPress Admin
- **URL**: https://wp2.malet.testart.cat/wp-admin/
- **Usuari**: admin
- **Password**: WZd6&F#@d$oAqSW!A) *(auto-generada durant instal·lació manual)*
- **Email**: admin@malet.testart.cat

### Base de Dades
- **Host**: malet-wp-db-vmvyjp:3306
- **Database**: malet_torrent
- **User**: malet_user
- **Password**: MaletSecurePass2024!
- **Root Password**: RootSecurePass2024!

## 📦 Components del Tema

### Fitxers Principals
- `style.css` - Estils principals del tema
- `functions.php` - Funcionalitats i hooks de WordPress
- `index.php`, `header.php`, `footer.php` - Templates base
- `single.php`, `archive.php`, `404.php` - Templates específics

### Directoris
- `assets/` - CSS, JS, imatges i fonts
- `inc/` - Funcionalitats modulars del tema
- `updater/` - Sistema d'actualitzacions automàtiques via GitHub

### Sistema d'Actualitzacions
- `updater/class-theme-updater.php` - Classe principal per actualitzacions
- Integració amb GitHub API per detectar noves versions
- Actualitzacions automàtiques via WordPress admin

## 🚀 Procés de Desplegament

### 1. Desenvolupament Local
```bash
# Modificar fitxers del tema
git add .
git commit -m "Descripció dels canvis"
git push origin main
```

### 2. Desplegament Automàtic
- El webhook de GitHub activa automàticament el desplegament a Dokploy
- Docker construeix nova imatge amb els canvis
- WordPress es reinicia amb la nova versió del tema

### 3. Verificació
- Comprovar https://wp2.malet.testart.cat/
- Verificar que el tema s'ha actualitzat correctament
- Revisar logs de desplegament a Dokploy si hi ha errors

## 🐳 Dockerfile

### Configuració Actual (Estable amb WP-CLI)
```dockerfile
FROM wordpress:latest

# Instal·lar dependències bàsiques i WP-CLI
RUN apt-get update && apt-get install -y \
    curl \
    default-mysql-client \
    less \
    nano \
    && rm -rf /var/lib/apt/lists/*

# Configuració PHP optimitzada
RUN echo "memory_limit = 256M" > /usr/local/etc/php/conf.d/custom.ini && \
    echo "upload_max_filesize = 64M" >> /usr/local/etc/php/conf.d/custom.ini && \
    echo "post_max_size = 64M" >> /usr/local/etc/php/conf.d/custom.ini && \
    echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/custom.ini

# Habilitar mod_rewrite per permalinks
RUN a2enmod rewrite

# Instal·lar WP-CLI (versió estable)
RUN curl -O https://raw.githubusercontent.com/wp-cli/wp-cli/v2.12.0/wp-cli.phar && \
    chmod +x wp-cli.phar && \
    mv wp-cli.phar /usr/local/bin/wp && \
    wp --info --allow-root

# Copiar tema
RUN mkdir -p /var/www/html/wp-content/themes/malet-torrent
COPY *.php /var/www/html/wp-content/themes/malet-torrent/
COPY style.css /var/www/html/wp-content/themes/malet-torrent/
COPY assets/ /var/www/html/wp-content/themes/malet-torrent/assets/
COPY inc/ /var/www/html/wp-content/themes/malet-torrent/inc/
COPY updater/ /var/www/html/wp-content/themes/malet-torrent/updater/

# Script opcional per configurar constants GitHub
RUN cat > /usr/local/bin/setup-github-constants.sh << 'EOF'
#!/bin/bash
# Execució manual: docker exec -it container_name /usr/local/bin/setup-github-constants.sh
if [ -f /var/www/html/wp-config.php ] && wp core is-installed --allow-root --path=/var/www/html 2>/dev/null; then
    echo "Configurant constants GitHub..."
    wp config set MALET_TORRENT_GITHUB_USER "orioltestart" --allow-root --path=/var/www/html
    wp config set MALET_TORRENT_GITHUB_REPO "malet-ecommerce-wp-theme" --allow-root --path=/var/www/html
    wp config set MALET_TORRENT_UPDATE_CHECK_INTERVAL 21600 --raw --allow-root --path=/var/www/html
    wp config set MALET_TORRENT_ALLOW_PRERELEASES false --raw --allow-root --path=/var/www/html
    echo "Constants GitHub configurades!"
fi
EOF

# Permisos correctes
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
```

### Ús de WP-CLI
```bash
# Entrar al contenidor
docker exec -it malet-wp-theme-complete-9mr0ul bash

# Configurar constants GitHub
/usr/local/bin/setup-github-constants.sh

# Comandos WP-CLI útils
wp theme list --allow-root --path=/var/www/html
wp theme activate malet-torrent --allow-root --path=/var/www/html
wp db export backup.sql --allow-root --path=/var/www/html
```

## 🔄 Sistema d'Actualitzacions Automàtiques

### Constants GitHub (wp-config.php)
```php
define('MALET_TORRENT_GITHUB_USER', 'orioltestart');
define('MALET_TORRENT_GITHUB_REPO', 'malet-ecommerce-wp-theme');
define('MALET_TORRENT_UPDATE_CHECK_INTERVAL', 21600); // 6 hores
define('MALET_TORRENT_ALLOW_PRERELEASES', false);
```

### Funcionalitats
- Comprovació automàtica d'actualitzacions cada 6 hores
- Notificacions d'actualització al WordPress admin
- Actualitzacions amb un clic des del panell d'administració
- Backups automàtics abans d'actualitzar

## 🚨 Errors Comuns i Solucions

### 1. Error 500 - Servidor
- **Causa**: Problema amb la base de dades o tema
- **Solució**: Revisar logs de Dokploy i verificar connexió DB

### 2. Deploy Fallit a Dokploy
- **Causa**: Scripts WP-CLI complexos o entrypoints modificats
- **Solució**: Usar Dockerfile simple amb entrypoint estàndard
- **Verificació**: Usar MCP Dokploy per comprovar status
```bash
# Revisar estat aplicació
mcp__dokploy-mcp__application-one applicationId=kGk31yQBQef0E9VsMaoxx

# Redesplegar si cal
mcp__dokploy-mcp__application-redeploy applicationId=kGk31yQBQef0E9VsMaoxx
```

### 3. Tema No Activat
- **Causa**: Fitxers del tema no copiats correctament
- **Solució**: Verificar que tots els directoris es copien al Dockerfile
- **Fix**: Executar manualment `wp theme activate malet-torrent --allow-root`

### 4. Constants GitHub No Configurades
- **Causa**: WP-CLI no pot accedir a wp-config.php durant build
- **Solució**: Executar script manual després del desplegament
```bash
docker exec -it malet-wp-theme-complete-9mr0ul /usr/local/bin/setup-github-constants.sh
```

### 5. Desplegaments Consecutius Fallits
- **Problema**: Commits ràpids causen múltiples deploys solapats
- **Solució**: Esperar que acabi un deploy abans del següent commit
- **Monitorització**: Usar Dokploy MCP per veure estat en temps real

## 📊 Monitorització

### URLs Importants
- **Site**: https://wp2.malet.testart.cat/
- **Admin**: https://wp2.malet.testart.cat/wp-admin/
- **API**: https://wp2.malet.testart.cat/wp-json/
- **Frontend**: https://malet.testart.cat/ (Next.js)

### Logs de Dokploy
- Path: `/etc/dokploy/logs/malet-wp-theme-complete-9mr0ul/`
- Format: `malet-wp-theme-complete-9mr0ul-YYYY-MM-DD:HH:mm:ss.log`

## 📚 Referències

- [WordPress Codex](https://codex.wordpress.org/)
- [WP-CLI Documentation](https://wp-cli.org/)
- [Dokploy Documentation](https://dokploy.com/docs)
- [Docker WordPress Image](https://hub.docker.com/_/wordpress)

## 🔒 Seguretat

### Fitxers Protegits
- `CREDENTIALS.md` - No es puja a git (gitignore)
- `wp-config.php` - Generat automàticament amb claus segures
- Passwords de base de dades - Emmagatzemats a variables d'entorn

### Bones Pràctiques
- Passwords complexos auto-generats
- HTTPS activat amb Let's Encrypt
- Accés restringit a base de dades
- Backups automàtics abans d'actualitzacions

---

*Documentació actualitzada: 18 d'agost de 2025*
*Generat amb Claude Code*