# Malet Torrent WordPress Theme

Tema personalitzat per a **Malet Torrent - Pastisseria Tradicional Catalana**, optimitzat per funcionar com a backend headless amb Next.js.

## 🔌 Sistema d'Instal·lació Automàtica de Plugins

Aquest tema inclou un sistema avançat que detecta i instal·la automàticament tots els plugins necessaris per al funcionament òptim de la botiga de melindros.

### Plugins Inclosos:
- **Requerits**: WooCommerce, Contact Form 7
- **Seguretat**: Wordfence, UpdraftPlus, Limit Login Attempts
- **Rendiment**: Redis Cache, Autoptimize, WP Super Cache
- **SEO**: Rank Math SEO
- **Experimentals**: WordPress MCP Server

### Funcionalitats del Sistema:
- ✅ **Instal·lació amb un clic** de tots els plugins essencials
- ✅ **Activació automàtica** dels plugins crítics
- ✅ **Configuració post-activació** automàtica
- ✅ **Avisos intel·ligents** a l'admin dashboard
- ✅ **Progrés visual** amb barres d'estat
- ✅ **Compatible 100%** amb WordPress.org

## 🎯 Característiques

### 🚀 Optimització Headless
- **API REST millorada** amb endpoints personalitzats
- **CORS configurat** per peticions des de Next.js
- **Suport WooCommerce** amb camps adicionals per l'API
- **Endpoints personalitzats** per configuració i menús

### 🛍️ E-commerce
- **Integració WooCommerce** completa
- **Productes destacats** amb endpoint específic
- **Informació de stock** detallada
- **Categories amb metadades** adicionals

### 🌐 API Endpoints

#### WordPress Estàndard
- `/wp-json/wp/v2/` - Posts, pàgines, media
- `/wp-json/wc/v3/` - WooCommerce API completa
- `/wp-json/wc/store/v1/` - Store API per carret

#### Malet Torrent Personalitzats
- `/wp-json/malet-torrent/v1/config` - Configuració del lloc
- `/wp-json/malet-torrent/v1/menus/{location}` - Menús per ubicació
- `/wp-json/malet-torrent/v1/products/featured` - Productes destacats
- `/wp-json/malet-torrent/v1/woocommerce/config` - Configuració WooCommerce

### 🎨 Interfície Admin
- **Pàgina de configuració** específica del tema
- **Dashboard informatiu** amb estat de l'API
- **Enllaços ràpids** a funcions principals
- **Avisos d'activació** i configuració

## 📦 Instal·lació

1. **Descarrega** el fitxer ZIP del tema
2. **Pujar al WordPress**: Aparença > Temes > Afegir nou > Pujar tema
3. **Activar** el tema Malet Torrent
4. **Configurar**: Aparença > Malet Torrent per accedir a la configuració

## ⚙️ Configuració

### Requisits
- WordPress 5.0+
- WooCommerce 5.0+ (per e-commerce)
- PHP 7.4+
- Permalinks activats

### Configuració Recomanada
1. **Permalinks**: Configuració > Enllaços permanents > Nom de l'entrada
2. **WooCommerce**: Instal·lar i configurar WooCommerce
3. **API Keys**: Generar claus API per Next.js
4. **CORS**: Automàticament configurat pel tema

## 🔧 Desenvolupament

### Estructura del Tema
```
malet-torrent/
├── style.css          # Informació del tema + estils admin
├── index.php          # Pàgina principal headless
├── functions.php      # Funcions principals i API
├── header.php         # Header HTML mínim
├── footer.php         # Footer HTML mínim
├── single.php         # Template per articles individuals
├── archive.php        # Template per arxius
├── 404.php           # Pàgina d'error personalitzada
└── README.md         # Aquesta documentació
```

### Funcions Principals

#### `malet-torrent_enhance_woocommerce_api()`
Afegeix camps adicionals a l'API de WooCommerce:
- ACF fields
- Informació de stock detallada
- Categories amb metadades

#### `malet-torrent_register_custom_endpoints()`
Registra endpoints personalitzats per:
- Configuració del lloc
- Menús de navegació
- Productes destacats
- Configuració WooCommerce

#### `malet-torrent_add_cors_support()`
Configura CORS per permetre peticions des de:
- malet.testart.cat (producció)
- localhost:3000 (desenvolupament)

## 🌐 Integració amb Next.js

### Variables d'Entorn Necessàries
```bash
# WordPress API
WORDPRESS_URL=https://wp.malet.testart.cat
NEXT_PUBLIC_WORDPRESS_URL=https://wp.malet.testart.cat
NEXT_PUBLIC_API_URL=https://wp.malet.testart.cat/wp-json

# WooCommerce API
WOOCOMMERCE_CONSUMER_KEY=ck_...
WOOCOMMERCE_CONSUMER_SECRET=cs_...
```

### Exemples d'Ús

#### Obtenir Configuració del Lloc
```javascript
const config = await fetch('/wp-json/malet-torrent/v1/config')
  .then(res => res.json());
```

#### Obtenir Productes Destacats
```javascript
const featured = await fetch('/wp-json/malet-torrent/v1/products/featured?per_page=8')
  .then(res => res.json());
```

#### Obtenir Menú Principal
```javascript
const menu = await fetch('/wp-json/malet-torrent/v1/menus/primary')
  .then(res => res.json());
```

## 🔒 Seguretat

- **CORS configurat** per dominis específics
- **Validació d'entrada** en endpoints personalitzats
- **Permisos adequats** per cada endpoint
- **Headers de seguretat** configurats

## 📞 Suport

- **Tema desenvolupat per**: Malet Torrent
- **Versió**: 1.0.0
- **Compatibilitat**: WordPress 5.0+, WooCommerce 5.0+
- **Llicència**: GPL v2 or later

## 🚀 Desplegament

1. **Desenvolupament Local**: Utilitzar amb docker-compose.local.yml
2. **Staging**: Configurar amb subdomini de prova
3. **Producció**: Desplegar a wp.malet.testart.cat

### Checklist de Desplegament
- [ ] WordPress instal·lat i configurat
- [ ] WooCommerce instal·lat
- [ ] Tema Malet Torrent activat
- [ ] Claus API generades
- [ ] SSL configurat
- [ ] Permalinks activats
- [ ] Productes de mostra creats
- [ ] Next.js configurat i connectat

---

**🥨 Fet amb amor per la pastisseria tradicional catalana**