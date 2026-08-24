<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Tells a guide tire that used to be in the affiliate catalog from one that
 * never was.
 *
 * A broken-link check answers "does this URL still resolve?" — and a delisted
 * tire passes it. The retailer's page is still there, the affiliate link still
 * redirects, and the product has quietly been dropped from the feed the
 * commission and the price come from. That is invisible from the URL and
 * obvious from the sweep's own history: every candidate row records when the
 * sweep last saw the product, so a listing that stops appearing is a delisting
 * with a date on it.
 *
 * The distinction that makes this trustworthy is the one it would be easiest
 * to skip: a listing can go stale because the retailer dropped it, or because
 * our own sweep never read that fitment. Only the first is a delisting, so a
 * fitment the last sweep did not read completely is reported as unknown rather
 * than as a loss. Announcing a delisting that is really our own gap would send
 * someone to renegotiate a link that was never dropped.
 *
 * @since 1.72.0
 */
class RTG_Catalog_Presence {

    /** The retailer is still listing this tire. */
    const STATUS_LISTED = 'listed';

    /** The retailer listed this tire and has stopped. */
    const STATUS_DELISTED = 'delisted';

    /** No retailer has ever listed it in any sweep. */
    const STATUS_NEVER_LISTED = 'never_listed';

    /** Not answerable — the fitment wasn't read, or the tire can't be keyed. */
    const STATUS_UNKNOWN = 'unknown';

    /**
     * Days a listing may go unseen before it counts as dropped.
     *
     * The sweep runs daily, so one missed day is ordinary — a slow run, a
     * failed request, a fitment that ran out of budget. Three consecutive days
     * of a completely-read fitment not containing a product is a decision the
     * retailer made.
     */
    const DEFAULT_STALE_DAYS = 3;

    /**
     * Work out where each guide tire stands with the affiliate catalog.
     *
     * @param array[]  $tires      Guide tires (tire_id, brand, model, size, link).
     * @param array    $by_key     Match key => candidate rows, from RTG_Candidates.
     * @param array    $read_sizes Canonical sizes the last sweep read completely.
     * @param int      $now        Unix time to measure staleness against.
     * @param int      $stale_days Days unseen before a listing counts as dropped.
     * @return array tire_id => { status, label, retailers, last_seen }.
     */
    public static function evaluate( $tires, $by_key, $read_sizes, $now, $stale_days = self::DEFAULT_STALE_DAYS ) {
        $out = array();

        foreach ( (array) $tires as $tire ) {
            $out[ (string) ( $tire['tire_id'] ?? '' ) ] = self::evaluate_one(
                $tire,
                $by_key,
                $read_sizes,
                $now,
                $stale_days
            );
        }

        return $out;
    }

