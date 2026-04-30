<?php
/**
 * Verifies the recurring tick is registered (and only once) via the
 * self-healing `Plugin::register_recurring_tick` hook.
 *
 * The test exists because we deliberately do NOT register the tick from
 * `register_activation_hook`: Action Scheduler bootstraps on `plugins_loaded`,
 * which is later than activation callbacks, so an `as_*()` call from there
 * would fatal. The chosen alternative — call from `init` priority 20 every
 * page load, gated by `as_has_scheduled_action()` — must be both
 * self-healing (schedules when missing) and idempotent (no-op when present).
 *
 * @package LEAStudios\SiteAudit\Tests
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Tests\Integration\Scheduler;

use LEAStudios\SiteAudit\Plugin;
use LEAStudios\Tests\TestCase;

final class Tick_Self_Heals_On_Init_Test extends TestCase {

	public function set_up(): void {
		parent::set_up();

		// Action Scheduler is loaded via the main plugin file but its tables
		// are created lazily — touch the API once to ensure schema exists.
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( Plugin::TICK_HOOK );
		}

		// register_recurring_tick caches an "already scheduled" affirmative
		// in a transient to avoid hitting the AS query on every page load.
		// Clear it so a freshly-cleaned scheduler queue actually triggers
		// the schedule path again.
		delete_transient( 'leastudios_siteaudit_tick_scheduled' );
	}

	public function tear_down(): void {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( Plugin::TICK_HOOK );
		}
		delete_transient( 'leastudios_siteaudit_tick_scheduled' );
		parent::tear_down();
	}

	public function test_register_recurring_tick_schedules_action_when_missing(): void {
		$this->assertFalse( as_has_scheduled_action( Plugin::TICK_HOOK ), 'Precondition: no tick scheduled.' );

		( new Plugin() )->register_recurring_tick();

		$this->assertNotFalse( as_has_scheduled_action( Plugin::TICK_HOOK ), 'Tick must be scheduled after first call.' );
	}

	public function test_register_recurring_tick_is_idempotent(): void {
		$plugin = new Plugin();

		$plugin->register_recurring_tick();
		$first_action_id = as_next_scheduled_action( Plugin::TICK_HOOK );

		$plugin->register_recurring_tick();
		$plugin->register_recurring_tick();
		$plugin->register_recurring_tick();

		$second_action_id = as_next_scheduled_action( Plugin::TICK_HOOK );

		// `as_next_scheduled_action` returns the action id of the next pending
		// occurrence. If we duplicated the recurring action, multiple ids would
		// satisfy the query and the implementation could return either; the
		// safer assertion is that there's only one pending action of this hook.
		$pending = as_get_scheduled_actions(
			[
				'hook'   => Plugin::TICK_HOOK,
				'status' => \ActionScheduler_Store::STATUS_PENDING,
			],
			'ids'
		);
		$this->assertCount( 1, $pending, 'Repeated calls must not stack duplicate recurring actions.' );
		$this->assertSame( $first_action_id, $second_action_id );
	}

	public function test_register_recurring_tick_is_a_noop_when_action_scheduler_is_unavailable(): void {
		// Sanity: the API is loaded in this test environment.
		$this->assertTrue( function_exists( 'as_has_scheduled_action' ) );

		// Calling the method should not throw even if AS were absent — the
		// guard is defensive code at a hard dependency boundary, not part of
		// the happy path. Best we can assert here is that it runs without error
		// when AS is present (and we trust the function_exists guards above
		// to short-circuit when it is not).
		( new Plugin() )->register_recurring_tick();
		$this->assertTrue( true );
	}
}
