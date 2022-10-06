<?php
/**
 * Tickets Emails Message Template List Icon
 *
 * @since  TBD   Icon (checkmark) for list item of email message templates for Emails settings tab.
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

$icon_classes   = [ 'dashicons' ];
$icon_classes[] = tribe_is_truthy( $template['enabled'] ) ? 'dashicons-yes' : '';

?>
<div class="tec_tickets-emails-template-list-item-icon">
	<span <?php tribe_classes( $icon_classes ); ?> ></span>
</div>
