<?php
/**
 * Tickets Emails Message Template List Title
 *
 * @since  TBD   Title for list item of email message templates for Emails settings tab.
 * 
 * @var Array[]  $templates  Array of template info.
 * @var Array    $template   Template info.
 */

// @todo $templates variable will be an array of Message_Template objects in the future.
// @todo $template variable will be a Message_Template object in the future.

// If no templates, bail.
if ( empty( $template ) ) {
	return;
}
// @todo Update template HTML.
?>
<div class="tec_tickets-emails-template-list-item-title">
	<?php echo esc_html( $template['title'] ); ?>
</div>
