# 🔌 Guia del Sistema d'Instal·lació Automàtica de Plugins

## Visió General

El tema **Malet Torrent** inclou un sistema avançat d'instal·lació automàtica de plugins que configura automàticament tots els components necessaris per a una botiga en línia professional de melindros.

## 🎯 Objectius del Sistema

1. **Simplificar la configuració inicial** del WordPress
2. **Garantir la seguretat** des del primer moment
3. **Optimitzar el rendiment** automàticament
4. **Proporcionar funcionalitat completa** de e-commerce
5. **Experiència d'usuari professional** sense complexitat tècnica

## 📦 Categories de Plugins

### **REQUERITS** (Crítics per al funcionament)

#### WooCommerce
- **Funcionalitat**: Plataforma d'e-commerce completa
- **Per què és essencial**: Gestió de productes, carret, pagaments, comandes
- **Configuració automàtica**: Moneda EUR, zona Europa/Madrid

#### Contact Form 7
- **Funcionalitat**: Formularis de contacte flexibles
- **Per què és essencial**: Comunicació directa amb clients
- **Configuració automàtica**: Formulari de contacte bàsic preconfgurat

### **MOLT RECOMANATS** (Seguretat i Backup)

#### Wordfence Security
- **Funcionalitat**: Firewall, escàner malware, protecció atacs
- **Beneficis**: Protecció en temps real, base de dades vulnerabilitats
- **Configuració automàtica**: Actualitzacions, alertes email activades

#### UpdraftPlus
- **Funcionalitat**: Backup automàtic i restauració
- **Beneficis**: Protecció de dades, emmagatzematge núvol
- **Configuració automàtica**: Backup setmanal automàtic

#### Limit Login Attempts Reloaded
- **Funcionalitat**: Protecció força bruta
- **Beneficis**: Bloqueig IPs malicioses, logs seguretat
- **Configuració automàtica**: 3 intents màxim, bloqueig 20 minuts

### **RECOMANATS** (Rendiment)

#### Redis Object Cache
- **Funcionalitat**: Cache d'objectes amb Redis
- **Beneficis**: Reducció consultes DB, millor rendiment
- **Configuració automàtica**: Activació si Redis disponible

#### Autoptimize
- **Funcionalitat**: Optimització CSS, JavaScript, HTML
- **Beneficis**: Minificació, combinació fitxers, cache navegador
- **Configuració automàtica**: Optimització conservadora

#### WP Super Cache
- **Funcionalitat**: Cache de pàgines estàtiques
- **Beneficis**: Acceleració lloc web, reducció càrrega servidor
- **Configuració automàtica**: Cache activat amb configuració òptima

### **OPCIONALS** (SEO i Utilitats)

#### Rank Math SEO
- **Funcionalitat**: Optimització SEO completa
- **Beneficis**: Schema markup, anàlisi contingut, integració xarxes socials
- **Configuració automàtica**: País Espanya, configuració pastisseria

#### WP Mail SMTP
- **Funcionalitat**: Enviament emails via SMTP
- **Beneficis**: Millor entregabilitat emails
- **Configuració automàtica**: Configuració bàsica

### **EXPERIMENTALS** (Tecnologies noves)

#### WordPress MCP Server
- **Funcionalitat**: Model Context Protocol per IA
- **Beneficis**: Integració amb sistemes IA, automatització
- **Font**: GitHub (Automattic/wordpress-mcp)

## 🚀 Flux d'Instal·lació

### 1. Activació del Tema
```
1. Usuari activa el tema Malet Torrent
2. Sistema detecta plugins faltants automàticament
3. Es mostra avís elegant amb llista de plugins
```

### 2. Detecció Intel·ligent
```
✅ Verifica plugins ja instal·lats
✅ Detecta plugins actius/inactius
✅ Comprova versions mínimes
✅ Avalua compatibilitat
```

### 3. Instal·lació Guiada
```
🔄 Opció 1: Instal·lació individual amb un clic
🔄 Opció 2: Instal·lació en lot (tots els requerits)
🔄 Opció 3: Instal·lació selectiva per categoria
```

### 4. Configuració Post-Activació
```
⚙️ Wordfence: Configuració bàsica seguretat
⚙️ Contact Form 7: Formulari contacte predeterminat
⚙️ Redis Cache: Activació si Redis disponible
⚙️ Rank Math: Configuració pastisseria
```

## 💡 Funcionalitats Avançades

