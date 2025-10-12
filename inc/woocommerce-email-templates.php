<?php
/**
 * WooCommerce Email Templates for Malet Torrent
 * Plantilles específiques per emails de WooCommerce
 *
 * @package Malet Torrent
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Malet_Torrent_WooCommerce_Email_Templates {

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Replace WooCommerce email templates
        add_filter('woocommerce_email_format_string', [$this, 'format_email_strings'], 10, 2);
        add_action('woocommerce_email_before_order_table', [$this, 'add_order_intro'], 10, 4);
        add_action('woocommerce_email_after_order_table', [$this, 'add_order_outro'], 10, 4);

        // Custom order email content
        add_filter('woocommerce_email_order_details', [$this, 'custom_order_details'], 10, 4);

        // Customize specific email templates
        add_action('woocommerce_order_status_pending_to_processing_notification', [$this, 'send_custom_processing_email'], 10, 2);
        add_action('woocommerce_order_status_processing_to_completed_notification', [$this, 'send_custom_completed_email'], 10, 2);
    }

    /**
     * Format email strings with Malet Torrent branding
     */
    public function format_email_strings($string, $email) {
        // Replace generic WordPress/WooCommerce branding
        $replacements = [
            '{site_title}' => 'Malet Torrent - Pastisseria Tradicional Catalana',
            '{site_url}' => home_url(),
        ];

        // Only add order-specific placeholders if order object exists
        if (isset($email->object) && $email->object && method_exists($email->object, 'get_order_number')) {
            $replacements['{order_number}'] = $email->object->get_order_number();
            $replacements['{order_date}'] = $email->object->get_date_created() ? $email->object->get_date_created()->date_i18n('d/m/Y') : '';
            $replacements['{customer_first_name}'] = $email->object->get_billing_first_name() ?? '';
            $replacements['{customer_last_name}'] = $email->object->get_billing_last_name() ?? '';
        } else {
            $replacements['{order_number}'] = '';
            $replacements['{order_date}'] = '';
            $replacements['{customer_first_name}'] = '';
            $replacements['{customer_last_name}'] = '';
        }

        return str_replace(array_keys($replacements), array_values($replacements), $string);
    }

    /**
     * Add intro content to order emails
     */
    public function add_order_intro($order, $sent_to_admin, $plain_text, $email) {
        if ($plain_text) {
            return;
        }

        $customer_name = $order->get_billing_first_name();
        $order_number = $order->get_order_number();
        $order_status = $order->get_status();

        ?>
        <div class="order-intro">
            <?php if ($sent_to_admin): ?>
                <div class="info-box">
                    <h2>🛒 Nova comanda rebuda</h2>
                    <p>S'ha rebut una nova comanda a la vostra botiga online de Malet Torrent.</p>
                </div>
            <?php else: ?>
                <div class="success-box">
                    <h2>🎉 Gràcies per la vostra comanda, <?php echo esc_html($customer_name); ?>!</h2>
                    <p>Hem rebut la vostra comanda #<?php echo esc_html($order_number); ?> i estem encantats de preparar els nostres melindros tradicionals per a vós.</p>
                </div>
            <?php endif; ?>

            <?php if ($order_status === 'processing'): ?>
                <div class="info-box">
                    <h3>📦 La vostra comanda està en procés</h3>
                    <p>Els nostres artesans pastissers ja han començat a preparar la vostra comanda amb el mateix amor i dedicació de sempre. Rebreu una nova notificació quan estigui llesta per enviar.</p>
                </div>
            <?php elseif ($order_status === 'completed'): ?>
                <div class="success-box">
                    <h3>✅ Comanda completada</h3>
                    <p>La vostra comanda ha estat preparada i enviada. Esperem que gaudiu dels nostres melindros tradicionals catalans!</p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Add outro content to order emails
     */
    public function add_order_outro($order, $sent_to_admin, $plain_text, $email) {
        if ($plain_text) {
            return;
        }

        ?>
        <div class="order-outro">
            <?php if (!$sent_to_admin): ?>
                <div class="info-box">
                    <h3>🍪 Sobre els vostres melindros</h3>
                    <p>Tots els nostres productes són elaborats artesanalment seguint les receptes tradicionals catalanes transmeses de generació en generació. No utilitzem colorants ni conservants artificials.</p>

                    <h4>💡 Consells de conservació:</h4>
                    <ul>
                        <li>Mantingueu els melindros en un lloc sec i fresc</li>
                        <li>Un cop oberts, consumiu-los en un termini de 15 dies</li>
                        <li>Perfectes per acompanyar amb cafè, te o vi dolç</li>
                    </ul>
                </div>

                <div style="text-align: center; margin: 30px 0;">
                    <h3>🌟 Comparteix la teva experiència</h3>
                    <p>Ens encantaria veure com gaudiu dels nostres melindros!</p>
                    <p>
                        <a href="https://www.instagram.com/pastisseria.malet.torrent/" class="btn">
                            Comparteix a Instagram
                        </a>
                        <a href="https://www.facebook.com/profile.php?id=61557664863691" class="btn btn-secondary">
                            Deixa'ns una ressenya
                        </a>
                    </p>
                </div>

                <div class="warning-box">
                    <h4>💬 Necessiteu ajuda?</h4>
                    <p>Si teniu alguna pregunta sobre la vostra comanda o els nostres productes, no dubteu en contactar-nos:</p>
                    <p>
                        <strong>📞 Telèfon:</strong> 972 86 93 08<br>
                        <strong>✉️ Email:</strong> <a href="mailto:info@malet.cat">info@malet.cat</a><br>
                        <strong>💬 WhatsApp:</strong> 635 78 90 12
                    </p>
                </div>
            <?php else: ?>
                <div class="info-box">
                    <h3>📋 Accions recomanades</h3>
                    <p>
                        <a href="<?php echo admin_url('post.php?post=' . $order->get_id() . '&action=edit'); ?>" class="btn">
                            Veure comanda completa
                        </a>
                        <a href="<?php echo admin_url('edit.php?post_type=shop_order'); ?>" class="btn btn-secondary">
                            Gestionar totes les comandes
                        </a>
                    </p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Custom order details table
     */
    public function custom_order_details($order, $sent_to_admin, $plain_text, $email) {
        if ($plain_text) {
            return;
        }

        ?>
        <div class="order-details-section">
            <h3>📋 Detalls de la comanda #<?php echo $order->get_order_number(); ?></h3>

            <table class="email-table order-details">
                <thead>
                    <tr>
                        <th>Producte</th>
                        <th>Quantitat</th>
                        <th>Preu</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($order->get_items() as $item_id => $item): ?>
                        <?php $product = $item->get_product(); ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($item->get_name()); ?></strong>
                                <?php if ($product && $product->get_weight()): ?>
                                    <br><small>Pes: <?php echo esc_html($product->get_weight()); ?>g</small>
                                <?php endif; ?>
                                <?php
                                // Show product attributes
                                $item_meta = new WC_Order_Item_Meta($item, $product);
                                if ($item_meta_array = $item_meta->get_formatted()) {
                                    foreach ($item_meta_array as $meta_key => $meta_value) {
                                        echo '<br><small>' . esc_html($meta_key) . ': ' . esc_html($meta_value) . '</small>';
                                    }
                                }
                                ?>
                            </td>
                            <td style="text-align: center;">
                                <?php echo esc_html($item->get_quantity()); ?>
                            </td>
                            <td style="text-align: right;">
                                <?php echo wc_price($item->get_subtotal()); ?>
                            </td>
                            <td style="text-align: right;">
                                <strong><?php echo wc_price($item->get_total()); ?></strong>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="border-top: 2px solid #5b493a;">
                        <td colspan="3" style="text-align: right; font-weight: bold; padding-top: 15px;">
                            Subtotal:
                        </td>
                        <td style="text-align: right; font-weight: bold; padding-top: 15px;">
                            <?php echo wp_kses_post($order->get_subtotal_to_display()); ?>
                        </td>
                    </tr>

                    <?php if ($order->get_total_shipping() > 0): ?>
                    <tr>
                        <td colspan="3" style="text-align: right;">Enviament:</td>
                        <td style="text-align: right;"><?php echo wp_kses_post(wc_price($order->get_total_shipping())); ?></td>
                    </tr>
                    <?php endif; ?>

                    <?php if ($order->get_total_tax() > 0): ?>
                    <tr>
                        <td colspan="3" style="text-align: right;">Impostos:</td>
                        <td style="text-align: right;"><?php echo wp_kses_post(wc_price($order->get_total_tax())); ?></td>
                    </tr>
                    <?php endif; ?>

                    <tr style="background-color: #5b493a; color: white;">
                        <td colspan="3" style="text-align: right; font-weight: bold; font-size: 16px;">
                            Total:
                        </td>
                        <td style="text-align: right; font-weight: bold; font-size: 16px;">
                            <?php echo wp_kses_post($order->get_formatted_order_total()); ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="shipping-billing-info">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin: 30px 0;">
                <div class="shipping-address">
                    <h4>📦 Adreça d'enviament</h4>
                    <div class="info-box">
                        <?php echo wp_kses_post($order->get_formatted_shipping_address()); ?>
                    </div>
                </div>

                <div class="billing-address">
                    <h4>💳 Adreça de facturació</h4>
                    <div class="info-box">
                        <?php echo wp_kses_post($order->get_formatted_billing_address()); ?>
                        <?php if ($order->get_billing_email()): ?>
                            <br><strong>Email:</strong> <?php echo esc_html($order->get_billing_email()); ?>
                        <?php endif; ?>
                        <?php if ($order->get_billing_phone()): ?>
                            <br><strong>Telèfon:</strong> <?php echo esc_html($order->get_billing_phone()); ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Send custom processing email
     */
    public function send_custom_processing_email($order_id, $order) {
        // Additional custom logic for processing emails if needed
    }

    /**
     * Send custom completed email
     */
    public function send_custom_completed_email($order_id, $order) {
        // Additional custom logic for completed emails if needed
    }

    /**
     * Get order tracking info template
     */
    public function get_order_tracking_template($order) {
        $tracking_number = get_post_meta($order->get_id(), '_tracking_number', true);
        $shipping_company = get_post_meta($order->get_id(), '_shipping_company', true);

        if ($tracking_number) {
            ?>
            <div class="info-box">
                <h3>📋 Informació de seguiment</h3>
                <p><strong>Número de seguiment:</strong> <?php echo esc_html($tracking_number); ?></p>
                <?php if ($shipping_company): ?>
                    <p><strong>Empresa de transport:</strong> <?php echo esc_html($shipping_company); ?></p>
                <?php endif; ?>
                <p>
                    <a href="#" class="btn">Seguir el meu enviament</a>
                </p>
            </div>
            <?php
        }
    }
}

// Initialize
new Malet_Torrent_WooCommerce_Email_Templates();