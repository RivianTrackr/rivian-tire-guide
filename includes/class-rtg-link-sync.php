<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Puts a tracked affiliate link on tires that have none, or an untracked one.
 *
 * A tire with no purchase link sends readers nowhere; one with the retailer's
 * plain URL sends them to exactly the right page and earns nothing. Both were
 * fixed by hand — finding the product on the retailer's site, minting a deep
 * link in CJ, pasting it in. The sweep now stores a CJ-minted tracked link on
 * every matched candidate (when a website ID is configured), so that manual
 * loop can close itself.
 *
 * The rules are deliberately narrower than "always fill the link":
 *
 *  - A link the admin already made affiliate is never touched. It may carry a
 *    hand-chosen campaign or a preferred retailer; overwriting it could only
 *    break something a person decided.
 *  - A plain retailer link is upgraded only to a tracked link for the SAME
 *    retailer. Where the reader lands is an editorial choice already made;
 *    monetizing it is mechanical, switching retailers is not.
 *  - A missing link is filled from the cheapest fresh tracked listing, and
 *    price sync then follows that retailer — the price shown and the page
 *    clicked stay consistent by construction.
 *  - Only listings the sweep has seen in the last few days qualify. A stale
 *    candidate's link may point at a delisted product.
 *
 * Everything not updated is reported with a reason, so "why didn't this
 * tire's link change?" never needs a re-run to answer.
 *
 * @since 1.76.0
 */
class RTG_Link_Sync {

    /** Option key holding the last run's per-tire outcomes. */
    const RESULTS_OPTION = 'rtg_link_sync_results';

    /** Days since a listing was last seen before its link is too old to apply. */
    const FRESH_DAYS = 3;

    /**
     * Classify a link the way the Affiliate Links page does.
     *
     * Same domain list, same containment test — the sync and the page must
     * agree on what "affiliate" means or the page would show Regular rows the
     * sync claims to have fixed.
     *
     * @param string   $url     Link to classify.
     * @param string[] $domains Affiliate domains, from RTG_Admin.
     * @return string 'missing' | 'affiliate' | 'regular'.
     */
    public static function classify( $url, $domains ) {
        if ( '' === trim( (string) $url ) ) {
            return 'missing';
        }

        foreach ( (array) $domains as $domain ) {
            if ( '' !== $domain && false !== stripos( $url, $domain ) ) {
                return 'affiliate';
            }
        }

        return 'regular';
    }

