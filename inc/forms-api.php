<?php
/**
 * API REST per gestionar formularis Contact Form 7
 *
 * @package MaletTorrent
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registrar endpoints personalitzats per formularis
 */
function malet_register_forms_endpoints() {
    // Endpoint per obtenir tots els formularis disponibles
    register_rest_route('malet-torrent/v1', '/forms', array(
        'methods' => 'GET',
        'callback' => 'malet_get_forms',
        'permission_callback' => '__return_true',
    ));

    // Endpoint per obtenir un formulari específic
    register_rest_route('malet-torrent/v1', '/forms/(?P<id>\d+)', array(
        'methods' => 'GET',
        'callback' => 'malet_get_single_form',
        'permission_callback' => '__return_true',
        'args' => array(
            'id' => array(
                'validate_callback' => function($param, $request, $key) {
                    return is_numeric($param);
                }
            ),
        ),
    ));

    // Endpoint per enviar formularis
    register_rest_route('malet-torrent/v1', '/forms/submit', array(
        'methods' => 'POST',
        'callback' => 'malet_submit_form',
        'permission_callback' => '__return_true',
    ));

    // Endpoint per obtenir submissions (només admin)
    register_rest_route('malet-torrent/v1', '/forms/submissions', array(
        'methods' => 'GET',
        'callback' => 'malet_get_submissions',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        },
    ));

    // Endpoint de debug per verificar últimes submissions (només en mode debug)
    register_rest_route('malet-torrent/v1', '/forms/debug/recent', array(
        'methods' => 'GET',
        'callback' => 'malet_debug_recent_submissions',
        'permission_callback' => function() {
            // Només accessible en entorns no productius
            $environment = wp_get_environment_type();
            return in_array($environment, array('local', 'development'));
        },
    ));
}
add_action('rest_api_init', 'malet_register_forms_endpoints');

/**
 * Obtenir tots els formularis disponibles
 */
function malet_get_forms($request) {
    $forms = get_posts(array(
        'post_type' => 'wpcf7_contact_form',
        'posts_per_page' => -1,
        'post_status' => 'publish',
    ));

    $forms_data = array();

    foreach ($forms as $form) {
        $contact_form = WPCF7_ContactForm::get_instance($form->ID);

        $forms_data[] = array(
            'id' => $form->ID,
            'title' => $form->post_title,
            'slug' => $form->post_name,
            'locale' => $contact_form->locale(),
            'fields' => malet_parse_form_fields($contact_form->prop('form')),
        );
    }

    return new WP_REST_Response($forms_data, 200);
}

/**
 * Obtenir un formulari específic
 */
function malet_get_single_form($request) {
    $form_id = $request['id'];
    $contact_form = WPCF7_ContactForm::get_instance($form_id);

    if (!$contact_form) {
        return new WP_Error('form_not_found', 'Formulari no trobat', array('status' => 404));
    }

    $form_data = array(
        'id' => $form_id,
        'title' => $contact_form->title(),
        'locale' => $contact_form->locale(),
        'form' => $contact_form->prop('form'),
        'fields' => malet_parse_form_fields($contact_form->prop('form')),
        'mail' => $contact_form->prop('mail'),
        'messages' => $contact_form->prop('messages'),
    );

    return new WP_REST_Response($form_data, 200);
}

/**
 * Processar enviament de formulari
 */
function malet_submit_form($request) {
    $params = $request->get_params();

    // Validacions inicials
    if (!isset($params['form_id'])) {
        return new WP_Error('missing_form_id', 'ID de formulari requerit', array('status' => 400));
    }

    $form_id = intval($params['form_id']);
    $contact_form = WPCF7_ContactForm::get_instance($form_id);

    if (!$contact_form) {
        return new WP_Error('form_not_found', 'Formulari no trobat', array('status' => 404));
    }

    // Proteccions de seguretat
    $rate_limit_check = malet_forms_rate_limiting();
    if (is_wp_error($rate_limit_check)) {
        return $rate_limit_check;
    }

    if (!malet_validate_honeypot($params)) {
        return new WP_Error('spam_detected', 'Spam detectat', array('status' => 403));
    }

    // Preparar context per CF7
    malet_setup_cf7_context($form_id, $contact_form, $params);

    // Processar formulari (dispara hooks de CF7 i Flamingo)
    $contact_form->submit();
    $submission = WPCF7_Submission::get_instance();

    if (!$submission) {
        malet_cleanup_globals();
        return new WP_Error('submission_failed', 'Error al processar el formulari', array('status' => 500));
    }

    // Generar resposta
    $result = malet_build_submission_response($form_id, $submission);

    malet_cleanup_globals();

    return new WP_REST_Response($result, $result['status'] === 'mail_sent' ? 200 : 400);
}

