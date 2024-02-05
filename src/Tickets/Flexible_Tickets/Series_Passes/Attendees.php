<?php
/**
 * Handles Attendees in the context of Series Passes.
 *
 * @since TBD
 *
 * @package TEC\Tickets\Flexible_Tickets\Series_Passes;
 */

namespace TEC\Tickets\Flexible_Tickets\Series_Passes;

use AppendIterator;
use ArrayIterator;
use Iterator;
use TEC\Common\Contracts\Provider\Controller;
use TEC\Common\lucatume\DI52\Container;
use TEC\Events\Custom_Tables\V1\Models\Occurrence;
use TEC\Events_Pro\Custom_Tables\V1\Events\Provisional\ID_Generator;
use TEC\Events_Pro\Custom_Tables\V1\Models\Provisional_Post;
use TEC\Events_Pro\Custom_Tables\V1\Models\Series_Relationship;
use TEC\Events_Pro\Custom_Tables\V1\Series\Post_Type as Series_Post_Type;
use Tribe__Date_Utils as Dates;
use Tribe__Repository__Interface;
use Tribe__Tickets__Tickets as Tickets;
use WP_Error;
use WP_Post;
use WP_REST_Response;
use Tribe__Events__Main as TEC;

/**
 * Class Attendees.
 *
 * @since TBD
 *
 * @package TEC\Tickets\Flexible_Tickets\Series_Passes;
 */
class Attendees extends Controller {
	/**
	 * The meta key set on a cloned Attendee to indicate the original Attendee it was cloned from.
	 *
	 * @since TBD
	 *
	 * @var string
	 */
	public const CLONE_META_KEY = '_tec_attendee_clone_of';

	/**
	 * A map of Attendees whose check in failed, a map from Attendee post ID to the data required
	 * from the user to complete the check in process.
	 *
	 * @since TBD
	 *
	 * @var array<int,{attendee_id: int, series_id: int, candidates: int[]}>
	 */
	private array $checkin_failures = [];

	/**
	 * A memoized map from Attendee post types to the corresponding provider check-in meta key.
	 *
	 * @since TBD
	 *
	 * @var array<string,string>
	 */
	private array $post_type_checkin_keys = [];

	/**
	 * A reference to the Series Passes' edit and editor handler.
	 *
	 * @since TBD
	 *
	 * @var Edit
	 */
	private Edit $edit;

	/**
	 * A list of Attendees cloned in this request.
	 *
	 * @since TBD
	 *
	 * @var int[]
	 */
	private array $reload_triggers = [];

	/**
	 * Attendees constructor.
	 *
	 * @since TBD
	 *
	 * @param Container $container The dependency injection container.
	 * @param Edit      $edit      The Series Passes' edit and editor handler.
	 */
	public function __construct( Container $container, Edit $edit ) {
		parent::__construct( $container );
		$this->edit = $edit;
	}

	/**
	 * Subscribes the controller from all Attendee post and meta updates.
	 *
	 * @since TBD
	 */
	private function subscribe_to_attendee_updates(): void {
		// Subscribe to Attendee post updated to sync data between original and cloned Attendees.
		foreach ( tribe_attendees()->attendee_types() as $attendee_post_type ) {
			add_action( "edit_post_{$attendee_post_type}", [ $this, 'sync_attendee_on_edit' ], 500 );
		}

		add_action( 'added_post_meta', [ $this, 'sync_attendee_meta_on_meta_add' ], 500, 4 );
		add_action( 'updated_post_meta', [ $this, 'sync_attendee_meta_on_meta_update' ], 500, 4 );
		add_action( 'deleted_post_meta', [ $this, 'sync_attendee_meta_on_meta_delete' ], 500, 4 );
		add_action( 'after_delete_post', [ $this, 'delete_clones_on_delete' ], 500, 2 );
		add_filter( 'tec_tickets_filter_event_id', [ $this, 'preserve_provisional_id' ], 500, 3 );
	}

	/**
	 * Unsubscribes the controller from all Attendee post and meta updates.
	 *
	 * @since TBD
	 */
	private function unsubscribe_from_attendee_updates(): void {
		// Subscribe to Attendee post updated to sync data between original and cloned Attendees.
		foreach ( tribe_attendees()->attendee_types() as $attendee_post_type ) {
			remove_action( "edit_post_{$attendee_post_type}", [ $this, 'sync_attendee_on_edit' ], 500 );
		}

		remove_action( 'added_post_meta', [ $this, 'sync_attendee_meta_on_meta_add' ], 500 );
		remove_action( 'updated_post_meta', [ $this, 'sync_attendee_meta_on_meta_update' ], 500 );
		remove_action( 'deleted_post_meta', [ $this, 'sync_attendee_meta_on_meta_delete' ], 500 );
		remove_action( 'after_delete_post', [ $this, 'delete_clones_on_delete' ], 500 );
		remove_filter( 'tec_tickets_filter_event_id', [ $this, 'preserve_provisional_id' ], 500 );
	}

