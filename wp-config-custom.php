<?php
/**
 * Configuració personalitzada WordPress
 * Aquest fitxer s'ha d'incloure al wp-config.php
 *
 * @package MaletTorrent
 */

// Configurar WP_ENVIRONMENT_TYPE des de variable d'entorn
if (!defined('WP_ENVIRONMENT_TYPE')) {
    $env_type = getenv('WP_ENVIRONMENT_TYPE') ?: getenv('WORDPRESS_ENVIRONMENT_TYPE');

    // Valors vàlids: local, development, staging, production
    $valid_environments = array('local', 'development', 'staging', 'production');

    if ($env_type && in_array($env_type, $valid_environments)) {
        define('WP_ENVIRONMENT_TYPE', $env_type);
    } else {
        // Per defecte a 'local' per desenvolupament
        define('WP_ENVIRONMENT_TYPE', 'local');
    }
}

// Habilitar Application Passwords per API REST
if (!defined('APPLICATION_PASSWORDS_ENABLED')) {
    define('APPLICATION_PASSWORDS_ENABLED', true);
}

// Configuració específica per entorn
switch (wp_get_environment_type()) {
    case 'local':
    case 'development':
        // Configuració per desenvolupament
        if (!defined('WP_DEBUG')) {
            define('WP_DEBUG', true);
        }
        if (!defined('WP_DEBUG_LOG')) {
            define('WP_DEBUG_LOG', true);
        }
        if (!defined('WP_DEBUG_DISPLAY')) {
            define('WP_DEBUG_DISPLAY', false);
        }
        if (!defined('SCRIPT_DEBUG')) {
            define('SCRIPT_DEBUG', true);
        }
        break;

    case 'staging':
        // Configuració per staging
        if (!defined('WP_DEBUG')) {
            define('WP_DEBUG', true);
        }
        if (!defined('WP_DEBUG_LOG')) {
            define('WP_DEBUG_LOG', true);
        }
        if (!defined('WP_DEBUG_DISPLAY')) {
            define('WP_DEBUG_DISPLAY', false);
        }
        break;

    case 'production':
        // Configuració per producció
        if (!defined('WP_DEBUG')) {
            define('WP_DEBUG', false);
        }
        if (!defined('WP_DEBUG_DISPLAY')) {
            define('WP_DEBUG_DISPLAY', false);
        }
        if (!defined('WP_DEBUG_LOG')) {
            define('WP_DEBUG_LOG', false);
        }
        break;
}

// Configuració de JWT per autenticació API
if (!defined('JWT_AUTH_SECRET_KEY')) {
    $jwt_secret = getenv('JWT_AUTH_SECRET_KEY') ?: 'your-secret-key-' . wp_generate_password(32, true, true);
    define('JWT_AUTH_SECRET_KEY', $jwt_secret);
}

if (!defined('JWT_AUTH_CORS_ENABLE')) {
    define('JWT_AUTH_CORS_ENABLE', true);
}

// URL del frontend per CORS
if (!defined('MALET_FRONTEND_URL')) {
    $frontend_url = getenv('MALET_FRONTEND_URL') ?: 'https://malet.testart.cat';
    define('MALET_FRONTEND_URL', $frontend_url);
}

// Habilitar REST API per usuaris autenticats
if (!defined('REST_API_ENABLED')) {
    define('REST_API_ENABLED', true);
}