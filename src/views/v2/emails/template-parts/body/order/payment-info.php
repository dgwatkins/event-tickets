<?php
/**
 * Event Tickets Emails: Order Payment Info
 *
 * Override this template in your own theme by creating a file at:
 * [your-theme]/tribe/tickets/v2/emails/template-parts/body/order/payment-info.php
 *
 * See more documentation about our views templating system.
 *
 * @link https://evnt.is/tickets-emails-tpl Help article for Tickets Emails template files.
 *
 * @version TBD
 *
 * @since TBD
 *
 * @var Tribe_Template $this               Current template object.
 * @var Email_Abstract $email              The email object.
 * @var string         $heading            The email heading.
 * @var string         $title              The email title.
 * @var bool           $preview            Whether the email is in preview mode or not.
 * @var string         $additional_content The email additional content.
 * @var bool           $is_tec_active      Whether `The Events Calendar` is active or not.
 * @var \WP_Post       $order              The order object.
 */

if ( empty( $order ) || empty( $order->provider ) ) {
	return;
}

$payment_info = empty( $order->status ) || 'completed' !== strtolower( $order->status ) ?
	sprintf(
		// Translators: %s - Payment provider's name.
		__( 'Payment unsuccessful with %s', 'event-tickets' ),
		$order->provider
	) : sprintf(
		// Translators: %s - Payment provider's name.
		__( 'Payment completed with %s', 'event-tickets' ),
		$order->gateway
	);

?>
<tr>
	<td class="tec-tickets__email-table-content-order-payment-info-container" align="right">
		<?php esc_html_e( $payment_info ); // phpcs:ignore ?>
	</td>
</tr>
