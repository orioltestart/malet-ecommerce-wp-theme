<?php
/**
 * JWT Authentication Class for Malet Torrent
 * 
 * @package Malet Torrent
 * @since 1.0.0
 */

// Evitar accés directe
if (!defined('ABSPATH')) {
    exit;
}

class Malet_Torrent_JWT_Auth {
    
    private $secret_key;
    private $issuer;
    private $audience;
    
    public function __construct() {
        $this->secret_key = $this->get_secret_key();
        $this->issuer = get_site_url();
        $this->audience = get_site_url();
        
        add_action('rest_api_init', array($this, 'register_auth_endpoints'));
        add_action('init', array($this, 'setup_jwt_auth'));
        add_filter('determine_current_user', array($this, 'determine_current_user'), 10);
    }
    
    /**
     * Configurar clau secreta per JWT
     */
    private function get_secret_key() {
        // Intentar obtenir la clau de wp-config.php
        if (defined('JWT_AUTH_SECRET_KEY') && JWT_AUTH_SECRET_KEY) {
            return JWT_AUTH_SECRET_KEY;
        }
        
        // Si no existeix, crear-la automàticament
        $secret = get_option('malet_jwt_secret_key');
        if (!$secret) {
            $secret = wp_generate_password(64, true, true);
            update_option('malet_jwt_secret_key', $secret);
            
            // Generar avís per afegir la constant a wp-config.php
            if (is_admin()) {
                add_action('admin_notices', function() use ($secret) {
                    echo '<div class="notice notice-warning"><p>';
                    echo '<strong>JWT Auth:</strong> Afegeix aquesta línia a wp-config.php: <code>define(\'JWT_AUTH_SECRET_KEY\', \'' . esc_html($secret) . '\');</code>';
                    echo '</p></div>';
                });
            }
        }
        
        return $secret;
    }
    
    /**
     * Configurar autenticació JWT
     */
    public function setup_jwt_auth() {
        // CORS per endpoints JWT
        add_action('rest_api_init', array($this, 'add_jwt_cors_support'));
        
        // Filtres per tokens
        add_filter('rest_authentication_errors', array($this, 'rest_authentication_errors'));
    }
    
    /**
     * Registrar endpoints d'autenticació
     */
    public function register_auth_endpoints() {
        // Endpoint per registre d'usuaris
        register_rest_route('malet-torrent/v1', '/auth/register', array(
            'methods' => 'POST',
            'callback' => array($this, 'register_user'),
            'permission_callback' => '__return_true',
            'args' => array(
                'email' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_email'
                ),
                'password' => array(
                    'required' => true,
                    'type' => 'string'
                ),
                'first_name' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'last_name' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
            )
        ));
        
        
        // Endpoint per login
        register_rest_route('malet-torrent/v1', '/auth/login', array(
            'methods' => 'POST',
            'callback' => array($this, 'login_user'),
            'permission_callback' => '__return_true',
            'args' => array(
                'username' => array(
                    'required' => true,
                    'type' => 'string'
                ),
                'password' => array(
                    'required' => true,
                    'type' => 'string'
                )
            )
        ));
        
        // Endpoint per refresh token
        register_rest_route('malet-torrent/v1', '/auth/refresh', array(
            'methods' => 'POST',
            'callback' => array($this, 'refresh_token'),
            'permission_callback' => '__return_true',
            'args' => array(
                'refresh_token' => array(
                    'required' => true,
                    'type' => 'string'
                )
            )
        ));
        
        // Endpoint per validar sessió
        register_rest_route('malet-torrent/v1', '/auth/validate', array(
            'methods' => 'POST',
            'callback' => array($this, 'validate_session'),
            'permission_callback' => array($this, 'validate_jwt_token')
        ));
        
        // Endpoint per logout
        register_rest_route('malet-torrent/v1', '/auth/logout', array(
            'methods' => 'POST',
            'callback' => array($this, 'logout_user'),
            'permission_callback' => array($this, 'validate_jwt_token')
        ));
        