/**
 * Configurar context global per CF7
 */
function malet_setup_cf7_context($form_id, $contact_form, $params) {
    $_POST = array();

    // Copiar camps del formulari
    foreach ($params as $key => $value) {
        if ($key !== 'form_id') {
            $_POST[$key] = $value;
        }
    }

    // Metadades CF7
    $_POST['_wpcf7'] = $form_id;
    $_POST['_wpcf7_version'] = WPCF7_VERSION;
    $_POST['_wpcf7_locale'] = $contact_form->locale();
    $_POST['_wpcf7_unit_tag'] = 'wpcf7-f' . $form_id . '-api-' . time();
    $_POST['_wpcf7_container_post'] = 0;
    $_POST['_wpnonce'] = wp_create_nonce('wpcf7-submit');

    $_SERVER['REQUEST_METHOD'] = 'POST';

    if (isset($params['files']) && is_array($params['files'])) {
        $_FILES = $params['files'];
    }
}

/**
 * Construir resposta per l'API
 */
function malet_build_submission_response($form_id, $submission) {
    $result = array(
        'contact_form_id' => $form_id,
        'status' => $submission->get_status(),
        'message' => $submission->get_response(),
        'invalid_fields' => array(),
    );

    // Afegir errors de validació si n'hi ha
    if ($submission->get_status() === 'validation_failed') {
        $invalid_fields = $submission->get_invalid_fields();
        if ($invalid_fields) {
            foreach ($invalid_fields as $field_name => $field_error) {
                // Gestionar diferents tipus de retorn segons versió de CF7
                if (is_wp_error($field_error)) {
                    // CF7 modern: objecte WP_Error
                    $result['invalid_fields'][$field_name] = $field_error->get_error_message();
                } elseif (is_array($field_error)) {
                    // CF7 antic: array d'errors
                    $result['invalid_fields'][$field_name] = implode(', ', $field_error);
                } else {
                    // Fallback: string simple
                    $result['invalid_fields'][$field_name] = (string) $field_error;
                }
            }
        }
    }

    return $result;
}

/**
 * Netejar variables globals
 */
function malet_cleanup_globals() {
    $_POST = array();
    $_FILES = array();
}

/**
 * Obtenir submissions guardades (Flamingo)
 */
function malet_get_submissions($request) {
    if (!class_exists('Flamingo_Inbound_Message')) {
        return new WP_Error('flamingo_not_active', 'Flamingo no està activat', array('status' => 500));
    }

    $args = array(
        'posts_per_page' => $request->get_param('per_page') ?: 20,
        'paged' => $request->get_param('page') ?: 1,
        'orderby' => 'date',
        'order' => 'DESC',
    );

    // Filtrar per form_id si es proporciona
    if ($request->get_param('form_id')) {
        $args['meta_query'] = array(
            array(
                'key' => '_contact_form_id',
                'value' => intval($request->get_param('form_id')),
                'compare' => '=',
            ),
        );
    }

    $submissions = Flamingo_Inbound_Message::find($args);
    $total = Flamingo_Inbound_Message::count();

    $submissions_data = array();

    foreach ($submissions as $submission) {
        $submissions_data[] = array(
            'id' => $submission->id(),
            'subject' => $submission->subject,
            'from' => $submission->from,
            'from_name' => $submission->from_name,
            'from_email' => $submission->from_email,
            'fields' => $submission->fields,
            'meta' => $submission->meta,
            'date' => $submission->date,
            'form_id' => get_post_meta($submission->id(), '_contact_form_id', true),
        );
    }

    return new WP_REST_Response(array(
        'submissions' => $submissions_data,
        'total' => $total,
        'page' => intval($args['paged']),
        'per_page' => intval($args['posts_per_page']),
    ), 200);
}

/**
 * Parsejar camps del formulari
 */
