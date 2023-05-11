<?php

namespace TEC\Tickets\Emails\JSON_LD;

/**
 * Class Reservation_Schema.
 *
 * @since TBD
 *
 * @package TEC\Tickets\Emails\JSON_LD
 */
class Reservation_Schema extends JSON_LD_Abstract {

	/**
	 * The type of the schema.
	 *
	 * @since TBD
	 *
	 * @var string
	 */
	public static string $type = 'EventReservation';

	/**
	 * The event data.
	 *
	 * @since TBD
	 *
	 * @var array
	 */
	public array $event_data;

	/**
	 * Tickets data.
	 *
	 * @since TBD
	 *
	 * @var array
	 */
	public array $tickets;

	/**
	 * Reservation_Schema constructor.
	 *
	 * @param array $event_data The event data.
	 * @param array $tickets The tickets data.
	 *
	 * @since TBD
	 */
	public function __construct( array $event_data, array $tickets ) {
		$this->event_data = $event_data;
		$this->tickets    = $tickets;
	}

	/**
	 * @inheritDoc
	 */
	public function get_data(): array {

		$data = [];
		foreach ( $this->tickets as $ticket ) {
			$ticket_data = [
				'reservationNumber' => $ticket['order_id'],
				'reservationStatus' => "https://schema.org/Confirmed",
				'underName'         => [
					'@type' => "Person",
					'name'  => $ticket['holder_name'],
					'email' => $ticket['holder_email'],
				],
				'reservationFor'    => $this->event_data,
				'ticketToken'       => $ticket['security_code'],
				'ticketNumber'      => $ticket['attendee_id'],
				'numSeats'          => "1",
			];
			$data[] = array_merge( $this->get_basic_data(), $ticket_data );
		}

		/**
		 * Filter the reservation ticket data for the JSON-LD schema.
		 *
		 * @since TBD
		 *
		 * @param array $data The event data.
		 * @param \WP_Post $event The event object.
		 */
		return apply_filters( 'tec_tickets_emails_json_ld_event_reservation_data', $data, $this->event_data, $this->tickets );
	}
}