    /**
     * Decide what should happen to one tire's link.
     *
     * Pure: tire, candidates, domain list and the clock come in, a decision
     * comes out.
     *
     * @param array    $tire       Guide tire (link, tire_id).
     * @param array[]  $candidates Candidate rows matched to this tire.
     * @param string[] $domains    Affiliate domains.
     * @param int      $now        Unix time, for listing freshness.
     * @return array {
     *     @type bool   $update   Whether to write a new link.
     *     @type string $link     Link to write, when updating.
     *     @type string $retailer Advertiser the link belongs to.
     *     @type string $code     Machine-readable outcome.
     *     @type string $label    Human-readable outcome.
     * }
     */
    public static function decide( $tire, $candidates, $domains, $now ) {
        $no = function ( $code, $label ) {
            return array(
                'update'   => false,
                'link'     => '',
                'retailer' => '',
                'code'     => $code,
                'label'    => $label,
            );
        };

        $current = trim( (string) ( $tire['link'] ?? '' ) );
        $state   = self::classify( $current, $domains );

        if ( 'affiliate' === $state ) {
            return $no( 'already_affiliate', 'Already carries an affiliate link' );
        }

        // Fresh, tracked listings are the only ones worth applying: a stale
        // candidate may describe a delisted product, and an untracked link is
        // the problem this sync exists to fix, not a fix.
        $tracked = array();
        $untracked_only = false;

        foreach ( (array) $candidates as $candidate ) {
            $link = trim( (string) ( $candidate['link'] ?? '' ) );
            if ( '' === $link ) {
                continue;
            }

            $seen = strtotime( (string) ( $candidate['last_seen_at'] ?? '' ) );
            if ( ! $seen || ( $now - $seen ) > ( self::FRESH_DAYS * DAY_IN_SECONDS ) ) {
                continue;
            }

            if ( 'affiliate' !== self::classify( $link, $domains ) ) {
                $untracked_only = true;
                continue;
            }

            $tracked[] = array(
                'link'     => $link,
                'retailer' => trim( (string) ( $candidate['advertiser_name'] ?? '' ) ),
                'price'    => floatval( $candidate['price'] ?? 0 ),
            );
        }

        if ( empty( $tracked ) ) {
            return $no(
                'no_tracked_link',
                $untracked_only
                    ? 'The catalog lists this tire, but without a tracked link — set the CJ website ID so the sweep can mint them'
                    : 'No current listing carries a tracked link'
            );
        }

        if ( 'missing' === $state ) {
            // The cheapest tracked listing. This choice also decides which
            // retailer prices the tire from now on, and cheapest is the pick a
            // reader would want made for them.
            usort( $tracked, function ( $a, $b ) {
                $ap = $a['price'] > 0 ? $a['price'] : PHP_FLOAT_MAX;
                $bp = $b['price'] > 0 ? $b['price'] : PHP_FLOAT_MAX;
                return $ap <=> $bp;
            } );

            $pick = $tracked[0];

            return array(
                'update'   => true,
                'link'     => $pick['link'],
                'retailer' => $pick['retailer'],
                'code'     => 'link_set',
                'label'    => sprintf( 'Link set to %s', '' !== $pick['retailer'] ? $pick['retailer'] : 'a tracked listing' ),
            );
        }

        // A regular link: where the reader lands was already chosen, so only a
        // tracked link to that same retailer may replace it.
        $retailer = RTG_Price_Sync::resolve_link_retailer( $current );

        if ( '' === $retailer ) {
            return $no(
                'link_elsewhere',
                'The current link points somewhere the catalog does not cover — switching retailers is not a call this sync makes'
            );
        }

        foreach ( $tracked as $candidate ) {
            if ( RTG_Price_Sync::normalize_retailer( $candidate['retailer'] ) === RTG_Price_Sync::normalize_retailer( $retailer ) ) {
                return array(
                    'update'   => true,
                    'link'     => $candidate['link'],
                    'retailer' => $candidate['retailer'],
                    'code'     => 'link_upgraded',
                    'label'    => sprintf( 'Untracked %s link upgraded to a tracked one', $retailer ),
                );
            }
        }

        return $no(
            'no_tracked_link_same_retailer',
            sprintf( 'Tracked links exist, but not for %s, where this link already points', $retailer )
        );
    }

    /**
     * Fill or upgrade every eligible tire's link.
     *
     * @return array Statistics, as saved to RESULTS_OPTION.
     */
    public static function run() {
        $settings = get_option( 'rtg_settings', array() );

        if ( isset( $settings['link_sync_enabled'] ) && ! $settings['link_sync_enabled'] ) {
            return array(
                'status'  => 'disabled',
                'message' => 'Link sync is disabled in settings.',
                'time'    => current_time( 'mysql' ),
            );
        }

        $domains = RTG_Admin::get_affiliate_domains();
        $by_tire = RTG_Candidates::get_matched_by_tire();
        $now     = current_time( 'timestamp' );

        $results = array(
            'status'   => 'success',
            'time'     => current_time( 'mysql' ),
            'set'      => 0,
            'upgraded' => 0,
            'skipped'  => 0,
            'outcomes' => array(),
        );

        foreach ( RTG_Database::get_all_tires() as $tire ) {
            $tire_id    = (string) $tire['tire_id'];
            $candidates = $by_tire[ $tire_id ] ?? array();

            // A tire that already carries an affiliate link is the normal,
            // finished state — recording thousands of those would bury the
            // rows worth reading.
            if ( 'affiliate' === self::classify( (string) ( $tire['link'] ?? '' ), $domains ) ) {
                continue;
            }

            $decision = self::decide( $tire, $candidates, $domains, $now );

            if ( $decision['update'] ) {
                RTG_Database::update_tire( $tire_id, array( 'link' => $decision['link'] ) );

                if ( 'link_set' === $decision['code'] ) {
                    $results['set']++;
                } else {
                    $results['upgraded']++;
                }
            } else {
                $results['skipped']++;
            }

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
