<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Load-index fitment — does this tire carry a Rivian?
 *
 * Every Rivian has a load-index floor (R1: 116, R2: 112; both editable
 * under Tire Discovery, where the qualifier already uses them to gate the
 * catalog). The guide has always shown the load index and let a tooltip
 * explain the rule; this is the first thing that applies it to what the
 * shopper is looking at.
 *
 * Pure of the database: rows, the size map and the floors come in, the
 * verdict comes out. The JS twin is frontend/js/modules/fitment.js.
 *
 * @since 1.88.0
 */
class RTG_Fitment {

    /**
     * The single-tire load index from whatever the column holds.
     *
     * Catalog data writes it many ways: "116", "116T", "116/113" (the LT
     * dual/single pair — the first figure is the single-tire rating that
     * matters here), "116 (2756 lb)". The first two- or three-digit run is it.
     *
     * @param mixed $raw Stored load_index value.
     * @return int 0 when the value has none.
     */
    public static function parse_load_index( $raw ) {
        if ( ! preg_match( '/\d{2,3}/', (string) $raw, $m ) ) {
            return 0;
        }
        $value = (int) $m[0];
        return ( $value >= 60 && $value <= 200 ) ? $value : 0;
    }

    /**
     * The load-index floor for each vehicle the guide knows about.
     *
     * @return array Vehicle => minimum load index.
     */
    public static function floors() {
        return RTG_Tire_Qualifier::get_vehicle_minimums();
    }

    /**
     * Which vehicles this tire falls short for.
     *
     * With a vehicle named, only that vehicle is judged. Without one, every
     * vehicle whose size list includes this tire's size is: "below the R2
     * minimum" is noise on a tire no R2 takes.
     *
     * @param array  $tire    Row with load_index and size.
     * @param array  $map     Vehicle => sizes (RTG_Database::get_vehicle_size_map()).
     * @param array  $floors  Vehicle => minimum load index.
     * @param string $vehicle Chosen vehicle, or '' for every fitting vehicle.
     * @return array[] Each ['vehicle' => 'R1', 'floor' => 116], in map order.
     */
    public static function shortfalls( $tire, $map, $floors, $vehicle = '' ) {
        $load_index = self::parse_load_index( $tire['load_index'] ?? '' );
        if ( ! $load_index ) {
            return array();
        }

        $size     = strtolower( trim( (string) ( $tire['size'] ?? '' ) ) );
        $vehicles = $vehicle ? array( $vehicle ) : array_keys( (array) $floors );
        $out      = array();

        foreach ( $vehicles as $name ) {
            $floor = (int) ( $floors[ $name ] ?? 0 );
            if ( $floor <= 0 ) {
                continue;
            }

            if ( ! $vehicle ) {
                $sizes = array_map( 'strtolower', array_map( 'trim', (array) ( $map[ $name ] ?? array() ) ) );
                if ( ! in_array( $size, $sizes, true ) ) {
                    continue;
                }
            }

            if ( $load_index < $floor ) {
                $out[] = array( 'vehicle' => $name, 'floor' => $floor );
            }
        }

        return $out;
    }

    /**
     * The vehicles a tire's size fits, each with its verdict.
     *
     * The tire page shows every fitting vehicle, pass or fail, because a
     * visitor arriving from a search has pressed no toggle: "R1 ✓ · R2 ✗"
     * answers the question for whichever they drive.
     *
     * @param array $tire   Row with load_index and size.
     * @param array $map    Vehicle => sizes.
     * @param array $floors Vehicle => minimum load index.
     * @return array[] Each ['vehicle', 'floor', 'ok'], only for fitting sizes
     *                 and only when the load index is known.
     */
    public static function verdicts( $tire, $map, $floors ) {
        $load_index = self::parse_load_index( $tire['load_index'] ?? '' );
        if ( ! $load_index ) {
            return array();
        }

        $size = strtolower( trim( (string) ( $tire['size'] ?? '' ) ) );
        $out  = array();

        foreach ( (array) $map as $name => $sizes ) {
            $sizes = array_map( 'strtolower', array_map( 'trim', (array) $sizes ) );
            if ( ! in_array( $size, $sizes, true ) ) {
                continue;
            }
            $floor = (int) ( $floors[ $name ] ?? 0 );
            if ( $floor <= 0 ) {
                continue;
            }
            $out[] = array(
                'vehicle' => $name,
                'floor'   => $floor,
                'ok'      => $load_index >= $floor,
            );
        }

        return $out;
    }

    /**
     * One sentence for a warning row.
     *
     * @param int     $load_index
     * @param array[] $shortfalls From shortfalls().
     * @return string Empty when there is nothing to say.
     */
    public static function describe( $load_index, $shortfalls ) {
        if ( empty( $shortfalls ) ) {
            return '';
        }
        $li = self::parse_load_index( $load_index );
        if ( 1 === count( $shortfalls ) ) {
            return sprintf( 'Load index %d is below the %s minimum of %d.', $li, $shortfalls[0]['vehicle'], $shortfalls[0]['floor'] );
        }
        $parts = array_map( function ( $s ) {
            return $s['vehicle'] . ' (' . $s['floor'] . ')';
        }, $shortfalls );
        $last = array_pop( $parts );
        return sprintf( 'Load index %d is below the %s and %s minimums.', $li, implode( ', ', $parts ), $last );
    }
}
