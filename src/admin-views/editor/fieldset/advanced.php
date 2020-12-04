<?php
$datepicker_format = Tribe__Date_Utils::datepicker_formats( Tribe__Date_Utils::get_datepicker_format_index() );

if ( ! isset( $post_id ) ) {
	$post_id = get_the_ID();
}

$provider = null;
$ticket   = null;
if ( ! isset( $ticket_id ) ) {
	$ticket_id = null;
} else {
	$provider = tribe_tickets_get_ticket_provider( $ticket_id );

	if ( ! empty( $provider ) ) {
		$ticket = $provider->get_ticket( $post_id, $ticket_id );
	}
}

// Avoid rendering an empty "Advanced" settings section, which all but RSVPs have.
$hide_advanced_section = $provider instanceof Tribe__Tickets__RSVP;

/**
 * Whether the 'Advanced Settings' section should load in the Classic Editor.
 *
 * The point is to avoid displaying an empty section.
 *
 * @since TBD
 *
 * @param bool                               $hide_advanced_section The default value of whether we should hide.
 * @param Tribe__Tickets__Ticket_Object|null $ticket                The current ticket.
 * @param int|false                          $post_id               The current post.
 *
 * @return bool True if we should bail and not render the 'Advanced' section.
 */
$hide_advanced_section = apply_filters(
	'tribe_tickets_classic_editor_hide_advanced_section',
	$hide_advanced_section,
	$ticket,
	$post_id
);

if ( $hide_advanced_section ) {
	return;
}
?>
<button class="accordion-header tribe_advanced_meta">
	<?php esc_html_e( 'Advanced', 'event-tickets' ); ?>
</button>
<section id="ticket_form_advanced" class="advanced accordion-content" data-datepicker_format="<?php echo esc_attr( Tribe__Date_Utils::get_datepicker_format_index() ); ?>">
	<h4 class="accordion-label screen_reader_text"><?php esc_html_e( 'Advanced Settings', 'event-tickets' ); ?></h4>
	<div id="advanced_fields">
		<?php
		/**
		 * Allows for the insertion of additional content into the ticket edit form - advanced section
		 *
		 * @since 4.6
		 *
		 * @param int      $post_id   Post ID
		 * @param int|null $ticket_id Ticket ID
		 */
		do_action( 'tribe_events_tickets_metabox_edit_advanced', $post_id, $ticket_id );
		?>
	</div>
</section><!-- #ticket_form_advanced -->
