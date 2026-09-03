<?php
/**
 * Executable contract check for RTG_Advisor.
 *
 * The advisor's promise is grounding: it only ever names a tire that is in
 * the catalog and fits the vehicle, and every number it cites is the
 * catalog's. That is decided in pure code, before and after the model call,
 * so it is pinned here on plain PHP with no WordPress, no database and no
 * network:
 *
 *  1. The candidate set: fitting, in budget, ranked by the chosen
 *     priorities, capped.
 *  2. The request: the right body for each model (structured output for
 *     all, effort and the refusal fallback only where the model takes them),
 *     no links or slugs in what the model sees.
 *  3. The answer: picks that name an unknown tire are dropped, duplicates
 *     collapse, the count is capped, and every failure mode of the HTTP
 *     layer becomes the one result shape.
 *  4. The rules fallback produces the same shape as the model.
 *
 * Run with: php tests/contract/advisor.php
 * Exits non-zero on failure, so CI gates on it.
 */

define( 'ABSPATH', __DIR__ );
define( 'RTG_VERSION', '2.0.0-test' );

function wp_json_encode( $v ) { return json_encode( $v ); }
function get_option( $k, $d = false ) { return $GLOBALS['opts'][ $k ] ?? $d; }
$GLOBALS['opts'] = array();

require __DIR__ . '/../../includes/class-rtg-fitment.php';
require __DIR__ . '/../../includes/class-rtg-advisor.php';

$failures = 0;
function check( $ok, $what ) {
    global $failures;
    echo ( $ok ? 'ok   ' : 'FAIL ' ) . $what . "\n";
    if ( ! $ok ) {
        $failures++;
    }
}

function tire( $id, $over = array() ) {
    return array_merge( array(
        'tire_id' => $id, 'brand' => 'Brand', 'model' => 'Model ' . $id, 'size' => '275/65R20', 'category' => 'All-Season',
        'price' => 300, 'mileage_warranty' => 60000, 'weight_lb' => 40, 'three_pms' => 'No', 'load_index' => '116',
        'load_range' => 'SL', 'tags' => '', 'roamer_efficiency' => 0, 'roamer_total_km' => 0, 'roamer_vehicle_count' => 0,
        'rating_average' => null, 'rating_count' => 0, 'slug' => 'brand-' . $id, 'updated_at' => '2026-09-01 00:00:00',
    ), $over );
}

$size_map = array( 'R1' => array( '275/65R20', '275/60R20' ), 'R2' => array( '255/45R21', '275/65R20' ) );
$floors   = array( 'R1' => 116, 'R2' => 112 );

$tires = array(
    tire( 'eff',    array( 'roamer_efficiency' => 2.81, 'roamer_total_km' => 100000, 'roamer_vehicle_count' => 65, 'price' => 320 ) ),
    tire( 'cheap',  array( 'price' => 210, 'mileage_warranty' => 40000 ) ),
    tire( 'snow',   array( 'three_pms' => 'Yes', 'category' => 'All-Terrain', 'price' => 340, 'roamer_efficiency' => 2.40, 'roamer_total_km' => 3000, 'roamer_vehicle_count' => 1 ) ),
    tire( 'weak',   array( 'load_index' => '112', 'price' => 150 ) ),
    tire( 'r2only', array( 'size' => '255/45R21', 'load_index' => '113', 'price' => 280 ) ),
    tire( 'rated',  array( 'rating_average' => 4.8, 'rating_count' => 12, 'price' => 330 ) ),
);

// --- Input ----------------------------------------------------------------