	/**
	 * Registers the implementations required by the Controller and hooks the Controller methods to the required
	 * filters.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	protected function do_register(): void {
		add_filter( 'tec_tickets_attendee_checkin', [ $this, 'handle_series_pass_attendee_checkin' ], 10, 4 );
		add_filter( 'tribe_tickets_attendee_table_columns', [ $this, 'filter_attendees_table_columns' ], 10, 2 );
		add_filter(
			'tec_tickets_qr_checkin_failure_rest_response',
			[
				$this,
				'build_attendee_failure_response',
			],
			10,
			2
		);
		$this->subscribe_to_attendee_updates();
		add_filter( 'tec_tickets_attendees_filter_by_event', [ $this, 'include_series_to_fetch_attendees' ], 10, 2 );
		add_filter(
			'tec_tickets_attendees_filter_by_event_not_in',
			[
				$this,
				'include_series_to_fetch_attendees',
			],
			10,
			2
		);
		add_filter(
			'tribe_tickets_attendees_report_js_config',
			[
				$this,
				'filter_tickets_attendees_report_js_config',
			]
		);
		add_filter( 'tec_tickets_attendee_manual_checkin_success_data', [
			$this,
			'trigger_attendees_list_reload'
		], 10, 2 );
	}

	/**
	 * Unsubscribes the Controller from all fiters.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function unregister(): void {
		remove_filter( 'tec_tickets_attendee_checkin', [ $this, 'handle_series_pass_attendee_checkin' ], 10, 3 );
		remove_filter( 'tribe_tickets_attendee_table_columns', [ $this, 'filter_attendees_table_columns' ], 10, 2 );
		remove_filter(
			'tec_tickets_qr_checkin_failure_rest_response',
			[
				$this,
				'build_attendee_failure_response',
			],
			10
		);
		$this->unsubscribe_from_attendee_updates();
		remove_filter( 'tec_tickets_attendees_filter_by_event', [ $this, 'include_series_to_fetch_attendees' ] );
		remove_filter( 'tec_tickets_attendees_filter_by_event_not_in', [ $this, 'include_series_to_fetch_attendees' ] );
		remove_filter(
			'tribe_tickets_attendees_report_js_config',
			[
				$this,
				'filter_tickets_attendees_report_js_config',
			]
		);
		remove_filter( 'tec_tickets_attendee_manual_checkin_success_data', [
			$this,
			'trigger_attendees_list_reload'
		], 10, 2 );
	}

	/**
	 * Filter the Attendees table columns to remove the "Check-in" column when looking at Series Passes.
	 *
	 * @since TBD
	 *
	 * @param array<string,string> $columns  The columns to display in the Attendees table.
	 * @param int                  $event_id The ID of the event being displayed.
	 *
	 * @return array<string,string> The modified columns to display.
	 */
	public function filter_attendees_table_columns( array $columns, int $event_id ) {
		$post_type = get_post_type( $event_id );

		if ( ! in_array( $post_type, [ Series_Post_Type::POSTTYPE, TEC::POSTTYPE ], true ) ) {
			return $columns;
		}

		if (
			$post_type === TEC::POSTTYPE
			&& (
				! tribe_is_recurring_event( $event_id )
				|| tribe( Provisional_Post::class )->is_provisional_post_id( $event_id )
			)
		) {
			return $columns;
		}

		return array_diff_key( $columns, [ 'check_in' => 'remove' ] );
	}

	/**
	 * Filters the Attendee checkin to prevent Series Pass Attendees from being checked in.
	 *
	 * @since TBD
	 *
	 * @param mixed    $checkin     Null by default, if not null, it will prevent the default checkin logic
	 *                              from firing.
	 * @param int      $attendee_id The post ID of the Attendee being checked in.
	 * @param int|null $event_id    The ID of the post the Attendee is checking into, if available.
	 * @param bool     $qr          Whether the checkin is being done via QR code or not.
	 *
	 * @return bool|null Null to let the default checkin logic run, boolean value to prevent it.
	 */
	public function handle_series_pass_attendee_checkin( $checkin, int $attendee_id, int $event_id = null, bool $qr = false ) {
		if ( tribe_attendees()->where( 'id', $attendee_id )->where( 'ticket_type', Series_Passes::TICKET_TYPE )->count() === 0 ) {
			// Not an Attendee for a Series Pass, let the default logic run its course.
			return $checkin;
		}

		if ( get_post_meta( $attendee_id, self::CLONE_META_KEY, true ) ) {
			// This Attendee is a clone, its check-in logic should be handled by the provider.
			return $checkin;
		}

		/** @var Tickets $ticket_provider */
		$ticket_provider = tribe_tickets_get_ticket_provider( $attendee_id );

		if ( ! $ticket_provider instanceof Tickets ) {
			// We tried to handle the check in, but it failed.
			return false;
		}

		$attendee_checkin_key = $ticket_provider->checkin_key;
		$attendee_event_key = $ticket_provider->attendee_event_key;

		if ( empty( $attendee_checkin_key ) || empty( $attendee_event_key ) ) {
			// We tried to handle the check in, but it failed.
			return false;
		}

		// We do not know the Attendee to Event meta key, but we can work it out from the original Attendee.
		$series_id = (int) get_post_meta( $attendee_id, $attendee_event_key, true );

		if ( ! $series_id ) {
			// Weird, let the default checkin logic run.
			return $checkin;
		}

		if ( $event_id ) {
			$is_in_series = Series_Relationship::where( 'series_post_id', $series_id )
				->where( 'event_post_id', Occurrence::normalize_id( $event_id ) )
				->count();

			if ( ! $is_in_series ) {
				// The provided Event ID does point to an Event part of the Series: fail the checkin.
				return false;
			}

			$event_id_candidate = $this->get_event_candidate_from_event( $event_id, $attendee_id, $series_id, $qr );
			$event_post_id = Occurrence::normalize_id( $event_id_candidate );

			if ( $event_id_candidate && ! tribe_is_recurring_event( $event_post_id ) ) {
				// Single Event Attendees are related to the Event ID, not to their only Occurrence Provisional ID.
				$event_id_candidate = $event_post_id;
			}
		} else {
			$event_id_candidate = $this->get_event_candidate_from_series( $attendee_id, $series_id, $qr );
		}

		if ( $event_id_candidate === false ) {
			/*
			 * We either could not find a candidate Event to check the Attendee into, or there were too many.
			 * The user should pick one of the possible Events to check the Attendee into.
			 * @see self::build_attendee_failure_response() for more information.
			 */
			return false;
		}

		/*
		 * Fetch the ID of the cloned Attendee for this Series Pass and this Event, if it exists.
		 * Use the Attendee to Event meta key directly to avoid injection from Series Passes when using
		 * `event_id`.
		 */
		$existing_clone_id = tribe_attendees()->where( 'meta_equals', $attendee_event_key, $event_id_candidate )
			->where( 'meta_equals', self::CLONE_META_KEY, $attendee_id )
			->first_id();

		if ( $existing_clone_id ) {
			// The cloned Attendee already exists: let the ticket provider handle the checkin request.
			return $this->checkin_attendee_using_provider( $series_id, $existing_clone_id, $qr, $event_id_candidate );
		}

		$clone_id = $this->clone_attendee_to_event( $attendee_id, $event_id_candidate );

		if ( $clone_id === false ) {
			// We did handle the checkin and could not clone, thus check in, the Attendee.
			return false;
		}

		// Check in the cloned Attendee.
		return $this->checkin_attendee_using_provider( $series_id, $clone_id, $qr, $event_id_candidate );
	}

