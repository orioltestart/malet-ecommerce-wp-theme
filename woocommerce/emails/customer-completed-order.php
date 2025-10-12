<?php
/**
 * Customer completed order email - Català
 *
 * @see https://woo.com/document/template-structure/
 * @package WooCommerce\Templates\Emails
 * @version 3.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<?php /* translators: %s: Customer first name */ ?>
<p><?php printf( esc_html__( 'Hola %s,', 'malet-torrent' ), esc_html( $order->get_billing_first_name() ) ); ?></p>

<p><?php esc_html_e( 'Bones notícies! La vostra comanda ja està preparada i llesta.', 'malet-torrent' ); ?></p>

<?php if ( $order->has_downloadable_item() ) : ?>
	<p><?php esc_html_e( 'Els vostres productes digitals estan disponibles per descarregar:', 'malet-torrent' ); ?></p>
<?php endif; ?>

<?php
/*
 * @hooked WC_Emails::order_details() Shows the order details table.
 * @hooked WC_Structured_Data::generate_order_data() Generates structured data.
 * @hooked WC_Structured_Data::output_structured_data() Outputs structured data.
 * @since 2.5.0
 */
do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );

/*
 * @hooked WC_Emails::order_meta() Shows order meta data.
 */
do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );

/*
 * @hooked WC_Emails::customer_details() Shows customer details
 * @hooked WC_Emails::email_address() Shows email address
 */
do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );
?>

<p><?php esc_html_e( 'Esperem que gaudiu dels nostres melindros tradicionals catalans. Gràcies per confiar en Malet Torrent!', 'malet-torrent' ); ?></p>

<?php
/*
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action( 'woocommerce_email_footer', $email );