<?php
/**
 * Gestió d'usuaris per al tema Malet Torrent
 *
 * Funcionalitats:
 * - Endpoints REST API per perfils d'usuari
 * - Gestió de contrasenyes
 * - Estadístiques d'usuari
 * - Preferències de subscripció
 *
 * @package Malet_Torrent
 * @since 1.0.0
 */

// Evitar accés directe
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registrar endpoints per gestió d'usuaris
 */
function malet_register_user_endpoints() {
    // GET /wp-json/malet-torrent/v1/user/profile - Obtenir dades de l'usuari
    register_rest_route('malet-torrent/v1', '/user/profile', array(
        'methods' => 'GET',
        'callback' => 'malet_get_user_profile_endpoint',
        'permission_callback' => function() {
            return is_user_logged_in();
        }
    ));

    // PUT /wp-json/malet-torrent/v1/user/profile - Actualitzar dades de l'usuari
    register_rest_route('malet-torrent/v1', '/user/profile', array(
        'methods' => 'PUT',
        'callback' => 'malet_update_user_profile_endpoint',
        'permission_callback' => function() {
            return is_user_logged_in();
        },
        'args' => array(
            'first_name' => array(
                'description' => 'Nom de l\'usuari',
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field'
            ),
            'last_name' => array(
                'description' => 'Cognoms de l\'usuari',
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field'
            ),
            'email' => array(
                'description' => 'Adreça de correu electrònic',
                'type' => 'string',
                'format' => 'email',
                'validate_callback' => function($param) {
                    return is_email($param);
                }
            ),
            'phone' => array(
                'description' => 'Número de telèfon',
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field'
            ),
            'birth_date' => array(
                'description' => 'Data de naixement (YYYY-MM-DD)',
                'type' => 'string',
                'format' => 'date'
            ),
            'company' => array(
                'description' => 'Nom de l\'empresa',
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field'
            ),
            'display_name' => array(
                'description' => 'Nom a mostrar públicament',
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field'
            ),
            'description' => array(
                'description' => 'Descripció/bio de l\'usuari',
                'type' => 'string',
                'sanitize_callback' => 'sanitize_textarea_field'
            ),
            'marketing_emails' => array(
                'description' => 'Rebre emails de màrqueting',
                'type' => 'boolean'
            ),
            'newsletter_subscribed' => array(
                'description' => 'Subscripció al newsletter',
                'type' => 'boolean'
            )
        )
    ));

    // PUT /wp-json/malet-torrent/v1/user/password - Canviar contrasenya
    register_rest_route('malet-torrent/v1', '/user/password', array(
        'methods' => 'PUT',
        'callback' => 'malet_change_user_password_endpoint',
        'permission_callback' => function() {
            return is_user_logged_in();
        },
        'args' => array(
            'current_password' => array(
                'required' => true,
                'type' => 'string',
                'description' => 'Contrasenya actual'
            ),
            'new_password' => array(
                'required' => true,
                'type' => 'string',
                'description' => 'Nova contrasenya',
                'minLength' => 8
            ),
            'confirm_password' => array(
                'required' => true,
                'type' => 'string',
                'description' => 'Confirmar nova contrasenya'
            )
        )
    ));
}
add_action('rest_api_init', 'malet_register_user_endpoints');