	/**
	 * Clones a Series Pass Attendee to an Attendee for the specified Event.
	 *
	 * @since TBD
	 *
	 * @param int $attendee_id The ID of the Attendee to clone.
	 * @param int $event_id    The ID of the Event to clone the Attendee to.
	 *
	 * @return int The cloned Attendee post ID, or `false` to indicate a failure.
	 */
	public function clone_attendee_to_event( int $attendee_id, int $event_id ) {
		/** @var Tickets $ticket_provider */
		$ticket_provider = tribe_tickets_get_ticket_provider( $attendee_id );

		if ( ! $ticket_provider instanceof Tickets ) {
			do_action(
				'tribe_log',
				'error',
				'Series Pass Attendee clone failed',
				[
					'source'               => __METHOD__,
					'original_attendee_id' => $attendee_id,
					'reason'               => 'Could not get the Attendee Ticket Provider',
				]
			);

			return false;
		}

		if (
			tribe_is_recurring_event( $event_id )
			&& ! tribe( Provisional_Post::class )->is_provisional_post_id( $event_id )
		) {
			// If the Event is recurring, a specific Provisional post ID must be provided.
			return false;
		}

		$attendee_event_key = $ticket_provider->attendee_event_key;

		$original_post = get_post( $attendee_id );

		if ( ! $original_post instanceof WP_Post ) {
			return false;
		}

		/** @var array<string,array<mixed>> $original_meta */
		$original_meta = get_post_meta( $original_post->ID );

		$clone_postarr = (array) $original_post;

		// Let's make this an insertion, not an update.
		unset( $clone_postarr['ID'] );

		/*
		 * Set the post type to one that will not trigger actions routinely associated with the insertion of an
		 * Attendee; e.g. the sending of emails. WordPress will not complain about the insertion of non-registered
		 * post types.
		 */
		$clone_postarr['post_type'] = '_tmp_attendee_clone';

		$clone_id = wp_insert_post( $clone_postarr );

		if ( empty( $clone_id ) || $clone_id instanceof WP_Error ) {
			do_action(
				'tribe_log',
				'error',
				'Series Pass Attendee clone failed',
				[
					'source'               => __METHOD__,
					'original_attendee_id' => $attendee_id,
					'reason'               => $clone_id instanceof WP_Error ? $clone_id->get_error_message() : 'n/a',
				]
			);

			return false;
		}

		/*
		 * This Attendee was cloned during this request. This information will be used to reload the Attendees list.
		 * See the `trigger_attendees_list_reload` method of this class.
		 */
		$this->reload_triggers[ $attendee_id ] = true;

		/*
		 * Insert the meta this way, not using `meta_input` to avoid the conflation of multiple entries for the same
		 * meta keys.
		 */
		foreach ( $original_meta as $meta_key => $meta_values ) {
			foreach ( $meta_values as $meta_value ) {
				add_post_meta( $clone_id, $meta_key, $meta_value );
			}
		}

		// Relate the clone with the Event.
		update_post_meta( $clone_id, $attendee_event_key, $event_id );

		// Mark the cloned Attendee as a clone of the original one.
		update_post_meta( $clone_id, self::CLONE_META_KEY, $attendee_id );

		/*
		 * Finally set the correct Attendee post type on the clone with a direct query to avoid triggering
		 * actions subscribed to the update of an Attendee post; e.g. emails.
		 */
		global $wpdb;
		$updated = $wpdb->update( $wpdb->posts, [ 'post_type' => $original_post->post_type ], [ 'ID' => $clone_id ], [ '%s' ], [ '%d' ] );

		if ( $updated === false ) {
			do_action(
				'tribe_log',
				'error',
				'Series Pass Attendee post type setting failed',
				[
					'source'               => __METHOD__,
					'original_attendee_id' => $attendee_id,
					'clone_id'             => $clone_id,
					'reason'               => $wpdb->last_error,
				]
			);

			return false;
		}

		clean_post_cache( $clone_id );

		/**
		 * Fires an action after the Series Pass Attendee has been cloned to Event.
		 *
		 * @since TBD
		 *
		 * @param int $clone_id    The cloned Attendee post ID.
		 * @param int $attendee_id The original Series Pass Attendee post ID.
		 * @param int $event_id    The provisional ID of the Event Occurrence the Attendee was related to.
		 */
		do_action( 'tec_tickets_flexible_tickets_series_pass_attendee_cloned', $clone_id, $attendee_id, $event_id );

		return $clone_id;
	}