        // Endpoint per obtenir perfil d'usuari
        register_rest_route('malet-torrent/v1', '/auth/profile', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_user_profile'),
            'permission_callback' => array($this, 'validate_jwt_token')
        ));
    }
    
    /**
     * Generar nom d'usuari únic basat en nom i cognoms
     */
    private function generate_unique_username($first_name, $last_name) {
        // Generar username base (joan_puig)
        $base_username = sanitize_user(
            strtolower($first_name . '_' . $last_name),
            true
        );
        
        // Eliminar accents i caràcters especials
        $base_username = remove_accents($base_username);
        $base_username = preg_replace('/[^a-z0-9_]/', '', $base_username);
        
        // Assegurar que no està buit
        if (empty($base_username)) {
            $base_username = 'user_' . time();
        }
        
        // Si no existeix, retornar-lo
        if (!username_exists($base_username)) {
            return $base_username;
        }
        
        // Si existeix, afegir números fins trobar un disponible
        $counter = 1;
        $username = $base_username;
        
        while (username_exists($username)) {
            $username = $base_username . $counter;
            $counter++;
            
            // Límit de seguretat
            if ($counter > 999) {
                // Afegir timestamp per garantir unicitat
                $username = $base_username . '_' . time();
                break;
            }
        }
        
        return $username;
    }
    
    /**
     * Registrar usuari amb rol customer
     */
    public function register_user($request) {
        $email = $request->get_param('email');
        $password = $request->get_param('password');
        $first_name = $request->get_param('first_name');
        $last_name = $request->get_param('last_name');
        
        // Validacions
        if (empty($first_name) || empty($last_name)) {
            return new WP_Error('missing_names', 'Nom i cognoms són obligatoris', array('status' => 400));
        }
        
        if (email_exists($email)) {
            return new WP_Error('email_exists', 'L\'email ja està registrat', array('status' => 400));
        }
        
        if (strlen($password) < 8) {
            return new WP_Error('weak_password', 'La contrasenya ha de tenir almenys 8 caràcters', array('status' => 400));
        }
        
        // Sempre generar username únic automàticament
        $username = $this->generate_unique_username($first_name, $last_name);
        
        // Crear usuari
        $user_id = wp_create_user($username, $password, $email);
        
        if (is_wp_error($user_id)) {
            return new WP_Error('registration_failed', $user_id->get_error_message(), array('status' => 400));
        }
        
        // Actualitzar meta dades
        wp_update_user(array(
            'ID' => $user_id,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'display_name' => $first_name . ' ' . $last_name
        ));
        
        // Assignar rol de customer per WooCommerce
        $user = new WP_User($user_id);
        $user->set_role('customer');
        
        // Obtenir objecte usuari per generar tokens
        $user = get_user_by('id', $user_id);
        
        // Generar tokens JWT
        $tokens = $this->generate_tokens($user);
        
        return array(
            'success' => true,
            'user' => array(
                'id' => $user_id,
                'username' => $username, // Username generat automàticament
                'email' => $email,
                'display_name' => $first_name . ' ' . $last_name,
                'first_name' => $first_name,
                'last_name' => $last_name
            ),
            'tokens' => $tokens
        );
    }
    
    
    /**
     * Login d'usuari
     */
    public function login_user($request) {
        $username = $request->get_param('username');
        $password = $request->get_param('password');
        
        // Intentar login
        $user = wp_authenticate($username, $password);
        
        if (is_wp_error($user)) {
            return new WP_Error('login_failed', 'Credencials incorrectes', array('status' => 401));
        }
        
        // Verificar que l'usuari està actiu
        if (!is_user_logged_in()) {
            wp_set_current_user($user->ID);
        }
        
        // Generar tokens
        $tokens = $this->generate_tokens($user);
        
        return array(
            'success' => true,
            'message' => 'Login exitós',
            'user' => array(
                'id' => $user->ID,
                'username' => $user->user_login,
                'email' => $user->user_email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'roles' => $user->roles
            ),
            'tokens' => $tokens
        );
    }
    
    /**
     * Generar tokens JWT
     */
    private function generate_tokens($user) {
        $issued_at = time();
        $expiration = $issued_at + (HOUR_IN_SECONDS * 2); // 2 hores
        $refresh_expiration = $issued_at + (DAY_IN_SECONDS * 7); // 7 dies
        
        // Payload per access token
        $access_payload = array(
            'iss' => $this->issuer,
            'aud' => $this->audience,
            'iat' => $issued_at,
            'exp' => $expiration,
            'data' => array(
                'user' => array(
                    'id' => $user->ID,
                    'username' => $user->user_login,
                    'email' => $user->user_email,
                    'roles' => $user->roles
                )
            )
        );
        
        // Payload per refresh token
        $refresh_payload = array(
            'iss' => $this->issuer,
            'aud' => $this->audience,
            'iat' => $issued_at,
            'exp' => $refresh_expiration,
            'type' => 'refresh',
            'data' => array(
                'user' => array(
                    'id' => $user->ID
                )
            )
        );
        
        $access_token = $this->jwt_encode($access_payload);
        $refresh_token = $this->jwt_encode($refresh_payload);
        
        // Guardar refresh token a la base de dades
        update_user_meta($user->ID, '_malet_refresh_token', wp_hash($refresh_token));
        update_user_meta($user->ID, '_malet_refresh_token_exp', $refresh_expiration);
        
        return array(
            'access_token' => $access_token,
            'refresh_token' => $refresh_token,
            'expires_in' => $expiration,
            'token_type' => 'Bearer'
        );
    }
    
    /**
     * Refresh token
     */
    public function refresh_token($request) {
        $refresh_token = $request->get_param('refresh_token');
        
        if (!$refresh_token) {
            return new WP_Error('no_refresh_token', 'Refresh token requerit', array('status' => 400));
        }
        
        // Decodificar refresh token
        $decoded = $this->jwt_decode($refresh_token);
        
        if (is_wp_error($decoded)) {
            return new WP_Error('invalid_refresh_token', 'Refresh token invàlid', array('status' => 401));
        }
        
        // Verificar que és un refresh token
        if (!isset($decoded['type']) || $decoded['type'] !== 'refresh') {
            return new WP_Error('invalid_token_type', 'Token type incorrecte', array('status' => 401));
        }
        
        $user_id = $decoded['data']['user']['id'];
        $user = get_user_by('id', $user_id);
        
        if (!$user) {
            return new WP_Error('user_not_found', 'Usuari no trobat', array('status' => 404));
        }
        
        // Verificar refresh token a la base de dades
        $stored_refresh_hash = get_user_meta($user_id, '_malet_refresh_token', true);
        $refresh_exp = get_user_meta($user_id, '_malet_refresh_token_exp', true);
        
        if (!$stored_refresh_hash || wp_hash($refresh_token) !== $stored_refresh_hash) {
            return new WP_Error('invalid_stored_token', 'Refresh token no vàlid', array('status' => 401));
        }
        
        if (time() > $refresh_exp) {
            // Netejar refresh token caducat
            delete_user_meta($user_id, '_malet_refresh_token');
            delete_user_meta($user_id, '_malet_refresh_token_exp');
            return new WP_Error('refresh_token_expired', 'Refresh token caducat', array('status' => 401));
        }
        
        // Generar nous tokens
        $new_tokens = $this->generate_tokens($user);
        
        return array(
            'success' => true,
            'message' => 'Token renovat correctament',
            'tokens' => $new_tokens
        );
    }
    
    /**
     * Validar sessió
     */
    public function validate_session($request) {
        $current_user = wp_get_current_user();
        
        if (!$current_user || $current_user->ID === 0) {
            return new WP_Error('invalid_session', 'Sessió invàlida', array('status' => 401));
        }
        
        return array(
            'success' => true,
            'message' => 'Sessió vàlida',
            'user' => array(
                'id' => $current_user->ID,
                'username' => $current_user->user_login,
                'email' => $current_user->user_email,
                'first_name' => $current_user->first_name,
                'last_name' => $current_user->last_name,
                'roles' => $current_user->roles
            )
        );
    }
    
    /**
     * Logout d'usuari
     */
    public function logout_user($request) {
        $current_user = wp_get_current_user();
        
        if ($current_user && $current_user->ID !== 0) {
            // Eliminar refresh tokens
            delete_user_meta($current_user->ID, '_malet_refresh_token');
            delete_user_meta($current_user->ID, '_malet_refresh_token_exp');
        }
        
        return array(
            'success' => true,
            'message' => 'Logout exitós'
        );
    }
    
    /**
     * Obtenir perfil d'usuari
     */
    public function get_user_profile($request) {
        $current_user = wp_get_current_user();
        
        if (!$current_user || $current_user->ID === 0) {
            return new WP_Error('unauthorized', 'No autoritzat', array('status' => 401));
        }
        
        return array(
            'success' => true,
            'user' => array(
                'id' => $current_user->ID,
                'username' => $current_user->user_login,
                'email' => $current_user->user_email,
                'first_name' => $current_user->first_name,
                'last_name' => $current_user->last_name,
                'roles' => $current_user->roles,
                'avatar' => get_avatar_url($current_user->ID),
                'registered' => $current_user->user_registered
            )
        );
    }
    
    /**
     * Determinar usuari actual basant-se en token JWT
     */
    public function determine_current_user($user_id) {
        if ($user_id && $user_id !== 0) {
            return $user_id;
        }
        
        $token = $this->get_auth_header();
        
        if (!$token) {
            return $user_id;
        }
        
        $decoded = $this->jwt_decode($token);
        
        if (is_wp_error($decoded)) {
            return $user_id;
        }
        
        if (isset($decoded['data']['user']['id'])) {
            return $decoded['data']['user']['id'];
        }
        
        return $user_id;
    }
    
    /**
     * Obtenir header d'autorització
     */
    private function get_auth_header() {
        $auth_header = null;
        
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth_header = $_SERVER['HTTP_AUTHORIZATION'];
        } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $auth_header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        } elseif (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            if (isset($headers['Authorization'])) {
                $auth_header = $headers['Authorization'];
            }
        }
        
        if ($auth_header && preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    /**
     * Validar token JWT per endpoints protegits
     */
    public function validate_jwt_token($request) {
        $token = $this->get_auth_header();
        
        if (!$token) {
            return new WP_Error('no_token', 'Token d\'autorització requerit', array('status' => 401));
        }
        
        $decoded = $this->jwt_decode($token);
        
        if (is_wp_error($decoded)) {
            return $decoded;
        }
        
        return true;
    }
    
    /**
     * Errors d'autenticació REST
     */
    public function rest_authentication_errors($result) {
        if (!empty($result)) {
            return $result;
        }
        
        if (strpos($_SERVER['REQUEST_URI'], '/wp-json/malet-torrent/v1/auth/') !== false) {
            return null;
        }
        
        return $result;
    }
    
    /**
     * CORS per endpoints JWT
     */
    public function add_jwt_cors_support() {
        if (strpos($_SERVER['REQUEST_URI'], '/wp-json/malet-torrent/v1/auth/') !== false) {
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            header('Access-Control-Expose-Headers: Authorization');
        }
    }
    
    /**
     * Codificar JWT (implementació simple)
     */
    private function jwt_encode($payload) {
        $header = json_encode(array('typ' => 'JWT', 'alg' => 'HS256'));
        $payload = json_encode($payload);
        
        $base64_header = $this->base64url_encode($header);
        $base64_payload = $this->base64url_encode($payload);
        
        $signature = hash_hmac('sha256', $base64_header . '.' . $base64_payload, $this->secret_key, true);
        $base64_signature = $this->base64url_encode($signature);
        
        return $base64_header . '.' . $base64_payload . '.' . $base64_signature;
    }
    
    /**
     * Decodificar JWT
     */
    private function jwt_decode($token) {
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            return new WP_Error('invalid_token_format', 'Format de token invàlid', array('status' => 401));
        }
        
        list($base64_header, $base64_payload, $base64_signature) = $parts;
        
        $header = json_decode($this->base64url_decode($base64_header), true);
        $payload = json_decode($this->base64url_decode($base64_payload), true);
        
        if (!$header || !$payload) {
            return new WP_Error('invalid_token_data', 'Dades de token invàlides', array('status' => 401));
        }
        
        // Verificar signatura
        $expected_signature = hash_hmac('sha256', $base64_header . '.' . $base64_payload, $this->secret_key, true);
        $expected_base64_signature = $this->base64url_encode($expected_signature);
        
        if (!hash_equals($expected_base64_signature, $base64_signature)) {
            return new WP_Error('invalid_signature', 'Signatura de token invàlida', array('status' => 401));
        }
        
        // Verificar expiració
        if (isset($payload['exp']) && time() > $payload['exp']) {
            return new WP_Error('token_expired', 'Token caducat', array('status' => 401));
        }
        
        return $payload;
    }
    
    /**
     * Base64 URL encode
     */
    private function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Base64 URL decode
     */
    private function base64url_decode($data) {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }
}

// Inicialitzar classe JWT Auth
function malet_torrent_init_jwt_auth() {
    new Malet_Torrent_JWT_Auth();
}
add_action('init', 'malet_torrent_init_jwt_auth');