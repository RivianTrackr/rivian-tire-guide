<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Explains why a guide tire has no retailer carrying it.
 *
 * "No retailer match" is a single line covering several very different
 * situations — the fitment never reached the queue at all, the retailers carry
 * that fitment but not from that brand, or they carry the exact tire under a
 * name that doesn't key the same. Only the last one is fixable, and lumping
 * all three together meant the fixable ones were invisible among the rest.
 *
 * So every uncovered tire gets a reason, and the reason names the evidence:
 * how many products in that size reached the queue, and what the retailers
 * call the tire when they do list it.
 *
 * The classification is a pure function of an index, so it can be tested
 * without a database.
 *
 * @since 1.65.0
 */
class RTG_Coverage {

    /** The guide's size can't be parsed, so nothing can key against it. */
    const GAP_SIZE_UNREADABLE = 'size_unreadable';

    /** The guide row has no brand, and the match key needs one. */
    const GAP_BRAND_MISSING = 'brand_missing';

    /** No product in this fitment has reached the queue from any retailer. */
    const GAP_SIZE_ABSENT = 'size_absent';

    /** Products in this fitment reached the queue, but none from this brand. */
    const GAP_BRAND_ABSENT = 'brand_absent';

    /**
     * This brand and fitment are carried, but not this model.
     *
     * Distinct from GAP_MODEL_VARIANT, and the distinction is the whole point.
     * The first version of this class had one code for both, and labelled it
     * "listed, but as X" on the strength of a brand and size match alone —
     * so a Michelin Defender LTX M/S2 was reported as being listed under the
     * name "Pilot Sport S 5", which is a different tire entirely. Sharing a
     * brand and a fitment says nothing about being the same model.
     */
    const GAP_MODEL_ABSENT = 'model_absent';

    /** A listing whose name looks like a spelling of this same model. */
    const GAP_MODEL_VARIANT = 'model_variant';

    /** Nearest model names to show before truncating. */
    const MAX_NEAR_MODELS = 5;

    /**
     * Similarity at or above which two model names are treated as the same
     * tire spelled differently.
     *
     * Only containment reaches it — "Ridge Grappler" inside "Ridge Grappler
     * LT". Edit distance was tried and rejected: "NT420V" and "NT421Q" are two
     * substitutions apart and are unrelated tires, so any threshold loose
     * enough to catch real variants also merges model codes that differ by a
     * digit.
     */
    const VARIANT_THRESHOLD = 0.9;

    /**
     * Build the lookup the classifier reads.
     *
     * Only the four columns needed are selected. The candidates table holds a
     * raw copy of every product node, and pulling those for sixteen thousand
     * rows to count sizes would cost tens of megabytes to answer a question
     * that needs none of it.
     *
     * @return array {
     *     @type array $sizes       Canonical size => { count, advertisers }.
     *     @type array $brand_sizes "brandkey|size" => list of { model, advertiser_name }.
     * }
     */
    public static function build_index() {
        global $wpdb;
        $table = RTG_Candidates::table();

        $rows = $wpdb->get_results(
            "SELECT brand, model, size, advertiser_name FROM {$table} WHERE size <> ''",
            ARRAY_A
        );

        return self::index_rows( $rows ?: array() );
    }

    /**
     * Fold candidate rows into the lookup. Split out so the classifier can be
     * exercised against hand-written rows.
     *
     * @param array[] $rows Rows with brand, model, size, advertiser_name.
     * @return array Index, in the shape build_index() returns.
     */
    public static function index_rows( $rows ) {
        $index = array(
            'sizes'       => array(),
            'brand_sizes' => array(),
        );

        foreach ( (array) $rows as $row ) {
            $size = RTG_Tire_Qualifier::normalize_size( $row['size'] ?? '' );
            if ( '' === $size ) {
                continue;
            }

            if ( ! isset( $index['sizes'][ $size ] ) ) {
                $index['sizes'][ $size ] = array(
                    'count'       => 0,
                    'advertisers' => array(),
                );
            }

            // Counted whether or not the feed named who is selling it: an
            // unattributed listing still proves the fitment reached the queue.
            $index['sizes'][ $size ]['count']++;

            $advertiser = trim( (string) ( $row['advertiser_name'] ?? '' ) );
            if ( '' !== $advertiser ) {
                $index['sizes'][ $size ]['advertisers'][ $advertiser ] = true;
            }

            $brand_key = RTG_Tire_Qualifier::normalize_brand( $row['brand'] ?? '' );
            if ( '' === $brand_key ) {
                continue;
            }

            $index['brand_sizes'][ $brand_key . '|' . $size ][] = array(
                'model'           => trim( (string) ( $row['model'] ?? '' ) ),
                'advertiser_name' => $advertiser,
            );
        }

        return $index;
    }

