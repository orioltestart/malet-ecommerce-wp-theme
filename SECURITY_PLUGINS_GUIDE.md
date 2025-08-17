# 🔒 Guía Completa de Plugins de Seguridad para WordPress - MaletNext

## 📋 Plugins de Seguridad Instalados

### ✅ Plugins Descargados y Disponibles

1. **Wordfence Security** v8.0.5 - Firewall y Scanner de Malware
2. **Limit Login Attempts Reloaded** v2.26.20 - Protección contra Fuerza Bruta
3. **WP Security Audit Log** v5.4.2 - Registro de Actividades
4. **Disable XML-RPC** v1.0.1 - Desactivar XML-RPC
5. **WP Force SSL** v1.68 - Forzar HTTPS
6. **WP Fail2Ban** v5.4.1 - Integración con Fail2Ban

---

## 🚀 Activación de Plugins

### Activación Manual (Recomendada)
1. Accede a: **http://localhost:8080/wp-admin**
2. Credenciales: `admin` / `admin123`
3. Ve a **Plugins > Plugins Instalados**
4. Activa cada plugin de seguridad

### Activación vía WP-CLI
```bash
# Activar todos los plugins de seguridad
docker run --rm --network maletnext_maletnext-network \
  --volumes-from maletnext-wordpress \
  -e WORDPRESS_DB_HOST=db:3306 \
  wordpress:cli-2.9-php8.1 \
  wp plugin activate wordfence limit-login-attempts-reloaded wp-security-audit-log disable-xml-rpc wp-force-ssl wp-fail2ban --allow-root --path=/var/www/html
```

---

## 🛡️ Configuración Detallada por Plugin

### 1. **Wordfence Security** - Firewall y Malware Scanner

#### 📍 Acceso: `Configuración > Wordfence`

#### ⚙️ Configuración Básica Recomendada

**Firewall Settings:**
- **Firewall Status**: `Enabled and Protecting`
- **Protection Level**: `Extended Protection` (Premium) o `Basic Protection`
- **Learning Mode**: Desactivar después de 7 días
- **Block immediately on failed login**: `Enabled`
- **Lock out after**: `3 failed logins`
- **Lock out time**: `20 minutes`

**Scan Settings:**
- **Scan Type**: `High Sensitivity`
- **Scan Schedule**: `Daily at 3:00 AM`
- **Email alerts**: `Enabled`
- **Monitor file changes**: `Enabled`
- **Check file signatures**: `Enabled`

**Login Security:**
- **Enable two-factor authentication**: `Enabled`
- **CAPTCHA on login**: `Enabled`
- **Hide WordPress version**: `Enabled`

#### 🔧 Configuración Avanzada

```bash
# Configuración via wp-config.php (opcional)
define('WFWAF_ENABLED', true);
define('WFWAF_AUTO_PREPEND', 1);
define('WFWAF_LOG_PATH', '/var/www/html/wp-content/wflogs/');
```

#### 📊 Monitoreo
- **Dashboard**: `Wordfence > Dashboard`
- **Live Traffic**: `Wordfence > Tools > Live Traffic`
- **Firewall**: `Wordfence > Firewall`

---

### 2. **Limit Login Attempts Reloaded** - Protección Fuerza Bruta

#### 📍 Acceso: `Configuración > Limit Login Attempts`

#### ⚙️ Configuración Recomendada

**Configuración Básica:**
- **Max login attempts**: `3`
- **Lockout time**: `20 minutes`
- **Max lockouts before long lockout**: `3`
- **Long lockout time**: `24 hours`

**Configuración Avanzada:**
- **Enable log of lockouts**: `✓`
- **Email notifications**: `✓`
- **Notify on lockout**: `After 3 lockouts`

#### 📧 Notificaciones Email
- **Admin email**: `admin@maletnext.local`
- **Email subject**: `[MaletNext] Login Security Alert`

#### 🚫 Lista Blanca/Negra
```
Lista Blanca (Whitelist):
127.0.0.1
192.168.1.0/24

Lista Negra (Blacklist):
(Agregar IPs maliciosas conocidas)
```

---

### 3. **WP Security Audit Log** - Registro de Actividades

#### 📍 Acceso: `WP Security Audit Log > Audit Log Viewer`

#### ⚙️ Configuración de Logs

**Configuración General:**
- **Log Level**: `All Events`
- **Keep logs for**: `180 days`
- **Prune logs older than**: `6 months`
- **Date format**: `Y-m-d H:i:s`

**Eventos a Monitorear:**
- ✅ **User Logins/Logouts**
- ✅ **Failed Login Attempts**
- ✅ **Plugin/Theme Changes**
- ✅ **Content Changes**
- ✅ **Settings Changes**
- ✅ **File Changes**

