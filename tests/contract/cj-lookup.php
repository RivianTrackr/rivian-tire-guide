<?php
/**
 * Executable contract check for the CJ connection probe.
 *
 * The probe is the diagnostic that settled every hard question this feature
 * faced — what a keyword really returns, whether paging advances — so its own
 * behavior is pinned here on plain PHP with a stubbed HTTP layer: the keyword
 * and offset it is given must reach the request and be echoed in the reply,
 * because a probe that silently substitutes its fallback reads as an answer
 * to a question that was never asked. That exact failure shipped once.
 *
 * Run with: php tests/contract/cj-lookup.php
 * Exits non-zero on failure, so CI gates on it.
 */

define( 'ABSPATH', __DIR__ );
$GLOBALS['opts'] = array(
    'rtg_settings' => array( 'cj_company_id' => '1', 'cj_pat' => 'x' ),
);

function get_option( $k, $d = false ) { return $GLOBALS['opts'][$k] ?? $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][$k] = $v; return true; }
function wp_json_encode( $v ) { return json_encode( $v ); }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function is_wp_error( $t ) { return false; }
function wp_remote_retrieve_response_code( $r ) { return 200; }
function wp_remote_retrieve_body( $r ) { return $r['body']; }
function number_format_i18n( $n ) { return number_format( $n ); }
class RTG_Admin { public static function get_dropdown_options( $w ) { return array( '255/65R19' ); } }

function wp_remote_post( $url, $args ) {
    $body = json_decode( $args['body'], true );
    $GLOBALS['last_keyword'] = (string) $body['variables']['keywords'][0];
    $GLOBALS['last_offset']  = intval( $body['variables']['offset'] ?? -1 );
    $GLOBALS['last_limit']   = intval( $body['variables']['limit'] ?? -1 );

    $nodes = array(
        array( 'id' => '845HR2DLTX2XL', 'title' => 'Michelin Defender LTX M/S2 285/45R22 XL 114H Highway All-Season Tire 44941',
               'brand' => 'Michelin', 'advertiserId' => '1463221', 'advertiserName' => 'The Tire Rack',
               'link' => 'https://www.tirerack.com/tires/tires.jsp?partnum=845HR2DLTX2XL', 'price' => array( 'amount' => '344.99' ) ),
    );

    return array( 'body' => json_encode( array( 'data' => array( 'shoppingProducts' => array(
        'totalCount' => 4213, 'count' => count( $nodes ), 'resultList' => $nodes ) ) ) ) );
}

require __DIR__ . '/../../includes/class-rtg-tire-qualifier.php';
require __DIR__ . '/../../includes/class-rtg-catalog-source.php';
require __DIR__ . '/../../includes/class-rtg-catalog-source-cj.php';

$source = new RTG_Catalog_Source_CJ();

$fail = 0;
function check( $label, $cond ) { printf( "%-58s %s\n", $label, $cond ? 'ok' : 'FAIL' ); if ( ! $cond ) { $GLOBALS['fail']++; } }

// A keyword the admin typed is the keyword that is sent and reported.
$probe = $source->test_connection( 'Michelin Defender LTX M/S2', 0 );
check( 'probe sends the keyword it was given',   'Michelin Defender LTX M/S2' === $GLOBALS['last_keyword'] );
check( 'probe echoes the keyword it used',       false !== strpos( $probe['message'], 'Michelin Defender LTX M/S2' ) );
check( 'probe reports the match total',          false !== strpos( $probe['message'], '4,213' ) );
check( 'probe returns readable titles',          ! empty( $probe['titles'] ) && is_string( $probe['titles'][0] ) );
check( 'probe titles name the advertiser',       false !== strpos( $probe['titles'][0], 'The Tire Rack' ) );

// The offset — how paging is proven to advance — reaches the request and the reply.
$deep = $source->test_connection( '305/45R22', 1000 );
check( 'the request carries the offset through', 1000 === $GLOBALS['last_offset'] );
check( 'probe reports the offset it read from',  false !== strpos( $deep['message'], 'starting at record 1,000' ) );

// Blank falls back to the first guide size — and says which keyword it used,
// so a fallback can never be mistaken for the answer to a typed keyword.
$blank = $source->test_connection( '' );
check( 'blank probe falls back to a guide size', '255/65R19' === $GLOBALS['last_keyword'] );
check( 'the fallback keyword is reported',       false !== strpos( $blank['message'], '255/65R19' ) );

// The probe reads a full page, same as the sweep.
check( 'probe reads a full page',                1000 === $GLOBALS['last_limit'] );

exit( $fail > 0 ? 1 : 0 );