    /**
     * Say why one guide tire has no retailer match.
     *
     * @param array $tire  Guide tire (brand, model, size).
     * @param array $index Output of build_index().
     * @return array {
     *     @type string $code  One of the GAP_* constants.
     *     @type string $label One sentence naming the evidence.
     *     @type array  $near  Retailer listings of the same brand and size.
     * }
     */
    public static function classify( $tire, $index ) {
        $size      = RTG_Tire_Qualifier::normalize_size( $tire['size'] ?? '' );
        $brand     = trim( (string) ( $tire['brand'] ?? '' ) );
        $brand_key = RTG_Tire_Qualifier::normalize_brand( $brand );
        $model     = trim( (string) ( $tire['model'] ?? '' ) );

        if ( '' === $size ) {
            return self::gap(
                self::GAP_SIZE_UNREADABLE,
                sprintf( 'The size on this tire ("%s") is not in a form the matcher reads.', $tire['size'] ?? '' )
            );
        }

        if ( '' === $brand_key ) {
            return self::gap(
                self::GAP_BRAND_MISSING,
                'This tire has no brand, and a match needs one.'
            );
        }

        $in_size = $index['sizes'][ $size ] ?? array( 'count' => 0, 'advertisers' => array() );

        if ( empty( $in_size['count'] ) ) {
            return self::gap(
                self::GAP_SIZE_ABSENT,
                sprintf( 'No %s product has reached the queue yet from any retailer.', $size )
            );
        }

        $near = $index['brand_sizes'][ $brand_key . '|' . $size ] ?? array();

        if ( empty( $near ) ) {
            return self::gap(
                self::GAP_BRAND_ABSENT,
                sprintf(
                    '%s %s listing(s) reached the queue%s, none of them %s.',
                    number_format( $in_size['count'] ),
                    $size,
                    empty( $in_size['advertisers'] )
                        ? ''
                        : ' from ' . implode( ' and ', array_keys( $in_size['advertisers'] ) ),
                    $brand
                )
            );
        }

        // De-duplicate on the model name, keeping who lists it and how close
        // the name is to the one the guide uses.
        $models = array();
        foreach ( $near as $listing ) {
            $name = $listing['model'];

            if ( ! isset( $models[ $name ] ) ) {
                $models[ $name ] = array(
                    'model'       => $name,
                    'advertisers' => array(),
                    'similarity'  => self::model_similarity( $model, $name ),
                );
            }

            if ( '' !== $listing['advertiser_name'] ) {
                $models[ $name ]['advertisers'][ $listing['advertiser_name'] ] = true;
            }
        }

        // Closest name first, so what's shown is the most useful comparison
        // rather than whichever row the database happened to return first.
        uasort( $models, function ( $a, $b ) {
            if ( $a['similarity'] === $b['similarity'] ) {
                return strcasecmp( $a['model'], $b['model'] );
            }
            return $a['similarity'] < $b['similarity'] ? 1 : -1;
        } );

        $listed = array();
        foreach ( array_slice( $models, 0, self::MAX_NEAR_MODELS ) as $entry ) {
            $listed[] = array(
                'model'       => $entry['model'],
                'advertisers' => array_keys( $entry['advertisers'] ),
                'similarity'  => $entry['similarity'],
            );
        }

        $closest = reset( $models );

        // Only a name that actually resembles this model may be reported as
        // this tire under another name.
        if ( $closest && $closest['similarity'] >= self::VARIANT_THRESHOLD ) {
            return array(
                'code'  => self::GAP_MODEL_VARIANT,
                'label' => sprintf(
                    '%s lists a "%s" in %s — likely this tire under another name. Worth confirming.',
                    empty( $closest['advertisers'] ) ? 'A retailer' : implode( ' and ', array_keys( $closest['advertisers'] ) ),
                    $closest['model'],
                    $size
                ),
                'near'  => $listed,
            );
        }

        return array(
            'code'  => self::GAP_MODEL_ABSENT,
            'label' => sprintf(
                '%s %s %s listing(s) arrived%s, none of them a %s. These are what came in:',
                number_format( count( $models ) ),
                $brand,
                $size,
                empty( $in_size['advertisers'] )
                    ? ''
                    : ' from ' . implode( ' and ', array_keys( $in_size['advertisers'] ) ),
                '"' . $model . '"'
            ),
            'near'  => $listed,
        );
    }