	/**
	 * Builds a REST response to indicate an Attendee checkin attempt failed due to missing information.
	 *
	 * The HTTP return code is 300 to indicate multiple choices.
	 *
	 * @see   https://developer.mozilla.org/en-US/docs/Web/HTTP/Status#redirection_messages
	 *
	 * @since TBD
	 *
	 * @param WP_REST_Response $response    The original REST response.
	 * @param int              $attendee_id The Attendee ID.
	 *
	 * @return WP_REST_Response The REST response to indicate that the user should pick one of the possible Events
	 *                           to check the Attendee into.
	 */
	public function build_attendee_failure_response( WP_REST_Response $response, int $attendee_id ): WP_REST_Response {
		if ( ! isset( $this->checkin_failures[ $attendee_id ] ) ) {
			return $response;
		}

		$post_repository = tribe( 'tec.rest-v1.repository' );
		$prepared_candidates = array_map(
			static fn( int $candidate ) => $post_repository->get_event_data( $candidate, 'single' ),
			$this->checkin_failures[ $attendee_id ]['candidates']
		);

		$response = new WP_REST_Response(
			[
				'msg'         => _x( 'Multiple Event options, pick one and specify the context_id parameter.', 'event-tickets' ),
				'attendee_id' => $attendee_id,
				'candidates'  => $prepared_candidates,
			],
			300
		);

		return $response;
	}

	/**
	 * Fetches from the database the set of Events part of the Series the Attendee could potentially be checked into.
	 *
	 * @since TBD
	 *
	 * @param int  $attendee_id The post ID of the Series Pass Attendee that could potentially be checked into.
	 * @param int  $series_id   The post ID of the Series the Attendee holds a Series Pass for.
	 * @param bool $qr          Whether the checkin is being done via QR code or not.
	 *
	 * @return int[] The Occurrence provisional IDs that are part of the Series and are current, or start, in the
	 *               check-in timeframe.
	 */
	public function fetch_checkin_candidates_for_series( int $attendee_id, int $series_id, bool $qr ): array {
		[ $start, $end ] = $this->get_checkin_candidate_timestamps( $series_id, $attendee_id, $qr );

		// Fetch the candidate Occurrences, this will be an array of provisional IDs.
		$candidates = iterator_to_array(
			tribe_events()
				->where( 'series', $series_id )
				->where( 'ends_after', $start )
				->where( 'starts_before', $end )
				->get_ids( true ),
			false
		);

		return $candidates;
	}

	/**
	 * Fetches from the database the set of Events the Attendee could potentially be checked into.
	 *
	 * @since TBD
	 *
	 * @param int  $attendee_id The post ID of the Series Pass Attendee that could potentially be checked into.
	 * @param int  $event_id    The post ID of the Event the Attendee could be checked into.
	 * @param bool $qr          Whether the checkin is being done via QR code or not.
	 *
	 * @return int[] The Occurrence provisional IDs that are part of the Series and are current, or start, in the
	 *               check-in timeframe.
	 */
	public function fetch_checkin_candidates_for_event( int $attendee_id, int $event_id, bool $qr ): array {
		[ $start, $end ] = $this->get_checkin_candidate_timestamps( $event_id, $attendee_id, $qr );

		// Fetch the candidate Occurrences, this will be an array of provisional IDs.
		$candidates = iterator_to_array(
			Occurrence::where( 'post_id', $event_id )
				->where( 'end_date', '>', Dates::immutable( $start ) )
				->where( 'start_date', '<=', Dates::immutable( $end ) )->all(),
			false
		);

		return array_map(
			static fn( Occurrence $o ) => $o->provisional_id,
			$candidates
		);
	}

	/**
	 * Returns the start and end timestamp of the time window to check for Occurrences the Series Pass Attendee
	 * could check into.
	 *
	 * @since TBD
	 *
	 * @param int  $post_id     The post ID of the Series, or Event, to get the checkin times for.
	 * @param int  $attendee_id The post ID of the Attendee to check in.
	 * @param bool $qr          Whether the checkin is being done via QR code or not.
	 *
	 * @return array{0: int, 1: int} The checkin window start and end timestamps.
	 */
	private function get_checkin_candidate_timestamps( int $post_id, int $attendee_id, bool $qr ): array {
		if ( ! $qr ) {
			/*
			 *  We are not checking in via QR code: the checkin should never be restricted.
			 * Use `1` as start of the interval to pass empty checks.
			 */
			return [ 1, strtotime( '2100-12-31 00:00:00' ) ];
		}

		/*
		 * If the check-in is restricted, use the set time buffer value.
		 * Note: this is a programmatic API that comes after check-in restrictions have been applied.
		 * For this reason, a time buffer of `0` that would allow the administrator to block check-ins
		 * via QR code, is not respected here and the value will default to 6 hours. This method will be
		 * called from QR check-ins, but also from manual check-ins that should always be allowed.
		 */
		$time_buffer = tribe_get_option(
			'tickets-plus-qr-check-in-events-happening-now-happening-now',
			6 * HOUR_IN_SECONDS
		);

		/**
		 * Filters the amount of seconds to look into the future for from the current moment to search for current or
		 * upcoming Events part of a Series in the context of checking-in a Series Pass Attendee.
		 *
		 * This filter will be applied whether the check-in is restricted or not.
		 *
		 * @since TBD
		 *
		 * @param int $time_buffer The time frame, in seconds; defaults to the check-in window value, or 6 hours if that
		 *                         is not set.
		 * @param int $post_id     The post ID of the Series, or Event, to get the checkin times for.
		 * @param int $attendee_id The post ID of the Series Pass Attendee to check in.
		 */
		$time_buffer = (int) apply_filters(
			'tec_tickets_flexible_tickets_series_checkin_time_buffer',
			$time_buffer,
			$post_id,
			$attendee_id
		);

		// Let's set up the time window to pull current and upcoming Events from.
		$now = wp_date( 'U' );
		$starts_before = $now + $time_buffer;

		return [ $now, $starts_before ];
	}

