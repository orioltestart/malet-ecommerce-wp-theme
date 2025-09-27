<?php
/**
 * Mètode d'enviament: Enviament Local
 *
 * @package Malet Torrent
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Malet_Enviament_Local_Shipping extends WC_Shipping_Method {

    public $cost;

    public function __construct($instance_id = 0) {
        $this->id                 = 'malet_enviament_local';
        $this->instance_id        = absint($instance_id);
        $this->method_title       = __('Enviament Local', 'malet-torrent');
        $this->method_description = __('Enviament a domicili a Torrent i rodalies', 'malet-torrent');
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
                'default'     => __('Enviament Local', 'malet-torrent'),
                'desc_tip'    => true,
            ),
            'cost' => array(
                'title'       => __('Cost base', 'malet-torrent'),
                'type'        => 'price',
                'description' => __('Cost base per enviament local', 'malet-torrent'),
                'default'     => '3.50',
                'desc_tip'    => true,
                'placeholder' => '3.50'
            ),
            'min_amount_free' => array(
                'title'       => __('Import mínim per enviament gratuït', 'malet-torrent'),
                'type'        => 'price',
                'description' => __('Import mínim de compra per enviament gratuït (deixar buit per desactivar)', 'malet-torrent'),
                'default'     => '25.00',
                'desc_tip'    => true,
                'placeholder' => '25.00'
            ),
            'zona_cobertura' => array(
                'title'       => __('Zona de cobertura', 'malet-torrent'),
                'type'        => 'textarea',
                'description' => __('Descripció de la zona de cobertura per enviament local', 'malet-torrent'),
                'default'     => 'Torrent, Picanya, Paiporta, Alaquàs, Aldaia, Xirivella',
                'desc_tip'    => true,
            ),
            'temps_lliurament' => array(
                'title'       => __('Temps de lliurament', 'malet-torrent'),
                'type'        => 'select',
                'description' => __('Temps de lliurament estimat', 'malet-torrent'),
                'default'     => '24',
                'options'     => array(
                    '4'  => __('4-6 hores', 'malet-torrent'),
                    '24' => __('24 hores', 'malet-torrent'),
                    '48' => __('48 hores', 'malet-torrent'),
                ),
                'desc_tip'    => true,
            ),
            'horari_lliurament' => array(
                'title'       => __('Horari de lliurament', 'malet-torrent'),
                'type'        => 'text',
                'description' => __('Horari en què es fan els lliuraments', 'malet-torrent'),
                'default'     => 'De 10:00 a 14:00 i de 17:00 a 20:00',
                'desc_tip'    => true,
            )
        );
    }

    public function calculate_shipping($package = array()) {
        $cost = $this->get_option('cost');
        $min_amount_free = $this->get_option('min_amount_free');

        // Calcular total del paquet (sense impostos ni enviament)
        $package_total = 0;
        foreach ($package['contents'] as $item) {
            $package_total += $item['line_total'];
        }

        // Aplicar enviament gratuït si s'assoleix el mínim
        if (!empty($min_amount_free) && $package_total >= $min_amount_free) {
            $cost = 0;
            $label = $this->title . ' - ' . __('Gratuït', 'malet-torrent');
        } else {
            $label = $this->title;
        }

        $this->add_rate(array(
            'id'       => $this->id . $this->instance_id,
            'label'    => $label,
            'cost'     => $cost,
            'package'  => $package,
            'meta_data' => array(
                'zona_cobertura' => $this->get_option('zona_cobertura'),
                'temps_lliurament' => $this->get_option('temps_lliurament'),
                'horari_lliurament' => $this->get_option('horari_lliurament'),
                'delivery_time' => sprintf(__('Lliurament en %s hores', 'malet-torrent'), $this->get_option('temps_lliurament')),
                'free_shipping_threshold' => $min_amount_free
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

        // Verificar zona de cobertura basada en codi postal
        if (isset($package['destination']['postcode'])) {
            $postcode = $package['destination']['postcode'];
            $allowed_postcodes = array('46900', '46901', '46940', '46200', '46960', '46970');

            if (!in_array($postcode, $allowed_postcodes)) {
                $is_available = false;
            }
        }

        return apply_filters('woocommerce_shipping_' . $this->id . '_is_available', $is_available, $package, $this);
    }
}