    /**
     * How alike two model names are, from 0 (unrelated) to 1 (identical).
     *
     * Two rules, deliberately in this order. Squashed equality or containment
     * scores high enough to call the same tire — that catches the punctuation
     * and suffix drift retailers actually produce ("M/S 2" against "M/S2",
     * "Ridge Grappler LT" against "Ridge Grappler"). Everything else scores on
     * shared words and stays below the threshold, which is only used to sort
     * what gets shown.
     *
     * @param string $guide_model   Model as the guide spells it.
     * @param string $listing_model Model parsed from the retailer's title.
     * @return float Similarity between 0 and 1.
     */
    public static function model_similarity( $guide_model, $listing_model ) {
        $a = RTG_Tire_Qualifier::normalize_brand( $guide_model );
        $b = RTG_Tire_Qualifier::normalize_brand( $listing_model );

        if ( '' === $a || '' === $b ) {
            return 0.0;
        }

        if ( $a === $b ) {
            return 1.0;
        }

        // Short strings are excluded: "AT" sits inside a great many names.
        if ( strlen( $a ) >= 6 && strlen( $b ) >= 6
            && ( false !== strpos( $a, $b ) || false !== strpos( $b, $a ) ) ) {
            return 0.9;
        }

        $tokens_a = self::tokens( $guide_model );
        $tokens_b = self::tokens( $listing_model );

        if ( empty( $tokens_a ) || empty( $tokens_b ) ) {
            return 0.0;
        }

        $shared = count( array_intersect( $tokens_a, $tokens_b ) );

        // Capped below the variant threshold: shared words order the list, they
        // never promote a listing to "the same tire". "Open Country R/T Trail"
        // and "Open Country A/T III EV" share two words and are not the same.
        return ( $shared / max( count( $tokens_a ), count( $tokens_b ) ) ) * 0.8;
    }

    /**
     * @param string $model Model name.
     * @return string[] Lowercased alphanumeric words.
     */
    private static function tokens( $model ) {
        return array_values( array_filter(
            preg_split( '/[^a-z0-9]+/', strtolower( (string) $model ) ),
            'strlen'
        ) );
    }

    /**
     * Diagnose a set of uncovered guide tires in one pass.
     *
     * @param array[] $tires Guide tires with no retailer match.
     * @return array tire_id => classification.
     */
    public static function diagnose( $tires ) {
        $index   = self::build_index();
        $reasons = array();

        foreach ( (array) $tires as $tire ) {
            $reasons[ (string) ( $tire['tire_id'] ?? '' ) ] = self::classify( $tire, $index );
        }

        return $reasons;
    }

    /**
     * Count how many tires fell into each gap, for a summary line.
     *
     * @param array $reasons Output of diagnose().
     * @return array Gap code => count, highest first.
     */
    public static function summarize( $reasons ) {
        $counts = array();

        foreach ( (array) $reasons as $reason ) {
            $code            = $reason['code'] ?? '';
            $counts[ $code ] = ( $counts[ $code ] ?? 0 ) + 1;
        }

        arsort( $counts );

        return $counts;
    }

    /**
     * @param string $code  Gap code.
     * @param string $label Explanation.
     * @return array Classification with no near matches.
     */
    private static function gap( $code, $label ) {
        return array(
            'code'  => $code,
            'label' => $label,
            'near'  => array(),
        );
    }
}
