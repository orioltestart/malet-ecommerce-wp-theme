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

### 💾 Volums Persistents
**⚠️ IMPORTANT**: Per preservar plugins, uploads i configuració entre desplegaments

#### Volums Docker Compose (Desenvolupament):
- `db_data` - Base de dades MariaDB persistent
- `wp_plugins_data` - Plugins WordPress (Contact Form 7, Flamingo, etc.)
- `wp_uploads_data` - Fitxers multimèdia i uploads
- `wp_content_data` - Contingut general wp-content
- `redis_data` - Cache Redis persistent

#### Plugins Instal·lats Automàticament:

**REQUERITS** (Crítics):
- **WooCommerce** (10.2.1) - Plataforma e-commerce per melindros
- **Contact Form 7** (6.1.1) - Gestió de formularis
- **JWT Authentication** (1.4.0) - Autenticació via tokens JWT

**MOLT RECOMANATS** (Seguretat):
- **Wordfence Security** (8.1.0) - Firewall i protecció malware
- **Limit Login Attempts** (2.26.23) - Protecció força bruta

**RECOMANATS** (Rendiment):
- **WP Super Cache** (3.0.1) - Cache de pàgines
- **Redis Object Cache** (2.6.5) - Cache d'objectes

**FORMULARIS I UTILITATS**:
- **Flamingo** (2.6) - Emmagatzematge de submissions
- **WP Mail SMTP** (4.6.0) - Gestió d'emails via MailHog
- **Duplicate Post** (4.5) - Duplicar contingut

#### Configuració Automàtica Inicial:
- Usuari `orioltestart` amb Application Passwords
- Formulari de contacte bàsic configurat
- `WP_ENVIRONMENT_TYPE` llegit de variables d'entorn
- Tema `malet-torrent` activat automàticament
- **WooCommerce configurat bàsicament** (Arbúcies, EUR, guest checkout)
- **WP Mail SMTP configurat** per MailHog
- **Redis Object Cache** activat automàticament

**Configuració actual**: Volums específics per evitar pèrdua de dades i configuració automàtica completa.

#### Scripts de Gestió:
- `scripts/backup-db.sh` - Backup automàtic de la base de dades
- **Rebuild safe**: Ara es poden eliminar volums sense perdre configuració bàsica

**Instruccions per configurar mounts manuals a Dokploy** (si es necessiten):
1. Accedir al panell de Dokploy de l'aplicació `malet-wp-theme-complete-9mr0ul`
2. Anar a la secció **Mounts**
3. Afegir els següents mounts:
   ```bash
   # Mount per uploads
   Host Path: /var/lib/dokploy/mounts/malet-wp-uploads
   Container Path: /var/www/html/wp-content/uploads
   
   # Mount per plugins
   Host Path: /var/lib/dokploy/mounts/malet-wp-plugins  
   Container Path: /var/www/html/wp-content/plugins
   ```

**✅ Estat actual**: Volums configurats via Dockerfile amb declaracions VOLUME. La persistència de dades queda garantida per Docker.

### Base de Dades
- **Tipus**: MySQL/MariaDB
- **Nom**: MaletWP DB
- **App Name**: malet-wp-db-vmvyjp
- **Host**: malet-wp-db-vmvyjp:3306
- **Database**: malet_torrent
- **User**: malet_user
- **Password**: MaletSecurePass2024!

### Variables d'Entorn

#### 🗄️ Variables de Base de Dades (OBLIGATÒRIES)
```bash
WORDPRESS_DB_HOST=malet-wp-db-vmvyjp:3306
WORDPRESS_DB_NAME=malet_torrent
WORDPRESS_DB_USER=malet_user
WORDPRESS_DB_PASSWORD=MaletSecurePass2024!
WORDPRESS_TABLE_PREFIX=wp_
WORDPRESS_DEBUG=false
```

#### 🔑 Claus de Seguretat WordPress (auto-generades)
```bash
WORDPRESS_AUTH_KEY=5c2d9ef4a7b1e8f3c6d0e9f2a5b8c1d4e7f0a3b6c9d2e5f8
WORDPRESS_SECURE_AUTH_KEY=1a4d7f0c3e6b9d2f5a8c1e4b7f0c3e6b9d2f5a8c1e4b7f0c
# ... (altres claus de seguretat)
```

