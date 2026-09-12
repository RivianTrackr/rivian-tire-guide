<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Truncate a URL for display in the table.
 */
if ( ! function_exists( 'rtg_truncate_url' ) ) {
    function rtg_truncate_url( $url ) {
        if ( empty( $url ) ) {
            return '';
        }
        $parsed  = wp_parse_url( $url );
        $display = ( $parsed['host'] ?? '' ) . ( $parsed['path'] ?? '' );
        if ( strlen( $display ) > 45 ) {
            return substr( $display, 0, 42 ) . '...';
        }
        return $display;
    }
}

// Filters.
$link_filter = isset( $_GET['link_filter'] ) ? sanitize_text_field( $_GET['link_filter'] ) : 'all';
$search      = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';

// Load affiliate domains from settings.
$affiliate_domains = RTG_Admin::get_affiliate_domains();

// Get data.
$counts = RTG_Database::get_link_status_counts();
$tires  = RTG_Database::get_tires_for_link_management( $link_filter, $search );

// Classify each tire's link type.
$classify_link = function( $url ) use ( $affiliate_domains ) {
    if ( empty( $url ) ) {
        return 'missing';
    }
    foreach ( $affiliate_domains as $domain ) {
        if ( stripos( $url, $domain ) !== false ) {
            return 'affiliate';
        }
    }
    return 'regular';
};

// Count affiliate vs regular for display.
$affiliate_count = 0;
$regular_count   = 0;
foreach ( $tires as $tire ) {
    $type = $classify_link( $tire['link'] );
    if ( $type === 'affiliate' ) {
        $affiliate_count++;
    } elseif ( $type === 'regular' ) {
        $regular_count++;
    }
}

// Tab counts for the "all" view.
$all_tires_for_counts = ( $link_filter !== 'all' || ! empty( $search ) )
    ? RTG_Database::get_tires_for_link_management( 'all', '' )
    : $tires;
// Catalog presence: whether the retailer is still listing each tire.
//
// A broken-link check asks whether the URL resolves, and a delisted tire passes
// that — the page is still there, the link still redirects, and the product has
// been dropped from the feed the commission and the price come from. The sweep
// records when it last saw each listing, so a listing that stops appearing is a
// delisting with a date on it.
$presence = RTG_Catalog_Presence::evaluate(
    $all_tires_for_counts,
    RTG_Candidates::get_by_match_key(),
    RTG_Catalog_Presence::fully_read_sizes( RTG_Catalog_Sync::get_stats() ?: array() ),
    current_time( 'timestamp' )
);
$presence_counts = RTG_Catalog_Presence::summarize( $presence );

$total_affiliate = 0;
$total_regular   = 0;
$total_missing   = 0;
foreach ( $all_tires_for_counts as $t ) {
    $type = $classify_link( $t['link'] );
    if ( $type === 'affiliate' ) {
        $total_affiliate++;
    } elseif ( $type === 'regular' ) {
        $total_regular++;
    } else {
        $total_missing++;
    }
}

// Link health check results.
$link_check_results = RTG_Link_Checker::get_results();
$broken_tire_ids    = RTG_Link_Checker::get_broken_tire_ids();
$broken_count       = count( $broken_tire_ids );
$last_checked       = ! empty( $link_check_results['checked_at'] ) ? $link_check_results['checked_at'] : '';

// Success message.
$message = isset( $_GET['message'] ) ? sanitize_text_field( $_GET['message'] ) : '';
?>

