<?php

namespace TEC\Tickets\Flexible_Tickets;

use TEC\Common\Tests\Provider\Controller_Test_Case;
use TEC\Events\Custom_Tables\V1\Migration\Strategies\Null_Migration_Strategy;
use TEC\Events_Pro\Custom_Tables\V1\Events\Recurrence;
use TEC\Tickets\Flexible_Tickets\CT1_Migration\Strategies\RSVP_Ticketed_Recurring_Event_Strategy;
use Tribe\Events_Pro\Tests\Traits\CT1\CT1_Fixtures;
use Tribe\Tickets\Test\Commerce\RSVP\Ticket_Maker as RSVP_Ticket_Maker;

class CT1_Migration_Test extends Controller_Test_Case {
	use CT1_Fixtures;
	use RSVP_Ticket_Maker;

	protected string $controller_class = CT1_Migration::class;

	private function given_a_non_migrated_multi_rule_recurring_event(): \WP_Post {
		$recurrence = static function ( int $id ): array {
			return ( new Recurrence() )
				->with_start_date( get_post_meta( $id, '_EventStartDate', true ) )
				->with_end_date( get_post_meta( $id, '_EventEndDate', true ) )
				->with_weekly_recurrence()
				->with_end_after( 50 )
				->with_monthly_recurrence( 1, false, 23 )
				->with_end_after( 5 )
				->to_event_recurrence();
		};

		return $this->given_a_non_migrated_recurring_event( $recurrence );
	}

	/**
	 * It should not alter the strategy to migrate a single event
	 *
	 * @test
	 */
	public function should_not_alter_the_strategy_to_migrate_a_single_event(): void {
		$single_event = $this->given_a_non_migrated_single_event();

		$controller = $this->make_controller();
		$strategy   = $controller->alter_migration_strategy( new Null_Migration_Strategy(), $single_event->ID, false );

		$this->assertInstanceOf( Null_Migration_Strategy::class, $strategy );
	}

	/**
	 * It should not alter the strategy to migrate a recurring event with one rule
	 *
	 * @test
	 */
	public function should_not_alter_the_strategy_to_migrate_a_recurring_event_with_one_rule(): void {
		$recurring_event = $this->given_a_non_migrated_recurring_event();

		$controller = $this->make_controller();
		$strategy   = $controller->alter_migration_strategy(
			new Null_Migration_Strategy(),
			$recurring_event->ID,
			false
		);

		$this->assertInstanceOf( Null_Migration_Strategy::class, $strategy );
	}

	/**
	 * It should not alter the strategy to migrate a recurring event with multiple rules
	 *
	 * @test
	 */
	public function should_not_alter_the_strategy_to_migrate_a_recurring_event_with_multiple_rules(): void {
		$multi_rule_recurring_event = $this->given_a_non_migrated_multi_rule_recurring_event();

		$controller = $this->make_controller();
		$strategy   = $controller->alter_migration_strategy(
			new Null_Migration_Strategy(),
			$multi_rule_recurring_event->ID,
			false
		);

		$this->assertInstanceOf( Null_Migration_Strategy::class, $strategy );
	}

	/**
	 * It should not alter the strategy to migrate a single event with RSVP tickets
	 *
	 * @test
	 */
	public function should_not_alter_the_strategy_to_migrate_a_single_event_with_rsvp_tickets(): void {
		$single_event = $this->given_a_non_migrated_single_event();
		$this->create_rsvp_ticket( $single_event->ID );

		$controller = $this->make_controller();
		$strategy   = $controller->alter_migration_strategy( new Null_Migration_Strategy(), $single_event->ID, false );

		$this->assertInstanceOf( Null_Migration_Strategy::class, $strategy );
	}

	/**
	 * It should alter the strategy to migrate a recurring event with one rule and RSVP tickets
	 *
	 * @test
	 */
	public function should_alter_the_strategy_to_migrate_a_recurring_event_with_one_rule_and_rsvp_tickets(): void {
		$recurring_event = $this->given_a_non_migrated_recurring_event();
		$this->create_rsvp_ticket( $recurring_event->ID );

		$controller = $this->make_controller();
		$strategy   = $controller->alter_migration_strategy(
			new Null_Migration_Strategy(),
			$recurring_event->ID,
			false
		);

		$this->assertInstanceOf( RSVP_Ticketed_Recurring_Event_Strategy::class, $strategy );
	}

	/**
	 * It should alter the strategy to migrate a recurring event with multiple rules and RSVP tickets
	 *
	 * @test
	 */
	public function should_alter_the_strategy_to_migrate_a_recurring_event_with_multiple_rules_and_rsvp_tickets(): void {
		$multi_rule_recurring_event = $this->given_a_non_migrated_multi_rule_recurring_event();
		$this->create_rsvp_ticket( $multi_rule_recurring_event->ID );

		$controller = $this->make_controller();
		$strategy   = $controller->alter_migration_strategy(
			new Null_Migration_Strategy(),
			$multi_rule_recurring_event->ID,
			false
		);

		$this->assertInstanceOf( RSVP_Ticketed_Recurring_Event_Strategy::class, $strategy );
	}
}