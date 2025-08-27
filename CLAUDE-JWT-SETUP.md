# JWT Authentication Implementation - Malet Torrent

## 📋 Resum de la Implementació

S'ha implementat un sistema JWT Authentication complet per WordPress amb els següents endpoints:

### Endpoints Disponibles

| Endpoint | Mètode | Descripció |
|----------|---------|------------|
| `/wp-json/maletnext/v1/auth/register` | POST | Registre d'usuaris amb rol 'customer' |
| `/wp-json/maletnext/v1/auth/login` | POST | Login amb JWT tokens |
| `/wp-json/maletnext/v1/auth/refresh` | POST | Renovar access token amb refresh token |
| `/wp-json/maletnext/v1/auth/validate` | POST | Validar sessió actual |
| `/wp-json/maletnext/v1/auth/logout` | POST | Logout i invalidar tokens |
| `/wp-json/maletnext/v1/auth/profile` | GET | Obtenir perfil d'usuari autenticat |

## 🔧 Configuració

### 1. Clau Secreta JWT

Afegeix aquesta línia al teu `wp-config.php`:

```php
define('JWT_AUTH_SECRET_KEY', 'la_teva_clau_secreta_aqui');
```

**Nota**: Si no es defineix, el sistema generarà una clau automàticament i mostrarà un avís d'admin.

### 2. CORS

El sistema està configurat per acceptar peticions de:
- `http://localhost:3000`
- `http://localhost:8080`
- `https://malet.testart.cat`
- `https://wp.malet.testart.cat`

## 📚 Ús dels Endpoints

### Registre d'Usuari

```javascript
POST /wp-json/maletnext/v1/auth/register

{
  "username": "usuari123",
  "email": "usuari@exemple.com",
  "password": "contrasenya123",
  "first_name": "Nom",
  "last_name": "Cognom"
}
```

**Resposta:**
```javascript
{
  "success": true,
  "message": "Usuari registrat correctament",
  "user": {
    "id": 123,
    "username": "usuari123",
    "email": "usuari@exemple.com",
    "first_name": "Nom",
    "last_name": "Cognom",
    "role": "customer"
  },
  "tokens": {
    "access_token": "jwt_token_aqui",
    "refresh_token": "refresh_token_aqui",
    "expires_in": 1640995200,
    "token_type": "Bearer"
  }
}
```

### Login

```javascript
POST /wp-json/maletnext/v1/auth/login

{
  "username": "usuari123",
  "password": "contrasenya123"
}
```

**Resposta:**
```javascript
{
  "success": true,
  "message": "Login exitós",
  "user": {
    "id": 123,
    "username": "usuari123",
    "email": "usuari@exemple.com",
    "first_name": "Nom",
    "last_name": "Cognom",
    "roles": ["customer"]
  },
  "tokens": {
    "access_token": "jwt_token_aqui",
    "refresh_token": "refresh_token_aqui",
    "expires_in": 1640995200,
    "token_type": "Bearer"
  }
}
```

### Refresh Token

```javascript
POST /wp-json/maletnext/v1/auth/refresh

{
  "refresh_token": "refresh_token_aqui"
}
```

### Endpoints Protegits

Per als endpoints que requereixen autenticació, inclou l'header:

```javascript
Authorization: Bearer jwt_token_aqui
```

### Validar Sessió

```javascript
POST /wp-json/maletnext/v1/auth/validate
Headers: Authorization: Bearer jwt_token_aqui
```

### Obtenir Perfil

```javascript
GET /wp-json/maletnext/v1/auth/profile
Headers: Authorization: Bearer jwt_token_aqui
```

### Logout

```javascript
POST /wp-json/maletnext/v1/auth/logout
Headers: Authorization: Bearer jwt_token_aqui
```

## 🔒 Seguretat

### Característiques de Seguretat

- **Rol per defecte**: Tots els usuaris registrats tenen rol `customer` (sense accés admin)
- **Validació de contrasenyes**: Mínim 8 caràcters
- **Tokens amb expiració**: Access tokens (2h), Refresh tokens (7 dies)
- **Signatura HMAC**: Tokens signats amb clau secreta
- **Refresh token storage**: Emmagatzemats hasheats a la base de dades
- **CORS configurat**: Només orígens permesos

### Limitacions

- No té accés a wp-admin per usuaris 'customer'
- Refresh tokens s'invaliden en logout
- Tokens caducats retornen error 401

## 🧪 Tests d'Exemple