<div class="rtg-wrap">

    <?php if ( $message === 'saved' ) : ?>
        <div class="rtg-notice rtg-notice-success">
            <span>Links updated successfully.</span>
            <button type="button" class="rtg-notice-dismiss" aria-label="Dismiss">&times;</button>
        </div>
    <?php endif; ?>

    <div class="rtg-page-header">
        <div class="rtg-page-heading">
            <h1 class="rtg-page-title">Affiliate Links</h1>
            <p class="rtg-page-subtitle">Every tire's purchase and review link in one place. Edit a row in place; the status updates as you save.</p>
        </div>
        <div class="rtg-page-actions">
            <button type="button" id="rtg-check-links-btn" class="rtg-btn rtg-btn-secondary">Check links now</button>
        </div>
    </div>

    <?php $delisted_count = intval( $presence_counts[ RTG_Catalog_Presence::STATUS_DELISTED ] ); ?>

    <!-- Stats Grid -->
    <div class="rtg-stats-grid is-compact">
        <a class="rtg-stat-card" href="<?php echo esc_url( add_query_arg( array( 'page' => 'rtg-affiliate-links', 'link_filter' => 'all' ), admin_url( 'admin.php' ) ) ); ?>">
            <div class="rtg-stat-value"><?php echo esc_html( $counts['total'] ); ?></div>
            <div class="rtg-stat-label">Tires</div>
        </a>
        <a class="rtg-stat-card is-success" href="<?php echo esc_url( add_query_arg( array( 'page' => 'rtg-affiliate-links', 'link_filter' => 'affiliate' ), admin_url( 'admin.php' ) ) ); ?>">
            <div class="rtg-stat-value"><?php echo esc_html( $total_affiliate ); ?></div>
            <div class="rtg-stat-label">Affiliate</div>
        </a>
        <a class="rtg-stat-card <?php echo $total_regular > 0 ? 'is-warning' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => 'rtg-affiliate-links', 'link_filter' => 'regular' ), admin_url( 'admin.php' ) ) ); ?>">
            <div class="rtg-stat-value"><?php echo esc_html( $total_regular ); ?></div>
            <div class="rtg-stat-label">Plain links</div>
        </a>
        <a class="rtg-stat-card <?php echo $total_missing > 0 ? 'is-error' : 'is-success'; ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => 'rtg-affiliate-links', 'link_filter' => 'missing' ), admin_url( 'admin.php' ) ) ); ?>">
            <div class="rtg-stat-value"><?php echo esc_html( $total_missing ); ?></div>
            <div class="rtg-stat-label">Missing</div>
        </a>
        <a class="rtg-stat-card <?php echo $broken_count > 0 ? 'is-error' : 'is-success'; ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => 'rtg-affiliate-links', 'link_filter' => 'broken' ), admin_url( 'admin.php' ) ) ); ?>">
            <div class="rtg-stat-value"><?php echo esc_html( $broken_count ); ?></div>
            <div class="rtg-stat-label">Broken</div>
        </a>
        <a class="rtg-stat-card <?php echo $delisted_count > 0 ? 'is-error' : 'is-success'; ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => 'rtg-affiliate-links', 'link_filter' => 'delisted' ), admin_url( 'admin.php' ) ) ); ?>">
            <div class="rtg-stat-value"><?php echo esc_html( $delisted_count ); ?></div>
            <div class="rtg-stat-label">Delisted</div>
        </a>
        <a class="rtg-stat-card" href="<?php echo esc_url( add_query_arg( array( 'page' => 'rtg-affiliate-links', 'link_filter' => 'no_review' ), admin_url( 'admin.php' ) ) ); ?>">
            <div class="rtg-stat-value"><?php echo esc_html( $counts['has_review'] ); ?></div>
            <div class="rtg-stat-label">Review links</div>
        </a>
    </div>

    <?php $link_sync_results = RTG_Link_Sync::get_results(); ?>
    <?php if ( $link_sync_results && isset( $link_sync_results['outcomes'] ) ) : ?>
        <div class="rtg-card">
            <div class="rtg-card-header is-split">
                <div>
                    <h2>Link sync</h2>
                    <p>The daily run fills missing links and upgrades plain retailer links to tracked ones. Its rules live in <a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-tire-discovery#tab-settings' ) ); ?>">Tire Discovery settings</a>.</p>
                </div>
                <span class="rtg-muted rtg-small">Last run <?php echo esc_html( $link_sync_results['time'] ?? '' ); ?></span>
            </div>
            <div class="rtg-card-body">
                <div class="rtg-kv-grid">
                    <div><span class="rtg-kv-label">Set</span><div class="rtg-kv-value is-success"><?php echo intval( $link_sync_results['set'] ?? 0 ); ?></div></div>
                    <div><span class="rtg-kv-label">Upgraded</span><div class="rtg-kv-value is-success"><?php echo intval( $link_sync_results['upgraded'] ?? 0 ); ?></div></div>
                    <div><span class="rtg-kv-label">Moved off delisted</span><div class="rtg-kv-value is-success"><?php echo intval( $link_sync_results['replaced'] ?? 0 ); ?></div></div>
                    <div><span class="rtg-kv-label">Skipped with a reason</span><div class="rtg-kv-value is-muted"><?php echo intval( $link_sync_results['skipped'] ?? 0 ); ?></div></div>
                </div>

                <?php
                $link_sync_pending = array();
                foreach ( (array) $link_sync_results['outcomes'] as $outcome_tire_id => $outcome ) {
                    if ( ! in_array( $outcome['code'], array( 'link_set', 'link_upgraded', 'link_replaced' ), true ) ) {
                        $link_sync_pending[ $outcome_tire_id ] = $outcome;
                    }
                }
                ?>
                <?php if ( ! empty( $link_sync_pending ) ) : ?>
                    <details class="rtg-details">
                        <summary><?php echo count( $link_sync_pending ); ?> tire<?php echo 1 === count( $link_sync_pending ) ? '' : 's'; ?> link sync could not fix, and why</summary>
                        <div class="rtg-details-body">
                            <div class="rtg-table-wrapper">
                                <table class="rtg-table rtg-table-compact">
                                    <thead><tr><th>Tire</th><th>Size</th><th>Reason</th></tr></thead>
                                    <tbody>
                                    <?php foreach ( $link_sync_pending as $outcome ) : ?>
                                        <tr>
                                            <td><?php echo esc_html( trim( $outcome['brand'] . ' ' . $outcome['model'] ) ); ?></td>
                                            <td class="rtg-mono"><?php echo esc_html( $outcome['size'] ); ?></td>
                                            <td class="rtg-muted rtg-small"><?php echo esc_html( $outcome['label'] ); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </details>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ( $presence_counts[ RTG_Catalog_Presence::STATUS_DELISTED ] > 0 ) : ?>
        <div class="rtg-notice rtg-notice-warning">
            <span>
                <strong><?php echo esc_html( $delisted_count ); ?>
                tire<?php echo 1 === $delisted_count ? ' was' : 's were'; ?> dropped from the affiliate catalog.</strong> The links still resolve
                but no longer earn or price. <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'rtg-affiliate-links', 'link_filter' => 'delisted' ), admin_url( 'admin.php' ) ) ); ?>">Show them</a>.
            </span>
        </div>
    <?php endif; ?>

    <!-- Link Health Check -->
    <div class="rtg-card">
        <div class="rtg-card-body">
            <div class="rtg-health-strip">
                <strong>Link health</strong>
                <?php if ( $last_checked ) : ?>
                    <span class="rtg-muted">Last checked <?php echo esc_html( date( 'M j, Y g:i A', strtotime( $last_checked ) ) ); ?></span>
                    <?php
                    // The weekly run rotates through the catalog in batches;
                    // say so when the last run was one slice of it.
                    $lc_total = intval( $link_check_results['total'] ?? 0 );
                    $lc_all   = intval( $link_check_results['link_count'] ?? 0 );
                    if ( $lc_all > $lc_total && $lc_total > 0 ) :
                    ?>
                        <span class="rtg-muted">(<?php echo esc_html( $lc_total ); ?> of <?php echo esc_html( $lc_all ); ?> links this run, the weekly check rotates through the rest)</span>
                    <?php endif; ?>
                <?php else : ?>
                    <span class="rtg-muted">Never checked</span>
                <?php endif; ?>
                <?php if ( $broken_count > 0 ) : ?>
                    <span class="rtg-badge rtg-badge-error"><?php echo esc_html( $broken_count ); ?> broken <?php echo $broken_count === 1 ? 'link' : 'links'; ?></span>
                <?php elseif ( $last_checked ) : ?>
                    <span class="rtg-badge rtg-badge-success">All links healthy</span>
                <?php endif; ?>
                <span class="rtg-toolbar-spacer"></span>
                <span class="rtg-muted rtg-small">A broken link lands on a retailer homepage instead of the product.</span>
            </div>
        </div>
        <div id="rtg-link-check-progress" class="rtg-card-body" style="display:none;">
            <div class="rtg-progress-meta">
                <span id="rtg-link-check-status">Preparing...</span>
                <span id="rtg-link-check-count"></span>
            </div>
            <div class="rtg-progress">
                <div id="rtg-link-check-bar" class="rtg-progress-bar"></div>
            </div>
        </div>
    </div>

    <!-- Filter pills + search -->
    <div class="rtg-pills">
        <?php
        $tabs = array(
            'all'       => array( 'All', $counts['total'] ),
            'affiliate' => array( 'Affiliate', $total_affiliate ),
            'regular'   => array( 'Plain link', $total_regular ),
            'missing'   => array( 'Missing link', $total_missing ),
            'broken'    => array( 'Broken', $broken_count ),
            'delisted'  => array( 'Delisted', $delisted_count ),
            'no_review' => array( 'No review link', $counts['missing_review'] ),
        );
        foreach ( $tabs as $key => $tab ) :
            $url = add_query_arg( array( 'page' => 'rtg-affiliate-links', 'link_filter' => $key ), admin_url( 'admin.php' ) );
            $is_active = $link_filter === $key;
        ?>
            <a href="<?php echo esc_url( $url ); ?>" class="rtg-pill <?php echo $is_active ? 'is-active' : ''; ?>" <?php echo $is_active ? 'aria-current="page"' : ''; ?>>
                <?php echo esc_html( $tab[0] ); ?>
                <span class="rtg-pill-count"><?php echo intval( $tab[1] ); ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <form method="get">
        <input type="hidden" name="page" value="rtg-affiliate-links">
        <input type="hidden" name="link_filter" value="<?php echo esc_attr( $link_filter ); ?>">
        <div class="rtg-toolbar">
            <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Search by brand, model, or tire ID" aria-label="Search tires">
            <button type="submit" class="rtg-btn rtg-btn-secondary">Search</button>
            <?php if ( $search ) : ?>
                <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'rtg-affiliate-links', 'link_filter' => $link_filter ), admin_url( 'admin.php' ) ) ); ?>" class="rtg-btn rtg-btn-ghost">Clear</a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Links Table -->
    <div class="rtg-card">
        <div class="rtg-tablenav rtg-tablenav-top">
            <div class="rtg-pagination">
                <span class="rtg-pagination-count"><?php echo esc_html( count( $tires ) ); ?> tire<?php echo count( $tires ) !== 1 ? 's' : ''; ?> shown</span>
            </div>
        </div>
        <div class="rtg-table-wrapper">
            <table class="rtg-table rtg-affiliate-table">
                <thead>
                    <tr>
                        <th>Tire</th>
                        <th>Status</th>
                        <th>Purchase link</th>
                        <th>Review link</th>
                        <th class="is-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $tires ) ) : ?>
                        <tr>
                            <td colspan="5">
                                <div class="rtg-empty-state">
                                    <span class="dashicons dashicons-admin-links"></span>
                                    <h3>No tires match this filter</h3>
                                    <p>Try a different filter or <a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-affiliate-links' ) ); ?>">view all tires</a>.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $tires as $tire ) :
                            $link_type = $classify_link( $tire['link'] );
                            $badge_class = 'rtg-badge-muted';
                            $badge_label = 'Missing';
                            if ( $link_type === 'affiliate' ) {
                                $badge_class = 'rtg-badge-success';
                                $badge_label = 'Affiliate';
                            } elseif ( $link_type === 'regular' ) {
                                $badge_class = 'rtg-badge-warning';
                                $badge_label = 'Regular';
                            }
                            $is_broken    = isset( $broken_tire_ids[ $tire['tire_id'] ] );
                            $broken_reason = $is_broken ? $broken_tire_ids[ $tire['tire_id'] ] : '';

                            // Skip non-broken tires when "broken" filter is active.
                            if ( $link_filter === 'broken' && ! $is_broken ) {
                                continue;
                            }

                            $tire_presence   = $presence[ $tire['tire_id'] ] ?? array();
                            $presence_status = $tire_presence['status'] ?? RTG_Catalog_Presence::STATUS_UNKNOWN;
                            $is_delisted     = RTG_Catalog_Presence::STATUS_DELISTED === $presence_status;

                            if ( $link_filter === 'delisted' && ! $is_delisted ) {
                                continue;
                            }
                        ?>
                            <tr data-tire-id="<?php echo esc_attr( $tire['tire_id'] ); ?>"<?php echo $is_broken ? ' data-broken="1"' : ''; ?>>
                                <td>
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-tire-edit&tire_id=' . rawurlencode( $tire['tire_id'] ) ) ); ?>" class="rtg-row-title"><?php echo esc_html( $tire['brand'] . ' ' . $tire['model'] ); ?></a>
                                    <span class="rtg-row-meta"><?php echo esc_html( $tire['tire_id'] ); ?> &middot; <?php echo esc_html( $tire['size'] ); ?> &middot; <?php echo esc_html( $tire['category'] ); ?></span>
                                </td>
                                <td>
                                    <div class="rtg-status-stack">
                                        <span class="rtg-badge <?php echo esc_attr( $badge_class ); ?> rtg-link-status-badge"><?php echo esc_html( $badge_label ); ?></span>
                                        <?php if ( $is_broken ) : ?>
                                            <span class="rtg-badge rtg-badge-error is-solid rtg-badge-sm rtg-broken-badge" title="<?php echo esc_attr( $broken_reason ); ?>">Broken</span>
                                        <?php endif; ?>
                                        <?php if ( $is_delisted ) : ?>
                                            <span class="rtg-badge rtg-badge-warning is-solid rtg-badge-sm" title="<?php echo esc_attr( $tire_presence['label'] ?? '' ); ?>">Delisted</span>
                                        <?php elseif ( RTG_Catalog_Presence::STATUS_NEVER_LISTED === $presence_status ) : ?>
                                            <span class="rtg-badge rtg-badge-muted rtg-badge-sm" title="<?php echo esc_attr( $tire_presence['label'] ?? '' ); ?>">Not in catalog</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="rtg-link-cell" data-field="link">
                                    <div class="rtg-link-display">
                                        <?php if ( ! empty( $tire['link'] ) ) : ?>
                                            <a href="<?php echo esc_url( $tire['link'] ); ?>" target="_blank" rel="noopener noreferrer" class="rtg-link-url" title="<?php echo esc_attr( $tire['link'] ); ?>"><?php echo esc_html( rtg_truncate_url( $tire['link'] ) ); ?></a>
                                        <?php else : ?>
                                            <span class="rtg-link-empty">No link set</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="rtg-link-edit" style="display:none;">
                                        <input type="url" class="rtg-link-input" value="<?php echo esc_attr( $tire['link'] ); ?>" placeholder="https://..." aria-label="Purchase link">
                                    </div>
                                </td>
                                <td class="rtg-link-cell" data-field="review_link">
                                    <div class="rtg-link-display">
                                        <?php if ( ! empty( $tire['review_link'] ) ) : ?>
                                            <a href="<?php echo esc_url( $tire['review_link'] ); ?>" target="_blank" rel="noopener noreferrer" class="rtg-link-url" title="<?php echo esc_attr( $tire['review_link'] ); ?>"><?php echo esc_html( rtg_truncate_url( $tire['review_link'] ) ); ?></a>
                                        <?php else : ?>
                                            <span class="rtg-link-empty">No link set</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="rtg-link-edit" style="display:none;">
                                        <input type="url" class="rtg-link-input" value="<?php echo esc_attr( $tire['review_link'] ); ?>" placeholder="https://..." aria-label="Review link">
                                    </div>
                                </td>
                                <td class="is-right">
                                    <div class="rtg-table-actions">
                                        <button type="button" class="rtg-btn rtg-btn-secondary rtg-btn-sm rtg-btn-edit-links" title="Edit links">Edit</button>
                                        <button type="button" class="rtg-btn rtg-btn-primary rtg-btn-sm rtg-btn-save-links" style="display:none;" title="Save links">Save</button>
                                        <button type="button" class="rtg-btn rtg-btn-secondary rtg-btn-sm rtg-btn-cancel-links" style="display:none;" title="Cancel editing">Cancel</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
