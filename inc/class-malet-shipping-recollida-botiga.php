<?php
/**
 * Mètode d'enviament: Recollida a la Botiga
 *
 * @package Malet Torrent
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Malet_Recollida_Botiga_Shipping extends WC_Shipping_Method {

    public $cost;

    public function __construct($instance_id = 0) {
        $this->id                 = 'malet_recollida_botiga';
        $this->instance_id        = absint($instance_id);
        $this->method_title       = __('Recollida a la Botiga', 'malet-torrent');
        $this->method_description = __('Els clients poden recollir els seus melindros directament a la botiga de Torrent', 'malet-torrent');
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
                'default'     => __('Recollida a la botiga', 'malet-torrent'),
                'desc_tip'    => true,
            ),
            'cost' => array(
                'title'       => __('Cost', 'malet-torrent'),
                'type'        => 'price',
                'description' => __('Cost de la recollida (normalment gratuït)', 'malet-torrent'),
                'default'     => '0',
                'desc_tip'    => true,
                'placeholder' => '0.00'
            ),
            'horari_info' => array(
                'title'       => __('Informació d\'horaris', 'malet-torrent'),
                'type'        => 'textarea',
                'description' => __('Informació sobre horaris de recollida que es mostrarà al client', 'malet-torrent'),
                'default'     => 'Horari de recollida: Dimarts a Dissabte de 9:00 a 13:00 i de 17:00 a 20:00. Diumenge de 9:00 a 14:00.',
                'desc_tip'    => true,
            ),
            'adreca_botiga' => array(
                'title'       => __('Adreça de la botiga', 'malet-torrent'),
                'type'        => 'textarea',
                'description' => __('Adreça completa de la botiga per a la recollida', 'malet-torrent'),
                'default'     => 'Carrer Major, 123\n46900 Torrent, València\nTel: 96 123 45 67',
                'desc_tip'    => true,
            ),
            'temps_preparacio' => array(
                'title'       => __('Temps de preparació', 'malet-torrent'),
                'type'        => 'select',
                'description' => __('Temps necessari per preparar la comanda', 'malet-torrent'),
                'default'     => '24',
                'options'     => array(
                    '2'  => __('2 hores', 'malet-torrent'),
                    '4'  => __('4 hores', 'malet-torrent'),
                    '24' => __('24 hores', 'malet-torrent'),
                    '48' => __('48 hores', 'malet-torrent'),
                ),
                'desc_tip'    => true,
            )
        );
    }

    public function calculate_shipping($package = array()) {
        $cost = $this->get_option('cost');

        $this->add_rate(array(
            'id'       => $this->id . $this->instance_id,
            'label'    => $this->title,
            'cost'     => $cost,
            'package'  => $package,
            'meta_data' => array(
                'horari_info' => $this->get_option('horari_info'),
                'adreca_botiga' => $this->get_option('adreca_botiga'),
                'temps_preparacio' => $this->get_option('temps_preparacio'),
                'delivery_time' => sprintf(__('Llest per recollir en %s hores', 'malet-torrent'), $this->get_option('temps_preparacio'))
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

        return apply_filters('woocommerce_shipping_' . $this->id . '_is_available', $is_available, $package, $this);
    }
}