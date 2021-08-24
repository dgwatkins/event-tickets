<?php

namespace TEC\Tickets\Commerce\Gateways\PayPal;

use Tribe__Utils__Array as Arr;

/**
 * Class Buttons
 *
 * @since   TBD
 *
 * @package TEC\Tickets\Commerce\Gateways\PayPal
 */
class Buttons {

	/**
	 * Stores the instance of the template engine that we will use for rendering the elements.
	 *
	 * @since TBD
	 *
	 * @var \Tribe__Template
	 */
	protected $template;

	/**
	 * Gets the template instance used to setup the rendering of the page.
	 *
	 * @since TBD
	 *
	 * @return \Tribe__Template
	 */
	public function get_template() {
		if ( empty( $this->template ) ) {
			$this->template = new \Tribe__Template();
			$this->template->set_template_origin( \Tribe__Tickets__Main::instance() );
			$this->template->set_template_folder( 'src/views/v2/commerce/gateway/paypal' );
			$this->template->set_template_context_extract( true );
			$this->template->set_template_folder_lookup( true );
		}

		return $this->template;
	}

	public function get_checkout_script() {
		$client            = tribe( Client::class );
		$client_token_data = $client->get_client_token();
		$template_vars     = [
			'url'                     => $client->get_js_sdk_url(),
			'attribution_id'          => Gateway::ATTRIBUTION_ID,
			'client_token'            => Arr::get( $client_token_data, 'client_token' ),
			'client_token_expires_in' => Arr::get( $client_token_data, 'expires_in' ),
		];

		tribe_asset_enqueue( 'tec-tickets-commerce-gateway-paypal-checkout' );

		return $this->get_template()->template( 'checkout-script', $template_vars, false );
	}
}