    /**
     * @param array $tire       One guide tire.
     * @param array $by_key     Match key => candidate rows.
     * @param array $read_sizes Canonical size => true, for fitments read completely.
     * @param int   $now        Unix time.
     * @param int   $stale_days Days unseen before a listing counts as dropped.
     * @return array { status, label, retailers, last_seen }
     */
    public static function evaluate_one( $tire, $by_key, $read_sizes, $now, $stale_days = self::DEFAULT_STALE_DAYS ) {
        $key  = RTG_Catalog_Sync::match_key( $tire['brand'] ?? '', $tire['model'] ?? '', $tire['size'] ?? '' );
        $size = RTG_Tire_Qualifier::normalize_size( $tire['size'] ?? '' );

        if ( '' === $key ) {
            return self::result(
                self::STATUS_UNKNOWN,
                'This tire has no brand or no readable size, so it cannot be looked up in the catalog.'
            );
        }

        $candidates = $by_key[ $key ] ?? array();

        // Latest sighting across every retailer that has ever listed it.
        $last_seen = 0;
        $retailers = array();

        foreach ( $candidates as $candidate ) {
            $seen = strtotime( (string) ( $candidate['last_seen_at'] ?? '' ) );
            if ( ! $seen ) {
                continue;
            }

            $name = trim( (string) ( $candidate['advertiser_name'] ?? '' ) );
            if ( '' !== $name && ( ! isset( $retailers[ $name ] ) || $seen > $retailers[ $name ] ) ) {
                $retailers[ $name ] = $seen;
            }

            $last_seen = max( $last_seen, $seen );
        }

        $stale_after = $stale_days * DAY_IN_SECONDS;

        if ( $last_seen > 0 && ( $now - $last_seen ) <= $stale_after ) {
            // Only retailers still listing it are named. Naming every retailer
            // that ever did would credit one that dropped the tire a month ago
            // as though it still carried it — and where the others dropped it,
            // that is worth saying rather than hiding behind the one that
            // didn't.
            $current = array();
            $dropped = array();

            foreach ( $retailers as $name => $seen ) {
                if ( ( $now - $seen ) <= $stale_after ) {
                    $current[] = $name;
                } else {
                    $dropped[] = $name;
                }
            }

            return self::result(
                self::STATUS_LISTED,
                sprintf(
                    'Listed by %s.%s',
                    empty( $current ) ? 'a retailer' : implode( ' and ', $current ),
                    empty( $dropped )
                        ? ''
                        : sprintf( ' %s stopped listing it.', implode( ' and ', $dropped ) )
                ),
                $retailers,
                $last_seen
            );
        }

        // Beyond here the tire is not in the current catalog. Whether that is
        // the retailer's doing or ours depends entirely on whether the sweep
        // actually read the fitment, so that is checked before anything is
        // claimed.
        if ( '' === $size || empty( $read_sizes[ $size ] ) ) {
            return self::result(
                self::STATUS_UNKNOWN,
                sprintf(
                    'The last sweep did not read %s completely, so whether this is still listed is not known yet.',
                    '' === $size ? 'this fitment' : $size
                ),
                $retailers,
                $last_seen
            );
        }

        if ( $last_seen > 0 ) {
            // Named from the most recent sighting, not from every retailer in
            // the row. Two retailers that dropped a tire weeks apart did not
            // both stop on the later date.
            $last_by = '';
            foreach ( $retailers as $name => $seen ) {
                if ( $seen === $last_seen ) {
                    $last_by = $name;
                    break;
                }
            }

            return self::result(
                self::STATUS_DELISTED,
                sprintf(
                    'Dropped from the affiliate catalog. Last listed by %s, %s ago. The link may still work, but it no longer earns or prices.',
                    '' === $last_by ? 'a retailer' : $last_by,
                    human_time_diff( $last_seen, $now )
                ),
                $retailers,
                $last_seen
            );
        }

        return self::result(
            self::STATUS_NEVER_LISTED,
            sprintf( 'No retailer has listed this in any sweep of %s.', $size ),
            $retailers,
            0
        );
    }

    /**
     * Count each status, for a summary line.
     *
     * @param array $evaluated Output of evaluate().
     * @return array Status => count.
     */
    public static function summarize( $evaluated ) {
        $counts = array(
            self::STATUS_LISTED       => 0,
            self::STATUS_DELISTED     => 0,
            self::STATUS_NEVER_LISTED => 0,
            self::STATUS_UNKNOWN      => 0,
        );

        foreach ( (array) $evaluated as $entry ) {
            $status = $entry['status'] ?? self::STATUS_UNKNOWN;
            if ( isset( $counts[ $status ] ) ) {
                $counts[ $status ]++;
            }
        }

        return $counts;
    }

    /**
     * Fitments the last sweep read completely, as a size => true set.
     *
     * A partially-read fitment cannot support a delisting claim, so it is left
     * out rather than assumed.
     *
     * @param array|false $stats Last run's statistics from RTG_Catalog_Sync.
     * @return array Canonical size => true.
     */
    public static function fully_read_sizes( $stats ) {
        $sizes = array();

        foreach ( ( $stats['sources'] ?? array() ) as $source ) {
            foreach ( ( $source['coverage'] ?? array() ) as $size => $coverage ) {
                $total    = $coverage['total'] ?? null;
                $received = intval( $coverage['received'] ?? 0 );

                if ( null === $total || $received < intval( $total ) ) {
                    continue;
                }

                $normalized = RTG_Tire_Qualifier::normalize_size( $size );
                if ( '' !== $normalized ) {
                    $sizes[ $normalized ] = true;
                }
            }
        }

        return $sizes;
    }

    /**
     * @param string $status    One of the STATUS_* constants.
     * @param string $label     Sentence for the admin.
     * @param array  $retailers Advertiser name => last-seen timestamp.
     * @param int    $last_seen Latest sighting, or 0.
     * @return array Result row.
     */
    private static function result( $status, $label, $retailers = array(), $last_seen = 0 ) {
        arsort( $retailers );

        return array(
            'status'    => $status,
            'label'     => $label,
            'retailers' => $retailers,
            'last_seen' => $last_seen,
        );
    }
}
