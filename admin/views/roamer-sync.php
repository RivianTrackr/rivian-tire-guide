<?php
/**
 * Roamer Data: real-world efficiency from Rivian Roamer, matched to the guide.
 *
 * Status and the work that needs a person (ambiguous and unmatched tires)
 * come first; the settings and the long reference lists are collapsed
 * below them.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$stats   = RTG_Roamer_Sync::get_stats();
$mapping = RTG_Roamer_Sync::get_mapping_status();
$settings = get_option( 'rtg_settings', array() );

$sync_enabled = $settings['roamer_sync_enabled'] ?? true;
$sync_url     = $settings['roamer_sync_url'] ?? RTG_Roamer_Sync::DEFAULT_URL;

$linked_count = count( $mapping['matched'] );
$total_guide  = $linked_count + count( $mapping['unlinked'] );
$coverage_pct = $total_guide > 0 ? round( $linked_count / $total_guide * 100 ) : 0;

$ambiguous_list = ( $stats && ! empty( $stats['ambiguous_list'] ) ) ? $stats['ambiguous_list'] : array();
$unmatched_list = ( $stats && ! empty( $stats['unmatched_list'] ) ) ? $stats['unmatched_list'] : array();
$hidden_ids     = get_option( RTG_Roamer_Sync::HIDDEN_OPTION, array() );

$sync_time = $stats['time'] ?? '';
$sync_rel  = $sync_time ? human_time_diff( strtotime( $sync_time ), current_time( 'timestamp' ) ) . ' ago' : '';
?>
<div class="rtg-wrap">

    <?php // Saved through RTG_Admin::handle_roamer_settings_save() (post-redirect-get). ?>
    <?php if ( isset( $_GET['message'] ) && 'settings_saved' === $_GET['message'] ) : ?>
        <div class="rtg-notice rtg-notice-success">
            <span>Roamer settings saved.</span>
            <button type="button" class="rtg-notice-dismiss" aria-label="Dismiss">&times;</button>
        </div>
    <?php endif; ?>

    <div class="rtg-page-header">
        <div class="rtg-page-heading">
            <h1 class="rtg-page-title">Roamer Data</h1>
            <p class="rtg-page-subtitle">
                Real-world miles per kWh from <a href="https://rivianroamer.com" target="_blank" rel="noopener">Rivian Roamer</a>, matched to the guide's tires and shown on their cards. The sync runs every five minutes and only writes when a number moves.
            </p>
        </div>
        <div class="rtg-page-actions">
            <button type="button" id="rtg-roamer-sync-btn" class="rtg-btn rtg-btn-primary">
                <span class="dashicons dashicons-update"></span> Sync now
            </button>
        </div>
    </div>

    <!-- Sync status -->
    <div class="rtg-card">
        <div class="rtg-card-header is-split">
            <div><h2>Sync status</h2></div>
            <?php if ( $stats && isset( $stats['status'] ) ) : ?>
                <?php if ( 'success' === $stats['status'] ) : ?>
                    <span class="rtg-badge rtg-badge-success">Last sync succeeded</span>
                <?php else : ?>
                    <span class="rtg-badge rtg-badge-error">Last sync failed</span>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <div class="rtg-card-body">
            <div id="rtg-roamer-sync-status">
                <?php if ( $stats && isset( $stats['status'] ) ) : ?>
                    <div class="rtg-kv-grid">
                        <div>
                            <span class="rtg-kv-label">Last sync</span>
                            <div class="rtg-kv-value" title="<?php echo esc_attr( $sync_time ); ?>"><?php echo $sync_rel ? esc_html( $sync_rel ) : 'N/A'; ?></div>
                        </div>
                        <?php if ( 'success' === $stats['status'] ) : ?>
                            <div>
                                <span class="rtg-kv-label">Coverage</span>
                                <div class="rtg-kv-value is-info"><?php echo esc_html( $linked_count . ' / ' . $total_guide ); ?></div>
                                <div class="rtg-kv-note"><?php echo esc_html( $coverage_pct ); ?>% of the guide has data</div>
                            </div>
                            <div>
                                <span class="rtg-kv-label">Matched</span>
                                <div class="rtg-kv-value is-success"><?php echo intval( $stats['matched'] ); ?></div>
                            </div>
                            <div>
                                <span class="rtg-kv-label">Ambiguous</span>
                                <div class="rtg-kv-value <?php echo intval( $stats['skipped'] ) > 0 ? 'is-warning' : 'is-muted'; ?>"><?php echo intval( $stats['skipped'] ); ?></div>
                            </div>
                            <div>
                                <span class="rtg-kv-label">Unmatched</span>
                                <div class="rtg-kv-value is-muted"><?php echo intval( $stats['unmatched'] ); ?></div>
                            </div>
                            <div>
                                <span class="rtg-kv-label">Roamer tires</span>
                                <div class="rtg-kv-value"><?php echo intval( $stats['total_roamer'] ); ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if ( 'success' !== $stats['status'] && ! empty( $stats['message'] ) ) : ?>
                        <div class="rtg-notice rtg-notice-error is-after">
                            <span><?php echo esc_html( $stats['message'] ); ?></span>
                        </div>
                    <?php endif; ?>
                <?php else : ?>
                    <p class="rtg-empty-line">No sync has run yet. Use <strong>Sync now</strong> to fetch data from Rivian Roamer.</p>
                <?php endif; ?>
            </div>
            <div id="rtg-roamer-sync-spinner" class="rtg-spinner-row" style="display:none;">
                <span class="spinner is-active"></span>
                Syncing with Rivian Roamer...
            </div>
        </div>
    </div>

    <!-- Ambiguous matches (from last sync) -->
    <?php if ( ! empty( $ambiguous_list ) ) : ?>
        <div class="rtg-card">
            <div class="rtg-card-header">
                <h2>Ambiguous matches <span class="rtg-count"><?php echo count( $ambiguous_list ); ?></span></h2>
                <p>
                    These Roamer tires match more than one guide entry, usually the same tire in two load ratings.
                    Pick which one gets the data. Choosing one marked <em>already linked</em> merges this ID into its existing Roamer IDs.
                </p>
            </div>
            <div class="rtg-card-body is-flush">
                <div class="rtg-table-wrapper">
                    <table class="rtg-table">
                        <thead>
                            <tr>
                                <th>Roamer tire</th>
                                <th>Size</th>
                                <th>mi/kWh</th>
                                <th>Distance</th>
                                <th>Assign to</th>
                                <th class="is-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $ambiguous_list as $amb ) :
                                // Look up candidate tires for display. Candidates
                                // already carrying Roamer data stay selectable —
                                // assigning merges this ID into their existing
                                // links — and are labelled so the choice is clear.
                                $candidates_info = array();
                                foreach ( $amb['candidates'] as $cid ) {
                                    $ct = RTG_Database::get_tire( $cid );
                                    if ( $ct ) {
                                        $candidates_info[] = $ct;
                                    }
                                }
                            ?>
                                <tr>
                                    <td><strong><?php echo esc_html( $amb['name'] ); ?></strong></td>
                                    <td class="rtg-mono"><?php echo esc_html( $amb['size'] ); ?></td>
                                    <td><strong><?php echo esc_html( number_format( $amb['efficiency'], 2 ) ); ?></strong></td>
                                    <td><?php echo esc_html( number_format( floatval( $amb['total_km'] ?? 0 ) * 0.621371, 0 ) ); ?> mi</td>
                                    <?php if ( empty( $candidates_info ) ) : ?>
                                    <td colspan="2" class="rtg-muted rtg-small">No candidate tires found in the guide.</td>
                                    <?php else : ?>
                                    <td>
                                        <select class="rtg-roamer-assign-select" data-roamer-id="<?php echo esc_attr( $amb['roamer_tire_id'] ); ?>" aria-label="Assign to tire">
                                            <option value="">Select tire...</option>
                                            <?php
                                            foreach ( $candidates_info as $ct ) :
                                                $c_label = $ct['brand'] . ' ' . $ct['model'] . ' — ' . $ct['size'] . ' (Load: ' . ( $ct['load_range'] ?: 'N/A' ) . ')';

                                                if ( ! empty( $ct['roamer_tire_id'] ) ) {
                                                    $c_linked = count( array_filter( array_map( 'trim', explode( ',', $ct['roamer_tire_id'] ) ) ) );
                                                    $c_label .= ' — already linked (' . $c_linked . ')';
                                                }
                                                ?>
                                                <option value="<?php echo esc_attr( $ct['tire_id'] ); ?>">
                                                    <?php echo esc_html( $c_label ); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td class="is-right">
                                        <button type="button" class="rtg-btn rtg-btn-primary rtg-btn-sm rtg-roamer-assign-btn" data-roamer-id="<?php echo esc_attr( $amb['roamer_tire_id'] ); ?>" disabled>Assign</button>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Unmatched Roamer tires (from last sync) -->
    <?php if ( ! empty( $unmatched_list ) ) :
        // Sort unmatched by total distance descending so most impactful are first.
        $unmatched_sorted = $unmatched_list;
        usort( $unmatched_sorted, function ( $a, $b ) {
            return ( floatval( $b['total_km'] ?? 0 ) <=> floatval( $a['total_km'] ?? 0 ) );
        } );

        // Every guide tire is assignable. Tires already carrying Roamer data
        // are labelled rather than hidden — assigning to one merges the
        // selected IDs into its existing links, which is how a duplicate
        // Roamer entry for an already-matched tire gets folded in.
        $all_tires = RTG_Database::get_all_tires();
        usort( $all_tires, function ( $a, $b ) {
            $cmp = strcasecmp( $a['brand'], $b['brand'] );
            return $cmp !== 0 ? $cmp : strcasecmp( $a['model'], $b['model'] );
        } );
    ?>
        <div class="rtg-card">
            <div class="rtg-card-header is-split">
                <div>
                    <h2>Unmatched Roamer tires <span class="rtg-count"><?php echo count( $unmatched_list ); ?></span></h2>
                    <p>
                        On Rivian Roamer but not in the guide, most-driven first. Select one or more and assign them to a guide tire.
                        With several selected, efficiency is averaged by distance. Assigning to a tire marked <em>already linked</em> merges the selection into its existing IDs, for when Roamer lists the same tire twice.
                    </p>
                </div>
                <div id="rtg-unmatched-assign-bar" class="rtg-card-header-actions" style="display:none;">
                    <span id="rtg-unmatched-selected-count" class="rtg-muted rtg-small">0 selected</span>
                    <select id="rtg-unmatched-assign-tire" class="rtg-input-medium" aria-label="Assign selected to">
                        <option value="">Assign selected to...</option>
                        <?php
                        foreach ( $all_tires as $t ) :
                            $label = $t['brand'] . ' ' . $t['model'] . ' — ' . $t['size'] . ( $t['load_range'] ? ' (' . $t['load_range'] . ')' : '' );

                            if ( ! empty( $t['roamer_tire_id'] ) ) {
                                $linked_n = count( array_filter( array_map( 'trim', explode( ',', $t['roamer_tire_id'] ) ) ) );
                                $label   .= ' — already linked (' . $linked_n . ')';
                            }
                            ?>
                            <option value="<?php echo esc_attr( $t['tire_id'] ); ?>">
                                <?php echo esc_html( $label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" id="rtg-unmatched-assign-btn" class="rtg-btn rtg-btn-primary rtg-btn-sm" disabled>Assign</button>
                    <button type="button" id="rtg-unmatched-hide-btn" class="rtg-btn rtg-btn-danger-quiet rtg-btn-sm" title="Hide the selected tires from this list for good">Hide</button>
                </div>
            </div>
            <div class="rtg-card-body is-flush">
                <div class="rtg-table-wrapper">
                    <table class="rtg-table">
                        <thead>
                            <tr>
                                <th class="column-cb"><input type="checkbox" id="rtg-unmatched-select-all" aria-label="Select all"></th>
                                <th>Tire</th>
                                <th>Size</th>
                                <th>mi/kWh</th>
                                <th>Distance</th>
                                <th>Roamer ID</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $unmatched_sorted as $tire ) : ?>
                                <tr>
                                    <td class="column-cb"><input type="checkbox" class="rtg-unmatched-cb" value="<?php echo esc_attr( $tire['roamer_tire_id'] ); ?>" data-name="<?php echo esc_attr( $tire['name'] ); ?>"></td>
                                    <td><strong><?php echo esc_html( $tire['name'] ); ?></strong></td>
                                    <td class="rtg-mono"><?php echo esc_html( $tire['size'] ); ?></td>
                                    <td><?php echo esc_html( number_format( $tire['efficiency'], 2 ) ); ?></td>
                                    <td><?php echo esc_html( number_format( floatval( $tire['total_km'] ?? 0 ) * 0.621371, 0 ) ); ?> mi</td>
                                    <td><code><?php echo esc_html( $tire['roamer_tire_id'] ); ?></code></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Settings -->
    <div class="rtg-card">
        <div class="rtg-card-header">
            <h2>Sync settings</h2>
        </div>
        <div class="rtg-card-body">
            <form method="post">
                <?php wp_nonce_field( 'rtg_roamer_settings', 'rtg_roamer_settings_nonce' ); ?>
                <div class="rtg-field-row">
                    <div class="rtg-field-label-row">
                        <label class="rtg-field-label" for="roamer_sync_enabled">Automatic sync</label>
                    </div>
                    <label class="rtg-toggle is-small">
                        <input type="checkbox" name="roamer_sync_enabled" id="roamer_sync_enabled" value="1" <?php checked( $sync_enabled ); ?>>
                        <span class="rtg-toggle-track"></span>
                        <span class="rtg-toggle-label">Sync efficiency data every five minutes</span>
                    </label>
                </div>
                <div class="rtg-field-row">
                    <div class="rtg-field-label-row">
                        <label class="rtg-field-label" for="roamer_notify_enabled">Email notifications</label>
                    </div>
                    <label class="rtg-toggle is-small">
                        <input type="checkbox" name="roamer_notify_enabled" id="roamer_notify_enabled" value="1" <?php checked( $settings['roamer_notify_enabled'] ?? true ); ?>>
                        <span class="rtg-toggle-track"></span>
                        <span class="rtg-toggle-label">Email me when new ambiguous or unmatched tires appear</span>
                    </label>
                </div>
                <div class="rtg-field-row">
                    <div class="rtg-field-label-row">
                        <label class="rtg-field-label" for="roamer_sync_url">Feed URL</label>
                    </div>
                    <input type="url" name="roamer_sync_url" id="roamer_sync_url" value="<?php echo esc_attr( $sync_url ); ?>" class="rtg-input-wide is-code">
                </div>
                <div class="rtg-footer-actions">
                    <button type="submit" name="rtg_roamer_settings_save" value="1" class="rtg-btn rtg-btn-primary">Save settings</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Linked tires (collapsed by default) -->
    <div class="rtg-card">
        <div class="rtg-card-header is-split rtg-card-toggle" id="rtg-linked-toggle" data-rtg-collapse="#rtg-linked-section" aria-expanded="false">
            <div><h2>Linked tires <span class="rtg-count"><?php echo $linked_count; ?></span></h2></div>
            <span class="dashicons dashicons-arrow-down-alt2 rtg-card-chevron" id="rtg-linked-arrow"></span>
        </div>
        <div id="rtg-linked-section" style="display:none;">
            <div class="rtg-card-body is-flush">
                <?php if ( ! empty( $mapping['matched'] ) ) : ?>
                    <div class="rtg-table-wrapper">
                        <table class="rtg-table rtg-table-compact">
                            <thead>
                                <tr>
                                    <th>Tire</th>
                                    <th>Size</th>
                                    <th>Load range</th>
                                    <th>Roamer ID</th>
                                    <th>mi/kWh</th>
                                    <th>Distance</th>
                                    <th>Vehicles</th>
                                    <th title="When this tire's Roamer data last changed. The sync runs every five minutes but only writes when the feed's numbers move.">Data updated</th>
                                    <th class="is-right">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $mapping['matched'] as $tire ) :
                                    $synced_rel = $tire['roamer_synced_at'] ? human_time_diff( strtotime( $tire['roamer_synced_at'] ), current_time( 'timestamp' ) ) . ' ago' : 'N/A';
                                ?>
                                    <tr>
                                        <td><a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-tire-edit&tire_id=' . rawurlencode( $tire['tire_id'] ) ) ); ?>" class="rtg-row-title"><?php echo esc_html( $tire['brand'] . ' ' . $tire['model'] ); ?></a></td>
                                        <td class="rtg-mono"><?php echo esc_html( $tire['size'] ); ?></td>
                                        <td><?php echo esc_html( $tire['load_range'] ?: '-' ); ?></td>
                                        <td><code><?php echo esc_html( $tire['roamer_tire_id'] ); ?></code></td>
                                        <td><strong><?php echo esc_html( number_format( $tire['roamer_efficiency'], 2 ) ); ?></strong></td>
                                        <td><?php echo esc_html( number_format( floatval( $tire['roamer_total_km'] ?? 0 ) * 0.621371, 0 ) ); ?> mi</td>
                                        <td><?php echo intval( $tire['roamer_vehicle_count'] ); ?></td>
                                        <td title="<?php echo esc_attr( $tire['roamer_synced_at'] ?: '' ); ?>"><?php echo esc_html( $synced_rel ); ?></td>
                                        <td class="is-right">
                                            <button type="button" class="rtg-btn rtg-btn-secondary rtg-btn-sm rtg-roamer-unlink" data-tire-id="<?php echo esc_attr( $tire['tire_id'] ); ?>">Unlink</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else : ?>
                    <p class="rtg-empty-line is-padded">No tires are linked to Roamer data yet. Run a sync to match them automatically.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Unlinked guide tires (collapsed by default) -->
    <?php
    $unlinked_total    = count( $mapping['unlinked'] );
    $unlinked_per_page = 20;
    $unlinked_page     = isset( $_GET['unlinked_page'] ) ? max( 1, intval( $_GET['unlinked_page'] ) ) : 1;
    $unlinked_pages    = max( 1, ceil( $unlinked_total / $unlinked_per_page ) );
    $unlinked_offset   = ( $unlinked_page - 1 ) * $unlinked_per_page;
    $unlinked_slice    = array_slice( $mapping['unlinked'], $unlinked_offset, $unlinked_per_page );
    $unlinked_open     = isset( $_GET['unlinked_page'] );
    ?>
    <div class="rtg-card" id="unlinked-tires">
        <div class="rtg-card-header is-split rtg-card-toggle" id="rtg-unlinked-toggle" data-rtg-collapse="#rtg-unlinked-section" aria-expanded="<?php echo $unlinked_open ? 'true' : 'false'; ?>">
            <div>
                <h2>Guide tires without Roamer data <span class="rtg-count"><?php echo $unlinked_total; ?></span></h2>
            </div>
            <span class="dashicons dashicons-arrow-down-alt2 rtg-card-chevron" id="rtg-unlinked-arrow"></span>
        </div>
        <div id="rtg-unlinked-section" style="display:<?php echo $unlinked_open ? 'block' : 'none'; ?>;">
            <div class="rtg-card-body is-flush">
                <?php if ( $unlinked_total > 0 ) : ?>
                    <p class="rtg-table-note">
                        Roamer may simply have no data for these. A Roamer ID can also be set by hand on the tire's edit page.
                    </p>
                    <div class="rtg-table-wrapper">
                        <table class="rtg-table rtg-table-compact">
                            <thead>
                                <tr>
                                    <th>Tire</th>
                                    <th>Size</th>
                                    <th>Load range</th>
                                    <th>ID</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $unlinked_slice as $tire ) : ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-tire-edit&tire_id=' . rawurlencode( $tire['tire_id'] ) ) ); ?>" class="rtg-row-title"><?php echo esc_html( $tire['brand'] . ' ' . $tire['model'] ); ?></a>
                                        </td>
                                        <td class="rtg-mono"><?php echo esc_html( $tire['size'] ); ?></td>
                                        <td><?php echo esc_html( $tire['load_range'] ?: '-' ); ?></td>
                                        <td><code><?php echo esc_html( $tire['tire_id'] ); ?></code></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ( $unlinked_pages > 1 ) : ?>
                        <div class="rtg-tablenav rtg-tablenav-bottom">
                            <span class="rtg-pagination-count">
                                Showing <?php echo $unlinked_offset + 1; ?>&ndash;<?php echo min( $unlinked_offset + $unlinked_per_page, $unlinked_total ); ?> of <?php echo $unlinked_total; ?>
                            </span>
                            <div class="rtg-pagination">
                                <span class="rtg-pagination-links">
                                    <?php for ( $p = 1; $p <= $unlinked_pages; $p++ ) :
                                        $url = add_query_arg( 'unlinked_page', $p, admin_url( 'admin.php?page=rtg-roamer-sync' ) ) . '#unlinked-tires';
                                    ?>
                                        <?php if ( $p === $unlinked_page ) : ?>
                                            <span class="is-current"><?php echo $p; ?></span>
                                        <?php else : ?>
                                            <a href="<?php echo esc_url( $url ); ?>"><?php echo $p; ?></a>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                </span>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php else : ?>
                    <p class="rtg-empty-line rtg-text-success is-padded">Every tire in the guide has Roamer data linked.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Hidden Roamer tires (collapsed by default) -->
    <?php if ( ! empty( $hidden_ids ) ) : ?>
        <div class="rtg-card">
            <div class="rtg-card-header is-split rtg-card-toggle" id="rtg-hidden-toggle" data-rtg-collapse="#rtg-hidden-section" aria-expanded="false">
                <div><h2>Hidden Roamer tires <span class="rtg-count"><?php echo count( $hidden_ids ); ?></span></h2></div>
                <span class="dashicons dashicons-arrow-down-alt2 rtg-card-chevron" id="rtg-hidden-arrow"></span>
            </div>
            <div id="rtg-hidden-section" style="display:none;">
                <div class="rtg-card-body is-flush">
                    <p class="rtg-table-note">
                        Roamer IDs hidden from the unmatched list and skipped by future syncs. Select one or more and restore them to bring them back.
                    </p>
                    <div id="rtg-hidden-restore-bar" class="rtg-toolbar is-inset" style="display:none;">
                        <span id="rtg-hidden-selected-count" class="rtg-muted rtg-small">0 selected</span>
                        <button type="button" id="rtg-hidden-restore-btn" class="rtg-btn rtg-btn-secondary rtg-btn-sm">Restore</button>
                    </div>
                    <div class="rtg-table-wrapper">
                        <table class="rtg-table rtg-table-compact">
                            <thead>
                                <tr>
                                    <th class="column-cb"><input type="checkbox" id="rtg-hidden-select-all" aria-label="Select all"></th>
                                    <th>Roamer ID</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $hidden_ids as $hid ) : ?>
                                    <tr>
                                        <td class="column-cb"><input type="checkbox" class="rtg-hidden-cb" value="<?php echo esc_attr( $hid ); ?>"></td>
                                        <td><code><?php echo esc_html( $hid ); ?></code></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