#### 🚀 Variables d'Instalació WordPress (OPCIONALS - Definides al Dockerfile)
```bash
# Si vols sobreescriure els valors per defecte, afegeix aquestes variables a Dokploy:
WORDPRESS_URL=https://wp2.malet.testart.cat
WORDPRESS_TITLE="Malet Torrent - Pastisseria Artesana"
WORDPRESS_ADMIN_USER=admin
WORDPRESS_ADMIN_PASSWORD="WZd6&F#@d$oAqSW!A)"
WORDPRESS_ADMIN_EMAIL=admin@malet.testart.cat
```

#### 🎨 Variables del Tema (OPCIONALS - Definides al Dockerfile)
```bash
# Si vols sobreescriure els valors per defecte, afegeix aquesta variable a Dokploy:
WORDPRESS_THEME_NAME=malet-torrent
```

#### 🐛 Variables de Debug WordPress (OPCIONALS)
```bash
# Activar mode debug de WordPress (true/false)
WP_DEBUG=false

# Mostrar errors en pantalla - recomanat false en producció (true/false)
WP_DEBUG_DISPLAY=false

# Guardar errors en fitxer wp-content/debug.log (true/false)
WP_DEBUG_LOG=false
```

**Recomanacions per entorn:**
- **Local/Development**: `WP_DEBUG=true`, `WP_DEBUG_DISPLAY=true`, `WP_DEBUG_LOG=true`
- **Staging**: `WP_DEBUG=true`, `WP_DEBUG_DISPLAY=false`, `WP_DEBUG_LOG=true`
- **Production**: `WP_DEBUG=false`, `WP_DEBUG_DISPLAY=false`, `WP_DEBUG_LOG=false`

#### 🛡️ Variables de Rate Limiting Formularis (OPCIONALS)
```bash
# Activar o desactivar el rate limiting dels formularis (true/false)
FORMS_RATE_LIMIT_ENABLED=false

# Màxim nombre de submissions per IP en el període definit (per defecte: 5)
FORMS_RATE_LIMIT_MAX=5

# Període de temps en segons (per defecte: 600 = 10 minuts)
FORMS_RATE_LIMIT_PERIOD=600
```

**Notes:**
- Per defecte, el rate limiting està **desactivat en entorns local/development**
- En producció/staging, si no es defineix `FORMS_RATE_LIMIT_ENABLED`, s'aplica el límit per defecte
- Per desactivar completament: `FORMS_RATE_LIMIT_ENABLED=false`
- Per proves locals sense límits: no cal definir cap variable (o `FORMS_RATE_LIMIT_ENABLED=false`)

#### 🔴 Variables de Redis Object Cache (OPCIONALS)
```bash
# Configuració del servidor Redis
REDIS_HOST=redis                    # Host del servidor Redis (per defecte: redis)
REDIS_PORT=6379                     # Port del servidor Redis (per defecte: 6379)
REDIS_DATABASE=0                    # Base de dades Redis (per defecte: 0)
REDIS_PASSWORD=your_password        # Password Redis (opcional)
```

**Notes:**
- Les constants `WP_REDIS_HOST`, `WP_REDIS_PORT`, `WP_REDIS_DATABASE` i `WP_REDIS_PASSWORD` es configuren automàticament al `wp-config.php` en arrencar el contenidor
- La constant `WP_CACHE` s'activa automàticament quan es defineixen variables Redis
- Compatible amb el plugin **Redis Object Cache**

#### 🌐 Variables de CORS (OPCIONAL)
```bash
# Origins permesos per CORS (separats per comes)
CORS_ALLOWED_ORIGINS=https://malet.testart.cat,https://wp2.malet.testart.cat
```

**Exemples per entorn:**
- **Local**: `CORS_ALLOWED_ORIGINS=http://localhost:3000,http://localhost:3001`
- **Staging**: `CORS_ALLOWED_ORIGINS=https://malet.testart.cat,https://wp2.malet.testart.cat,https://staging.malet.testart.cat`
- **Production**: `CORS_ALLOWED_ORIGINS=https://malet.cat,https://www.malet.cat`

**Notes:**
- Centralitza la configuració CORS per tots els endpoints de l'API
- Si no es defineix, s'usen valors per defecte segons `WP_ENVIRONMENT_TYPE`
- Afecta tant la API general (`inc/api/cors.php`) com la Forms API (`inc/forms-api.php`)
- Els origins es separen per comes (`,`) sense espais

### 🔧 Configuració per Entorns

#### **Docker Compose (Desenvolupament Local)**
1. Copia `.env.example` a `.env`
2. Modifica els valors segons necessitis
3. Executa `docker-compose up -d`

