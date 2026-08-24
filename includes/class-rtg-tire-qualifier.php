<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Decides whether a product pulled from an affiliate catalog is a tire that
 * belongs in the Rivian tire guide.
 *
 * Retailer feeds describe a tire as marketing copy, not as structured specs —
 * "Michelin Defender LTX M/S2 275/65R18 116T" is a typical title and there may
 * be no size field at all. So this class does two jobs: pull specs out of the
 * text (parse_specs), then judge the result against the guide's rules
 * (qualify).
 *
 * Both are pure functions of their inputs. The thresholds live in the settings
 * the admin already edits, and are passed in as a context array rather than
 * read from the database here, so the rules can be unit tested and so a
 * "what would change if I lowered the load index floor?" preview stays cheap.
 *
 * @since 1.59.0
 */
class RTG_Tire_Qualifier {

    /**
     * Minimum load index accepted when the setting is unset.
     *
     * 112 is the R2 floor; R1 needs 116. The lower of the two is the default
     * so an R2-legal tire still surfaces for review — the load index is shown
     * on every row, and a tire that clears 112 but not 116 is a real candidate
     * for part of the fleet, not noise.
     */
    const DEFAULT_MIN_LOAD_INDEX = 112;

    /**
     * Build the rule context from saved plugin settings.
     *
     * @return array Context consumable by qualify().
     */
    public static function default_context() {
        $settings = get_option( 'rtg_settings', array() );

        $min_load_index = isset( $settings['catalog_min_load_index'] )
            ? intval( $settings['catalog_min_load_index'] )
            : self::DEFAULT_MIN_LOAD_INDEX;

        return array(
            'sizes'          => RTG_Admin::get_dropdown_options( 'sizes' ),
            'min_load_index' => $min_load_index,
            'load_ranges'    => RTG_Admin::get_dropdown_options( 'load_ranges' ),
            'speed_ratings'  => RTG_Admin::get_dropdown_options( 'speed_ratings' ),
        );
    }

    /**
     * Normalize a tire size to the guide's canonical form.
     *
     * Feeds write the same size as "LT275/65R18", "275/65 R18", "275/65ZR18"
     * and "P275/65R18". All of those are the same fitment for our purposes.
     *
     * @param string $size Raw size string.
     * @return string Canonical size (e.g. 275/65R18), or '' if unparseable.
     */
    public static function normalize_size( $size ) {
        if ( ! is_string( $size ) || '' === trim( $size ) ) {
            return '';
        }

        $size = strtoupper( trim( $size ) );
        $size = str_replace( array( ' ', '-' ), '', $size );

        // Strip a leading service-type prefix (LT/P/ST) — the guide keys on
        // the numbers, and carrying the prefix would split one size in two.
        $size = preg_replace( '/^(?:LT|P|ST)/', '', $size );

        if ( ! preg_match( '#^(\d{3})/(\d{2})Z?R(\d{2})#', $size, $m ) ) {
            return '';
        }

        return $m[1] . '/' . $m[2] . 'R' . $m[3];
    }

    /**
     * Extract tire specs from a feed product's text.
     *
     * Anything the feed supplies as a real field wins; parsing only fills the
     * gaps. Load index and speed rating are read from the text immediately
     * following the size, which is where every retailer puts them — searching
     * the whole title instead would happily read the "18" out of "275/65R18"
     * as a load index.
     *
     * @param array $product Raw product with at least a 'title'; may also carry
     *                       brand, size, load_index, load_range, speed_rating.
     * @return array Specs: size, load_index, load_range, speed_rating, brand, model.
     */
    public static function parse_specs( $product ) {
        $title = isset( $product['title'] ) ? (string) $product['title'] : '';
        $text  = trim( preg_replace( '/\s+/', ' ', $title ) );

        $specs = array(
            'size'         => self::normalize_size( $product['size'] ?? '' ),
            'load_index'   => isset( $product['load_index'] ) ? trim( (string) $product['load_index'] ) : '',
            'load_range'   => isset( $product['load_range'] ) ? strtoupper( trim( (string) $product['load_range'] ) ) : '',
            'speed_rating' => isset( $product['speed_rating'] ) ? strtoupper( trim( (string) $product['speed_rating'] ) ) : '',
            'brand'        => isset( $product['brand'] ) ? trim( (string) $product['brand'] ) : '',
            'model'        => '',
        );

        // Locate the size inside the title. Even when the feed gave us a size
        // field we still need its position, because the load index and speed
        // rating that follow it are what we parse next.
        $size_offset = null;
        $size_length = 0;
        if ( preg_match( '#\b(?:LT|P|ST)?(\d{3})\s*/\s*(\d{2})\s*Z?R\s*(\d{2})\b#i', $text, $m, PREG_OFFSET_CAPTURE ) ) {
            $size_offset = $m[0][1];
            $size_length = strlen( $m[0][0] );

            if ( '' === $specs['size'] ) {
                $specs['size'] = $m[1][0] . '/' . $m[2][0] . 'R' . $m[3][0];
            }
        }

        // The spec cluster is the ~20 characters after the size: "116T",
        // "116/113S", "121/118S E". Bounded so a model name further along the
        // title can't be misread as a rating.
        $tail = null === $size_offset ? '' : substr( $text, $size_offset + $size_length, 20 );

        if ( '' === $specs['load_index'] && '' !== $tail
            && preg_match( '/^\s*(\d{2,3})(?:\s*\/\s*(\d{2,3}))?\s*([A-Z])\b/i', $tail, $lm ) ) {
            $specs['load_index'] = $lm[1];
            if ( '' === $specs['speed_rating'] ) {
                $specs['speed_rating'] = strtoupper( $lm[3] );
            }
        }

        if ( '' === $specs['load_range'] ) {
            if ( preg_match( '/\bLOAD\s+RANGE\s+([C-F])\b/i', $text, $rm ) ) {
                $specs['load_range'] = strtoupper( $rm[1] );
            } elseif ( preg_match( '/\b(SL|XL|HL|RF)\b/i', $text, $rm ) ) {
                $specs['load_range'] = strtoupper( $rm[1] );
            }
        }

        $specs['model'] = self::derive_model( $text, $specs['brand'], $size_offset );

        return $specs;
    }