#### 📧 Alertas Email
```
Email Settings:
- Admin Email: admin@maletnext.local
- Alert on: Critical Events
- Events to Alert:
  * Failed logins (after 3 attempts)
  * Plugin installations
  * Theme changes
  * User role changes
  * Critical file changes
```

#### 🔍 Filtros Útiles
```
Filtros de Búsqueda:
- Event ID 1000-1004: User logins/logouts
- Event ID 1001: Failed login
- Event ID 2000-2999: Content changes
- Event ID 5000-5999: Plugin/Theme changes
```

---

### 4. **Disable XML-RPC** - Desactivar XML-RPC

#### 📍 Este plugin funciona automáticamente

#### ⚙️ Configuración
- **Activar el plugin** - No requiere configuración adicional
- **Verificación**: Visita `http://localhost:8080/xmlrpc.php` debería devolver error

#### ✅ Funcionalidad
- Desactiva completamente XML-RPC
- Previene ataques de fuerza bruta via XML-RPC
- Mejora la seguridad general

---

### 5. **WP Force SSL** - Forzar HTTPS

#### 📍 Acceso: `Configuración > WP Force SSL`

#### ⚙️ Configuración para Desarrollo Local

**IMPORTANTE**: Para desarrollo local con HTTP, mantener desactivado.

**Para Producción:**
```
SSL Settings:
- Force SSL: ✓ Enabled
- Force SSL Admin: ✓ Enabled
- Force SSL Login: ✓ Enabled
- HTTPS Content Detection: ✓ Enabled
- Mixed Content Fixer: ✓ Enabled
```

#### 🔧 Configuración Avanzada (Producción)
```bash
# En wp-config.php para producción
define('FORCE_SSL_ADMIN', true);
define('FORCE_SSL_LOGIN', true);
```

---

### 6. **WP Fail2Ban** - Integración con Fail2Ban

#### 📍 Acceso: `Configuración > WP Fail2Ban`

#### ⚙️ Configuración Básica

**Configuración de Logs:**
- **Log file location**: `/var/log/auth.log`
- **Log failed logins**: `✓ Enabled`
- **Log spam**: `✓ Enabled`
- **Log pingbacks**: `✓ Enabled`

**WordPress Events:**
- **Authentication**: `✓ Log to syslog`
- **Comments**: `✓ Log spam attempts`
- **Password**: `✓ Log password reset`
- **Pingback**: `✓ Log pingback requests`

#### 🔧 Integración con Sistema (Producción)

**Archivo Fail2Ban: `/etc/fail2ban/filter.d/wordpress.conf`**
```ini
[Definition]
failregex = ^%(__prefix_line)s.*authentication failure.* user=.*$
            ^%(__prefix_line)s.*spam comment.*$
            ^%(__prefix_line)s.*pingback error.*$

ignoreregex =
```

**Jail Configuration: `/etc/fail2ban/jail.local`**
```ini
[wordpress]
enabled = true
port = http,https
filter = wordpress
logpath = /var/log/auth.log
maxretry = 3
bantime = 3600
findtime = 600
```

---

## 🎯 Configuración Global de Seguridad

### wp-config.php - Configuraciones Adicionales

```php
// Seguridad adicional en wp-config.php
define('DISALLOW_FILE_EDIT', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
define('FORCE_SSL_ADMIN', false); // true en producción
define('AUTOMATIC_UPDATER_DISABLED', true);

// Claves de seguridad (cambiar en producción)
define('AUTH_KEY',         'generate-unique-key');
define('SECURE_AUTH_KEY',  'generate-unique-key');
define('LOGGED_IN_KEY',    'generate-unique-key');
define('NONCE_KEY',        'generate-unique-key');
define('AUTH_SALT',        'generate-unique-key');
define('SECURE_AUTH_SALT', 'generate-unique-key');
define('LOGGED_IN_SALT',   'generate-unique-key');
define('NONCE_SALT',       'generate-unique-key');
```

### .htaccess - Reglas de Seguridad

```apache
# Protección de archivos sensibles
<Files wp-config.php>
order allow,deny
deny from all
</Files>

# Bloquear acceso a xmlrpc.php
<Files xmlrpc.php>
order allow,deny
deny from all
</Files>

# Ocultar versión de WordPress
RewriteRule ^wp-admin/includes/ - [F,L]
RewriteRule !^wp-includes/ - [S=3]
RewriteRule ^wp-includes/[^/]+\.php$ - [F,L]
RewriteRule ^wp-includes/js/tinymce/langs/.+\.php - [F,L]
RewriteRule ^wp-includes/theme-compat/ - [F,L]

# Limitar acceso a wp-admin
<Files wp-login.php>
AuthType Basic
AuthName "Administración"
AuthUserFile /path/to/.htpasswd
Require valid-user
</Files>
```