(function($) {
    'use strict';

    var ajaxUrl = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';
    var nonce   = '<?php echo esc_attr( wp_create_nonce( 'rtg_affiliate_links_nonce' ) ); ?>';

    // Known affiliate domains for client-side classification.
    var affiliateDomains = <?php echo wp_json_encode( $affiliate_domains ); ?>;

    function classifyLink(url) {
        if (!url) return 'missing';
        for (var i = 0; i < affiliateDomains.length; i++) {
            if (url.toLowerCase().indexOf(affiliateDomains[i]) !== -1) {
                return 'affiliate';
            }
        }
        return 'regular';
    }

    function truncateUrl(url) {
        if (!url) return '';
        try {
            var u = new URL(url);
            var display = u.hostname + u.pathname;
            return display.length > 45 ? display.substring(0, 42) + '...' : display;
        } catch (e) {
            return url.length > 45 ? url.substring(0, 42) + '...' : url;
        }
    }

    // Edit row.
    $(document).on('click', '.rtg-btn-edit-links', function() {
        var $row = $(this).closest('tr');
        $row.find('.rtg-link-display').hide();
        $row.find('.rtg-link-edit').show();
        $row.find('.rtg-btn-edit-links').hide();
        $row.find('.rtg-btn-save-links, .rtg-btn-cancel-links').show();
        $row.find('.rtg-link-input').first().focus();
    });

    // Cancel edit.
    $(document).on('click', '.rtg-btn-cancel-links', function() {
        var $row = $(this).closest('tr');
        // Restore original values.
        $row.find('.rtg-link-cell').each(function() {
            var $display = $(this).find('.rtg-link-display');
            var $input   = $(this).find('.rtg-link-input');
            var originalUrl = $display.find('.rtg-link-url').attr('href') || '';
            $input.val(originalUrl);
        });
        $row.find('.rtg-link-display').show();
        $row.find('.rtg-link-edit').hide();
        $row.find('.rtg-btn-edit-links').show();
        $row.find('.rtg-btn-save-links, .rtg-btn-cancel-links').hide();
    });

    // Save links via AJAX.
    $(document).on('click', '.rtg-btn-save-links', function() {
        var $btn = $(this);
        var $row = $btn.closest('tr');
        var tireId = $row.data('tire-id');

        var link       = $row.find('[data-field="link"] .rtg-link-input').val().trim();
        var reviewLink = $row.find('[data-field="review_link"] .rtg-link-input').val().trim();

        $btn.prop('disabled', true).text('Saving...');

        $.post(ajaxUrl, {
            action:      'rtg_update_tire_links',
            nonce:       nonce,
            tire_id:     tireId,
            link:        link,
            bundle_link: '',
            review_link: reviewLink
        }, function(response) {
            $btn.prop('disabled', false).text('Save');

            if (response.success) {
                // Update display values.
                updateLinkCell($row.find('[data-field="link"]'), link);
                updateLinkCell($row.find('[data-field="review_link"]'), reviewLink);

                // Update status badge.
                var linkType = classifyLink(link);
                var $badge = $row.find('.rtg-link-status-badge');
                $badge.removeClass('rtg-badge-success rtg-badge-warning rtg-badge-muted');
                if (linkType === 'affiliate') {
                    $badge.addClass('rtg-badge-success').text('Affiliate');
                } else if (linkType === 'regular') {
                    $badge.addClass('rtg-badge-warning').text('Regular');
                } else {
                    $badge.addClass('rtg-badge-muted').text('Missing');
                }

                // Switch back to display mode.
                $row.find('.rtg-link-display').show();
                $row.find('.rtg-link-edit').hide();
                $row.find('.rtg-btn-edit-links').show();
                $row.find('.rtg-btn-save-links, .rtg-btn-cancel-links').hide();

                // Brief flash to confirm save.
                $row.addClass('is-flash');
                setTimeout(function() { $row.removeClass('is-flash'); }, 800);
            } else {
                alert('Error: ' + (response.data || 'Failed to save links.'));
            }
        }).fail(function() {
            $btn.prop('disabled', false).text('Save');
            alert('Network error. Please try again.');
        });
    });

    function updateLinkCell($cell, url) {
        var $display = $cell.find('.rtg-link-display');
        if (url) {
            $display.html('<a href="' + escHtml(url) + '" target="_blank" rel="noopener noreferrer" class="rtg-link-url" title="' + escHtml(url) + '">' + escHtml(truncateUrl(url)) + '</a>');
        } else {
            $display.html('<span class="rtg-link-empty">No link set</span>');
        }
    }

    function escHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    // Track whether a link check is running and whether the page is unloading.
    var linkCheckRunning = false;
    var isUnloading      = false;

    $(window).on('beforeunload', function() {
        if (linkCheckRunning) {
            isUnloading = true;
            return 'A link check is still running. Are you sure you want to leave?';
        }
    });

    // Check Links Now button — batched with progress bar.
    $(document).on('click', '#rtg-check-links-btn', function() {
        var $btn       = $(this);
        var $progress  = $('#rtg-link-check-progress');
        var $bar       = $('#rtg-link-check-bar');
        var $status    = $('#rtg-link-check-status');
        var $count     = $('#rtg-link-check-count');

        linkCheckRunning = true;
        $btn.prop('disabled', true).text('Checking...');
        $bar.css('width', '0%');
        $status.text('Fetching link list...');
        $count.text('');
        $progress.slideDown(200);

        // Step 1: Get the list of tires to check.
        $.post(ajaxUrl, { action: 'rtg_check_links_start', nonce: nonce }, function(startResp) {
            if (!startResp.success) {
                linkCheckRunning = false;
                $btn.prop('disabled', false).text('Check links now');
                $progress.slideUp(200);
                alert('Error: ' + (startResp.data || 'Could not fetch link list.'));
                return;
            }

            var allTires   = startResp.data.tires;
            var total      = startResp.data.total;
            var batchSize  = startResp.data.batch_size;
            var checked    = 0;
            var allBroken  = [];

            if (total === 0) {
                linkCheckRunning = false;
                $btn.prop('disabled', false).text('Check links now');
                $progress.slideUp(200);
                alert('No tires with purchase links to check.');
                return;
            }

            // Step 2: Process batches sequentially.
            function nextBatch(offset) {
                if (isUnloading) { return; }

                if (offset >= total) {
                    // Step 3: Finalize — save results and send notification.
                    $status.text('Saving results...');
                    $bar.css('width', '100%');

                    $.post(ajaxUrl, {
                        action: 'rtg_check_links_finish',
                        nonce:  nonce,
                        total:  checked,
                        broken: allBroken
                    }, function() {
                        linkCheckRunning = false;
                        $btn.prop('disabled', false).text('Check links now');
                        var brokenCount = allBroken.length;
                        if (brokenCount > 0) {
                            $status.html('<span class="rtg-text-error rtg-strong">Done: ' + brokenCount + ' broken link' + (brokenCount !== 1 ? 's' : '') + ' found</span>');
                        } else {
                            $status.html('<span class="rtg-text-success rtg-strong">Done: all ' + checked + ' links healthy</span>');
                        }
                        setTimeout(function() { location.reload(); }, 1500);
                    }).fail(function() {
                        if (isUnloading) { return; }
                        linkCheckRunning = false;
                        $btn.prop('disabled', false).text('Check links now');
                        $status.text('Error saving results.');
                    });
                    return;
                }

                var batch = allTires.slice(offset, offset + batchSize);
                var pct   = Math.round((offset / total) * 100);
                $bar.css('width', pct + '%');
                $status.text('Checking link ' + (offset + 1) + ' of ' + total + '...');
                $count.text(allBroken.length + ' broken so far');

                $.post(ajaxUrl, {
                    action: 'rtg_check_links_batch',
                    nonce:  nonce,
                    tires:  batch
                }, function(batchResp) {
                    if (batchResp.success) {
                        checked += batchResp.data.checked;
                        if (batchResp.data.broken.length) {
                            allBroken = allBroken.concat(batchResp.data.broken);
                            $count.text(allBroken.length + ' broken so far');
                        }
                    }
                    nextBatch(offset + batchSize);
                }).fail(function() {
                    if (isUnloading) { return; }
                    // Skip failed batch and continue.
                    nextBatch(offset + batchSize);
                });
            }

            nextBatch(0);
        }).fail(function() {
            if (isUnloading) { return; }
            linkCheckRunning = false;
            $btn.prop('disabled', false).text('Check links now');
            $progress.slideUp(200);
            alert('Network error. Please try again.');
        });
    });

    // Dismiss notices.
    $(document).on('click', '.rtg-notice-dismiss', function() {
        $(this).closest('.rtg-notice').fadeOut(200, function() { $(this).remove(); });
    });

})(jQuery);
</script>

