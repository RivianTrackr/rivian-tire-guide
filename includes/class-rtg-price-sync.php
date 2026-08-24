<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Keeps guide prices current from the retailer each tire actually links to.
 *
 * A tire carries one price and one purchase link, and the frontend shows them
 * together. So a price is only written when it comes from the retailer that
 * link points to — taking a cheaper figure from the other retailer would put a
 * price on the page that doesn't match what the reader sees on click, which is
 * worse than a stale price.
 *
 * That makes link resolution the whole problem, and affiliate links make it
 * awkward: a CJ deep link goes to a tracking host with the real destination
 * buried in a query parameter, so the retailer is rarely just the hostname.
 *
 * Everything not updated is reported with a reason rather than passed over, so
 * "why didn't this tire's price change?" is always answerable.
 *
 * @since 1.63.0
 */
class RTG_Price_Sync {

    /** Option key holding the last run's per-tire outcomes. */
    const RESULTS_OPTION = 'rtg_price_sync_results';

    /**
     * Reject a price this far from the current one, as a fraction.
     *
     * Match keys are brand + model + size, which can collide across load
     * ratings — a 116 and a 121 of the same model look identical to the key.
     * A price that moves by more than half is more likely to be that collision
     * than a real sale, so it is reported instead of written.
     */
    const DEFAULT_MAX_CHANGE = 0.5;

    /**
     * Hosts that identify a retailer directly.
     *
     * Keyed by advertiser name so the result can be compared against the
     * advertiser on a candidate row.
     */
    const RETAILER_HOSTS = array(
        'Tire Rack'  => array( 'tirerack.com', 'tirerackaffiliates.com' ),
        'SimpleTire' => array( 'simpletire.com' ),
    );

    /**
     * Query parameters affiliate networks use to carry the destination URL.
     */
    const DESTINATION_PARAMS = array( 'url', 'u', 'murl', 'RD_PARM1', 'destination' );

    /**
     * Work out which retailer a purchase link leads to.
     *
     * Tried in order of confidence: the hostname itself, then the destination
     * carried in a tracking parameter, then anywhere in the raw string (which
     * catches double-encoded deep links). Returns '' when the link leads
     * somewhere the sync doesn't price — Amazon, Discount Tire, a manufacturer
     * — because guessing there would attach one retailer's price to another's
     * link.
     *
     * @param string $link Purchase link.
     * @return string Advertiser name, or '' when it isn't one we price.
     */
    public static function resolve_link_retailer( $link ) {
        $link = trim( (string) $link );
        if ( '' === $link ) {
            return '';
        }

        // 1. The hostname itself.
        $host = strtolower( (string) wp_parse_url( $link, PHP_URL_HOST ) );
        if ( '' !== $host ) {
            $direct = self::match_host( $host );
            if ( '' !== $direct ) {
                return $direct;
            }
        }

        // 2. A destination URL carried in a tracking parameter.
        $query = (string) wp_parse_url( $link, PHP_URL_QUERY );
        if ( '' !== $query ) {
            parse_str( $query, $params );

            foreach ( self::DESTINATION_PARAMS as $key ) {
                foreach ( $params as $name => $value ) {
                    if ( strcasecmp( $name, $key ) !== 0 || ! is_string( $value ) || '' === $value ) {
                        continue;
                    }

                    // Networks sometimes encode the destination twice.
                    $candidate = rawurldecode( $value );
                    $inner     = strtolower( (string) wp_parse_url( $candidate, PHP_URL_HOST ) );

                    if ( '' === $inner ) {
                        $inner = strtolower( (string) wp_parse_url( rawurldecode( $candidate ), PHP_URL_HOST ) );
                    }

                    $matched = self::match_host( $inner );
                    if ( '' !== $matched ) {
                        return $matched;
                    }
                }
            }
        }

        // 3. Anywhere in the string. Last resort, and only for a full domain,
        //    so a model name can't be mistaken for a retailer.
        $haystack = strtolower( rawurldecode( $link ) );
        foreach ( self::RETAILER_HOSTS as $retailer => $hosts ) {
            foreach ( $hosts as $needle ) {
                if ( false !== strpos( $haystack, $needle ) ) {
                    return $retailer;
                }
            }
        }

        return '';
    }

    /**
     * @param string $host Hostname, lowercased.
     * @return string Advertiser name, or '' when unmatched.
     */
    private static function match_host( $host ) {
        if ( '' === $host ) {
            return '';
        }

        foreach ( self::RETAILER_HOSTS as $retailer => $hosts ) {
            foreach ( $hosts as $known ) {
                if ( $host === $known || substr( $host, -strlen( '.' . $known ) ) === '.' . $known ) {
                    return $retailer;
                }
            }
        }

        return '';
    }

