<?php
/**
 * Admin new order email - Català
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

<p><?php esc_html_e( 'S\'ha rebut una nova comanda a la vostra botiga online de Malet Torrent.', 'malet-torrent' ); ?></p>

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

<p>
	<?php esc_html_e( 'Podeu gestionar aquesta comanda accedint al panell d\'administració:', 'malet-torrent' ); ?>
	<a class="link" href="<?php echo esc_url( admin_url( 'post.php?post=' . absint( $order->get_id() ) . '&action=edit' ) ); ?>">
		<?php
		/* translators: %s: Order number. */
		printf( esc_html__( 'Veure comanda #%s', 'malet-torrent' ), esc_html( $order->get_order_number() ) );
		?>
	</a>
</p>

<?php
/*
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action( 'woocommerce_email_footer', $email );