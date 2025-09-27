<?php
/**
 * Mètode d'enviament: Enviament Nacional
 *
 * @package Malet Torrent
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Malet_Enviament_Nacional_Shipping extends WC_Shipping_Method {

    public $cost;

    public function __construct($instance_id = 0) {
        $this->id                 = 'malet_enviament_nacional';
        $this->instance_id        = absint($instance_id);
        $this->method_title       = __('Enviament Nacional', 'malet-torrent');
        $this->method_description = __('Enviament a qualsevol punt d\'Espanya via Correus/transportista', 'malet-torrent');
        $this->supports           = array(
            'shipping-zones',
            'instance-settings',
            'instance-settings-modal',
        );

        $this->init();
    }

    public function init() {
        // Cargar la configuració
        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->cost = $this->get_option('cost');

        // Hook per guardar la configuració
        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
    }

    public function init_form_fields() {
        $this->instance_form_fields = array(
            'title' => array(
                'title'       => __('Nom del mètode', 'malet-torrent'),
                'type'        => 'text',
                'description' => __('Nom que veuran els clients', 'malet-torrent'),
                'default'     => __('Enviament Nacional', 'malet-torrent'),
                'desc_tip'    => true,
            ),
            'cost' => array(
                'title'       => __('Cost base', 'malet-torrent'),
                'type'        => 'price',
                'description' => __('Cost base per enviament nacional', 'malet-torrent'),
                'default'     => '5.95',
                'desc_tip'    => true,
                'placeholder' => '5.95'
            ),
            'min_amount_free' => array(
                'title'       => __('Import mínim per enviament gratuït', 'malet-torrent'),
                'type'        => 'price',
                'description' => __('Import mínim de compra per enviament gratuït (deixar buit per desactivar)', 'malet-torrent'),
                'default'     => '50.00',
                'desc_tip'    => true,
                'placeholder' => '50.00'
            ),
            'cost_per_kg' => array(
                'title'       => __('Cost addicional per kg', 'malet-torrent'),
                'type'        => 'price',
                'description' => __('Cost addicional per cada kg de pes (opcional)', 'malet-torrent'),
                'default'     => '0.50',
                'desc_tip'    => true,
                'placeholder' => '0.50'
            ),
            'temps_lliurament' => array(
                'title'       => __('Temps de lliurament', 'malet-torrent'),
                'type'        => 'select',
                'description' => __('Temps de lliurament estimat', 'malet-torrent'),
                'default'     => '48',
                'options'     => array(
                    '24' => __('24-48 hores', 'malet-torrent'),
                    '48' => __('48-72 hores', 'malet-torrent'),
                    '72' => __('3-5 dies laborables', 'malet-torrent'),
                ),
                'desc_tip'    => true,
            ),
            'transportista' => array(
                'title'       => __('Transportista', 'malet-torrent'),
                'type'        => 'select',
                'description' => __('Empresa transportista utilitzada', 'malet-torrent'),
                'default'     => 'correos',
                'options'     => array(
                    'correos' => __('Correus', 'malet-torrent'),
                    'seur'    => __('SEUR', 'malet-torrent'),
                    'ups'     => __('UPS', 'malet-torrent'),
                    'mrw'     => __('MRW', 'malet-torrent'),
                ),
                'desc_tip'    => true,
            ),
            'seguiment' => array(
                'title'       => __('Seguiment disponible', 'malet-torrent'),
                'type'        => 'checkbox',
                'description' => __('Els clients rebran un codi de seguiment', 'malet-torrent'),
                'default'     => 'yes',
                'desc_tip'    => true,
            ),
            'asseguranca' => array(
                'title'       => __('Assegurança inclosa', 'malet-torrent'),
                'type'        => 'checkbox',
                'description' => __('L\'enviament inclou assegurança', 'malet-torrent'),
                'default'     => 'yes',
                'desc_tip'    => true,
            )
        );
    }

    public function calculate_shipping($package = array()) {
        $cost = $this->get_option('cost');
        $min_amount_free = $this->get_option('min_amount_free');
        $cost_per_kg = $this->get_option('cost_per_kg');

        // Calcular total del paquet (sense impostos ni enviament)
        $package_total = 0;
        $package_weight = 0;

        foreach ($package['contents'] as $item) {
            $package_total += $item['line_total'];
            $product = $item['data'];
            if ($product && $product->get_weight()) {
                $package_weight += $product->get_weight() * $item['quantity'];
            }
        }

        // Calcular cost addicional per pes
        if ($cost_per_kg && $package_weight > 1) { // Primer kg inclòs
            $additional_weight = $package_weight - 1;
            $cost += $additional_weight * $cost_per_kg;
        }

        // Aplicar enviament gratuït si s'assoleix el mínim
        if (!empty($min_amount_free) && $package_total >= $min_amount_free) {
            $cost = 0;
            $label = $this->title . ' - ' . __('Gratuït', 'malet-torrent');
        } else {
            $label = $this->title;
        }

        // Afegir informació del transportista al label
        $transportista = $this->get_option('transportista');
        if ($transportista && $transportista !== 'correos') {
            $label .= ' (' . ucfirst($transportista) . ')';
        }

        $this->add_rate(array(
            'id'       => $this->id . $this->instance_id,
            'label'    => $label,
            'cost'     => $cost,
            'package'  => $package,
            'meta_data' => array(
                'temps_lliurament' => $this->get_option('temps_lliurament'),
                'transportista' => $this->get_option('transportista'),
                'seguiment' => $this->get_option('seguiment'),
                'asseguranca' => $this->get_option('asseguranca'),
                'delivery_time' => sprintf(__('Lliurament en %s hores', 'malet-torrent'), $this->get_option('temps_lliurament')),
                'free_shipping_threshold' => $min_amount_free,
                'weight_based_pricing' => !empty($cost_per_kg),
                'package_weight' => $package_weight
            )
        ));
    }

    /**
     * Verificar si el mètode està disponible
     */
    public function is_available($package) {
        $is_available = true;

        // Verificar si WooCommerce està actiu
        if (!class_exists('WooCommerce')) {
            $is_available = false;
        }

        // Verificar si el mètode està habilitat
        if ('yes' !== $this->enabled) {
            $is_available = false;
        }

        // Verificar que sigui Espanya
        if (isset($package['destination']['country']) && $package['destination']['country'] !== 'ES') {
            $is_available = false;
        }

        // No disponible per codis postals locals (ja coberts per enviament local)
        if (isset($package['destination']['postcode'])) {
            $postcode = $package['destination']['postcode'];
            $local_postcodes = array('46900', '46901', '46940', '46200', '46960', '46970');

            if (in_array($postcode, $local_postcodes)) {
                $is_available = false;
            }
        }

        return apply_filters('woocommerce_shipping_' . $this->id . '_is_available', $is_available, $package, $this);
    }
}