#### **Dokploy (Producció)**
Les variables es defineixen al panell de Dokploy:
1. Accedir a l'aplicació `malet-wp-theme-complete-9mr0ul`
2. Anar a la secció **Environment Variables**
3. Afegir només les variables que vols sobreescriure (les obligatòries són les de DB)

#### **Jerarquia de Variables**
1. **Dokploy Environment Variables** (prioritat alta)
2. **Docker Compose .env** (prioritat mitjana)
3. **Dockerfile ENV defaults** (prioritat baixa)

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

## 🚀 Procés de Desplegament

### 1. Desenvolupament Local amb Docker Compose
```bash
# Clonar el repositori
git clone https://github.com/orioltestart/malet-ecommerce-wp-theme.git
cd malet-ecommerce-wp-theme

# Configurar variables d'entorn
cp .env.example .env
# Editar .env amb els teus valors

# Executar amb Docker Compose
docker-compose up -d

# Accedir a WordPress
# http://localhost:8080
```

### 2. Modificació i Push
```bash
# Modificar fitxers del tema
git add .
git commit -m "Descripció dels canvis"
git push origin main
```

### 3. Desplegament Automàtic (Dokploy)
- El webhook de GitHub activa automàticament el desplegament a Dokploy
- Docker construeix nova imatge amb els canvis
- WordPress es reinicia amb la nova versió del tema

### 4. Verificació
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

# Permisos correctes
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
```

### Ús de WP-CLI
```bash
# Entrar al contenidor (Dokploy no dona accés SSH directe)
# Cal usar el panell de Dokploy o configurar via wp-admin

# Comandos WP-CLI útils
wp theme list --allow-root --path=/var/www/html
wp theme activate malet-torrent --allow-root --path=/var/www/html
wp db export backup.sql --allow-root --path=/var/www/html
wp plugin list --allow-root --path=/var/www/html
wp core version --allow-root --path=/var/www/html
```

## 🔄 Sistema d'Actualitzacions Automàtiques

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
- **Site**: https://wp2.malet.testart.cat/ (producció) / http://localhost:8080 (local)
- **Admin**: https://wp2.malet.testart.cat/wp-admin/ (producció) / http://localhost:8080/wp-admin (local)
- **API**: https://wp2.malet.testart.cat/wp-json/ (producció) / http://localhost:8080/wp-json (local)
- **Frontend**: https://malet.testart.cat/ (Next.js)

### URLs Desenvolupament Local
- **WordPress**: http://localhost:8080
- **phpMyAdmin**: http://localhost:8081
- **MailHog**: http://localhost:8025 (interfície web emails)
- **SMTP MailHog**: localhost:1025 (port SMTP)

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

## ✅ Estat Actual del Projecte

### 🎯 Volums Persistents Implementats (18/08/2025 - 22:12h)

**Status**: ✅ COMPLET I OPERATIU

#### Funcionalitats implementades:
1. **Volums Docker configurats**:
   - `VOLUME ["/var/www/html/wp-content/uploads", "/var/www/html/wp-content/plugins"]`
   - Gestió automàtica per Docker sense configuració adicional

2. **Preservació de dades**:
   - ✅ Plugins instal·lats es mantenen entre desplegaments
   - ✅ Fitxers d'upload (imatges, documents) persistents
   - ✅ Configuració WordPress i base de dades preservada

3. **Desplegament estable**:
   - WordPress operatiu a https://wp2.malet.testart.cat/
   - Tema `malet-torrent` actiu i funcional
   - Auto-deploy GitHub → Dokploy operatiu

#### Commit actual:
- **Hash**: `bc0f419b4198d5d7390167645f20413753c79147`
- **Missatge**: "Simplificar volums persistents per evitar errors de desplegament"
- **Status**: `done` ✅

### Desplegament Resolt (18 d'agost 2025)
- **Status**: ✅ FUNCIONANT
- **URL**: https://wp2.malet.testart.cat/
- **Dockerfile**: Simplificat amb WP-CLI instal·lat
- **Desplegament**: Automàtic via GitHub webhook
- **Theme**: malet-torrent actiu i funcional

## 📝 API de Formularis (Forms API)

### Endpoints Disponibles
- `GET /malet-torrent/v1/forms` - Llistar formularis
- `GET /malet-torrent/v1/forms/{id}` - Obtenir formulari específic
- `POST /malet-torrent/v1/forms/submit` - Enviar formulari
- `GET /malet-torrent/v1/forms/submissions` - Obtenir submissions (admin)

### Credencials API
- **Usuari**: `orioltestart`
- **Password**: `Arbucies8`
- **Application Password Frontend**: `tlgEkZt6z6wHkB29q8E3nuy8`
- **Application Password Formularis**: `wGXHbXdlGh81QXBQFcXa6YW2`

### Configuració
- **WP_ENVIRONMENT_TYPE**: `local`
- **APPLICATION_PASSWORDS_ENABLED**: `true`
- **Plugins**: Contact Form 7 6.1.1, Flamingo 2.6, WP Mail SMTP 4.6.0
- **Email SMTP**: Configurat per MailHog (localhost:1025)
- **Redis Cache**: Configurable per variables d'entorn
- **Documentació completa**: `FORMS_API_DOCUMENTATION.md`

### Variables d'Entorn Redis
Configuració flexible del servidor Redis per Object Cache:
```bash
REDIS_HOST=redis                    # Host del servidor Redis (defecte: redis)
REDIS_PORT=6379                     # Port del servidor Redis (defecte: 6379)
REDIS_DATABASE=0                    # Base de dades Redis (defecte: 0)
REDIS_PASSWORD=your_password        # Password Redis
REDIS_URL=redis://user:pass@host:port/db  # URL completa (opcional, sobreescriu altres)
```

**Exemples d'ús:**
- **Desenvolupament local**: Usar valors per defecte
- **Producció**: `REDIS_URL=redis://username:password@redis-server:6379/0`
- **Redis Cloud**: `REDIS_URL=rediss://user:pass@endpoint:port/db`

