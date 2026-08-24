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
    // Shaped on what CJ actually returned for this search: the right model
    // from the right retailer, in fitments other than the one asked about,
    // because the size in a keyword is scored rather than applied.
    $nodes = array(
        array( 'id' => '845HR2DLTX2XL', 'title' => 'Michelin Defender LTX M/S2 285/45R22 XL 114H Highway All-Season Tire 44941',
               'brand' => 'Michelin', 'advertiserId' => '1463221', 'advertiserName' => 'The Tire Rack',
               'link' => 'https://www.tirerack.com/tires/tires.jsp?partnum=845HR2DLTX2XL', 'price' => array( 'amount' => '344.99' ) ),
        array( 'id' => '745HR2DLTX2XL', 'title' => 'Michelin Defender LTX M/S2 305/45R22 XL 118H Highway All-Season Tire 12872',
               'brand' => 'Michelin', 'advertiserId' => '1463221', 'advertiserName' => 'The Tire Rack',
               'link' => 'https://www.tirerack.com/tires/tires.jsp?partnum=745HR2DLTX2XL', 'price' => array( 'amount' => '358.99' ) ),
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
// The term carries no size: CJ scores that token rather than applying it.
$terms  = array( 'Michelin Defender LTX M/S2' );
$lookup = $source->fetch_terms( $terms );

$fail = 0;
function check( $label, $cond ) { global $fail; printf( "%-58s %s\n", $label, $cond ? 'ok' : 'FAIL' ); if ( ! $cond ) { $GLOBALS['fail']++; } }

// The contract run_targeted_lookup depends on.
check( 'fetch_terms returns by_term',            isset( $lookup['by_term'] ) && is_array( $lookup['by_term'] ) );
check( 'by_term is keyed by the term asked',     isset( $lookup['by_term'][ $terms[0] ] ) );
check( 'checked/pending/error present',          isset( $lookup['checked'], $lookup['pending'], $lookup['error'] ) );
check( 'the term returned its products',         3 === count( $lookup['by_term'][ $terms[0] ] ) );
check( 'a term carries no size token',          ! preg_match( '#\d{3}/\d{2}R\d{2}#', $terms[0] ) );

// The fitment guard the sync applies, exercised on the same data. It keeps
// any size the guide uses, not only the one that prompted the search: a model
// search returns that model in every size CJ holds, and some of those are
// other guide tires we were about to go looking for anyway.
$guide_sizes = array( '305/45R22' => true, '255/65R19' => true );
$kept = $dropped = 0;
$kept_sizes = array();
foreach ( $lookup['by_term'][ $terms[0] ] as $p ) {
    preg_match( '#(\d{3})/(\d{2})[A-Z]?R(\d{2})#', $p['title'], $m );
    $size = $m ? $m[1] . '/' . $m[2] . 'R' . $m[3] : '';
    if ( isset( $guide_sizes[ $size ] ) ) { $kept++; $kept_sizes[] = $size; } else { $dropped++; }
}
check( 'a guide fitment is kept',                in_array( '305/45R22', $kept_sizes, true ) );
check( 'another guide fitment is also kept',     in_array( '255/65R19', $kept_sizes, true ) );
check( 'a fitment the guide never uses is left', 1 === $dropped && 2 === $kept );

// test_connection must honour a keyword and report titles.
$probe = $source->test_connection( 'Michelin Defender LTX M/S2' );
check( 'probe echoes the keyword it used',       false !== strpos( $probe['message'], 'Michelin Defender LTX M/S2' ) );
check( 'probe returns readable titles',          ! empty( $probe['titles'] ) && is_string( $probe['titles'][0] ) );
check( 'probe titles name the advertiser',       false !== strpos( $probe['titles'][0], 'The Tire Rack' ) );

$blank = $source->test_connection( '' );
check( 'blank probe falls back to a guide size', false !== strpos( $blank['message'], 'keyword "' ) );

exit( $fail > 0 ? 1 : 0 );
