<?php

/**
 * Email Templates for Malet Torrent
 * Plantilles d'email personalitzades per Contact Form 7 i WooCommerce
 *
 * @package Malet Torrent
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Malet_Torrent_Email_Templates
{

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
        // Contact Form 7 hooks
        add_filter('wpcf7_mail_components', [$this, 'customize_cf7_mail'], 10, 3);

        // WooCommerce hooks
        add_action('woocommerce_email_header', [$this, 'woocommerce_email_header'], 10, 1);
        add_action('woocommerce_email_footer', [$this, 'woocommerce_email_footer'], 10, 1);
        add_filter('woocommerce_email_styles', [$this, 'add_woocommerce_email_styles']);
        add_filter('woocommerce_email_footer_text', '__return_empty_string', 999);

        // Eliminar textos promocionals de WooCommerce
        add_filter('woocommerce_get_settings_emails', [$this, 'remove_wc_promo_text'], 999);
        add_filter('woocommerce_email_get_option', [$this, 'filter_wc_email_options'], 999, 4);

        // WordPress Core Email hooks
        add_filter('wp_mail', [$this, 'customize_wp_mail'], 10, 1);

        // Password related emails
        add_filter('password_reset_message', [$this, 'customize_password_reset_email'], 10, 4);
        add_filter('wp_password_change_notification_email', [$this, 'customize_password_change_email'], 10, 3);

        // User registration emails
        add_filter('wp_new_user_notification_email', [$this, 'customize_new_user_email'], 10, 3);
        add_filter('wp_new_user_notification_email_admin', [$this, 'customize_new_user_admin_email'], 10, 3);

        // Comment emails
        add_filter('comment_notification_text', [$this, 'customize_comment_notification'], 10, 2);
        add_filter('comment_moderation_text', [$this, 'customize_comment_moderation'], 10, 2);

        // Admin emails
        add_filter('wp_site_admin_email_change_notification_email', [$this, 'customize_admin_email_change'], 10, 3);

        // Email address change
        add_filter('email_change_email', [$this, 'customize_email_change_notification'], 10, 3);

        // Custom email styles
        add_action('wp_head', [$this, 'add_email_styles']);
    }

    /**
     * Get email header HTML
     */
    public function get_email_header($title = '')
    {
        $site_url = home_url();
        // Usar el logo existent del directori assets/img/
        $logo_url = get_template_directory_uri() . '/assets/img/logo.png';

        if (empty($title)) {
            $title = 'Malet Torrent - Pastisseria Tradicional Catalana';
        }

        return '
        <!DOCTYPE html>
        <html lang="ca">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>' . esc_html($title) . '</title>
            <style>
                ' . $this->get_email_styles() . '
            </style>
        </head>
        <body>
            <div class="email-container">
                <!-- Header -->
                <header class="email-header">
                    <div class="header-content">
                        <a href="' . esc_url($site_url) . '" class="logo-link">
                            <img src="' . esc_url($logo_url) . '" alt="Malet Torrent" class="logo" width="200" height="60">
                        </a>
                        <div class="header-tagline">
                            <p>Pastisseria Tradicional Catalana des de 1973</p>
                        </div>
                    </div>
                </header>

                <!-- Main Content -->
                <main class="email-content">
        ';
    }

    /**
     * Get email footer HTML (versió simple per autoresponders)
     */
    public function get_email_footer_simple()
    {
        $current_year = date('Y');

        return '
                </main>

                <!-- Footer Simple -->
                <footer class="email-footer">
                    <div class="footer-bottom">
                        <div class="footer-divider"></div>
                        <p class="copyright">
                            © ' . $current_year . ' Malet Torrent. Tots els drets reservats.<br>
                            <small>Elaborem amb ingredients naturals i seguint les receptes tradicionals catalanes.</small>
                        </p>
                    </div>
                </footer>
            </div>
        </body>
        </html>
        ';
    }

    /**
     * Get email footer HTML (versió completa per administració)
     */
    public function get_email_footer()
    {
        $site_url = home_url();
        $current_year = date('Y');

        return '
                </main>

                <!-- Footer -->
                <footer class="email-footer">
                    <div class="footer-bottom">
                        <div class="footer-divider"></div>
                        <p class="copyright">
                            © ' . $current_year . ' Malet Torrent. Tots els drets reservats.<br>
                            <small>Elaborem amb ingredients naturals i seguint les receptes tradicionals catalanes.</small>
                        </p>
                        <p class="unsubscribe">
                            <small>Si no vols rebre més correus, pots <a href="#" class="unsubscribe-link">donar-te de baixa aquí</a>.</small>
                        </p>
                    </div>
                </footer>
            </div>
        </body>
        </html>
        ';
    }

    /**
     * Get email CSS styles
     */
    public function get_email_styles()
    {
        return '
        /* Reset i base */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
            background-color: #f8f9fa;
            padding: 20px 0;
        }

        /* Container principal */
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        /* Header */
        .email-header {
            background: #f2e3d7;
            color: #5b493a;
            padding: 15px 20px;
            text-align: center;
        }

        .logo {
            max-width: 200px;
            height: auto;
            margin-bottom: 10px;
        }

        .logo-link {
            display: inline-block;
            text-decoration: none;
        }

        .header-tagline p {
            font-size: 14px;
            color: #5b493a;
            opacity: 0.8;
            margin: 0;
            font-weight: 300;
        }

        .header-divider {
            height: 3px;
            background: linear-gradient(90deg, transparent 0%, #5b493a 50%, transparent 100%);
        }

        /* Contingut principal */
        .email-content {
            padding: 40px 30px;
            line-height: 1.7;
        }

        .email-content h1 {
            color: #5b493a;
            font-size: 28px;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .email-content h2 {
            color: #5b493a;
            font-size: 22px;
            margin: 30px 0 15px 0;
            font-weight: 500;
        }

        .email-content h3 {
            color: #5b493a;
            font-size: 18px;
            margin: 25px 0 10px 0;
            font-weight: 500;
        }

        .email-content p {
            margin-bottom: 15px;
            color: #555555;
        }

        .email-content ul {
            margin: 15px 0;
            padding-left: 20px;
        }

        .email-content li {
            margin-bottom: 8px;
            color: #555555;
        }

        /* Botons */
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background-color: #5b493a;
            color: white !important;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
            margin: 10px 0;
            transition: background-color 0.3s ease;
        }

        .btn:hover {
            background-color: #6b5a4a;
        }

        .btn-secondary {
            background-color: #6c757d;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
        }

        /* Caixes destacades */
        .info-box {
            background-color: #f2e3d7;
            border-left: 4px solid #5b493a;
            padding: 20px;
            margin: 20px 0;
            border-radius: 0 6px 6px 0;
        }

        .success-box {
            background-color: #d4edda;
            border-left: 4px solid #28a745;
            padding: 20px;
            margin: 20px 0;
            border-radius: 0 6px 6px 0;
            color: #155724;
        }

        .warning-box {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 20px;
            margin: 20px 0;
            border-radius: 0 6px 6px 0;
            color: #856404;
        }

        /* Taules (per WooCommerce) */
        .email-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            border-radius: 6px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .email-table th {
            background-color: #5b493a;
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 500;
        }

        .email-table td {
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
        }

        .email-table tr:nth-child(even) {
            background-color: #f8f9fa;
        }

        .email-table tr:hover {
            background-color: #e9ecef;
        }

        /* Footer */
        .email-footer {
            background-color: #5b493a;
            color: white;
            padding: 0px 30px 20px;
        }

        .footer-content {
            display: block;
            margin-bottom: 30px;
        }

        .footer-section {
            width: 100%;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #34495e;
        }

        .footer-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .footer-content h3 {
            color: #f2e3d7;
            margin-bottom: 15px;
            font-size: 16px;
            font-weight: 500;
        }

        .footer-content p {
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 10px;
        }

        .footer-content ul {
            list-style: none;
            padding: 0;
        }

        .footer-content li {
            margin-bottom: 8px;
        }

        .footer-content a {
            color: #ecf0f1;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .footer-content a:hover {
            color: #f2e3d7;
        }

        .social-icons {
            display: flex;
            gap: 15px;
        }

        .social-link {
            display: inline-block;
            padding: 8px 12px;
            background-color: #34495e;
            border-radius: 4px;
            font-size: 12px;
            transition: background-color 0.3s ease;
        }

        .social-link:hover {
            background-color: #5b493a;
        }

        .footer-divider {
            height: 1px;
            background: linear-gradient(90deg, transparent 0%, #f2e3d7 50%, transparent 100%);
            margin: 20px 0;
        }

        .footer-bottom {
            text-align: center;
            padding-top: 20px;
        }

        .copyright {
            font-size: 14px;
            margin-bottom: 10px;
            color: white !important;
        }

        .copyright small {
            color: white !important;
        }

        .unsubscribe {
            font-size: 12px;
            opacity: 0.8;
            color: white !important;
        }

        .unsubscribe-link {
            color: #f2e3d7 !important;
        }

        /* Responsive */
        @media only screen and (max-width: 600px) {
            .email-container {
                margin: 0 10px;
            }

            .email-content {
                padding: 30px 20px;
            }

            .email-footer {
                padding: 30px 20px 15px;
            }

            .footer-content {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .social-icons {
                justify-content: center;
                flex-wrap: wrap;
            }

            .email-table {
                font-size: 14px;
            }

            .email-table th,
            .email-table td {
                padding: 10px;
            }
        }

        @media only screen and (max-width: 480px) {
            .email-content h1 {
                font-size: 24px;
            }

            .email-content h2 {
                font-size: 20px;
            }

            .btn {
                display: block;
                text-align: center;
                margin: 15px 0;
            }
        }
        ';
    }

    /**
     * Customize Contact Form 7 emails
     */
    public function customize_cf7_mail($components, $cf7, $object)
    {
        // Get form data from submission
        $submission = WPCF7_Submission::get_instance();
        if (!$submission) {
            return $components;
        }

        $form_data = $submission->get_posted_data();
        $mail_name = $object->name();

        // Aplicar template diferent segons si és mail principal o autoresponder
        if ($mail_name === 'mail') {
            // Email per administrador (amb tots els detalls)
            $custom_content = $this->create_contact_form_email($form_data, $cf7);
        } elseif ($mail_name === 'mail_2') {
            // Email per client (autoresponder elegant)
            $custom_content = $this->create_autoresponder_email($form_data, $cf7);
        } else {
            return $components;
        }

        // Replace the mail body with our custom template
        $components['body'] = $custom_content;

        // Set HTML content type
        $components['additional_headers'] = "Content-Type: text/html; charset=UTF-8";

        return $components;
    }

    /**
     * Create Contact Form email content
     */
    private function create_contact_form_email($form_data, $cf7)
    {
        $form_title = $cf7->title();

        // Suportar tant els camps nous (full-name, email...) com els antics (your-name, your-email...)
        $name = $form_data['full-name'] ?? $form_data['your-name'] ?? '';
        $email = $form_data['email'] ?? $form_data['your-email'] ?? '';
        $phone = $form_data['phone'] ?? $form_data['your-phone'] ?? '';
        $subject = $form_data['subject'] ?? $form_data['your-subject'] ?? '';
        $message = $form_data['message'] ?? $form_data['your-message'] ?? '';

        ob_start();
        echo $this->get_email_header('Nou missatge de contacte - ' . $form_title);
?>

        <div class="success-box">
            <h1>📧 Nou missatge de contacte</h1>
            <p>Has rebut un nou missatge des del formulari de contacte.</p>
        </div>

        <h2>Informació del contacte:</h2>

        <div class="info-box">
            <?php if (!empty($name)): ?>
                <p><strong>👤 Nom:</strong> <?php echo esc_html($name); ?></p>
            <?php endif; ?>

            <?php if (!empty($email)): ?>
                <p><strong>✉️ Email:</strong> <a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a></p>
            <?php endif; ?>

            <?php if (!empty($phone)): ?>
                <p><strong>📞 Telèfon:</strong> <a href="tel:<?php echo esc_attr($phone); ?>"><?php echo esc_html($phone); ?></a></p>
            <?php endif; ?>

            <?php if (!empty($subject)): ?>
                <p><strong>📋 Assumpte:</strong> <?php echo esc_html($subject); ?></p>
            <?php endif; ?>

            <p><strong>📅 Data:</strong> <?php echo date('d/m/Y H:i'); ?></p>
        </div>

        <?php if (!empty($message)): ?>
            <h3>💬 Missatge:</h3>
            <div style="background-color: #f2e3d7; padding: 20px; border-radius: 6px; border-left: 4px solid #5b493a; white-space: pre-line;">
                <?php echo esc_html($message); ?>
            </div>
        <?php endif; ?>

        <div style="margin: 30px 0; text-align: center;">
            <a href="mailto:<?php echo esc_attr($email); ?>?subject=Re: <?php echo esc_attr($subject ?: 'Consulta'); ?>" class="btn">
                Respondre per email
            </a>
            <?php if (!empty($phone)): ?>
                <a href="tel:<?php echo esc_attr($phone); ?>" class="btn btn-secondary">
                    Trucar
                </a>
            <?php endif; ?>
        </div>

    <?php
        echo $this->get_email_footer();

        return ob_get_clean();
    }

    /**
     * Create Autoresponder email content (per al client)
     */
    private function create_autoresponder_email($form_data, $cf7)
    {
        // Obtenir nom del client
        $name = $form_data['full-name'] ?? $form_data['your-name'] ?? 'estimat client';

        ob_start();
        echo $this->get_email_header('Hem rebut el teu missatge');
    ?>

        <p style="font-size: 18px; color: #333;">Hola <strong><?php echo esc_html($name); ?></strong>,</p>

        <p>Gràcies per contactar amb nosaltres!</p>

        <p>Hem rebut el teu missatge i t'el llegirem amb atenció. <strong>Et respondrem el més aviat possible.</strong></p>

        <p style="margin-top: 30px;">Una forta abraçada,<br>
            <em style="color: #5b493a; font-weight: 500;">L'equip de Malet Torrent</em>
        </p>

    <?php
        echo $this->get_email_footer_simple();

        return ob_get_clean();
    }

    /**
     * WooCommerce email header
     */
    public function woocommerce_email_header($email_heading)
    {
        echo $this->get_email_header($email_heading);
    }

    /**
     * WooCommerce email footer (sense enllaç de baixa - són emails transaccionals)
     */
    public function woocommerce_email_footer()
    {
        echo $this->get_email_footer_simple();
    }

    /**
     * Add WooCommerce email styles
     */
    public function add_woocommerce_email_styles($css)
    {
        // Ocultar informació de botiga i textos promocionals de WooCommerce
        $hide_wc_footer = '
        /* Ocultar informació automàtica de WooCommerce */
        #template_footer_text,
        .wc-footer-text,
        .woocommerce-email-footer-text,
        #template_container #template_footer #template_footer_id,
        .woocommerce-email-footer-cta,
        #body_content_inner .wc-app-link,
        .woocommerce-store-notice,
        #credit {
            display: none !important;
            visibility: hidden !important;
            height: 0 !important;
            overflow: hidden !important;
        }
        ';

        return $this->get_email_styles() . $hide_wc_footer . $css;
    }

    /**
     * Eliminar textos promocionals de configuració
     */
    public function remove_wc_promo_text($settings)
    {
        return $settings;
    }

    /**
     * Filtrar opcions d'email per eliminar textos promocionals
     */
    public function filter_wc_email_options($value, $object, $name, $default)
    {
        // Eliminar textos com "Process your orders on the go. Get the app"
        if ($name === 'additional_content' || $name === 'footer_text') {
            $promo_texts = [
                'Process your orders on the go',
                'Get the app',
                'Download',
                'iOS',
                'Android'
            ];

            foreach ($promo_texts as $promo) {
                if (stripos($value, $promo) !== false) {
                    return '';
                }
            }
        }

        return $value;
    }

    /**
     * Add email styles to head (for preview)
     */
    public function add_email_styles()
    {
        if (is_admin() && isset($_GET['preview_email'])) {
            echo '<style>' . $this->get_email_styles() . '</style>';
        }
    }

    /**
     * Customize all WordPress emails to use HTML
     */
    public function customize_wp_mail($args)
    {
        // Only process plain text emails
        if (!isset($args['headers']) || !is_array($args['headers'])) {
            $args['headers'] = [];
        }

        // Check if already HTML
        $has_html_header = false;
        foreach ($args['headers'] as $header) {
            if (strpos($header, 'Content-Type: text/html') !== false) {
                $has_html_header = true;
                break;
            }
        }

        // Convert to HTML if not already
        if (!$has_html_header) {
            $args['headers'][] = 'Content-Type: text/html; charset=UTF-8';

            // Wrap plain text content in our template
            if (!empty($args['message']) && !$this->is_html_content($args['message'])) {
                $args['message'] = $this->wrap_plain_text_email($args['message'], $args['subject']);
            }
        }

        return $args;
    }

    /**
     * Check if content is already HTML
     */
    private function is_html_content($content)
    {
        return strpos($content, '<html') !== false || strpos($content, '<!DOCTYPE') !== false;
    }

    /**
     * Wrap plain text email in our HTML template
     */
    private function wrap_plain_text_email($message, $subject)
    {
        ob_start();
        echo $this->get_email_header($subject);
    ?>

        <div style="white-space: pre-line; line-height: 1.6;">
            <?php echo nl2br(esc_html($message)); ?>
        </div>

        <div class="info-box" style="margin-top: 30px;">
            <h3>🍪 Sobre Malet Torrent</h3>
            <p>Som una pastisseria tradicional catalana especialitzada en melindros artesans. Elaborem els nostres productes seguint les receptes familiars transmeses de generació en generació, sense colorants ni conservants artificials.</p>
        </div>

    <?php
        echo $this->get_email_footer();

        return ob_get_clean();
    }

    /**
     * Customize password reset email
     */
    public function customize_password_reset_email($message, $key, $user_login, $user_data)
    {
        $reset_url = network_site_url("wp-login.php?action=resetpass&key=$key&login=" . rawurlencode($user_login), 'login');

        ob_start();
        echo $this->get_email_header('Restablir contrasenya - Malet Torrent');
    ?>

        <div class="info-box">
            <h1>🔑 Sol·licitud de restabliment de contrasenya</h1>
            <p>Hola <strong><?php echo esc_html($user_data->display_name); ?></strong>,</p>
            <p>Hem rebut una sol·licitud per restablir la contrasenya del vostre compte a Malet Torrent.</p>
        </div>

        <div style="text-align: center; margin: 30px 0;">
            <p><strong>Nom d'usuari:</strong> <?php echo esc_html($user_login); ?></p>

            <a href="<?php echo esc_url($reset_url); ?>" class="btn" style="display: inline-block; padding: 15px 30px; background-color: #5b493a; color: white; text-decoration: none; border-radius: 8px; font-weight: 500; margin: 20px 0;">
                🔑 Restablir contrasenya
            </a>
        </div>

        <div class="warning-box">
            <h3>⚠️ Important</h3>
            <ul>
                <li>Aquest enllaç és vàlid durant 24 hores</li>
                <li>Si no heu sol·licitat aquest canvi, ignoreu aquest correu</li>
                <li>No compartiu aquest enllaç amb ningú</li>
            </ul>
        </div>

        <div class="info-box">
            <h3>💬 Necessiteu ajuda?</h3>
            <p>Si teniu problemes per restablir la contrasenya, contacteu-nos:</p>
            <p>
                <strong>📞 Telèfon:</strong> 972 86 93 08<br>
                <strong>✉️ Email:</strong> <a href="mailto:info@malet.cat">info@malet.cat</a>
            </p>
        </div>

    <?php
        echo $this->get_email_footer();

        return ob_get_clean();
    }

    /**
     * Customize password change notification
     */
    public function customize_password_change_email($email_data, $user, $userdata)
    {
        ob_start();
        echo $this->get_email_header('Contrasenya canviada - Malet Torrent');
    ?>

        <div class="success-box">
            <h1>✅ Contrasenya canviada correctament</h1>
            <p>Hola <strong><?php echo esc_html($user['display_name']); ?></strong>,</p>
            <p>Us confirmem que la contrasenya del vostre compte a Malet Torrent ha estat canviada correctament.</p>
        </div>

        <div class="info-box">
            <p><strong>📅 Data del canvi:</strong> <?php echo date('d/m/Y H:i'); ?></p>
            <p><strong>👤 Compte:</strong> <?php echo esc_html($user['user_login']); ?></p>
            <p><strong>✉️ Email:</strong> <?php echo esc_html($user['user_email']); ?></p>
        </div>

        <div class="warning-box">
            <h3>🔒 Seguretat</h3>
            <p>Si no heu estat vós qui ha canviat la contrasenya, contacteu-nos immediatament per protegir el vostre compte.</p>
        </div>

    <?php
        echo $this->get_email_footer();

        $email_data['message'] = ob_get_clean();
        return $email_data;
    }

    /**
     * Customize new user notification email
     */
    public function customize_new_user_email($email_data, $user, $blogname)
    {
        ob_start();
        echo $this->get_email_header('Benvingut/da a Malet Torrent');
    ?>

        <div class="success-box">
            <h1>🎉 Benvingut/da a la família Malet Torrent!</h1>
            <p>Hola <strong><?php echo esc_html($user->display_name); ?></strong>,</p>
            <p>Ens alegrem de tenir-vos com a membre de la nostra comunitat de melindros tradicionals catalans.</p>
        </div>

        <div class="info-box">
            <h3>📋 Detalls del vostre compte:</h3>
            <p><strong>👤 Nom d'usuari:</strong> <?php echo esc_html($user->user_login); ?></p>
            <p><strong>✉️ Email:</strong> <?php echo esc_html($user->user_email); ?></p>
            <p><strong>📅 Data de registre:</strong> <?php echo date('d/m/Y H:i'); ?></p>
        </div>

        <div style="text-align: center; margin: 30px 0;">
            <h3>🚀 Comenceu ara</h3>
            <p>
                <a href="<?php echo wp_login_url(); ?>" class="btn">Iniciar sessió</a>
                <a href="<?php echo home_url('/productes'); ?>" class="btn btn-secondary">Veure productes</a>
            </p>
        </div>

        <div class="info-box">
            <h3>🍪 Els nostres melindros</h3>
            <p>Descobriu la nostra gamma de melindros tradicionals catalans, elaborats artesanalment amb ingredients naturals i seguint les receptes familiars de sempre.</p>

            <ul>
                <li>🌟 Melindros tradicionals</li>
                <li>🍫 Melindros amb xocolata</li>
                <li>🥜 Carquinyolis amb ametlles</li>
                <li>🔨 Malets (la nostra especialitat)</li>
            </ul>
        </div>

    <?php
        echo $this->get_email_footer();

        $email_data['message'] = ob_get_clean();
        return $email_data;
    }

    /**
     * Customize new user admin notification
     */
    public function customize_new_user_admin_email($email_data, $user, $blogname)
    {
        ob_start();
        echo $this->get_email_header('Nou usuari registrat - Malet Torrent');
    ?>

        <div class="info-box">
            <h1>👤 Nou usuari registrat</h1>
            <p>S'ha registrat un nou usuari al lloc web de Malet Torrent.</p>
        </div>

        <div class="info-box">
            <h3>📋 Detalls de l'usuari:</h3>
            <p><strong>👤 Nom d'usuari:</strong> <?php echo esc_html($user->user_login); ?></p>
            <p><strong>✉️ Email:</strong> <?php echo esc_html($user->user_email); ?></p>
            <p><strong>🏷️ Nom complet:</strong> <?php echo esc_html($user->display_name); ?></p>
            <p><strong>📅 Data de registre:</strong> <?php echo date('d/m/Y H:i'); ?></p>
        </div>

        <div style="text-align: center; margin: 30px 0;">
            <p>
                <a href="<?php echo admin_url('users.php'); ?>" class="btn">Gestionar usuaris</a>
                <a href="<?php echo admin_url('user-edit.php?user_id=' . $user->ID); ?>" class="btn btn-secondary">Veure perfil</a>
            </p>
        </div>

    <?php
        echo $this->get_email_footer();

        $email_data['message'] = ob_get_clean();
        return $email_data;
    }

    /**
     * Customize comment notification
     */
    public function customize_comment_notification($notify_message, $comment_id)
    {
        $comment = get_comment($comment_id);
        $post = get_post($comment->comment_post_ID);

        ob_start();
        echo $this->get_email_header('Nou comentari - Malet Torrent');
    ?>

        <div class="info-box">
            <h1>💬 Nou comentari rebut</h1>
            <p>S'ha rebut un nou comentari al vostre article <strong>"<?php echo esc_html($post->post_title); ?>"</strong>.</p>
        </div>

        <div class="info-box">
            <h3>👤 Detalls de l'autor:</h3>
            <p><strong>Nom:</strong> <?php echo esc_html($comment->comment_author); ?></p>
            <p><strong>Email:</strong> <?php echo esc_html($comment->comment_author_email); ?></p>
            <?php if (!empty($comment->comment_author_url)): ?>
                <p><strong>Web:</strong> <?php echo esc_html($comment->comment_author_url); ?></p>
            <?php endif; ?>
            <p><strong>IP:</strong> <?php echo esc_html($comment->comment_author_IP); ?></p>
            <p><strong>Data:</strong> <?php echo date('d/m/Y H:i', strtotime($comment->comment_date)); ?></p>
        </div>

        <h3>💬 Comentari:</h3>
        <div style="background-color: #f8f9fa; padding: 20px; border-radius: 6px; border-left: 4px solid #5b493a;">
            <?php echo nl2br(esc_html($comment->comment_content)); ?>
        </div>

        <div style="text-align: center; margin: 30px 0;">
            <p>
                <a href="<?php echo admin_url("comment.php?action=approve&c={$comment_id}"); ?>" class="btn">Aprovar comentari</a>
                <a href="<?php echo get_permalink($post->ID) . '#comment-' . $comment_id; ?>" class="btn btn-secondary">Veure comentari</a>
            </p>
        </div>

    <?php
        echo $this->get_email_footer();

        return ob_get_clean();
    }

    /**
     * Customize comment moderation notification
     */
    public function customize_comment_moderation($notify_message, $comment_id)
    {
        $comment = get_comment($comment_id);
        $post = get_post($comment->comment_post_ID);

        ob_start();
        echo $this->get_email_header('Comentari pendent de moderació - Malet Torrent');
    ?>

        <div class="warning-box">
            <h1>⏳ Comentari pendent de moderació</h1>
            <p>Hi ha un comentari esperant la vostra aprovació a l'article <strong>"<?php echo esc_html($post->post_title); ?>"</strong>.</p>
        </div>

        <div class="info-box">
            <h3>👤 Detalls de l'autor:</h3>
            <p><strong>Nom:</strong> <?php echo esc_html($comment->comment_author); ?></p>
            <p><strong>Email:</strong> <?php echo esc_html($comment->comment_author_email); ?></p>
            <?php if (!empty($comment->comment_author_url)): ?>
                <p><strong>Web:</strong> <?php echo esc_html($comment->comment_author_url); ?></p>
            <?php endif; ?>
            <p><strong>Data:</strong> <?php echo date('d/m/Y H:i', strtotime($comment->comment_date)); ?></p>
        </div>

        <h3>💬 Comentari:</h3>
        <div style="background-color: #f8f9fa; padding: 20px; border-radius: 6px; border-left: 4px solid #ffc107;">
            <?php echo nl2br(esc_html($comment->comment_content)); ?>
        </div>

        <div style="text-align: center; margin: 30px 0;">
            <p>
                <a href="<?php echo admin_url("comment.php?action=approve&c={$comment_id}"); ?>" class="btn">✅ Aprovar</a>
                <a href="<?php echo admin_url("comment.php?action=spam&c={$comment_id}"); ?>" class="btn btn-secondary">🚫 Marcar com spam</a>
                <a href="<?php echo admin_url("comment.php?action=delete&c={$comment_id}"); ?>" class="btn btn-secondary">🗑️ Eliminar</a>
            </p>
        </div>

    <?php
        echo $this->get_email_footer();

        return ob_get_clean();
    }

    /**
     * Customize admin email change notification
     */
    public function customize_admin_email_change($email_data, $old_email, $new_email)
    {
        ob_start();
        echo $this->get_email_header('Email d\'administrador canviat - Malet Torrent');
    ?>

        <div class="warning-box">
            <h1>⚠️ Email d'administrador canviat</h1>
            <p>L'adreça de correu electrònic d'administrador del lloc web de Malet Torrent ha estat canviada.</p>
        </div>

        <div class="info-box">
            <h3>📧 Detalls del canvi:</h3>
            <p><strong>Email anterior:</strong> <?php echo esc_html($old_email); ?></p>
            <p><strong>Email nou:</strong> <?php echo esc_html($new_email); ?></p>
            <p><strong>Data del canvi:</strong> <?php echo date('d/m/Y H:i'); ?></p>
            <p><strong>Lloc web:</strong> <?php echo esc_html(get_bloginfo('name')); ?></p>
        </div>

        <div class="warning-box">
            <h3>🔒 Seguretat</h3>
            <p>Si no heu autoritzat aquest canvi, contacteu immediatament amb el vostre proveïdor d'hosting o administrador tècnic.</p>
        </div>

    <?php
        echo $this->get_email_footer();

        $email_data['message'] = ob_get_clean();
        return $email_data;
    }

    /**
     * Customize email address change notification
     */
    public function customize_email_change_notification($email_data, $user, $userdata)
    {
        ob_start();
        echo $this->get_email_header('Adreça de correu canviada - Malet Torrent');
    ?>

        <div class="success-box">
            <h1>✅ Adreça de correu canviada</h1>
            <p>Hola <strong><?php echo esc_html($user['display_name']); ?></strong>,</p>
            <p>La vostra adreça de correu electrònic ha estat canviada correctament.</p>
        </div>

        <div class="info-box">
            <h3>📧 Detalls del canvi:</h3>
            <p><strong>👤 Usuari:</strong> <?php echo esc_html($user['user_login']); ?></p>
            <p><strong>📧 Nova adreça:</strong> <?php echo esc_html($user['user_email']); ?></p>
            <p><strong>📅 Data del canvi:</strong> <?php echo date('d/m/Y H:i'); ?></p>
        </div>

        <div class="warning-box">
            <h3>🔒 Seguretat</h3>
            <p>Si no heu estat vós qui ha canviat l'adreça de correu, contacteu-nos immediatament per protegir el vostre compte.</p>
        </div>

<?php
        echo $this->get_email_footer();

        $email_data['message'] = ob_get_clean();
        return $email_data;
    }
}

// Initialize
new Malet_Torrent_Email_Templates();
