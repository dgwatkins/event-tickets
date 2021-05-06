<?php
/**
 * Shortcode [tribe_tickets_checkout].
 *
 * @since   TBD
 * @package Tribe\Tickets\Shortcodes
 */

namespace Tribe\Tickets\Shortcodes;

use Tribe\Shortcode\Shortcode_Abstract;
use Tribe\Tickets\Commerce\Tickets_Commerce\Gateways\PayPal_Commerce\SDK_Interface\Repositories\MerchantDetails;
use Tribe\Tickets\Commerce\Tickets_Commerce\Gateways\PayPal_Commerce\Settings;
use Tribe__Tickets__Editor__Template;

/**
 * Class for Shortcode Tribe_Tickets_Checkout.
 *
 * @since   TBD
 * @package Tribe\Tickets\Shortcodes
 */
class Tribe_Tickets_Checkout extends Shortcode_Abstract {

	/**
	 * {@inheritDoc}
	 */
	protected $slug = 'tribe_tickets_checkout';

	/**
	 * {@inheritDoc}
	 */
	public function get_html() {
		$context = tribe_context();

		if ( is_admin() && ! $context->doing_ajax() ) {
			return '';
		}

		/** @var Tribe__Tickets__Editor__Template $template */
		$template = tribe( 'tickets.editor.template' );

		$merchant_details = tribe( MerchantDetails::class );
		$details = $merchant_details->getDetails();

		$args = [
			// @todo Set up args here.
			'client_id' => $details->clientId,
			'custom_payments' => $details->supportsCustomPayments,
		];

		// Add the rendering attributes into global context.
		$template->add_template_globals( $args );

		// Enqueue assets.
		tribe_asset_enqueue_group( 'tribe-tickets-commerce-checkout' );

		return $template->template( 'v2/tickets/commerce/checkout', $args, false );
	}

}
