<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Tells the reader when the retailer a tire links to cannot sell it today.
 *
 * Every candidate row carries the retailer's own stock wording, read by the
 * nightly sweep and kept fresh for three days. This projects it onto the
 * guide tire: the linked retailer's fresh listings decide whether the tire
 * is in stock there, and when it is not, the cheapest fresh, in-stock,
 * tracked listing at the other retailer is kept beside it so the page can
 * offer that instead of a dead end.
 *
 * Only the linked retailer's word counts, for the same reason only its
 * price does: the button says "View at Tire Rack", so "out of stock" has to
 * mean out of stock there. Stock at the other retailer is never a verdict
 * on the tire, only an alternative.
 *
 * The write goes through its own whitelist rather than update_tire(), so a
 * nightly stock check never bumps updated_at and makes a hand-typed price
 * look freshly reviewed.
 *
 * @since 2.10.0
 */
class RTG_Stock_Sync {

    /** Option key holding the last run's per-tire outcomes. */
    const RESULTS_OPTION = 'rtg_stock_sync_results';

    const IN_STOCK     = 'in_stock';
    const OUT_OF_STOCK = 'out_of_stock';

    /**
     * Days a stock check stays worth showing. After that the note is
     * withheld rather than shown stale: a sync that stopped running must
     * not leave "out of stock" on a tire for a month. The same window the
     * link sync trusts a listing for. Mirrors STOCK_FRESH_DAYS in
     * frontend/js/modules/pricing.js.
     */
    const FRESH_DAYS = RTG_Link_Sync::FRESH_DAYS;

    /**
     * Decide one tire's stock from the listings matched to it.
     *
     * Pure, so the rule is testable without a database.
     *
     * @param array    $tire       Guide tire (needs link).
     * @param array[]  $candidates Candidate rows matched to this tire.
     * @param string[] $domains    Affiliate domains, from RTG_Admin.
     * @param int      $now        Unix time.
     * @return array {
     *     @type string $status       '', IN_STOCK or OUT_OF_STOCK.
     *     @type string $retailer     The retailer the link leads to, or ''.
     *     @type string $alt_retailer Other retailer with a fresh, tracked, in-stock listing.
     *     @type string $alt_link     That listing's tracked URL.
     *     @type string $code         Machine-readable outcome.
     *     @type string $label        Human-readable outcome.
     * }
     */
    public static function decide( $tire, $candidates, $domains, $now ) {
        $out = array(
            'status'       => '',
            'retailer'     => '',
            'alt_retailer' => '',
            'alt_link'     => '',
            'code'         => '',
            'label'        => '',
        );

        $link     = trim( (string) ( $tire['link'] ?? '' ) );
        $retailer = '' !== $link ? RTG_Price_Sync::resolve_link_retailer( $link ) : '';

        if ( '' === $retailer ) {
            $out['code']  = 'link_not_tracked';
            $out['label'] = 'Purchase link points somewhere discovery does not read stock for';
            return $out;
        }

        $out['retailer'] = $retailer;
        $key             = RTG_Price_Sync::normalize_retailer( $retailer );

        $in_stock     = false;
        $out_of_stock = false;
        $alternatives = array();

        foreach ( (array) $candidates as $candidate ) {
            $seen = strtotime( (string) ( $candidate['last_seen_at'] ?? '' ) );
            if ( ! $seen || ( $now - $seen ) > ( self::FRESH_DAYS * DAY_IN_SECONDS ) ) {
                continue;
            }

            // A listing that says nothing about stock has no opinion.
            $availability = RTG_Candidates::normalize_availability( $candidate['availability'] ?? '' );
            if ( '' === $availability ) {
                continue;
            }

            $sold_out = RTG_Candidates::is_out_of_stock( $availability );
            $name     = trim( (string) ( $candidate['advertiser_name'] ?? '' ) );

            if ( RTG_Price_Sync::normalize_retailer( $name ) === $key ) {
                if ( $sold_out ) {
                    $out_of_stock = true;
                } else {
                    $in_stock = true;
                }
                continue;
            }

            // The other retailer only matters as somewhere to send the
            // reader instead, so it has to be sellable and earn the click.
            $alt_link = trim( (string) ( $candidate['link'] ?? '' ) );
            if ( $sold_out || '' === $name || '' === $alt_link || 'affiliate' !== RTG_Link_Sync::classify( $alt_link, $domains ) ) {
                continue;
            }

            $alternatives[] = array(
                'link'     => $alt_link,
                'retailer' => $name,
                'price'    => floatval( $candidate['price'] ?? 0 ),
            );
        }

        // One fresh listing the retailer can sell outranks one it cannot:
        // the same tire is often listed twice, and the reader can buy the
        // one that is there.
        if ( $in_stock ) {
            $out['status'] = self::IN_STOCK;
            $out['code']   = 'in_stock';
            $out['label']  = sprintf( '%s has it in stock', $retailer );
            return $out;
        }

        if ( $out_of_stock ) {
            $out['status'] = self::OUT_OF_STOCK;
            $out['code']   = 'out_of_stock';
            $out['label']  = sprintf( '%s lists it as out of stock', $retailer );

            if ( ! empty( $alternatives ) ) {
                $pick                = self::cheapest( $alternatives );
                $out['alt_retailer'] = $pick['retailer'];
                $out['alt_link']     = $pick['link'];
                $out['label']       .= sprintf( ', in stock at %s', $pick['retailer'] );
            }

            return $out;
        }

        $out['code']  = 'no_wording';
        $out['label'] = sprintf( '%s sent no stock wording in the last %d days', $retailer, self::FRESH_DAYS );
        return $out;
    }