	/**
	 * Returns the Occurrence provisional ID to check the Attendee into from an Event ID.
	 *
	 * @since TBD
	 *
	 * @param int  $event_id    The Event ID to check the Attendee into.
	 * @param int  $attendee_id The Attendee ID to check in.
	 * @param int  $series_id   The Series ID the Attendee holds a Series Pass for.
	 * @param bool $qr          Whether the checkin is being done via QR code or not.
	 *
	 * @return false|int The Occurrence provisional ID to check the Attendee into, or `false` if there are no
	 *                   candidate Events to check the Attendee into, or there are too many.
	 */
	private function get_event_candidate_from_event( int $event_id, int $attendee_id, int $series_id, bool $qr ) {
		// Reset the failure state for this Attendee.
		unset( $this->checkin_failures[ $attendee_id ] );

		// We have an Event ID, let's make sure it's normalized to the an Occurrence provisional ID.
		if ( tribe( Provisional_Post::class )->is_provisional_post_id( $event_id ) ) {
			// Is the Occurrence in the time frame we are interested in?
			$candidates = $this->is_occurrence_a_candidate( $attendee_id, $event_id, $qr ) ? [ $event_id ] : [];
		} else {
			// Fetch all the Occurrences in the time frame we are interested in.
			$candidates = $this->fetch_checkin_candidates_for_event( $attendee_id, $event_id, $qr );
		}

		if ( count( $candidates ) === 0 ) {
			// Checking-in cannot be done at this time.
			return false;
		}

		if ( count( $candidates ) === 1 ) {
			// Only one Occurrence can be found in the given timeframe, let's use that.
			return (int) reset( $candidates );
		}

		/*
		 * Too many options, log the failure and let the user pick one, if possible
		 * @see self::build_attendee_failure_response() for more information.
		 */
		$this->checkin_failures[ $attendee_id ] = [
			'attendee_id' => $attendee_id,
			'series_id'   => $series_id,
			'candidates'  => $candidates,
		];

		return false;
	}

	/**
	 * Returns the Occurrence provisional ID to check the Attendee into from a Series ID.
	 *
	 * @since TBD
	 *
	 * @param int  $attendee_id The Attendee ID to check in.
	 * @param int  $series_id   The Series ID the Attendee holds a Series Pass for.
	 * @param bool $qr          Whether the checkin is being done via QR code or not.
	 *
	 * @return false|int The Occurrence provisional ID to check the Attendee into, or `false` if there are no
	 *                   candidate Events to check the Attendee into, or there are too many.
	 */
	private function get_event_candidate_from_series( int $attendee_id, int $series_id, bool $qr ) {
		// Reset the failure state for this Attendee.
		unset( $this->checkin_failures[ $attendee_id ] );

		// We need to find out which Event part of the Series we should check the Attendee into.
		$candidates = $this->fetch_checkin_candidates_for_series( $attendee_id, $series_id, $qr );

		if ( count( $candidates ) === 0 ) {
			// Checking-in cannot be done at this time.
			return false;
		}

		if ( count( $candidates ) > 1 ) {
			/*
			 * Too many options, log the failure and let the user pick one, if possible
			 * @see self::build_attendee_failure_response() for more information.
			 */
			$this->checkin_failures[ $attendee_id ] = [
				'attendee_id' => $attendee_id,
				'series_id'   => $series_id,
				'candidates'  => $candidates,
			];

			// We did handle the checkin and could not check in the Attendee.
			return false;
		}

		return (int) reset( $candidates );
	}

	/**
	 * Checks in an Attendee using the ticket provider.
	 *
	 * @since TBD
	 *
	 * @param int       $series_id   The post ID of the Series the Attendee holds a Series Pass for.
	 * @param int       $attendee_id The post ID of the cloned Attendee to check in.
	 * @param bool|null $qr          Whether the checkin is being done via QR code or not.
	 * @param int|null  $event_id    The post ID of the Event to check the Attendee into, if available.
	 *
	 * @return bool Whether the Attendee was checked in or not.
	 */
	private function checkin_attendee_using_provider( int $series_id, int $attendee_id, bool $qr = false, int $event_id = null ): bool {
		$ticket_provider = Tickets::get_event_ticket_provider_object( $series_id );

		if ( ! $ticket_provider instanceof Tickets ) {
			// We tried to handle the check in, but it failed.
			return false;
		}

		// Let the default logic handle the 2nd checkin.
		remove_filter( 'tec_tickets_attendee_checkin', [ $this, 'handle_series_pass_attendee_checkin' ] );
		$checked_in = $ticket_provider->checkin( $attendee_id, $qr, $event_id );
		add_filter( 'tec_tickets_attendee_checkin', [ $this, 'handle_series_pass_attendee_checkin' ], 10, 4 );

		return $checked_in;
	}

