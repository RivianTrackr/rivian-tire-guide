<?php
/**
 * Executable contract check for the CJ catalog source.
 *
 * The plugin's PHPUnit suite needs a WordPress test library and a database,
 * so in practice it does not run on every change — and `php -l` only proves a
 * file parses. That gap shipped a real defect: RTG_Catalog_Sync was rewritten
 * to read `$lookup['by_term']` while RTG_Catalog_Source_CJ still returned
 * `products`, which is perfectly valid PHP. Lint passed, CI went green, and
 * the targeted lookup silently ingested nothing.
 *
 * This file closes that specific gap the cheapest way that actually executes:
 * plain PHP, no WordPress, no database, a stubbed HTTP layer. It runs the real
 * fetch_terms and asserts the shape the sync depends on, checks the fitment
 * guard against a response shaped like a live one, and confirms the connection
 * probe honours the keyword it is given.
 *
 * Run with: php tests/contract/cj-lookup.php
 * Exits non-zero on failure, so CI gates on it.
 */

define( 'ABSPATH', __DIR__ );
$GLOBALS['opts'] = array(
    'rtg_settings' => array( 'cj_company_id' => '1', 'cj_pat' => 'x', 'cj_targeted_budget' => 600 ),
);
function get_option( $k, $d = false ) { return $GLOBALS['opts'][$k] ?? $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][$k] = $v; return true; }
function wp_json_encode( $v ) { return json_encode( $v ); }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function is_wp_error( $t ) { return false; }
function wp_remote_retrieve_response_code( $r ) { return 200; }
function wp_remote_retrieve_body( $r ) { return $r['body']; }
function current_time( $t ) { return '2026-08-24 00:00:00'; }
function apply_filters( $t, $v ) { return $v; }
class RTG_Admin { public static function get_dropdown_options( $w ) { return array( '255/65R19' ); } }

// One CJ response: the asked-for tire plus off-fitment noise, as observed live.
function wp_remote_post( $url, $args ) {
    $body = json_decode( $args['body'], true );
    $kw   = $body['variables']['keywords'][0];
    $nodes = array(
        array( 'id' => 'a1', 'title' => 'Michelin Defender LTX M/S2 305/45R22 118H', 'brand' => 'Michelin',
               'advertiserId' => '1463221', 'advertiserName' => 'The Tire Rack',
               'link' => 'https://tirerack.com/x', 'price' => array( 'amount' => '358.00' ) ),
        array( 'id' => 'b2', 'title' => 'Continental CrossContact RX 255/65R19 114V', 'brand' => 'Continental',
               'advertiserId' => '5660604', 'advertiserName' => 'SimpleTire',
               'link' => 'https://simpletire.com/y', 'price' => array( 'amount' => '304.15' ) ),
    );
    return array( 'body' => json_encode( array( 'data' => array( 'shoppingProducts' => array(
        'totalCount' => 2, 'count' => 2, 'resultList' => $nodes ) ) ) ) );
}

require __DIR__ . '/../../includes/class-rtg-catalog-source.php';
require __DIR__ . '/../../includes/class-rtg-catalog-source-cj.php';

$source = new RTG_Catalog_Source_CJ();
$terms  = array( 'Michelin Defender LTX M/S2 305/45R22' );
$lookup = $source->fetch_terms( $terms );

$fail = 0;
function check( $label, $cond ) { global $fail; printf( "%-58s %s\n", $label, $cond ? 'ok' : 'FAIL' ); if ( ! $cond ) { $GLOBALS['fail']++; } }

// The contract run_targeted_lookup depends on.
check( 'fetch_terms returns by_term',            isset( $lookup['by_term'] ) && is_array( $lookup['by_term'] ) );
check( 'by_term is keyed by the term asked',     isset( $lookup['by_term'][ $terms[0] ] ) );
check( 'checked/pending/error present',          isset( $lookup['checked'], $lookup['pending'], $lookup['error'] ) );
check( 'the term returned its products',         2 === count( $lookup['by_term'][ $terms[0] ] ) );

// The fitment guard the sync applies, exercised on the same data.
$want = '305/45R22';
$kept = $dropped = 0;
foreach ( $lookup['by_term'][ $terms[0] ] as $p ) {
    preg_match( '#(\d{3})/(\d{2})[A-Z]?R(\d{2})#', $p['title'], $m );
    $size = $m ? $m[1] . '/' . $m[2] . 'R' . $m[3] : '';
    if ( $size === $want ) { $kept++; } else { $dropped++; }
}
check( 'asked-for fitment is kept',              1 === $kept );
check( 'other fitment is left out',              1 === $dropped );

// test_connection must honour a keyword and report titles.
$probe = $source->test_connection( 'Michelin Defender LTX M/S2 305/45R22' );
check( 'probe echoes the keyword it used',       false !== strpos( $probe['message'], 'Michelin Defender LTX M/S2 305/45R22' ) );
check( 'probe returns readable titles',          ! empty( $probe['titles'] ) && is_string( $probe['titles'][0] ) );
check( 'probe titles name the advertiser',       false !== strpos( $probe['titles'][0], 'The Tire Rack' ) );

$blank = $source->test_connection( '' );
check( 'blank probe falls back to a guide size', false !== strpos( $blank['message'], 'keyword "' ) );

exit( $fail > 0 ? 1 : 0 );
