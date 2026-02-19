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

// Known affiliate domains (used for classifying links).
$affiliate_domains = array(
    'tkqlhce.com', 'commission-junction.com', 'cj.com',
    'linksynergy.com', 'click.linksynergy.com', 'shareasale.com',
    'avantlink.com', 'impact.com', 'partnerize.com',
    'tirerackaffiliates.com', 'walmart-affiliates.com',
    'costco-affiliates.com', 'walmart-redirect.com',
    'ebay-redirect.com', 'simplifytires.com',
);

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
        <h1 class="rtg-page-title">Affiliate Links</h1>
    </div>

    <!-- Stats Grid -->
    <div class="rtg-stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));">
        <div class="rtg-stat-card">
            <div class="rtg-stat-value"><?php echo esc_html( $counts['total'] ); ?></div>
            <div class="rtg-stat-label">Total Tires</div>
        </div>
        <div class="rtg-stat-card">
            <div class="rtg-stat-value" style="color: var(--rtg-success);"><?php echo esc_html( $total_affiliate ); ?></div>
            <div class="rtg-stat-label">Affiliate Links</div>
        </div>
        <div class="rtg-stat-card">
            <div class="rtg-stat-value" style="color: var(--rtg-warning-text);"><?php echo esc_html( $total_regular ); ?></div>
            <div class="rtg-stat-label">Regular Links</div>
        </div>
        <div class="rtg-stat-card">
            <div class="rtg-stat-value" style="color: var(--rtg-error);"><?php echo esc_html( $total_missing ); ?></div>
            <div class="rtg-stat-label">Missing Links</div>
        </div>
        <div class="rtg-stat-card">
            <div class="rtg-stat-value"><?php echo esc_html( $counts['has_bundle'] ); ?></div>
            <div class="rtg-stat-label">Bundle Links</div>
        </div>
        <div class="rtg-stat-card">
            <div class="rtg-stat-value"><?php echo esc_html( $counts['has_review'] ); ?></div>
            <div class="rtg-stat-label">Review Links</div>
        </div>
    </div>

    <!-- Filter Tabs + Search -->
    <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:20px;">
        <?php
        $tabs = array(
            'all'       => 'All (' . $counts['total'] . ')',
            'affiliate' => 'Affiliate (' . $total_affiliate . ')',
            'regular'   => 'Regular (' . $total_regular . ')',
            'missing'   => 'Missing Link (' . $total_missing . ')',
            'no_bundle' => 'No Bundle (' . $counts['missing_bundle'] . ')',
            'no_review' => 'No Review (' . $counts['missing_review'] . ')',
        );
        foreach ( $tabs as $key => $label ) :
            $url   = add_query_arg( array( 'page' => 'rtg-affiliate-links', 'link_filter' => $key ), admin_url( 'admin.php' ) );
            $class = $link_filter === $key ? 'rtg-btn rtg-btn-primary' : 'rtg-btn rtg-btn-secondary';
        ?>
            <a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $class ); ?>" style="text-decoration:none;"><?php echo esc_html( $label ); ?></a>
        <?php endforeach; ?>
    </div>

    <form method="get" style="margin-bottom:20px;">
        <input type="hidden" name="page" value="rtg-affiliate-links">
        <input type="hidden" name="link_filter" value="<?php echo esc_attr( $link_filter ); ?>">
        <div class="rtg-search-box" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
            <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Search by brand, model, or tire ID..." style="min-width:280px;">
            <button type="submit" class="rtg-btn rtg-btn-secondary">Search</button>
            <?php if ( $search ) : ?>
                <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'rtg-affiliate-links', 'link_filter' => $link_filter ), admin_url( 'admin.php' ) ) ); ?>" class="rtg-btn rtg-btn-secondary" style="text-decoration:none;">Clear</a>
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
                        <th style="width:180px;">Tire</th>
                        <th style="width:90px;">Status</th>
                        <th>Purchase Link</th>
                        <th>Bundle Link</th>
                        <th>Review Link</th>
                        <th style="width:100px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $tires ) ) : ?>
                        <tr>
                            <td colspan="6">
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
                        ?>
                            <tr data-tire-id="<?php echo esc_attr( $tire['tire_id'] ); ?>">
                                <td>
                                    <strong><?php echo esc_html( $tire['brand'] . ' ' . $tire['model'] ); ?></strong>
                                    <div style="font-size:12px;color:var(--rtg-text-muted);"><?php echo esc_html( $tire['tire_id'] ); ?> &middot; <?php echo esc_html( $tire['size'] ); ?> &middot; <?php echo esc_html( $tire['category'] ); ?></div>
                                </td>
                                <td>
                                    <span class="rtg-badge <?php echo esc_attr( $badge_class ); ?> rtg-link-status-badge"><?php echo esc_html( $badge_label ); ?></span>
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
                                        <input type="url" class="rtg-link-input" value="<?php echo esc_attr( $tire['link'] ); ?>" placeholder="https://..." style="max-width:100%;width:100%;">
                                    </div>
                                </td>
                                <td class="rtg-link-cell" data-field="bundle_link">
                                    <div class="rtg-link-display">
                                        <?php if ( ! empty( $tire['bundle_link'] ) ) : ?>
                                            <a href="<?php echo esc_url( $tire['bundle_link'] ); ?>" target="_blank" rel="noopener noreferrer" class="rtg-link-url" title="<?php echo esc_attr( $tire['bundle_link'] ); ?>"><?php echo esc_html( rtg_truncate_url( $tire['bundle_link'] ) ); ?></a>
                                        <?php else : ?>
                                            <span class="rtg-link-empty">No link set</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="rtg-link-edit" style="display:none;">
                                        <input type="url" class="rtg-link-input" value="<?php echo esc_attr( $tire['bundle_link'] ); ?>" placeholder="https://..." style="max-width:100%;width:100%;">
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
                                        <input type="url" class="rtg-link-input" value="<?php echo esc_attr( $tire['review_link'] ); ?>" placeholder="https://..." style="max-width:100%;width:100%;">
                                    </div>
                                </td>
                                <td>
                                    <button type="button" class="rtg-btn rtg-btn-secondary rtg-btn-edit-links" title="Edit links">Edit</button>
                                    <button type="button" class="rtg-btn rtg-btn-primary rtg-btn-save-links" style="display:none;" title="Save links">Save</button>
                                    <button type="button" class="rtg-btn rtg-btn-secondary rtg-btn-cancel-links" style="display:none;" title="Cancel editing">Cancel</button>
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
        var bundleLink = $row.find('[data-field="bundle_link"] .rtg-link-input').val().trim();
        var reviewLink = $row.find('[data-field="review_link"] .rtg-link-input').val().trim();

        $btn.prop('disabled', true).text('Saving...');

        $.post(ajaxUrl, {
            action:      'rtg_update_tire_links',
            nonce:       nonce,
            tire_id:     tireId,
            link:        link,
            bundle_link: bundleLink,
            review_link: reviewLink
        }, function(response) {
            $btn.prop('disabled', false).text('Save');

            if (response.success) {
                // Update display values.
                updateLinkCell($row.find('[data-field="link"]'), link);
                updateLinkCell($row.find('[data-field="bundle_link"]'), bundleLink);
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
                $row.css('background', 'var(--rtg-success-light)');
                setTimeout(function() { $row.css('background', ''); }, 800);
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

    // Dismiss notices.
    $(document).on('click', '.rtg-notice-dismiss', function() {
        $(this).closest('.rtg-notice').fadeOut(200, function() { $(this).remove(); });
    });

})(jQuery);
</script>

