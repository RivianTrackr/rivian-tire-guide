<?php
/**
 * Tests for RTG_Lock — the mutual exclusion the sync jobs run under.
 */
class Test_RTG_Lock extends WP_UnitTestCase {

    public function tearDown(): void {
        RTG_Lock::release( 'unit' );
        parent::tearDown();
    }

    public function test_a_lock_can_be_taken_once_until_released() {
        $this->assertTrue( RTG_Lock::acquire( 'unit', 60 ) );
        $this->assertTrue( RTG_Lock::is_held( 'unit' ) );
        $this->assertFalse( RTG_Lock::acquire( 'unit', 60 ), 'a second taker must be refused while the first holds it' );

        RTG_Lock::release( 'unit' );

        $this->assertFalse( RTG_Lock::is_held( 'unit' ) );
        $this->assertTrue( RTG_Lock::acquire( 'unit', 60 ), 'released, it can be taken again' );
    }

    /**
     * A run that died without releasing must not wedge the job forever: once
     * the TTL has passed the next caller takes the lock over.
     */
    public function test_an_expired_lock_is_taken_over() {
        global $wpdb;

        $this->assertTrue( RTG_Lock::acquire( 'unit', 60 ) );

        // Age the lock past its expiry by hand.
        $wpdb->update(
            $wpdb->options,
            array( 'option_value' => (string) ( time() - 5 ) ),
            array( 'option_name' => RTG_Lock::PREFIX . 'unit' )
        );

        $this->assertFalse( RTG_Lock::is_held( 'unit' ), 'an expired lock does not count as held' );
        $this->assertTrue( RTG_Lock::acquire( 'unit', 60 ), 'the expired lock is taken over' );
        $this->assertFalse( RTG_Lock::acquire( 'unit', 60 ), 'and is exclusive again' );
    }

    public function test_locks_are_independent_by_name() {
        $this->assertTrue( RTG_Lock::acquire( 'unit', 60 ) );
        $this->assertTrue( RTG_Lock::acquire( 'unit_other', 60 ) );
        RTG_Lock::release( 'unit_other' );
    }

    public function test_the_busy_result_carries_the_locked_status() {
        $result = RTG_Lock::busy_result( 'discovery run' );
        $this->assertSame( 'locked', $result['status'] );
        $this->assertStringContainsString( 'discovery run', $result['message'] );
        $this->assertArrayHasKey( 'time', $result );
    }

    /**
     * The jobs themselves honour the lock: a second run while one is in
     * progress reports itself rather than doing the work twice.
     */
    public function test_sync_jobs_report_locked_instead_of_running_twice() {
        update_option( 'rtg_settings', array(
            'catalog_sync_enabled' => true,
            'roamer_sync_enabled'  => true,
        ) );

        RTG_Lock::acquire( RTG_Catalog_Sync::LOCK_NAME, 60 );
        RTG_Lock::acquire( RTG_Roamer_Sync::LOCK_NAME, 60 );
        RTG_Lock::acquire( RTG_Link_Checker::LOCK_NAME, 60 );

        try {
            $this->assertSame( 'locked', RTG_Catalog_Sync::run()['status'] );
            $this->assertSame( 'locked', RTG_Roamer_Sync::run()['status'] );
            $this->assertSame( 'locked', RTG_Link_Checker::run()['status'] );
        } finally {
            RTG_Lock::release( RTG_Catalog_Sync::LOCK_NAME );
            RTG_Lock::release( RTG_Roamer_Sync::LOCK_NAME );
            RTG_Lock::release( RTG_Link_Checker::LOCK_NAME );
        }

        // A busy run never overwrites the last real run's stats.
        $this->assertFalse( RTG_Catalog_Sync::get_stats() );
        $this->assertFalse( RTG_Roamer_Sync::get_stats() );
    }

    /**
     * The disabled check comes first: a disabled job answers "disabled"
     * without contending for the lock.
     */
    public function test_a_disabled_job_does_not_take_the_lock() {
        update_option( 'rtg_settings', array( 'roamer_sync_enabled' => false ) );

        $this->assertSame( 'disabled', RTG_Roamer_Sync::run()['status'] );
        $this->assertFalse( RTG_Lock::is_held( RTG_Roamer_Sync::LOCK_NAME ) );
    }
}