/**
 * Endpoint: Obtenir perfil de l'usuari
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function malet_get_user_profile_endpoint($request) {
    $user_id = get_current_user_id();
    $user = get_userdata($user_id);

    if (!$user) {
        return new WP_Error('user_not_found', 'Usuari no trobat', array('status' => 404));
    }

    // Preparar dades de l'usuari
    $profile_data = array(
        'id' => $user->ID,
        'username' => $user->user_login,
        'email' => $user->user_email,
        'first_name' => $user->first_name,
        'last_name' => $user->last_name,
        'display_name' => $user->display_name,
        'description' => $user->description,
        'registered' => $user->user_registered,
        'roles' => $user->roles,

        // Meta camps addicionals
        'phone' => get_user_meta($user_id, 'billing_phone', true),
        'birth_date' => get_user_meta($user_id, 'birth_date', true),
        'company' => get_user_meta($user_id, 'billing_company', true),

        // Estadístiques
        'total_orders' => malet_get_user_order_count($user_id),
        'total_spent' => malet_get_user_total_spent($user_id),
        'last_login' => get_user_meta($user_id, 'last_login', true),

        // Preferències
        'marketing_emails' => get_user_meta($user_id, 'marketing_emails', true) === 'yes',
        'newsletter_subscribed' => get_user_meta($user_id, 'newsletter_subscribed', true) === 'yes'
    );

    return rest_ensure_response(array(
        'success' => true,
        'data' => $profile_data
    ));
}

/**
 * Endpoint: Actualitzar perfil de l'usuari
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function malet_update_user_profile_endpoint($request) {
    $user_id = get_current_user_id();
    $user = get_userdata($user_id);

    if (!$user) {
        return new WP_Error('user_not_found', 'Usuari no trobat', array('status' => 404));
    }

    $errors = array();
    $updated_fields = array();

    // Camps que es poden actualitzar directament a wp_users
    $user_fields = array('first_name', 'last_name', 'email', 'display_name', 'description');
    $user_data = array('ID' => $user_id);

    foreach ($user_fields as $field) {
        $value = $request->get_param($field);
        if ($value !== null) {
            // Validació especial per email
            if ($field === 'email') {
                if (!is_email($value)) {
                    $errors['email'] = 'Format d\'email no vàlid';
                    continue;
                }

                // Verificar que l'email no estigui en ús per un altre usuari
                $existing_user = get_user_by('email', $value);
                if ($existing_user && $existing_user->ID !== $user_id) {
                    $errors['email'] = 'Aquest email ja està en ús per un altre usuari';
                    continue;
                }

                $user_data['user_email'] = $value;
            } else {
                $user_data['user_' . $field] = $value;
            }
            $updated_fields[] = $field;
        }
    }

    // Camps meta
    $meta_fields = array(
        'phone' => 'billing_phone',
        'birth_date' => 'birth_date',
        'company' => 'billing_company'
    );

    foreach ($meta_fields as $param => $meta_key) {
        $value = $request->get_param($param);
        if ($value !== null) {
            // Validació especial per data de naixement
            if ($param === 'birth_date' && !empty($value)) {
                $date = DateTime::createFromFormat('Y-m-d', $value);
                if (!$date || $date->format('Y-m-d') !== $value) {
                    $errors['birth_date'] = 'Format de data no vàlid (YYYY-MM-DD)';
                    continue;
                }

                // Verificar que la data sigui raonable
                $now = new DateTime();
                $age = $now->diff($date)->y;
                if ($age > 120 || $age < 13) {
                    $errors['birth_date'] = 'Data de naixement no vàlida';
                    continue;
                }
            }

            update_user_meta($user_id, $meta_key, $value);
            $updated_fields[] = $param;
        }
    }

    // Camps de preferències
    $preference_fields = array('marketing_emails', 'newsletter_subscribed');
    foreach ($preference_fields as $field) {
        $value = $request->get_param($field);
        if ($value !== null) {
            $meta_value = $value ? 'yes' : 'no';
            update_user_meta($user_id, $field, $meta_value);
            $updated_fields[] = $field;
        }
    }

    // Si hi ha errors, retornar-los
    if (!empty($errors)) {
        return new WP_Error('validation_failed', 'Errors de validació', array(
            'status' => 400,
            'errors' => $errors
        ));
    }

    // Actualitzar dades de l'usuari si cal
    if (count($user_data) > 1) {
        $result = wp_update_user($user_data);
        if (is_wp_error($result)) {
            return new WP_Error('update_failed', 'Error actualitzant l\'usuari', array('status' => 500));
        }
    }

    // Registrar activitat
    update_user_meta($user_id, 'profile_last_updated', current_time('mysql'));

    return rest_ensure_response(array(
        'success' => true,
        'message' => 'Perfil actualitzat correctament',
        'updated_fields' => $updated_fields
    ));
}

/**
 * Endpoint: Canviar contrasenya
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function malet_change_user_password_endpoint($request) {
    $user_id = get_current_user_id();
    $user = get_userdata($user_id);

    if (!$user) {
        return new WP_Error('user_not_found', 'Usuari no trobat', array('status' => 404));
    }

    $current_password = $request->get_param('current_password');
    $new_password = $request->get_param('new_password');
    $confirm_password = $request->get_param('confirm_password');

    // Verificar contrasenya actual
    if (!wp_check_password($current_password, $user->user_pass, $user_id)) {
        return new WP_Error('incorrect_password', 'La contrasenya actual no és correcta', array('status' => 400));
    }

    // Verificar que les noves contrasenyes coincideixin
    if ($new_password !== $confirm_password) {
        return new WP_Error('password_mismatch', 'Les contrasenyes no coincideixen', array('status' => 400));
    }

    // Verificar força de la contrasenya
    if (strlen($new_password) < 8) {
        return new WP_Error('weak_password', 'La contrasenya ha de tenir almenys 8 caràcters', array('status' => 400));
    }

    // Verificar que la nova contrasenya sigui diferent
    if (wp_check_password($new_password, $user->user_pass, $user_id)) {
        return new WP_Error('same_password', 'La nova contrasenya ha de ser diferent de l\'actual', array('status' => 400));
    }

    // Actualitzar contrasenya
    wp_set_password($new_password, $user_id);

    // Registrar canvi
    update_user_meta($user_id, 'password_last_changed', current_time('mysql'));

    return rest_ensure_response(array(
        'success' => true,
        'message' => 'Contrasenya canviada correctament'
    ));
}

/**
 * Obtenir el nombre de comandes d'un usuari
 *
 * @param int $user_id ID de l'usuari
 * @return int Nombre de comandes
 */
function malet_get_user_order_count($user_id) {
    if (!function_exists('wc_get_orders')) {
        return 0;
    }

    $orders = wc_get_orders(array(
        'customer_id' => $user_id,
        'status' => array('completed', 'processing', 'on-hold'),
        'limit' => -1
    ));

    return count($orders);
}

/**
 * Obtenir el total gastat per un usuari
 *
 * @param int $user_id ID de l'usuari
 * @return float Total gastat
 */
function malet_get_user_total_spent($user_id) {
    if (!function_exists('wc_get_orders')) {
        return 0;
    }

    $orders = wc_get_orders(array(
        'customer_id' => $user_id,
        'status' => array('completed'),
        'limit' => -1
    ));

    $total = 0;
    foreach ($orders as $order) {
        $total += $order->get_total();
    }

    return $total;
}