$input = RTG_Advisor::normalize_input( array(
    'vehicle' => 'r1', 'size' => '', 'priorities' => array( 'efficiency', 'bogus', 'price', 'efficiency', 'winter', 'towing' ),
    'budget' => '350', 'notes' => "  I tow   a <b>camper</b>\n twice a year ",
) );
check( 'R1' === $input['vehicle'], 'the vehicle is upper-cased and stripped' );
check( array( 'efficiency', 'price', 'winter' ) === $input['priorities'], 'unknown and repeated priorities drop, three are kept' );
check( '350' === $input['budget'], 'a known budget is kept' );
check( 'I tow a camper twice a year' === $input['notes'], 'notes are stripped of tags and collapsed' );
check( '' === RTG_Advisor::normalize_input( array( 'budget' => '999' ) )['budget'], 'an unknown budget becomes any' );
check( 'R1 · range, price, snow · under $350' === RTG_Advisor::describe_request( $input ), 'the analytics line reads as a query' );

// --- Candidates -----------------------------------------------------------

$c = RTG_Advisor::candidates( $tires, $input, $size_map, $floors );
$ids = array_map( function ( $x ) { return $x['tire_id']; }, $c );
check( ! in_array( 'weak', $ids, true ), 'a load index below the R1 floor is out' );
check( ! in_array( 'r2only', $ids, true ), 'a size the R1 does not take is out' );
check( in_array( 'eff', $ids, true ) && in_array( 'cheap', $ids, true ), 'fitting, in-budget tires are in' );
check( 'eff' === $ids[0], 'with efficiency first among the priorities, the efficient tire ranks first' );
check( 1 === $c[0]['rules_rank'], 'ranks are numbered from one' );
check( isset( $c[0]['components']['efficiency'] ) && 1.0 === (float) $c[0]['components']['efficiency'], 'the best efficiency normalizes to one' );

$budget = RTG_Advisor::candidates( $tires, RTG_Advisor::normalize_input( array( 'vehicle' => 'R1', 'budget' => '250', 'priorities' => array( 'price' ) ) ), $size_map, $floors );
check( array( 'cheap' ) === array_map( function ( $x ) { return $x['tire_id']; }, $budget ), 'a $250 budget leaves only the $210 tire' );

$snow = RTG_Advisor::candidates( $tires, RTG_Advisor::normalize_input( array( 'vehicle' => 'R1', 'priorities' => array( 'winter' ) ) ), $size_map, $floors );
check( 'snow' === $snow[0]['tire_id'], 'with winter first, the 3PMS tire ranks first' );
check( true === $snow[0]['efficiency_limited'], 'one vehicle over 1,900 miles is a limited sample' );

$any = RTG_Advisor::candidates( $tires, RTG_Advisor::normalize_input( array() ), $size_map, $floors );
check( 6 === count( $any ), 'no vehicle means every tire is a candidate' );
$sized = RTG_Advisor::candidates( $tires, RTG_Advisor::normalize_input( array( 'size' => '255/45R21' ) ), $size_map, $floors );
check( array( 'r2only' ) === array_map( function ( $x ) { return $x['tire_id']; }, $sized ), 'a size narrows to that size' );
check( 6 === count( RTG_Advisor::candidates( $tires, RTG_Advisor::normalize_input( array( 'vehicle' => 'R9' ) ), $size_map, $floors ) ), 'an unknown vehicle is treated as any' );

$many = array();
for ( $i = 0; $i < 20; $i++ ) {
    $many[] = tire( 'm' . $i, array( 'price' => 200 + $i ) );
}
check( RTG_Advisor::CANDIDATE_LIMIT === count( RTG_Advisor::candidates( $many, RTG_Advisor::normalize_input( array() ), $size_map, $floors ) ), 'the candidate list is capped' );

// --- The request ------------------------------------------------------------

$req = RTG_Advisor::build_advise_request( $input, $c, $floors, array_keys( $size_map ) );
$user = json_decode( $req['user'], true );
check( 116 === $user['owner']['minimum_load_index'], 'the request carries the vehicle minimum' );
check( 350 === $user['owner']['budget_per_tire'], 'and the budget' );
check( array( 'Range and efficiency', 'Price', 'Snow and winter' ) === $user['owner']['priorities'], 'priorities go over as their labels' );
check( ! isset( $user['candidates'][0]['slug'] ) && ! isset( $user['candidates'][0]['components'] ), 'the model never sees slugs or scores' );
check( isset( $user['candidates'][0]['rules_rank'] ) && 2.81 === (float) $user['candidates'][0]['efficiency'], 'it sees the rank and the real numbers' );
check( 'object' === $req['schema']['type'] && false === $req['schema']['additionalProperties'], 'the schema is a closed object' );

