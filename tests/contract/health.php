<?php
/**
 * Executable contract check for RTG_Health.
 *
 * The promise this pins is behavioural, not structural: a problem emails once
 * when it appears and once when it clears — a week-long outage is two emails,
 * not seven — and a disabled setting silences everything. That lifecycle is
 * exactly the kind of logic `php -l` cannot see and the DB-backed PHPUnit
 * suite never runs, which is how the last silent defect shipped.
 *
 * Runs on plain PHP with stubbed WordPress functions and a recording mailer;
 * nothing touches a database or the network.
 *
 * Run with: php tests/contract/health.php
 * Exits non-zero on failure, so CI gates on it.
 */

define( 'ABSPATH', __DIR__ );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
function human_time_diff( $f, $t ) { return '1 hour'; }
function current_time( $t ) { return 'timestamp' === $t ? $GLOBALS['now'] : date( 'Y-m-d H:i:s', $GLOBALS['now'] ); }
$GLOBALS['opts'] = array();
function get_option( $k, $d = false ) { return $GLOBALS['opts'][$k] ?? $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][$k] = $v; return true; }

require __DIR__ . '/../../includes/class-rtg-tire-qualifier.php';
class RTG_Admin { public static function get_dropdown_options( $w ) { return array( '275/65R20' ); } }
class RTG_Catalog_Sync {
    public static function match_key( $b, $m, $s ) { return ''; }
    public static function match_keys_for_tire( $t ) { return array(); }
    public static function get_stats() { return $GLOBALS['stats']; }
}
class RTG_Database { public static function get_all_tires() { return array(); } }
class RTG_Candidates { public static function get_by_match_key() { return array(); } }
// Record what gets emailed instead of sending.
class RTG_Mailer {
    public static $sent = array();
    public static function send_health_alert( $issues ) { self::$sent[] = 'alert:' . implode( '+', array_keys( $issues ) ); return true; }
    public static function send_health_recovered() { self::$sent[] = 'recovered'; return true; }
    public static function send_delisting_notification( $rows ) { self::$sent[] = 'delisted:' . count( $rows ); return true; }
}
require __DIR__ . '/../../includes/class-rtg-catalog-presence.php';
require __DIR__ . '/../../includes/class-rtg-health.php';

$GLOBALS['now'] = strtotime( '2026-08-25 09:00:00' );
$broken  = array( 'status' => 'error', 'time' => date( 'Y-m-d H:i:s', $GLOBALS['now'] - 3600 ), 'fetched' => 0,
    'errors' => array( array( 'message' => 'HTTP 401 — the token was rejected.' ) ), 'sources' => array() );
$healthy = array( 'status' => 'success', 'time' => date( 'Y-m-d H:i:s', $GLOBALS['now'] - 3600 ), 'fetched' => 15000,
    'errors' => array(), 'sources' => array( array( 'coverage' => array( '275/65R20' => array( 'received' => 10, 'total' => 10 ) ) ) ) );

$fail = 0;
function check( $label, $cond ) { printf( "%-52s %s\n", $label, $cond ? 'ok' : 'FAIL' ); if ( ! $cond ) { $GLOBALS['fail']++; } }

// --- Classification: each failure mode this feature has actually had maps to
// --- its own code, with its own fix in the label.
$now2  = $GLOBALS['now'];
$full  = array( 'sources' => array( array( 'coverage' => array( '275/65R20' => array( 'received' => 10, 'total' => 10 ) ) ) ) );
$ok_at = function ( $hours_ago ) use ( $now2 ) { return date( 'Y-m-d H:i:s', $now2 - $hours_ago * 3600 ); };
$codes = function ( $stats ) use ( $now2 ) {
    return array_keys( RTG_Health::evaluate( $stats, array( '275/65R20' ), $now2 ) );
};

check( 'a healthy run raises nothing',
    array() === $codes( array_merge( array( 'status' => 'success', 'time' => $ok_at( 2 ), 'fetched' => 100, 'errors' => array() ), $full ) ) );
check( 'never having run is setup, not breakage',
    array() === $codes( false ) );
check( 'a dead schedule is its own finding',
    array( 'sweep_stale' ) === $codes( array_merge( array( 'status' => 'success', 'time' => $ok_at( 72 ), 'fetched' => 100, 'errors' => array() ), $full ) ) );
check( 'a rejected token names the PAT, not a generic failure',
    array( 'auth_rejected' ) === $codes( array( 'status' => 'error', 'time' => $ok_at( 2 ), 'fetched' => 0,
        'errors' => array( array( 'message' => 'HTTP 401 — the token was rejected.' ) ), 'sources' => array() ) ) );
check( 'any other failed run reports as failed',
    array( 'sweep_failed' ) === $codes( array( 'status' => 'error', 'time' => $ok_at( 2 ), 'fetched' => 0,
        'errors' => array( array( 'message' => 'GraphQL error: Cannot query field x' ) ), 'sources' => array() ) ) );
check( 'a run that read zero products is flagged',
    array( 'nothing_fetched' ) === $codes( array( 'status' => 'success', 'time' => $ok_at( 2 ), 'fetched' => 0, 'errors' => array(), 'sources' => array() ) ) );
check( 'a fitment no longer read completely is flagged',
    array( 'coverage_partial' ) === $codes( array( 'status' => 'success', 'time' => $ok_at( 2 ), 'fetched' => 100, 'errors' => array(),
        'sources' => array( array( 'coverage' => array( '275/65R20' => array( 'received' => 4, 'total' => 10 ) ) ) ) ) ) );
check( 'stats with no coverage data cannot claim partial',
    array() === $codes( array( 'status' => 'success', 'time' => $ok_at( 2 ), 'fetched' => 100, 'errors' => array(), 'sources' => array( array( 'slug' => 'fixture' ) ) ) ) );

$GLOBALS['stats'] = $broken;  RTG_Health::check();
check( 'day 1 of an outage emails one alert', array( 'alert:auth_rejected' ) === RTG_Mailer::$sent );

$GLOBALS['stats'] = $broken;  RTG_Health::check();  RTG_Health::check();
check( 'days 2 and 3 of the same outage email nothing', 1 === count( RTG_Mailer::$sent ) );

$GLOBALS['stats'] = $healthy; RTG_Health::check();
check( 'recovery emails once', array( 'alert:auth_rejected', 'recovered' ) === RTG_Mailer::$sent );

$GLOBALS['stats'] = $healthy; RTG_Health::check();
check( 'staying healthy emails nothing', 2 === count( RTG_Mailer::$sent ) );

$GLOBALS['stats'] = $broken;  RTG_Health::check();
check( 'breaking again emails again', 3 === count( RTG_Mailer::$sent ) );

// Alerts disabled: silence even when broken.
$GLOBALS['opts']['rtg_settings'] = array( 'health_alerts_enabled' => 0 );
RTG_Mailer::$sent = array();
$GLOBALS['stats'] = $broken; RTG_Health::check(); RTG_Health::watch_delistings();
check( 'disabled alerts send nothing', array() === RTG_Mailer::$sent );

exit( $GLOBALS['fail'] > 0 ? 1 : 0 );
