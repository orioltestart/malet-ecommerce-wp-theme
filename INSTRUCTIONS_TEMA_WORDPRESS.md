# 📦 Instal·lació del Tema Malet Torrent per WordPress

## 🎯 Què inclou aquest ZIP

Has generat el tema **Malet Torrent Headless Theme** (fitxer: `malet-torrent-headless-theme.zip`) que inclou:

### 📁 Fitxers del Tema
- `style.css` - Informació del tema i estils admin
- `index.php` - Pàgina principal headless amb dashboard informatiu
- `functions.php` - API millorada, endpoints personalitzats i configuració
- `header.php` / `footer.php` - Templates HTML mínims
- `single.php` / `archive.php` / `404.php` - Templates per contingut
- `README.md` - Documentació completa del tema

### 🚀 Funcionalitats
- **API REST millorada** amb CORS configurat
- **Endpoints personalitzats** per Malet Torrent
- **Integració WooCommerce** amb camps adicionals
- **Dashboard admin** amb informació d'estat
- **Configuració automàtica** de permalinks i opcions

## 📋 Instruccions d'Instal·lació

### 1️⃣ **Pujar el Tema a WordPress**

1. **Accedeix al teu WordPress** a `wp.malet.testart.cat/wp-admin`
2. **Navega a**: Aparença > Temes
3. **Clica**: "Afegir nou"
4. **Clica**: "Pujar tema"
5. **Selecciona**: El fitxer `malet-torrent-headless-theme.zip`
6. **Clica**: "Instal·lar ara"

### 2️⃣ **Activar el Tema**

1. **Després de la instal·lació**, clica "Activar"
2. **Apareixerà un missatge** de confirmació
3. **Navega a**: Aparença > Malet Torrent (nova opció de menú)

### 3️⃣ **Configurar WooCommerce** (Si no ho has fet)

1. **Instal·la WooCommerce**: Plugins > Afegir nou > Cercar "WooCommerce"
2. **Activa WooCommerce** i segueix l'assistent
3. **Configura la botiga** amb la informació bàsica

### 4️⃣ **Generar Claus API de WooCommerce**

1. **Navega a**: WooCommerce > Configuració > Avançat > API REST
2. **Clica**: "Afegir clau"
3. **Omple**:
   - Descripció: `Malet Torrent Next.js App`
   - Usuari: Selecciona un administrador
   - Permisos: `Lectura/Escriptura`
4. **Clica**: "Generar clau API"
5. **COPIA LES CLAUS** (Consumer Key i Consumer Secret)

### 5️⃣ **Configurar Variables d'Entorn a Next.js**

Actualitza el fitxer `.env.production` amb les claus generades:

```bash
# Claus generades a l'apartat anterior
WOOCOMMERCE_CONSUMER_KEY=ck_1234567890abcdef...
WOOCOMMERCE_CONSUMER_SECRET=cs_abcdef1234567890...
```

## ✅ Verificació de la Instal·lació

### **Dashboard del Tema**
1. **Navega a**: Aparença > Malet Torrent
2. **Verifica** que veus:
   - ✅ API REST accessible
   - ✅ WooCommerce actiu
   - ✅ Endpoints disponibles

### **API Endpoints Actius**
Aquests endpoints estaran disponibles:

- `/wp-json/wp/v2/` - WordPress API estàndard
- `/wp-json/wc/v3/` - WooCommerce API completa
- `/wp-json/wc/store/v1/` - Store API per carret
- `/wp-json/maletnext/v1/config` - Configuració del lloc
- `/wp-json/maletnext/v1/products/featured` - Productes destacats

### **Testejar la Connexió**
Pots testejar l'API visitant:
```
https://wp.malet.testart.cat/wp-json/maletnext/v1/config
```

Hauries de veure un JSON amb la configuració del lloc.

## 🛍️ Gestió de Productes

### **Crear Productes de Melindros**
1. **Navega a**: Productes > Afegir nou
2. **Omple la informació**:
   - Nom: Ex. "Melindros Tradicionals"
   - Descripció: Descripció del producte
   - Preu: Preu en euros
   - Imatges: Afegir imatges del producte
3. **Categories**: Crea categories com "Tradicionals", "Artesans", "Premium"
4. **Producte destacat**: Marca si vols que aparegui a la pàgina principal

## 🔧 Configuració Avançada

### **Permalinks**
El tema configura automàticament els permalinks a `/%postname%/`

### **CORS**
El tema configura automàticament CORS per permetre peticions des de:
- `https://malet.testart.cat` (producció)
- `http://localhost:3000` (desenvolupament)

### **Cache**
Si tens Redis instal·lat, es detectarà automàticament.

## 🆘 Resolució de Problemes

### **❌ API no accessible**
- Verifica que els permalinks estiguin activats
- Comprova que no hi hagi plugins que bloquegin l'API REST

### **❌ WooCommerce no detectat**
- Assegura't que WooCommerce està instal·lat i activat
- Actualitza WooCommerce a la versió més recent

### **❌ Errors de CORS**
- El tema configura CORS automàticament
- Si tens problemes, comprova que no hi hagi altres plugins que interfereixin

### **❌ Claus API no funcionen**
- Regenera les claus API a WooCommerce > Configuració > Avançat > API REST
- Assegura't que l'usuari té permisos d'administrador

## 📞 Suport

Si tens problemes amb la instal·lació:

1. **Comprova** el dashboard del tema a Aparença > Malet Torrent
2. **Verifica** els logs d'error del WordPress
3. **Testeja** els endpoints API directament al navegador

---

## 🎉 **Tema Instal·lat amb Èxit!**

Un cop completats aquests passos, el teu WordPress funcionarà com a backend headless perfecte per a l'aplicació Next.js de Malet Torrent.

El tema inclou un dashboard informatiu que et permet veure l'estat de tots els serveis i enllaços ràpids per gestionar el contingut.

**🥨 Malet Torrent - Pastisseria Tradicional Catalana**