$body = RTG_Advisor::request_body( 'claude-opus-5', 'SYS', 'USER', $req['schema'], 2048, 'low' );
check( 'json_schema' === $body['output_config']['format']['type'], 'structured output is requested' );
check( 'low' === $body['output_config']['effort'], 'Opus 5 gets an effort level' );
check( 'default' === $body['fallbacks'], 'and the server-side refusal fallback' );
check( 'ephemeral' === $body['system'][0]['cache_control']['type'], 'the system prompt carries a cache breakpoint' );
check( ! isset( $body['thinking'] ), 'thinking is left to the model default' );
$haiku = RTG_Advisor::request_body( 'claude-haiku-4-5', 'SYS', 'USER', $req['schema'], 2048, 'low' );
check( ! isset( $haiku['output_config']['effort'] ) && ! isset( $haiku['fallbacks'] ), 'Haiku 4.5 gets neither effort nor fallbacks' );
$sonnet = RTG_Advisor::request_body( 'claude-sonnet-5', 'SYS', 'USER', $req['schema'], 2048, 'low' );
check( isset( $sonnet['output_config']['effort'] ) && ! isset( $sonnet['fallbacks'] ), 'Sonnet 5 gets effort but no fallbacks' );

$headers = RTG_Advisor::request_headers( 'sk-test', 'claude-opus-5' );
check( 'sk-test' === $headers['x-api-key'] && '2023-06-01' === $headers['anthropic-version'], 'the key and version headers' );
check( RTG_Advisor::FALLBACK_BETA === $headers['anthropic-beta'], 'the fallback beta header rides with Opus 5' );
check( ! isset( RTG_Advisor::request_headers( 'k', 'claude-haiku-4-5' )['anthropic-beta'] ), 'and not with Haiku' );

// --- The answer -------------------------------------------------------------

$decoded = array( 'summary' => ' Weighed  range first. ', 'picks' => array(
    array( 'tire_id' => 'ghost', 'headline' => 'Invented', 'reason' => 'x', 'tradeoff' => '' ),
    array( 'tire_id' => 'eff', 'headline' => 'Best range', 'reason' => '2.81 mi/kWh.', 'tradeoff' => 'Pricier.' ),
    array( 'tire_id' => 'eff', 'headline' => 'Again', 'reason' => 'dup', 'tradeoff' => '' ),
    array( 'tire_id' => 'cheap', 'headline' => 'Cheapest', 'reason' => '$210.', 'tradeoff' => '' ),
    array( 'tire_id' => 'snow', 'headline' => 'Snow', 'reason' => '3PMS.', 'tradeoff' => '' ),
    array( 'tire_id' => 'rated', 'headline' => 'Fourth', 'reason' => 'over the cap', 'tradeoff' => '' ),
) );
$picked = RTG_Advisor::validate_picks( $decoded, $c );
check( array( 'eff', 'cheap', 'snow' ) === array_map( function ( $p ) { return $p['tire_id']; }, $picked['picks'] ), 'an unknown tire is dropped, a repeat collapses, three are kept' );
check( 'Weighed range first.' === $picked['summary'], 'the summary is cleaned' );
check( 'x…' !== RTG_Advisor::clean_text( 'x', 1 ) && 'ab…' === RTG_Advisor::clean_text( 'abcdef', 2 ), 'long text is cut with an ellipsis' );