	/**
	 * Checks whether an Occurrence is a candidate for the checkin of a Series Pass Attendee given the current
	 * time buffer.
	 *
	 * @since TBD
	 *
	 * @param int  $attendee_id    The post ID of the Attendee to check in.
	 * @param int  $provisional_id The provisional ID of the Occurrence to check.
	 * @param bool $qr             Whether the checkin is being done via QR code or not.
	 *
	 * @return bool Whether the Occurrence is a candidate for the checkin of the Attendee.
	 */
	private function is_occurrence_a_candidate( int $attendee_id, int $provisional_id, bool $qr ): bool {
		[ $start, $end ] = $this->get_checkin_candidate_timestamps( $provisional_id, $attendee_id, $qr );

		$occurrence_id = tribe( ID_Generator::class )->unprovide_id( $provisional_id );

		return Occurrence::where( 'occurrence_id', '=', $occurrence_id )
			       ->where( 'start_date', '<=', Dates::immutable( $end ) )
			       ->where( 'end_date', '>', Dates::immutable( $start ) )
			       ->count() > 0;
	}

	/**
	 * Returns the original Attendee post ID and an iterator of the Attendee post IDs to update.
	 *
	 * @since TBD
	 *
	 * @param int $post_id The post ID of the Attendee being updated.
	 *
	 * @return Iterator An iterator over the list of Attendee post IDs to update.
	 */
	private function get_update_targets( int $post_id ): Iterator {
		// Clone or original?
		$original_post_id = get_post_meta( $post_id, self::CLONE_META_KEY, true );

		$update_targets = new AppendIterator();
		if ( $original_post_id ) {
			// Clone: include the original post ID in the update targets, all other clones but this one.
			$update_targets->append( new ArrayIterator( [ $original_post_id ] ) );
			$attendees_generator = tribe_attendees()->where( 'meta_equals', self::CLONE_META_KEY, $original_post_id )
				->where( 'post__not_in', [ $post_id ] )
				->get_ids( true );
		} else {
			// Original: update all the clones. Fetch them from any status.
			$attendees_generator = tribe_attendees()->where( 'meta_equals', self::CLONE_META_KEY, $post_id )
				->where( 'post_status', get_post_stati() )
				->get_ids( true );
			$attendees_generator->rewind();
		}

		if ( $attendees_generator->valid() ) {
			$update_targets->append( $attendees_generator );
		}

		return $update_targets;
	}

	/**
	 * Syncs the data between the original Attendee and its clones.
	 *
	 * @since TBD
	 *
	 * @param int $post_id The post ID of the Attendee being updated.
	 *
	 * @return void The data is synced between the original Attendee and its clones.
	 */
	public function sync_attendee_on_edit( $post_id ): void {
		if ( ! is_int( $post_id ) ) {
			return;
		}

		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			return;
		}

		// Clone or original?
		$original_post_id = get_post_meta( $post_id, self::CLONE_META_KEY, true );
		$update_targets = $this->get_update_targets( $post_id );

		$updates = (array) $post;
		unset( $updates['ID'] );

		if ( $original_post_id && $updates['post_status'] === 'trash' ) {
			// Trashing a clone should never propagate to the original Attendee or other clones.
			unset( $updates['post_status'] );
		}

