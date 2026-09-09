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
    public static function verdicts( $tire, $map, $floors, $third_party = array() ) {
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
            $entry = self::third_party_entry( $size, $name, $third_party );
            $out[] = array(
                'vehicle'     => $name,
                'floor'       => $floor,
                'ok'          => $load_index >= $floor,
                'third_party' => null !== $entry,
                'note'        => $entry ? $entry['note'] : '',
            );
        }

        return $out;
    }

    /**
     * The third-party entry for a size on a vehicle, if it is one.
     *
     * @param string $size        Tire size, any case.
     * @param string $vehicle     Vehicle group (R1, R2).
     * @param array  $third_party Vehicle => [ size => [ 'wheel', 'note' ] ] (RTG_Database::get_third_party_size_map()).
     * @return array|null [ 'wheel' => ..., 'note' => ... ] or null when the size is a factory size or unknown.
     */
    public static function third_party_entry( $size, $vehicle, $third_party ) {
        $size  = strtolower( trim( (string) $size ) );
        $sizes = (array) ( $third_party[ $vehicle ] ?? array() );
        foreach ( $sizes as $listed => $entry ) {
            if ( strtolower( trim( (string) $listed ) ) === $size ) {
                return array(
                    'wheel' => (string) ( $entry['wheel'] ?? '' ),
                    'note'  => (string) ( $entry['note'] ?? '' ),
                );
            }
        }
        return null;
    }

    /**
     * Which vehicles take this tire only on third-party wheels.
     *
     * With a vehicle named, only that vehicle is judged. Without one, every
     * vehicle in the third-party map is. Unlike the load-index rule this
     * needs no load index: the question is about the size alone.
     *
     * @param array  $tire        Row with size.
     * @param array  $third_party Vehicle => [ size => [ 'wheel', 'note' ] ].
     * @param string $vehicle     Chosen vehicle, or '' for every vehicle.
     * @return array[] Each [ 'vehicle', 'wheel', 'note' ], in map order.
     */
    public static function third_party_fits( $tire, $third_party, $vehicle = '' ) {
        $size     = (string) ( $tire['size'] ?? '' );
        $vehicles = $vehicle ? array( $vehicle ) : array_keys( (array) $third_party );
        $out      = array();
        foreach ( $vehicles as $name ) {
            $entry = self::third_party_entry( $size, $name, $third_party );
            if ( $entry ) {
                $out[] = array_merge( array( 'vehicle' => $name ), $entry );
            }
        }
        return $out;
    }

    /**
     * The rim diameter a size names, e.g. 18 for 245/60R18.
     *
     * @param string $size
     * @return int 0 when the size has none.
     */
    public static function rim_inches( $size ) {
        return preg_match( '/R(\d{2})/i', (string) $size, $m ) ? (int) $m[1] : 0;
    }

    /**
     * One sentence for a third-party note.
     *
     *   "Fits R2 on 3rd-party 18\" wheels only. Not a factory size, so fitment may vary."
     *
     * @param string  $size Tire size, for the rim diameter.
     * @param array[] $fits From third_party_fits().
     * @return string Empty when there is nothing to say.
     */
    public static function describe_third_party( $size, $fits ) {
        if ( empty( $fits ) ) {
            return '';
        }
        $names = array_column( $fits, 'vehicle' );
        $last  = array_pop( $names );
        $who   = $names ? implode( ', ', $names ) . ' and ' . $last : $last;
        $rim   = self::rim_inches( $size );
        $on    = $rim ? sprintf( '3rd-party %d" wheels', $rim ) : '3rd-party wheels';
        return sprintf( 'Fits %s on %s only. Not a factory size, so fitment may vary.', $who, $on );
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
