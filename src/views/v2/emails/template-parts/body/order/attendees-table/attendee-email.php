<?php
/**
 * Event Tickets Emails: Order Attendee Email
 *
 * Override this template in your own theme by creating a file at:
 * [your-theme]/tribe/tickets/v2/emails/template-parts/body/order/attendees-table/attendee-email.php
 *
 * See more documentation about our views templating system.
 *
 * @link https://evnt.is/tickets-emails-tpl Help article for Tickets Emails template files.
 *
 * @version TBD
 *
 * @since TBD
 *
 * @var Tribe__Template $this Current template object.
 * @var array           $order         [Global] The order object.
 * @var bool            $is_tec_active [Global] Whether `The Events Calendar` is active or not.
 */

if ( empty( $attendee['email'] ) ) {
	return;
}

?>
<div>
	<?php echo esc_html( $attendee['email'] ); ?>
</div>
