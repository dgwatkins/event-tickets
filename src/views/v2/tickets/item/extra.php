<?php
/**
 * Block: Tickets
 * Extra column
 *
 * Override this template in your own theme by creating a file at:
 * [your-theme]/tribe/tickets/v2/tickets/item/extra.php
 *
 * See more documentation about our views templating system.
 *
 * @link http://m.tri.be/1amp
 *
 * @since TBD
 *
 * @version TBD
 *
 * @var Tribe__Tickets__Editor__Template $this The Template Object
 * @var Tribe__Tickets__Ticket_Object $ticket  The Ticket Object
 * @var int $key                               Ticket Item index
 */

$has_suffix = ! empty( $ticket->price_suffix );

$classes = [
	'tribe-tickets__tickets-item-extra',
	'tribe-tickets__tickets-item-extra--price-suffix' => $has_suffix,
];

?>
<div <?php tribe_classes( $classes ); ?>>

	<?php $this->template( 'v2/tickets/item/extra/price', [ 'ticket' => $ticket, 'key' => $key ] ); ?>

	<?php $this->template( 'v2/tickets/item/extra/available', [ 'ticket' => $ticket, 'key' => $key ] ); ?>

	<?php $this->template( 'v2/tickets/item/extra/description-toggle', [ 'ticket' => $ticket, 'key' => $key ] ); ?>

</div>