function malet_parse_form_fields($form_content) {
    $fields = array();

    // Expresió regular per trobar camps del formulari
    preg_match_all('/\[([^\]]+)\]/', $form_content, $matches);

    foreach ($matches[1] as $tag) {
        $tag_parts = explode(' ', $tag);
        $type = str_replace('*', '', $tag_parts[0]);

        // Saltar botons i honeypots
        if (in_array($type, array('submit', 'honeypot'))) {
            continue;
        }

        $field = array(
            'type' => $type,
            'name' => isset($tag_parts[1]) ? $tag_parts[1] : '',
            'required' => strpos($tag_parts[0], '*') !== false,
            'options' => array(),
        );

        // Per selects, extreure opcions
        if ($type === 'select' && count($tag_parts) > 2) {
            for ($i = 2; $i < count($tag_parts); $i++) {
                $field['options'][] = trim($tag_parts[$i], '"');
            }
        }

        $fields[] = $field;
    }

    return $fields;
}

/**
 * Endpoint de debug per verificar últimes submissions
 * Només accessible en entorns local/development
 */
function malet_debug_recent_submissions($request) {
    $environment = wp_get_environment_type();

    // Verificar entorn
    if (!in_array($environment, array('local', 'development'))) {
        return new WP_Error(
            'debug_disabled',
            'Aquest endpoint només està disponible en mode desenvolupament',
            array('status' => 403)
        );
    }

    // Verificar que Flamingo està actiu
    if (!class_exists('Flamingo_Inbound_Message')) {
        return new WP_REST_Response(array(
            'environment' => $environment,
            'flamingo_active' => false,
            'error' => 'Flamingo no està instal·lat o activat',
            'submissions' => array(),
        ), 200);
    }

    // Obtenir paràmetres opcionals
    $limit = $request->get_param('limit') ? intval($request->get_param('limit')) : 10;
    $form_id = $request->get_param('form_id') ? intval($request->get_param('form_id')) : null;

    // Arguments per cercar submissions
    $args = array(
        'posts_per_page' => $limit,
        'orderby' => 'date',
        'order' => 'DESC',
    );

    // Filtrar per form_id si es proporciona
    if ($form_id) {
        $args['meta_query'] = array(
            array(
                'key' => '_contact_form_id',
                'value' => $form_id,
                'compare' => '=',
            ),
        );
    }

    // Obtenir submissions
    $submissions = Flamingo_Inbound_Message::find($args);
    $total = Flamingo_Inbound_Message::count();

    // Processar submissions amb tots els detalls
    $submissions_data = array();

    foreach ($submissions as $submission) {
        $submission_id = $submission->id();
        $cf7_form_id = get_post_meta($submission_id, '_contact_form_id', true);

        // Obtenir nom del formulari CF7
        $form_title = 'Unknown';
        if ($cf7_form_id) {
            $cf7_form = WPCF7_ContactForm::get_instance($cf7_form_id);
            if ($cf7_form) {
                $form_title = $cf7_form->title();
            }
        }

        // Formatar dades per debug
        $date_formatted = 'N/A';
        if ($submission->date) {
            $timestamp = strtotime($submission->date);
            if ($timestamp !== false) {
                $date_formatted = date('d/m/Y H:i:s', $timestamp);
            }
        }

        $submissions_data[] = array(
            'flamingo_id' => $submission_id,
            'cf7_form_id' => $cf7_form_id,
            'cf7_form_title' => $form_title,
            'subject' => $submission->subject,
            'from' => $submission->from,
            'from_name' => $submission->from_name,
            'from_email' => $submission->from_email,
            'date' => $submission->date,
            'date_formatted' => $date_formatted,
            'fields' => $submission->fields,
            'meta' => $submission->meta,
        );
    }

    // Resposta detallada per debug
    return new WP_REST_Response(array(
        'debug_info' => array(
            'environment' => $environment,
            'timestamp' => current_time('mysql'),
            'timezone' => wp_timezone_string(),
            'flamingo_active' => true,
            'cf7_version' => defined('WPCF7_VERSION') ? WPCF7_VERSION : 'unknown',
        ),
        'query_info' => array(
            'limit' => $limit,
            'form_id_filter' => $form_id,
            'total_submissions' => $total,
            'showing' => count($submissions_data),
        ),
        'submissions' => $submissions_data,
        'usage' => array(
            'endpoint' => '/wp-json/malet-torrent/v1/forms/debug/recent',
            'parameters' => array(
                'limit' => '(optional) Number of submissions to show (default: 10)',
                'form_id' => '(optional) Filter by Contact Form 7 ID',
            ),
            'example' => '/wp-json/malet-torrent/v1/forms/debug/recent?limit=5&form_id=85',
        ),
    ), 200);
}