		// Run the updates removing the action to avoid infinite loops.
		$this->unsubscribe_from_attendee_updates();
		foreach ( $update_targets as $update_target ) {
			$postarr = array_merge( [ 'ID' => $update_target ], $updates );
			$updated = wp_update_post( $postarr );
			if ( ! $updated ) {
				do_action(
					'tribe_log',
					'error',
					'Update of original or cloned Attendee failed.',
					[
						'source'      => __METHOD__,
						'attendee_id' => $update_target,
						'reason'      => $updated instanceof WP_Error ? $updated->get_error_message() : 'n/a',
					]
				);
			}
		}
		$this->subscribe_to_attendee_updates();
	}

	/**
	 * Returns whether the meta key is the one used to store the checkin status or the check-in
	 * details.
	 *
	 * @since TBD
	 *
	 * @param int    $post_id  The post ID of the Attendee to check.
	 * @param string $meta_key The meta key to check.
	 *
	 * @return bool Whether the meta key is the one used to store the checkin status or the check-in
	 *              details.
	 */
	private function is_checked_in_meta_key( int $post_id, string $meta_key ): bool {
		$post_type = get_post_type( $post_id );

		if ( empty( $post_type ) ) {
			return false;
		}

		$checkin_key = $this->post_type_checkin_keys[ $post_type ] ?? null;

		if ( $checkin_key === null ) {
			/** @var Tickets $ticket_provider */
			$ticket_provider = tribe_tickets_get_ticket_provider( $post_id );

			if ( ! $ticket_provider instanceof Tickets ) {
				// We tried to handle the check in, but it failed.
				return false;
			}

			$checkin_key = $ticket_provider->checkin_key;
			$this->post_type_checkin_keys[ $post_type ] = $checkin_key;
		}

		return $meta_key === $checkin_key || $meta_key === $checkin_key . '_details' || $meta_key === '_tribe_qr_status';
	}

	/**
	 * Syncs the meta between the original Attendee and its clones when added.
	 *
	 * @since TBD
	 *
	 * @param int    $meta_id    The meta ID.
	 * @param int    $post_id    The ID of the post the meta was added to.
	 * @param string $meta_key   The meta key.
	 * @param mixed  $meta_value The meta value.
	 *
	 * @return void The meta is updated across Attendees if `$post_id` points to an Attendee post.
	 */
	public function sync_attendee_meta_on_meta_add( $meta_id, $post_id, $meta_key, $meta_value ): void {
		if ( ! (
			is_int( $post_id )
			&& is_string( $meta_key )
			&& in_array( get_post_type( $post_id ), tribe_attendees()->attendee_types(), true ) )
		) {
			return;
		}

		if ( $this->is_checked_in_meta_key( $post_id, $meta_key ) ) {
			return;
		}

		$update_targets = $this->get_update_targets( $post_id );

		$this->unsubscribe_from_attendee_updates();
		foreach ( $update_targets as $update_target ) {
			if ( ! ( add_post_meta( $update_target, $meta_key, $meta_value ) ) ) {
				do_action(
					'tribe_log',
					'error',
					'Adding meta to original or cloned Attendee failed.',
					[
						'source'      => __METHOD__,
						'attendee_id' => $update_target,
						'meta_key'    => $meta_key,
					]
				);
			}
		}
		$this->subscribe_to_attendee_updates();
	}

	/**
	 * Syncs the meta between the original Attendee and its clones when updated.
	 *
	 * @since TBD
	 *
	 * @param int    $meta_id    The meta ID.
	 * @param int    $post_id    The ID of the post the meta was updated for.
	 * @param string $meta_key   The meta key.
	 * @param mixed  $meta_value The meta value.
	 *
	 * @return void The meta is updated across Attendees if `$post_id` points to an Attendee post.
	 */
	public function sync_attendee_meta_on_meta_update( $meta_id, $post_id, $meta_key, $meta_value ): void {
		if ( ! (
			is_int( $post_id )
			&& is_string( $meta_key )
			&& in_array( get_post_type( $post_id ), tribe_attendees()->attendee_types(), true ) )
		) {
			return;
		}

		if ( $this->is_checked_in_meta_key( $post_id, $meta_key ) ) {
			return;
		}

		$update_targets = $this->get_update_targets( $post_id );

		$this->unsubscribe_from_attendee_updates();
		foreach ( $update_targets as $update_target ) {
			// Not checking the return value as a `false` could just mean the meta value is unchanged.
			update_post_meta( $update_target, $meta_key, $meta_value );
		}
		$this->subscribe_to_attendee_updates();
	}

	/**
	 * Syncs the meta between the original Attendee and its clones when deleted.
	 *
	 * @since TBD
	 *
	 * @param int    $meta_id    The meta ID.
	 * @param int    $post_id    The ID of the post the meta was deleted from.
	 * @param string $meta_key   The meta key.
	 * @param mixed  $meta_value The meta value.
	 *
	 * @return void The meta is updated across Attendees if `$post_id` points to an Attendee post.
	 */
	public function sync_attendee_meta_on_meta_delete( $meta_id, $post_id, $meta_key, $meta_value ): void {
		if ( ! (
			is_int( $post_id )
			&& is_string( $meta_key )
			&& in_array( get_post_type( $post_id ), tribe_attendees()->attendee_types(), true ) )
		) {
			return;
		}

		if ( $this->is_checked_in_meta_key( $post_id, $meta_key ) ) {
			return;
		}

		$update_targets = $this->get_update_targets( $post_id );

		$this->unsubscribe_from_attendee_updates();
		foreach ( $update_targets as $update_target ) {
			// Not checking the return value as a `false` could just mean the meta value was not there.
			delete_post_meta( $update_target, $meta_key, $meta_value );
		}
		$this->subscribe_to_attendee_updates();
	}

	/**
	 * Deletes all the clones of a Series Pass Attendee when the original is deleted.
	 *
	 * @since TBD
	 *
	 * @param int     $post_id The post ID of the Attendee being deleted.
	 * @param WP_Post $post    The post object before it was deleted.
	 *
	 * @return void The clones are deleted.
	 */
	public function delete_clones_on_delete( $post_id, $post ): void {
		if ( ! (
			is_int( $post_id )
			&& $post instanceof WP_post
			&& in_array( $post->post_type, tribe_attendees()->attendee_types(), true ) )
		) {
			return;
		}

		if ( get_post_meta( $post_id, self::CLONE_META_KEY, true ) ) {
			// Deleting a clone should not trigger the deletion of the original post.
			return;
		}

		$update_targets = $this->get_update_targets( $post_id );

		$this->unsubscribe_from_attendee_updates();
		foreach ( $update_targets as $update_target ) {
			if ( ! ( wp_delete_post( $update_target, true ) ) ) {
				do_action(
					'tribe_log',
					'error',
					'Series Pass clone Attendee deletion failed',
					[
						'source'               => __METHOD__,
						'original_attendee_id' => $update_target,
					]
				);
			}
		}
		$this->subscribe_to_attendee_updates();
	}

	/**
	 * Filters the post IDs used to fetch an Event attendees to include the Series the Event belongs to and,
	 * thus, include Series Passes into the results.
	 *
	 * @since TBD
	 *
	 * @param int|array<int>                    $post_id    The post ID or IDs.
	 * @param Tribe__Repository__Interface|null $repository The repository instance.
	 *
	 * @return int|array<int> The updated post ID or IDs.
	 */
	public function include_series_to_fetch_attendees( $post_id, $repository = null ): array {
		if ( ! $repository instanceof Tribe__Repository__Interface ) {
			return $post_id;
		}

		$post_ids = (array) $post_id;
		$event_ids = array_filter( $post_ids, static fn( int $id ) => get_post_type( $id ) === TEC::POSTTYPE );

		if ( ! count( $event_ids ) ) {
			return $post_id;
		}

		$ids_generator = tec_series()->where( 'event_post_id', $event_ids )->get_ids( true );
		$series_ids = iterator_to_array( $ids_generator, false );

		if ( ! count( $series_ids ) ) {
			return $post_id;
		}

		global $wpdb;
		$attendee_to_event_keys = tribe_attendees()->attendee_to_event_keys();
		$prepared_attendee_to_event_keys = $wpdb->prepare(
			implode( ', ', array_fill( 0, count( $attendee_to_event_keys ), '%s' ) ),
			...array_values($attendee_to_event_keys)
		);
		$event_ids_set = $wpdb->prepare(
			implode( ', ', array_fill( 0, count( $post_ids ), '%d' ) ),
			...$post_ids
		);

		/*
		 * Exclude from the results Series Pass Attendees that have been cloned to the Events
		 * by pulling the list of original IDs pointed by the Events' Attendees clones.
		 */
		$repository->where_clause(
			$wpdb->prepare(
				"{$wpdb->posts}.ID NOT IN (
					SELECT clone_of.meta_value from {$wpdb->postmeta} clone_of
						  JOIN {$wpdb->postmeta} for_event
						  ON for_event.post_id = clone_of.post_id
					      AND for_event.meta_key IN ({$prepared_attendee_to_event_keys})
					WHERE clone_of.meta_key = %s
					      AND for_event.meta_value IN ({$event_ids_set})
						  AND clone_of.meta_value IS NOT NULL
						  AND clone_of.meta_value != ''
				)",
				self::CLONE_META_KEY
			)
		);

		// Add the Series to the posts to fetch Attendees for.
		$post_ids = array_values( array_unique( array_merge( $post_ids, $series_ids ) ) );

		return $post_ids;
	}

	/**
	 * Filters the JavaScript configuration for the Attendees report to include the confirmation strings for
	 * Series Passes.
	 *
	 * @since TBD
	 *
	 * @param array<string,mixed> $config_data The JavaScript configuration.
	 *
	 * @return array<string,mixed> The updated JavaScript configuration.
	 */
	public function filter_tickets_attendees_report_js_config( array $config_data ): array {
		return $this->edit->filter_tickets_attendees_report_js_config( $config_data );
	}

	/**
	 * Returns whether an Attendee is a clone of Another Attendee.
	 *
	 * @since TBD
	 *
	 * @param int $clone_id   The post ID of the Attendee to check.
	 * @param int $original_id The post ID of the Attendee to check against.
	 *
	 * @return bool Whether the Attendee is a clone of the other Attendee.
	 */
	public function attendee_is_a_clone_of( int $clone_id, int $original_id ): bool {
		return (int) get_post_meta( $clone_id, self::CLONE_META_KEY, true ) === $original_id;
	}

	/**
	 * Returns the list of contexts where the normalization of the Event ID should be avoided.
	 *
	 * @since TBD
	 *
	 * @return string[] The list of contexts where the normalization of the Event ID should be avoided.
	 */
	public function get_controlled_event_filter_contexts(): array {
		return [ 'attendees-table', 'tickets-metabox-render', 'attendees-report-link' ];
	}

	/**
	 * Alters the normalization of the Event ID to avoid it being normalized when looking at Occurrences
	 * Attendees table.
	 *
	 * @since TBD
	 *
	 * @param int         $event_id    The Event ID to normalize.
	 * @param string|null $context     The context of the normalization.
	 * @param int|null    $original_id The original ID of the Event.
	 *
	 * @return int The normalized Event ID, or the original ID if the context is `attendees-table` and the
	 *             original ID is a provisional ID.
	 */
	public function preserve_provisional_id( $event_id, $context = 'default', $original_id = null ) {
		if ( ! is_int( $event_id ) || ! in_array( $context, $this->get_controlled_event_filter_contexts(), true ) ) {
			return $event_id;
		}

		if ( tribe_is_recurring_event( Occurrence::normalize_id( $original_id ) ) ) {
			return $original_id;
		}

		return $event_id;
	}

	/**
	 * Raises the reload flag in the AJAX response to trigger a reload of the Attendees list.
	 *
	 * @since TBD
	 *
	 * @param array{did_checkin: bool} $data        The AJAX response data.
	 * @param int                      $attendee_id The original Attendee ID.
	 *
	 * @return array{did_checkin: bool, reload?: bool} The AJAX response data, with the reload flag set if the
	 *                                                 Attendee should trigger a reload of the Attendees list.
	 */
	public function trigger_attendees_list_reload( array $data, int $attendee_id ): array {
		if ( isset( $this->reload_triggers[ $attendee_id ] ) ) {
			$data['reload'] = true;
		}

		return $data;
	}

	/**
	 * Resets the list of Attendees that should trigger a reload of the Attendees list.
	 *
	 * @since TBD
	 *
	 * @return void The list of Attendees that should trigger a reload of the Attendees list is reset.
	 */
	public function reset_reload_trigger(): void {
		$this->reload_triggers = [];
	}
}