$rules = RTG_Advisor::rules_picks( $c, $input );
check( 3 === count( $rules ) && isset( $rules[0]['headline'], $rules[0]['reason'], $rules[0]['tradeoff'] ), 'the rules fallback has the model\'s shape' );
check( 'eff' === $rules[0]['tire_id'] && false !== strpos( $rules[0]['reason'], '2.81 mi/kWh' ), 'and cites the catalog numbers' );

$ok = RTG_Advisor::parse_response( 200, json_encode( array(
    'model' => 'claude-opus-5', 'stop_reason' => 'end_turn',
    'content' => array( array( 'type' => 'text', 'text' => json_encode( array( 'summary' => 's', 'picks' => array() ) ) ) ),
    'usage' => array( 'input_tokens' => 1200, 'output_tokens' => 300, 'cache_read_input_tokens' => 900, 'cache_creation_input_tokens' => 0 ),
) ) );
check( true === $ok['ok'] && 's' === $ok['data']['summary'] && 900 === $ok['usage']['cache_read'], 'a good response yields the decoded JSON and usage' );
check( false === RTG_Advisor::parse_response( 401, '{}' )['ok'] && false !== strpos( RTG_Advisor::parse_response( 401, '{}' )['error'], 'key' ), '401 names the key' );
check( false !== strpos( RTG_Advisor::parse_response( 429, '{}' )['error'], '429' ), '429 says so' );
check( false !== strpos( RTG_Advisor::parse_response( 500, json_encode( array( 'error' => array( 'message' => 'Overloaded' ) ) ) )['error'], 'Overloaded' ), 'another status carries the API message' );
check( false !== strpos( RTG_Advisor::parse_response( 200, json_encode( array( 'stop_reason' => 'refusal', 'content' => array() ) ) )['error'], 'declined' ), 'a refusal is reported, not parsed' );
check( false !== strpos( RTG_Advisor::parse_response( 200, json_encode( array( 'stop_reason' => 'max_tokens', 'content' => array() ) ) )['error'], 'max_tokens' ), 'a cut-off answer is reported' );
check( false === RTG_Advisor::parse_response( 200, json_encode( array( 'stop_reason' => 'end_turn', 'content' => array( array( 'type' => 'text', 'text' => 'not json' ) ) ) ) )['ok'], 'non-JSON text is an error' );
check( false === RTG_Advisor::parse_response( 200, 'garbage' )['ok'], 'a non-JSON body is an error' );

// --- The other two prompts ----------------------------------------------------

$rev = RTG_Advisor::build_review_request( tire( 'eff' ), array(
    array( 'rating' => 5, 'review_title' => 'Great', 'review_text' => 'Quiet and <i>smooth</i>.', 'created_at' => '2026-08-02 10:00:00' ),
) );
$rev_user = json_decode( $rev['user'], true );
check( 'Quiet and smooth.' === $rev_user['reviews'][0]['text'] && '2026-08' === $rev_user['reviews'][0]['when'], 'reviews go over as clean text with the month' );
check( array( 'summary', 'pros', 'cons' ) === $rev['schema']['required'], 'the review schema asks for summary, pros and cons' );

$cmp = RTG_Advisor::build_compare_request( array( tire( 'eff' ), tire( 'cheap' ) ), $size_map, $floors );
$cmp_user = json_decode( $cmp['user'], true );
check( 2 === count( $cmp_user['tires'] ) && ! isset( $cmp_user['tires'][0]['slug'] ), 'the compare prompt carries the tires without slugs' );
check( array( 'R1', 'R2' ) === $cmp_user['tires'][0]['vehicles'], 'and which vehicles each fits' );

$version_a = RTG_Advisor::catalog_version( $tires );
$changed   = $tires;
$changed[1]['updated_at'] = '2026-09-02 00:00:00';
check( $version_a !== RTG_Advisor::catalog_version( $changed ), 'an edit to any tire changes the catalog version' );

echo $failures ? "\n$failures failure(s)\n" : "\nAll checks passed\n";
exit( $failures ? 1 : 0 );
