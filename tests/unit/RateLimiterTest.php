<?php

use PHPUnit\Framework\TestCase;

class RateLimiterTest extends TestCase {

	private const MAX_ATTEMPTS    = 5;
	private const LOCKOUT_SECONDS = 15 * MINUTE_IN_SECONDS;

	protected function setUp(): void {
		$GLOBALS['cop_test_transients'] = array();
		$GLOBALS['cop_test_time']       = 1700000000;
		$GLOBALS['wpdb']->inserts       = array();
		$_SERVER['REMOTE_ADDR']         = '203.0.113.7';
		$_SERVER['REQUEST_URI']         = '/wp-login.php';
	}

	public function test_login_is_allowed_below_the_attempt_limit() {
		$user = new stdClass();

		for ( $i = 0; $i < self::MAX_ATTEMPTS - 1; $i++ ) {
			COP_Rate_Limiter::record_failed_login( 'alice' );
		}

		$result = COP_Rate_Limiter::maybe_block_login( $user, 'alice', 'secret' );

		$this->assertSame( $user, $result );
	}

	public function test_login_is_blocked_after_max_failed_attempts_and_logged() {
		for ( $i = 0; $i < self::MAX_ATTEMPTS; $i++ ) {
			COP_Rate_Limiter::record_failed_login( 'alice' );
		}

		$result = COP_Rate_Limiter::maybe_block_login( new stdClass(), 'alice', 'secret' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'cop_too_many_attempts', $result->get_error_code() );

		$this->assertCount( 1, $GLOBALS['wpdb']->inserts );
		$this->assertSame( COP_Audit_Log::EVENT_LOGIN_THROTTLED, $GLOBALS['wpdb']->inserts[0]['data']['event'] );
	}

	public function test_successful_login_clears_the_failure_counter() {
		for ( $i = 0; $i < self::MAX_ATTEMPTS; $i++ ) {
			COP_Rate_Limiter::record_failed_login( 'alice' );
		}

		COP_Rate_Limiter::clear_login_counter( 'alice', new stdClass() );

		$user   = new stdClass();
		$result = COP_Rate_Limiter::maybe_block_login( $user, 'alice', 'secret' );

		$this->assertSame( $user, $result );
	}

	public function test_lockout_expires_after_the_time_window() {
		for ( $i = 0; $i < self::MAX_ATTEMPTS; $i++ ) {
			COP_Rate_Limiter::record_failed_login( 'alice' );
		}

		$blocked = COP_Rate_Limiter::maybe_block_login( new stdClass(), 'alice', 'secret' );
		$this->assertInstanceOf( WP_Error::class, $blocked );

		$GLOBALS['cop_test_time'] += self::LOCKOUT_SECONDS + 1;

		$user   = new stdClass();
		$result = COP_Rate_Limiter::maybe_block_login( $user, 'alice', 'secret' );

		$this->assertSame( $user, $result );
	}

	public function test_counters_are_scoped_per_username() {
		for ( $i = 0; $i < self::MAX_ATTEMPTS; $i++ ) {
			COP_Rate_Limiter::record_failed_login( 'alice' );
		}

		$user   = new stdClass();
		$result = COP_Rate_Limiter::maybe_block_login( $user, 'bob', 'secret' );

		$this->assertSame( $user, $result );
	}
}
