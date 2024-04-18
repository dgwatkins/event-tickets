<?php
/**
 * Handles hooking all the actions and filters used by the module.
 *
 * To remove a filter:
 * remove_filter( 'some_filter', [ tribe( TEC\Tickets\Commerce\Gateways\Manual\Hooks::class ), 'some_filtering_method' ] );
 * remove_filter( 'some_filter', [ tribe( 'tickets.commerce.gateways.manual.hooks' ), 'some_filtering_method' ] );
 *
 * To remove an action:
 * remove_action( 'some_action', [ tribe( TEC\Tickets\Commerce\Gateways\Manual\Hooks::class ), 'some_method' ] );
 * remove_action( 'some_action', [ tribe( 'tickets.commerce.gateways.manual.hooks' ), 'some_method' ] );
 *
 * @since TBD
 *
 * @package TEC\Tickets\Commerce\Gateways\Free
 */

namespace TEC\Tickets\Commerce\Gateways\Free;

use TEC\Common\Contracts\Service_Provider;

/**
 * Class Hooks.
 *
 * @since TBD
 *
 * @package TEC\Tickets\Commerce\Gateways\Free
 */
class Hooks extends Service_Provider {
	
	/**
	 * Binds and sets up implementations.
	 *
	 * @since TBD
	 */
	public function register() {
		$this->add_filters();
	}
	
	/**
	 * Adds the filters required by each Tickets Commerce component.
	 *
	 * @since TBD
	 */
	protected function add_filters() {
		add_filter( 'tec_tickets_commerce_gateways', [ $this, 'filter_add_gateway' ], 10, 2 );
		add_action( 'rest_api_init', [ $this, 'register_endpoints' ] );
	}
	
	/**
	 * Add this gateway to the list of available.
	 *
	 * @since TBD
	 *
	 * @param array $gateways List of available gateways.
	 *
	 * @return array
	 */
	public function filter_add_gateway( array $gateways = [] ) {
		return $this->container->make( Gateway::class )->register_gateway( $gateways );
	}
	
	/**
	 * Register the REST API endpoints.
	 *
	 * @since TBD
	 */
	public function register_endpoints() {
		$this->container->make( REST\Order_Endpoint::class )->register();
	}
}