    /**
     * Derive the model name: the title with the brand and everything from the
     * size onwards removed. "Michelin Defender LTX M/S2 275/65R18 116T"
     * becomes "Defender LTX M/S2".
     *
     * @param string   $text        Whitespace-collapsed title.
     * @param string   $brand       Brand to strip from the front, if present.
     * @param int|null $size_offset Byte offset of the size in $text, if found.
     * @return string Model name, possibly empty.
     */
    private static function derive_model( $text, $brand, $size_offset ) {
        $model = null === $size_offset ? $text : substr( $text, 0, $size_offset );

        if ( '' !== $brand && stripos( $model, $brand ) === 0 ) {
            $model = substr( $model, strlen( $brand ) );
        }

        // Retailers often prefix the whole title with their own name.
        $model = preg_replace( '/^\s*(?:tire\s*rack|simple\s*tire)\s*/i', '', $model );

        return trim( $model, " \t\n\r\0\x0B-–—|," );
    }

    /**
     * Judge a parsed candidate against the guide's requirements.
     *
     * Failures are returned as structured reasons rather than a bare false so
     * the admin queue can show near misses — "correct size, load index 109" is
     * worth seeing, and a silent filter would hide exactly the rows most worth
     * a second opinion.
     *
     * @param array      $specs   Output of parse_specs(), plus optional price.
     * @param array|null $context Rule context; defaults to default_context().
     * @return array {
     *     @type bool  $qualifies Whether the candidate belongs in the guide.
     *     @type array $reasons   List of { code, label } failure reasons.
     * }
     */
    public static function qualify( $specs, $context = null ) {
        if ( null === $context ) {
            $context = self::default_context();
        }

        $reasons = array();

        // --- Size: the hard gate. ---
        $allowed = array();
        foreach ( (array) ( $context['sizes'] ?? array() ) as $allowed_size ) {
            $normalized = self::normalize_size( $allowed_size );
            if ( '' !== $normalized ) {
                $allowed[ $normalized ] = true;
            }
        }

        $size = self::normalize_size( $specs['size'] ?? '' );
        if ( '' === $size ) {
            $reasons[] = array(
                'code'  => 'size_unparsed',
                'label' => 'No tire size could be read from the listing',
            );
        } elseif ( ! isset( $allowed[ $size ] ) ) {
            $reasons[] = array(
                'code'  => 'size_not_stocked',
                'label' => sprintf( 'Size %s is not a Rivian fitment', $size ),
            );
        }

        // --- Load index: the safety gate. ---
        $min_index  = intval( $context['min_load_index'] ?? self::DEFAULT_MIN_LOAD_INDEX );
        $load_index = trim( (string) ( $specs['load_index'] ?? '' ) );

        if ( '' === $load_index ) {
            $reasons[] = array(
                'code'  => 'load_index_unknown',
                'label' => 'Load index not listed — needs manual confirmation',
            );
        } elseif ( intval( $load_index ) < $min_index ) {
            $reasons[] = array(
                'code'  => 'load_index_low',
                'label' => sprintf(
                    'Load index %d is below the %d minimum',
                    intval( $load_index ),
                    $min_index
                ),
            );
        }

        // --- Speed rating: only judged when the feed stated one. ---
        $speed = strtoupper( trim( (string) ( $specs['speed_rating'] ?? '' ) ) );
        if ( '' !== $speed ) {
            $valid_speeds = array_map( 'strtoupper', (array) ( $context['speed_ratings'] ?? array() ) );
            if ( ! empty( $valid_speeds ) && ! in_array( $speed, $valid_speeds, true ) ) {
                $reasons[] = array(
                    'code'  => 'speed_rating_unknown',
                    'label' => sprintf( 'Unrecognized speed rating "%s"', $speed ),
                );
            }
        }

        // --- Identity: a row we can't name isn't reviewable. ---
        if ( '' === trim( (string) ( $specs['brand'] ?? '' ) ) ) {
            $reasons[] = array(
                'code'  => 'brand_missing',
                'label' => 'No brand on the listing',
            );
        }
        if ( '' === trim( (string) ( $specs['model'] ?? '' ) ) ) {
            $reasons[] = array(
                'code'  => 'model_missing',
                'label' => 'No model name could be read from the listing',
            );
        }

        return array(
            'qualifies' => empty( $reasons ),
            'reasons'   => $reasons,
        );
    }

    /**
     * Convenience wrapper: parse a raw feed product and judge it in one step.
     *
     * @param array      $product Raw product from a catalog source.
     * @param array|null $context Rule context; defaults to default_context().
     * @return array Specs merged with qualifies and reasons.
     */
    public static function evaluate( $product, $context = null ) {
        $specs  = self::parse_specs( $product );
        $result = self::qualify( $specs, $context );

        return array_merge( $specs, $result );
    }
}