### Exemple amb cURL

```bash
# Registre
curl -X POST https://wp2.malet.testart.cat/wp-json/maletnext/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "username": "test123",
    "email": "test@exemple.com",
    "password": "password123",
    "first_name": "Test",
    "last_name": "User"
  }'

# Login
curl -X POST https://wp2.malet.testart.cat/wp-json/maletnext/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "username": "test123",
    "password": "password123"
  }'

# Usar token per obtenir perfil
curl -X GET https://wp2.malet.testart.cat/wp-json/maletnext/v1/auth/profile \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

### Exemple amb JavaScript (Next.js)

```javascript
// Servei d'autenticació
class AuthService {
  constructor(baseUrl) {
    this.baseUrl = baseUrl;
    this.tokenKey = 'malet_access_token';
    this.refreshKey = 'malet_refresh_token';
  }

  async register(userData) {
    const response = await fetch(`${this.baseUrl}/wp-json/maletnext/v1/auth/register`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(userData)
    });
    
    const data = await response.json();
    
    if (data.success) {
      localStorage.setItem(this.tokenKey, data.tokens.access_token);
      localStorage.setItem(this.refreshKey, data.tokens.refresh_token);
    }
    
    return data;
  }

  async login(username, password) {
    const response = await fetch(`${this.baseUrl}/wp-json/maletnext/v1/auth/login`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ username, password })
    });
    
    const data = await response.json();
    
    if (data.success) {
      localStorage.setItem(this.tokenKey, data.tokens.access_token);
      localStorage.setItem(this.refreshKey, data.tokens.refresh_token);
    }
    
    return data;
  }

  async getProfile() {
    const token = localStorage.getItem(this.tokenKey);
    
    const response = await fetch(`${this.baseUrl}/wp-json/maletnext/v1/auth/profile`, {
      headers: {
        'Authorization': `Bearer ${token}`
      }
    });
    
    return await response.json();
  }

  async logout() {
    const token = localStorage.getItem(this.tokenKey);
    
    await fetch(`${this.baseUrl}/wp-json/maletnext/v1/auth/logout`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`
      }
    });
    
    localStorage.removeItem(this.tokenKey);
    localStorage.removeItem(this.refreshKey);
  }
}

// Ús
const auth = new AuthService('https://wp2.malet.testart.cat');
```

## 📁 Fitxers Implementats

- `inc/class-jwt-auth.php` - Classe principal JWT Auth
- Modificacions a `functions.php` - Incloure classe i configurar CORS
- Aquest fitxer de documentació

## 🛠️ Troubleshooting Frontend Issues

### WooCommerce Sync Errors

Si el teu frontend Next.js mostra errors com:
```
WooCommerce sync error: Error: Failed to sync customer: Bad Request
```

**Solucions:**

1. **Usar endpoints directes** en lloc d'API Routes:
   ```javascript
   // ❌ Problemàtic: API Route Next.js
   fetch('/api/woocommerce/customers')
   
   // ✅ Recomanat: Endpoint directe WordPress  
   fetch('http://localhost:8080/wp-json/malet-torrent/v1/customers')
   ```

2. **Verificar URL correcta** (HTTP, no HTTPS en desenvolupament):
   ```javascript
   const WORDPRESS_API_URL = 'http://localhost:8080'; // Desenvolupament
   ```

3. **Endpoints personalitzats disponibles** per evitar problemes d'API Routes:
   - `GET /wp-json/malet-torrent/v1/customers` - Llistar customers
   - `GET /wp-json/malet-torrent/v1/products/featured` - Productes destacats
   - `GET /wp-json/malet-torrent/v1/woocommerce/config` - Configuració WC

### IntlError Missing Messages

Per als errors de traducció:
```javascript
IntlError: MISSING_MESSAGE: Could not resolve `account.dashboard.recentActivity.startShopping` in messages for locale `es`
```

Afegeix les traduccions que falten al teu fitxer de locale (`es.json`):
```json
{
  "account": {
    "dashboard": {
      "recentActivity": {
        "noActivity": "No hay actividad reciente",
        "startShopping": "Comenzar a comprar"
      }
    }
  }
}
```

## 🚀 Estat

✅ **COMPLET I OPERATIU**

- JWT Authentication implementat
- WooCommerce sync endpoints funcionals
- Troubleshooting documentat per problemes comuns frontend

El sistema està llest per integrar-se amb el frontend Next.js.

---

*Implementació completada per Claude Code*
*Data: Agost 2025*