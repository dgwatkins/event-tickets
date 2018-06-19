<?php

namespace Tribe\Tickets\Test\Commerce;


use Tribe__Utils__Array as Arr;

trait Attendee_Maker {
	protected static $generated = 0;

	/**
	 * Generates a number of attendees for a ticket.
	 *
	 * @param int   $count
	 * @param int   $ticket_id
	 * @param int   $post_id
	 * @param array $overrides See single generation method code for overrides possibilities.
	 *
	 * @return array An array of generated attendees post IDs.
	 */
	protected function create_many_attendees_for_ticket( int $count, int $ticket_id, int $post_id, array $overrides = [] ): array {

		$attendes = [];

		for ( $i = 0; $i < $count; $i ++ ) {
			$attendes[] = $this->create_attendee_for_ticket( $ticket_id, $post_id, $overrides );
		}

		return $attendes;
	}

	/**
	 * Generates an attendee for a ticket.
	 *
	 * @param int   $ticket_id
	 * @param int   $post_id
	 * @param array $overrides See code for overrides possibilities.
	 *
	 * @return int The generated attendee
	 */
	protected function create_attendee_for_ticket( int $ticket_id, int $post_id, array $overrides = array() ): int {
		$faker = \Faker\Factory::create();

		/** @var \Tribe__Tickets__Tickets $provider */
		$provider            = tribe_tickets_get_ticket_provider( $ticket_id );
		$provider_reflection = new \ReflectionClass( $provider );

		$post_key = $provider_reflection->getConstant( 'ATTENDEE_EVENT_KEY' );

		$product_key     = ! empty( $provider->attendee_product_key )
			? $provider->attendee_product_key
			: $provider_reflection->getConstant( 'ATTENDEE_PRODUCT_KEY' );
		$optout_key      = ! empty( $provider->attendee_optout_key )
			? $provider->attendee_optout_key
			: $provider_reflection->getConstant( 'ATTENDEE_OPTOUT_KEY' );
		$user_id_key     = ! empty( $provider->attendee_user_id )
			? $provider->attendee_user_id
			: $provider_reflection->getConstant( 'ATTENDEE_USER_ID' );
		$ticket_sent_key = ! empty( $provider->attendee_ticket_sent )
			? $provider->attendee_ticket_sent
			: $provider_reflection->getConstant( 'ATTENDEE_TICKET_SENT' );

		$default_sku = $provider instanceof \Tribe__Tickets__RSVP ? '' : 'test-attnd' . self::$generated;

		$meta = [
			$provider->checkin_key              => (bool) Arr::get( $overrides, 'checkin', false ),
			$provider->checkin_key . '_details' => Arr::get( $overrides, 'checkin_details', false ),
			$provider->security_code            => Arr::get( $overrides, 'security_code', md5( time() ) ),
			$post_key                           => $post_id,
			$product_key                        => $ticket_id,
			$optout_key                         => Arr::get( $overrides, 'optout', false ),
			$user_id_key                        => Arr::get( $overrides, 'user_id', 0 ),
			$ticket_sent_key                    => Arr::get( $overrides, 'ticket_sent', true ),
			$provider->full_name                => Arr::get( $overrides, 'full_name', $faker->name ),
			$provider->email                    => Arr::get( $overrides, 'email', $faker->email ),
			'_sku'                              => \Tribe__Utils__Array::get( $overrides, 'sku', $default_sku ),
		];

		if ( $provider instanceof \Tribe__Tickets__RSVP ) {
			$meta[ \Tribe__Tickets__RSVP::ATTENDEE_RSVP_KEY ] = $going = Arr::get( $overrides, 'rsvp_status', 'yes' );
		}

		$explicit_keys        = [
			'checkin',
			'checkin_details',
			'security_code',
			'optout',
			'user_id',
			'ticket_sent',
			'full_name',
			'email',
			'rsvp_status',
			'order_id',
			'sku',
		];
		$meta_input_overrides = array_diff_key( $overrides, array_combine( $explicit_keys, $explicit_keys ) );

		$postarr = [
			'post_title'  => 'Generated Attendee ' . self::$generated,
			'post_type'   => $provider_reflection->getConstant( 'ATTENDEE_OBJECT' ),
			'post_status' => 'publish',
			'meta_input'  => array_merge( $meta, $meta_input_overrides ),
		];

		$attendee_id = wp_insert_post( $postarr );

		self::$generated ++;

		if ( empty( $attendee_id ) || $attendee_id instanceof \WP_Error ) {
			throw new \RuntimeException( 'There was an error while generating the attendee, data: ' . json_encode( $postarr, JSON_PRETTY_PRINT ) );
		}

		if ( ! $provider instanceof \Tribe__Tickets__RSVP ) {
			$order_key = ! empty( $provider->attendee_order_key )
				? $provider->attendee_order_key
				: $provider_reflection->getConstant( 'ATTENDEE_ORDER_KEY' );
			$order     = $provider instanceof \Tribe__Tickets__RSVP
				? $attendee_id
				: \Tribe__Utils__Array::get( $overrides, 'order_id', md5( time() ) );
			update_post_meta( $attendee_id, $order_key, $order );
		}

		return $attendee_id;
	}
}
