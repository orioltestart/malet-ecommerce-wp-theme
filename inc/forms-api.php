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

    // Validar que tenim ID de formulari
    if (!isset($params['form_id'])) {
        return new WP_Error('missing_form_id', 'ID de formulari requerit', array('status' => 400));
    }

    $form_id = intval($params['form_id']);
    $contact_form = WPCF7_ContactForm::get_instance($form_id);

    if (!$contact_form) {
        return new WP_Error('form_not_found', 'Formulari no trobat', array('status' => 404));
    }

    // Preparar dades per Contact Form 7
    $_POST = array_merge($_POST, $params);
    $_POST['_wpcf7'] = $form_id;
    $_POST['_wpcf7_version'] = WPCF7_VERSION;
    $_POST['_wpcf7_locale'] = $contact_form->locale();
    $_POST['_wpcf7_unit_tag'] = 'wpcf7-f' . $form_id . '-p' . get_the_ID() . '-o1';
    $_POST['_wpcf7_container_post'] = 0;

    // Processar submission
    $submission = WPCF7_Submission::get_instance($contact_form);

    if (!$submission) {
        $submission = WPCF7_Submission::get_instance($contact_form, array(
            'skip_mail' => false,
        ));
    }

    $result = array(
        'contact_form_id' => $form_id,
        'status' => $submission ? $submission->get_status() : 'failed',
        'message' => $submission ? $submission->get_response() : 'Error processant el formulari',
        'invalid_fields' => array(),
    );

    // Si hi ha errors de validació
    if ($submission && $submission->get_status() === 'validation_failed') {
        $invalid_fields = $submission->get_invalid_fields();
        if ($invalid_fields) {
            $result['invalid_fields'] = $invalid_fields;
        }
    }

    $status_code = ($result['status'] === 'mail_sent') ? 200 : 400;

    return new WP_REST_Response($result, $status_code);
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
 * Afegir headers CORS específics per formularis
 */
function malet_forms_cors_headers() {
    $allowed_origin = get_option('malet_frontend_url', 'https://malet.testart.cat');

    // Permetre origen del frontend
    header("Access-Control-Allow-Origin: " . $allowed_origin);
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    header("Access-Control-Allow-Credentials: true");

    // Per peticions OPTIONS (preflight)
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header("Access-Control-Max-Age: 86400");
        exit(0);
    }
}
add_action('rest_api_init', 'malet_forms_cors_headers', 5);

/**
 * Configurar rate limiting per formularis
 */
function malet_forms_rate_limiting() {
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