### Pendents
- [ ] Configurar constants GitHub per actualitzacions automàtiques
- [ ] Verificar sistema d'actualitzacions del tema
- [ ] Configurar backup automàtic de base de dades

## 🔄 Sistema de Webhooks per Next.js Cache Revalidation

### Descripció

Sistema automàtic de webhooks implementat amb PHP custom (100% gratuït) per invalidar la caché de Next.js quan hi ha canvis en productes, categories o posts de WordPress.

### Funcionalitats

✅ **Webhooks Automàtics**:
- **Productes WooCommerce**: crear, actualitzar, eliminar, canvis d'stock
- **Categories de productes**: crear, editar, eliminar
- **Blog posts**: publicar, actualitzar, eliminar

✅ **Dashboard Widget**:
- Mostra l'estat de configuració al WordPress Dashboard
- Verifica si `REVALIDATE_SECRET` està definit
- Avís visual si falta configuració

✅ **Botó de Test**:
- Botó "🔄 Test Cache Revalidation" al WordPress Admin Bar
- Envia webhook de prova amb un producte aleatori
- Mostra missatge de confirmació/error

✅ **Logs Detallats**:
- Registra tots els webhooks enviats
- Indica success/error amb emojis
- Compatible amb `WP_DEBUG_LOG`

### Configuració Requerida

**Pas 1: Generar Secret Token**
```bash
openssl rand -base64 32
```

**Pas 2: Configurar WordPress (wp-config.php)**
```php
// Next.js Cache Revalidation
define('REVALIDATE_SECRET', 'el_teu_token_aqui');
define('NEXTJS_REVALIDATE_URL', 'https://malet.cat/api/revalidate');
```

**Pas 3: Configurar Next.js (.env.production)**
```bash
REVALIDATE_SECRET=el_teu_token_aqui
```

### Fitxers Implementats

- **`inc/webhook-functions.php`** - Sistema complet de webhooks
- **`functions.php`** - Ja inclou el fitxer de webhooks (línia 92)
- **`WEBHOOKS_CONFIGURATION.md`** - Documentació completa amb troubleshooting

### Verificació

Després de configurar, vés al WordPress Dashboard i busca:
- Widget **"🔄 Next.js Cache Revalidation Status"**
- Si veus **✅ Configured**, tot està correcte!

### Documentació Completa

Consulta [WEBHOOKS_CONFIGURATION.md](WEBHOOKS_CONFIGURATION.md) per:
- Instruccions detallades d'instal·lació
- Exemples de test amb cURL
- Troubleshooting d'errors comuns
- Best practices de seguretat

### Flux de Treball

```
WordPress Admin (editar producte)
    ↓
Webhook Trigger automàtic
    ↓
POST https://malet.cat/api/revalidate
    ↓
Next.js Revalidation API
    ↓
Cache Invalidated ✅
```

---

*Documentació actualitzada: 16 de gener de 2025*
*Generat amb Claude Code*
*Estat: Forms API i Webhooks implementats completament ✅*