    /**
     * Decide what should happen to one tire's price.
     *
     * Pure, so the rule is testable without a database: given a tire and the
     * candidates matching it, say whether to write a price and why.
     *
     * @param array   $tire       Guide tire (needs link and price).
     * @param array[] $candidates Candidate rows matched to this tire.
     * @param float   $max_change Reject a change larger than this fraction.
     * @return array {
     *     @type bool   $update   Whether to write a new price.
     *     @type float  $price    Price to write, when updating.
     *     @type string $retailer Advertiser the price came from.
     *     @type string $code     Machine-readable outcome.
     *     @type string $label    Human-readable outcome.
     * }
     */
    public static function decide( $tire, $candidates, $max_change = self::DEFAULT_MAX_CHANGE ) {
        $no = function ( $code, $label, $retailer = '' ) {
            return array(
                'update'   => false,
                'price'    => 0.0,
                'retailer' => $retailer,
                'code'     => $code,
                'label'    => $label,
            );
        };

        $link = trim( (string) ( $tire['link'] ?? '' ) );
        if ( '' === $link ) {
            return $no( 'no_link', 'No purchase link, so there is no retailer to price against' );
        }

        $retailer = self::resolve_link_retailer( $link );
        if ( '' === $retailer ) {
            return $no( 'link_not_priced', 'Purchase link points somewhere discovery does not price' );
        }

        // Only the retailer the link leads to may set the price.
        $quote = null;
        foreach ( (array) $candidates as $candidate ) {
            if ( (string) ( $candidate['advertiser_name'] ?? '' ) !== $retailer ) {
                continue;
            }

            $price = floatval( $candidate['price'] ?? 0 );
            if ( $price <= 0 ) {
                continue;
            }

            // Cheapest listing from that retailer, when it has several.
            if ( null === $quote || $price < $quote ) {
                $quote = $price;
            }
        }

        if ( null === $quote ) {
            return $no( 'retailer_not_carrying', sprintf( '%s is not currently listing this tire', $retailer ), $retailer );
        }

        $current = floatval( $tire['price'] ?? 0 );

        if ( $current > 0 ) {
            $delta = abs( $quote - $current ) / $current;
            if ( $delta > $max_change ) {
                return $no(
                    'change_implausible',
                    sprintf(
                        'Price moved from $%s to $%s (%d%%) — too far to apply unreviewed',
                        number_format( $current, 2 ),
                        number_format( $quote, 2 ),
                        (int) round( $delta * 100 )
                    ),
                    $retailer
                );
            }
        }

        // Compared in cents; a sub-cent difference is noise, not a change.
        if ( (int) round( $current * 100 ) === (int) round( $quote * 100 ) ) {
            return $no( 'unchanged', 'Price is already current', $retailer );
        }

        return array(
            'update'   => true,
            'price'    => $quote,
            'retailer' => $retailer,
            'code'     => 'updated',
            'label'    => sprintf(
                'Updated from $%s to $%s',
                number_format( $current, 2 ),
                number_format( $quote, 2 )
            ),
        );
    }

    /**
     * Refresh every guide tire's price from its own retailer.
     *
     * @return array Statistics, as saved to RESULTS_OPTION.
     */
    public static function run() {
        $settings = get_option( 'rtg_settings', array() );

        if ( isset( $settings['price_sync_enabled'] ) && ! $settings['price_sync_enabled'] ) {
            return array(
                'status'  => 'disabled',
                'message' => 'Price sync is disabled in settings.',
                'time'    => current_time( 'mysql' ),
            );
        }

        $max_change = isset( $settings['price_sync_max_change'] )
            ? max( 1, min( 100, intval( $settings['price_sync_max_change'] ) ) ) / 100
            : self::DEFAULT_MAX_CHANGE;

        $by_tire = RTG_Candidates::get_matched_by_tire();

        $results = array(
            'status'   => 'success',
            'time'     => current_time( 'mysql' ),
            'updated'  => 0,
            'skipped'  => 0,
            'outcomes' => array(),
        );

        foreach ( RTG_Database::get_all_tires() as $tire ) {
            $tire_id    = (string) $tire['tire_id'];
            $candidates = $by_tire[ $tire_id ] ?? array();

            if ( empty( $candidates ) ) {
                // No retailer match at all — that is the coverage report's
                // business, not a price failure, so it isn't counted here.
                continue;
            }

            $decision = self::decide( $tire, $candidates, $max_change );

            if ( $decision['update'] ) {
                RTG_Database::update_tire( $tire_id, array(
                    'price'           => $decision['price'],
                    'price_source'    => $decision['retailer'],
                    'price_synced_at' => current_time( 'mysql' ),
                ) );
                $results['updated']++;
            } else {
                $results['skipped']++;
            }

            // Everything is recorded, updated or not, so the admin page can
            // answer "why didn't this tire change?" without a re-run.
            $results['outcomes'][ $tire_id ] = array(
                'brand'    => $tire['brand'] ?? '',
                'model'    => $tire['model'] ?? '',
                'size'     => $tire['size'] ?? '',
                'retailer' => $decision['retailer'],
                'code'     => $decision['code'],
                'label'    => $decision['label'],
            );
        }

        update_option( self::RESULTS_OPTION, $results, false );

        return $results;
    }

    /**
     * @return array|false Last run's results, or false if never run.
     */
    public static function get_results() {
        return get_option( self::RESULTS_OPTION, false );
    }
}