/**
 * Afegir headers CORS específics per formularis
 */
function malet_forms_cors_headers() {
    // Detectar entorn actual
    $environment = wp_get_environment_type();

    // Definir origins permesos segons entorn
    $allowed_origins = array();

    switch ($environment) {
        case 'local':
        case 'development':
            $allowed_origins = array(
                'http://localhost:3000',
                'http://localhost:3001',
                'http://127.0.0.1:3000',
            );
            break;

        case 'staging':
            $allowed_origins = array(
                'https://staging.malet.testart.cat',
                'https://malet.testart.cat',
            );
            break;

        case 'production':
            $allowed_origins = array(
                'https://malet.cat',
                'https://www.malet.cat',
            );
            break;

        default:
            // Fallback a valor de configuració
            $custom_origin = get_option('malet_frontend_url', '');
            if ($custom_origin) {
                $allowed_origins = array($custom_origin);
            }
            break;
    }

    // Obtenir origin de la petició
    $request_origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';

    // Verificar si l'origin està permès
    $allowed_origin = '*'; // Per defecte permetre tots (només per desenvolupament)

    if (in_array($request_origin, $allowed_origins)) {
        $allowed_origin = $request_origin;
    } elseif ($environment === 'production' || $environment === 'staging') {
        // En producció/staging, només permetre origins definits
        $allowed_origin = !empty($allowed_origins) ? $allowed_origins[0] : '';
    }

    // Configurar headers CORS
    if ($allowed_origin) {
        header("Access-Control-Allow-Origin: " . $allowed_origin);
    }

    header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-WP-Nonce");
    header("Access-Control-Allow-Credentials: true");

    // Per peticions OPTIONS (preflight)
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header("Access-Control-Max-Age: 86400");
        status_header(200);
        exit(0);
    }
}
add_action('rest_api_init', 'malet_forms_cors_headers', 5);

/**
 * Configurar rate limiting per formularis
 */
function malet_forms_rate_limiting() {
    // Desactivat temporalment per testing
    $environment = wp_get_environment_type();
    if (in_array($environment, array('local', 'development'))) {
        return true;
    }

    $transient_key = 'malet_form_submit_' . $_SERVER['REMOTE_ADDR'];
    $submissions = get_transient($transient_key);

    if ($submissions === false) {
        $submissions = 0;
    }

    // Màxim 5 submissions per IP cada 10 minuts
    if ($submissions >= 5) {
        return new WP_Error('rate_limit_exceeded', 'Has superat el límit de submissions. Prova més tard.', array('status' => 429));
    }

    set_transient($transient_key, $submissions + 1, 600); // 10 minuts

    return true;
}

/**
 * Validació honeypot anti-spam
 */
function malet_validate_honeypot($params) {
    $honeypot_fields = array('honeypot-472', 'honeypot-custom', 'honeypot-news');

    foreach ($honeypot_fields as $field) {
        if (isset($params[$field]) && !empty($params[$field])) {
            return false; // Bot detectat
        }
    }

    return true;
}

/**
 * Guardar form_id a Flamingo després de submission
 * Això complementa flamingo_subject, flamingo_name i flamingo_email
 */
function malet_save_form_id_to_flamingo($contact_form) {
    if (!class_exists('Flamingo_Inbound_Message')) {
        return;
    }

    $submission = WPCF7_Submission::get_instance();
    if (!$submission || $submission->get_status() !== 'mail_sent') {
        return;
    }

    // Buscar última submission per IP
    $messages = Flamingo_Inbound_Message::find(array(
        'posts_per_page' => 1,
        'orderby' => 'date',
        'order' => 'DESC',
    ));

    if (empty($messages)) {
        return;
    }

    $message = $messages[0];
    $message_id = $message->id();
    $message_meta = get_post_meta($message_id, '_meta', true);

    // Verificar que és la mateixa IP (seguretat)
    if (!is_array($message_meta) ||
        !isset($message_meta['remote_ip']) ||
        $message_meta['remote_ip'] !== $_SERVER['REMOTE_ADDR']) {
        return;
    }

    // Guardar form_id
    update_post_meta($message_id, '_contact_form_id', $contact_form->id());
}
add_action('wpcf7_mail_sent', 'malet_save_form_id_to_flamingo');