    /**
     * The listing a reader would want picked for them. Unpriced listings
     * sort last: a known price beats a mystery.
     *
     * @param array[] $listings Tracked listings (link, retailer, price).
     * @return array The cheapest listing.
     */
    private static function cheapest( $listings ) {
        usort( $listings, function ( $a, $b ) {
            $ap = $a['price'] > 0 ? $a['price'] : PHP_FLOAT_MAX;
            $bp = $b['price'] > 0 ? $b['price'] : PHP_FLOAT_MAX;
            return $ap <=> $bp;
        } );

        return $listings[0];
    }

    /**
     * Record every guide tire's stock from its own retailer.
     *
     * Runs off the same fetch as the link and price syncs. A tire whose
     * verdict and alternative have not changed is written only to refresh
     * the check date when it has a verdict at all; a tire with none stays
     * untouched, so the guide's edit history is not churned nightly.
     *
     * @return array Statistics, as saved to RESULTS_OPTION.
     */
    public static function run() {
        $domains   = RTG_Admin::get_affiliate_domains();
        $by_tire   = RTG_Candidates::get_matched_by_tire();
        $now       = current_time( 'timestamp' );
        $now_mysql = current_time( 'mysql' );

        $results = array(
            'status'       => 'success',
            'time'         => $now_mysql,
            'in_stock'     => 0,
            'out_of_stock' => 0,
            'alternatives' => 0,
            'unknown'      => 0,
            'written'      => 0,
            'outcomes'     => array(),
        );

        foreach ( RTG_Database::get_all_tires() as $tire ) {
            $tire_id  = (string) $tire['tire_id'];
            $decision = self::decide( $tire, $by_tire[ $tire_id ] ?? array(), $domains, $now );

            $data = array(
                'stock_status'       => $decision['status'],
                'stock_checked_at'   => '' === $decision['status'] ? null : $now_mysql,
                'stock_alt_retailer' => $decision['alt_retailer'],
                'stock_alt_link'     => $decision['alt_link'],
            );

            $changed = (string) ( $tire['stock_status'] ?? '' ) !== $decision['status']
                || (string) ( $tire['stock_alt_retailer'] ?? '' ) !== $decision['alt_retailer']
                || (string) ( $tire['stock_alt_link'] ?? '' ) !== $decision['alt_link'];

            if ( $changed || '' !== $decision['status'] ) {
                RTG_Database::update_stock_data( $tire_id, $data );
                $results['written']++;
            }

            if ( self::IN_STOCK === $decision['status'] ) {
                $results['in_stock']++;
            } elseif ( self::OUT_OF_STOCK === $decision['status'] ) {
                $results['out_of_stock']++;
                if ( '' !== $decision['alt_retailer'] ) {
                    $results['alternatives']++;
                }
            } else {
                $results['unknown']++;
            }

            $results['outcomes'][ $tire_id ] = array(
                'brand'        => $tire['brand'] ?? '',
                'model'        => $tire['model'] ?? '',
                'size'         => $tire['size'] ?? '',
                'retailer'     => $decision['retailer'],
                'status'       => $decision['status'],
                'alt_retailer' => $decision['alt_retailer'],
                'code'         => $decision['code'],
                'label'        => $decision['label'],
            );
        }

        if ( $results['written'] > 0 ) {
            RTG_Database::flush_cache();
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

    /**
     * What the page says about a tire's stock, if anything.
     *
     * Only a fresh out-of-stock verdict earns a line; in stock is the
     * normal state and says nothing, and a stale verdict is withheld.
     * Mirrors stockNote() in frontend/js/modules/pricing.js.
     *
     * @param array $tire Guide tire row.
     * @param int   $now  Unix time.
     * @return array ['show' => bool, 'status' => string, 'retailer' => string,
     *               'label' => string, 'title' => string,
     *               'alt_retailer' => string, 'alt_link' => string]
     */
    public static function note( $tire, $now ) {
        $status   = (string) ( $tire['stock_status'] ?? '' );
        $checked  = (int) strtotime( (string) ( $tire['stock_checked_at'] ?? '' ) );
        $retailer = RTG_Retailer::label( $tire );

        $out = array(
            'show'         => false,
            'status'       => $status,
            'retailer'     => $retailer,
            'label'        => '',
            'title'        => '',
            'alt_retailer' => '',
            'alt_link'     => '',
        );

        $fresh = $checked > 0 && ( $now - $checked ) <= ( self::FRESH_DAYS * DAY_IN_SECONDS );
        if ( self::OUT_OF_STOCK !== $status || ! $fresh ) {
            return $out;
        }

        $format = gmdate( 'Y', $checked ) === gmdate( 'Y', $now ) ? 'M j' : 'M j, Y';

        $out['show']         = true;
        $out['label']        = 'Out of stock at ' . ( '' !== $retailer ? $retailer : 'the retailer' );
        $out['title']        = sprintf(
            '%s listed this tire as out of stock when we checked on %s.',
            '' !== $retailer ? $retailer : 'The retailer',
            date_i18n( $format, $checked )
        );
        $out['alt_retailer'] = (string) ( $tire['stock_alt_retailer'] ?? '' );
        $out['alt_link']     = (string) ( $tire['stock_alt_link'] ?? '' );

        return $out;
    }
}