---

## 📊 Panel de Monitoreo de Seguridad

### Dashboard Centralizado
1. **Wordfence Dashboard**: Estado del firewall y amenazas
2. **Security Audit Log**: Actividad reciente
3. **Limit Login Attempts**: Intentos de acceso
4. **Server Status**: Estado de plugins de seguridad

### Alertas Críticas
- **Intentos de login fallidos** > 5 en 1 hora
- **Cambios en plugins/temas** no autorizados
- **Acceso de IP no reconocida** a admin
- **Modificación de archivos core** de WordPress

---

## 🔄 Rutinas de Mantenimiento

### Diarias
- [ ] Revisar **Wordfence Dashboard**
- [ ] Verificar **Audit Log** para actividad inusual
- [ ] Comprobar **intentos de login** bloqueados

### Semanales
- [ ] **Scan completo** con Wordfence
- [ ] Revisar **lista de IPs bloqueadas**
- [ ] Actualizar **reglas de firewall**
- [ ] Verificar **logs de seguridad**

### Mensuales
- [ ] **Backup** de configuraciones de seguridad
- [ ] Revisar y **limpiar logs** antiguos
- [ ] **Actualizar plugins** de seguridad
- [ ] **Audit** de usuarios y permisos

---

## 🚨 Protocolo de Incidentes

### En caso de Intrusión Detectada:

1. **Inmediato**:
   - Cambiar todas las contraseñas
   - Revisar logs de acceso
   - Activar modo mantenimiento

2. **Investigación**:
   - Analizar **Security Audit Log**
   - Revisar **Wordfence Activity Log**
   - Identificar vector de ataque

3. **Remediación**:
   - Limpiar malware si existe
   - Fortalecer medidas de seguridad
   - Actualizar todas las credenciales

4. **Prevención**:
   - Implementar medidas adicionales
   - Monitoreo reforzado
   - Documentar el incidente

---

## 📧 Configuración de Notificaciones

### Emails de Seguridad
```
Destinatarios:
- admin@maletnext.local (Principal)
- security@maletnext.local (Secundario)

Eventos que generan alerta:
- Login fallidos (>3 intentos)
- Cambios en plugins/temas
- Detección de malware
- Modificaciones críticas
- Accesos administrativos
```

### Niveles de Alerta
- 🟢 **INFO**: Actividad normal documentada
- 🟡 **WARNING**: Actividad sospechosa
- 🟠 **CRITICAL**: Intento de intrusión
- 🔴 **EMERGENCY**: Intrusión confirmada

---

## ✅ Checklist de Configuración Inicial

### Activación y Configuración Básica
- [ ] Activar todos los plugins de seguridad
- [ ] Configurar **Wordfence** con settings recomendados
- [ ] Configurar **Limit Login Attempts** 
- [ ] Activar logs en **Security Audit Log**
- [ ] Verificar funcionamiento de **Disable XML-RPC**
- [ ] Configurar **WP Fail2Ban** (si aplica)

### Configuración Avanzada
- [ ] Añadir reglas de seguridad a **.htaccess**
- [ ] Configurar **wp-config.php** con settings de seguridad
- [ ] Establecer **notificaciones email**
- [ ] Crear **rutinas de monitoreo**
- [ ] Documentar **credenciales** y **configuraciones**

### Testing
- [ ] Probar **intentos de login fallidos**
- [ ] Verificar **funcionamiento del firewall**
- [ ] Confirmar **logs de actividad**
- [ ] Validar **notificaciones email**
- [ ] Test de **acceso a archivos protegidos**

---

## 🎯 URLs de Acceso Rápido

- **WordPress Admin**: http://localhost:8080/wp-admin
- **Wordfence Dashboard**: http://localhost:8080/wp-admin/admin.php?page=Wordfence
- **Security Audit Log**: http://localhost:8080/wp-admin/admin.php?page=wsal-auditlog
- **Login Attempts**: http://localhost:8080/wp-admin/options-general.php?page=limit-login-attempts
- **WP Force SSL**: http://localhost:8080/wp-admin/options-general.php?page=wp-force-ssl

---

**⚠️ IMPORTANTE**: Esta configuración está optimizada para desarrollo local. Para producción, activar HTTPS, configurar Fail2Ban en el servidor, y usar contraseñas seguras.

Los plugins están listos para configurar y proporcionarán una seguridad robusta a tu aplicación WordPress! 🛡️