### **AJAX Real-time**
- Instal·lació sense recarregar pàgina
- Barra de progrés visual
- Gestió d'errors en temps real
- Feedback immediat a l'usuari

### **Gestió d'Errors**
- Recuperació automàtica de fallades
- Missatges d'error informatius
- Continuació automàtica si un plugin falla
- Log detallat per debugging

### **Experiència d'Usuari**
- Avisos no intrusius
- Opció de descartar avisos
- Indicadors visuals d'estat
- Instruccions clares i simples

### **Compatibilitat**
- 100% compatible amb WordPress.org
- Suport per plugins externs (GitHub)
- Multisite compatible
- Respecta permisos d'usuari

## 📊 Dashboard de Control

### **Pàgina de Configuració**
Accessible a `Aparença > Malet Torrent`, inclou:

- **Estat de l'API**: Verificació endpoints actius
- **Estat dels Plugins**: Progés per categories
- **WooCommerce**: Informació de la botiga
- **Seguretat i Rendiment**: Status plugins crítics
- **Enllaços Útils**: Accés ràpid a configuracions

### **Indicadors Visuals**
- Barres de progrés per categories
- Percentatges de completitud
- Codificació per colors (verd/vermell/groc)
- Icones d'estat clarament identificables

## 🔧 Personalització Avançada

### **Modificar Lista de Plugins**
Edita `/inc/required-plugins-config.php`:

```php
'nou-plugin' => [
    'name' => 'Nom del Plugin',
    'priority' => 'required', // required|recommended|optional
    'description' => 'Descripció del plugin',
    'source' => null, // null per WordPress.org o URL externa
    'auto_activate' => true, // Activació automàtica
    'features' => ['Funcionalitat 1', 'Funcionalitat 2']
]
```

### **Configuració Post-Activació**
Afegeix configuració personalitzada a `class-plugin-installer.php`:

```php
private function configure_plugin_after_activation($plugin_slug) {
    switch ($plugin_slug) {
        case 'nou-plugin':
            $this->configure_nou_plugin();
            break;
    }
}
```

### **Personalitzar Avisos**
Modifica `admin-notices.php` per canviar l'aparença o comportament dels avisos.

## 🔐 Seguretat

### **Verificacions de Seguretat**
- Verificació de permisos d'usuari
- Nonces per totes les peticions AJAX
- Validació i sanitització d'entrada
- Verificació de fonts de plugins

### **Fonts de Plugins**
- **WordPress.org**: Font principal, verificada automàticament
- **GitHub**: Per plugins experimentals, amb verificació SSL
- **URLs externes**: Només si especificat explícitament

## 📈 Monitoratge i Anàlisi

### **Logs d'Activitat**
El sistema registra:
- Plugins instal·lats i quan
- Errors d'instal·lació
- Configuracions aplicades
- Temps d'instal·lació

### **Mètriques de Rendiment**
- Temps d'instal·lació per plugin
- Taxa d'èxit d'instal·lació
- Plugins més utilitzats
- Errors més comuns

## 🆘 Resolució de Problemes

### **Error: "Permisos insuficients"**
**Solució**: L'usuari necessita permisos `install_plugins` i `activate_plugins`

### **Error: "Plugin no trobat"**
**Solució**: Verificar que el slug del plugin és correcte a WordPress.org

### **Error: "Timeout durant instal·lació"**
**Solució**: Augmentar `max_execution_time` a PHP o instal·lar plugins individualment

### **Error: "CORS blocked"**
**Solució**: El sistema CORS està integrat, verificar configuració servidor

## 🔄 Actualitzacions Futures

### **Funcionalitats Planificades**
- Instal·lació programada (cron jobs)
- Integració amb WP-CLI
- Sync automàtic amb configuració tema
- Gestió automàtica d'actualitzacions

### **Millores de Rendiment**
- Cache de metadata plugins
- Instal·lació paral·lela
- Compressió de descàrregues
- CDN per plugins populars

---

## 📞 Suport

Per problemes amb el sistema d'instal·lació de plugins:

1. **Comprova** el dashboard del tema a `Aparença > Malet Torrent`
2. **Verifica** els logs d'error del WordPress
3. **Testeja** la instal·lació manual del plugin problemàtic
4. **Consulta** aquesta documentació per a configuracions avançades

---

**🥨 Malet Torrent - Sistema d'Instal·lació Professional per WordPress**