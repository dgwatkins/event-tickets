<?php

use Rt_smoketestTester as Tester;

class ActivationCest {
	/**
	 * It should activate deactivate correctly
	 *
	 * @test
	 */
	public function should_activate_deactivate_correctly( Tester $I ): void {
		$I->loginAsAdmin();

		$I->amOnPluginsPage( '' );
		$I->seePluginActivated( 'event-tickets' );

		$I->deactivatePlugin( 'event-tickets' );
		$I->amOnPluginsPage(); // Back to the plugins page to deal with redirection after deactivation.
		$I->seePluginDeactivated( 'event-tickets' );

		$I->activatePlugin( 'event-tickets' );
		$I->amOnPluginsPage(); // Back to plugins page to deal with redirection after activation.
		$I->amOnPluginsPage(); // And back again to deal with the second redirection after activation.
		$I->seePluginActivated( 'event-tickets' );
	}
}
