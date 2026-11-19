<?php
/**
 * Executable contract check for RTG_Catalog_Presence::discontinued_suspects().
 *
 * The promise: a model is only suspected discontinued when every size the
 * sweep read has dropped out of the catalog for 30 days. One size still
 * listed, one size the sweep could not read, a recent sighting, a model
 * already marked, or one a person dismissed: none of those is a suspect.
 *
 * Run with: php tests/contract/discontinued.php
 */

define( 'ABSPATH', __DIR__ );
define( 'DAY_IN_SECONDS', 86400 );

require __DIR__ . '/../../includes/class-rtg-catalog-presence.php';

$failures = 0;
function check( $cond, $label ) {
    global $failures;
    echo ( $cond ? 'ok   ' : 'FAIL ' ) . $label . PHP_EOL;
    if ( ! $cond ) {
        $failures++;
    }
}

$now = 1800000000;
$old = $now - 45 * DAY_IN_SECONDS;
$new = $now - 10 * DAY_IN_SECONDS;

$t = function ( $id, $brand, $model, $over = array() ) {
    return array_merge( array( 'tire_id' => $id, 'brand' => $brand, 'model' => $model ), $over );
};
$e = function ( $status, $seen = 0 ) {
    return array( 'status' => $status, 'last_seen' => $seen );
};

$tires = array(
    $t( 'a1', 'Goodyear', 'Old Trac' ), $t( 'a2', 'Goodyear', 'Old Trac' ),
    $t( 'b1', 'Kumho', 'Half Gone' ), $t( 'b2', 'Kumho', 'Half Gone' ),
    $t( 'c1', 'Michelin', 'Recent Drop' ),
    $t( 'd1', 'Nokian', 'Unread' ), $t( 'd2', 'Nokian', 'Unread' ),
    $t( 'f1', 'Toyo', 'Never Carried' ),
    $t( 'g1', 'Falken', 'Already Marked', array( 'discontinued' => 1 ) ),
    $t( 'h1', 'Hankook', 'Dismissed' ),
    $t( 'i1', 'Cooper', 'Mixed Never' ), $t( 'i2', 'Cooper', 'Mixed Never' ),
);
$evaluated = array(
    'a1' => $e( 'delisted', $old ), 'a2' => $e( 'delisted', $old - DAY_IN_SECONDS ),
    'b1' => $e( 'delisted', $old ), 'b2' => $e( 'listed', $now ),
    'c1' => $e( 'delisted', $new ),
    'd1' => $e( 'delisted', $old ), 'd2' => $e( 'unknown', 0 ),
    'f1' => $e( 'never_listed', 0 ),
    'g1' => $e( 'delisted', $old ),
    'h1' => $e( 'delisted', $old ),
    'i1' => $e( 'delisted', $old ), 'i2' => $e( 'never_listed', 0 ),
);

$suspects = RTG_Catalog_Presence::discontinued_suspects( $tires, $evaluated, $now, 30, array( 'hankook|dismissed' ) );
$keys     = array_map( function ( $s ) { return $s['key']; }, $suspects );

check( in_array( 'goodyear|old trac', $keys, true ), 'every size delisted for 45 days is a suspect' );
check( array( 'a1', 'a2' ) === $suspects[ array_search( 'goodyear|old trac', $keys, true ) ]['tire_ids'], 'the suspect carries every size of the model' );
check( ! in_array( 'kumho|half gone', $keys, true ), 'one size still listed keeps the model off the list' );
check( ! in_array( 'michelin|recent drop', $keys, true ), 'a drop 10 days ago is too recent' );
check( ! in_array( 'nokian|unread', $keys, true ), 'a size the sweep did not read keeps the model off the list' );
check( ! in_array( 'toyo|never carried', $keys, true ), 'a model no retailer ever carried is not a suspect' );
check( ! in_array( 'falken|already marked', $keys, true ), 'a model already marked discontinued is not asked about' );
check( ! in_array( 'hankook|dismissed', $keys, true ), 'a dismissed model is not asked about again' );
check( in_array( 'cooper|mixed never', $keys, true ), 'never-carried sizes beside delisted ones still count as gone' );
check( 2 === count( $suspects ), 'exactly the two expected suspects' );
check( 'Goodyear' === $suspects[0]['brand'] && 'Old Trac' === $suspects[0]['model'], 'brand and model keep their spelling' );

exit( $failures ? 1 : 0 );
