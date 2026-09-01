<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$stats   = RTG_Roamer_Sync::get_stats();
$mapping = RTG_Roamer_Sync::get_mapping_status();
$settings = get_option( 'rtg_settings', array() );

$sync_enabled = $settings['roamer_sync_enabled'] ?? true;
$sync_url     = $settings['roamer_sync_url'] ?? RTG_Roamer_Sync::DEFAULT_URL;

// Saved through RTG_Admin::handle_roamer_settings_save() (post-redirect-get),
// so a refresh of this page can't re-submit the form.
if ( isset( $_GET['message'] ) && 'settings_saved' === $_GET['message'] ) {
    echo '<div class="notice notice-success is-dismissible"><p>Roamer sync settings saved.</p></div>';
}
?>
<div class="wrap rtg-admin-wrap">
    <h1 class="wp-heading-inline">Roamer Sync — Real-World Efficiency Data</h1>
    <p class="rtg-page-description" style="margin-top:8px;color:#86868b;">
        Sync live tire efficiency data from <a href="https://rivianroamer.com" target="_blank" rel="noopener">Rivian Roamer</a>
        to show real-world mi/kWh alongside calculated efficiency scores.
    </p>

    <!-- Settings -->
    <div class="rtg-card" style="margin-top:20px;">
        <div class="rtg-card-header">
            <h2>Sync Settings</h2>
        </div>
        <div class="rtg-card-body">
            <form method="post">
                <?php wp_nonce_field( 'rtg_roamer_settings', 'rtg_roamer_settings_nonce' ); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="roamer_sync_enabled">Enable Sync</label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="roamer_sync_enabled" id="roamer_sync_enabled" value="1" <?php checked( $sync_enabled ); ?>>
                                Automatically sync efficiency data every 5 minutes
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="roamer_notify_enabled">Email Notifications</label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="roamer_notify_enabled" id="roamer_notify_enabled" value="1" <?php checked( $settings['roamer_notify_enabled'] ?? true ); ?>>
                                Email me when new ambiguous or unmatched tires are detected
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="roamer_sync_url">Feed URL</label></th>
                        <td>
                            <input type="url" name="roamer_sync_url" id="roamer_sync_url" value="<?php echo esc_attr( $sync_url ); ?>" class="regular-text" style="width:100%;max-width:600px;">
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" name="rtg_roamer_settings_save" class="button button-primary" value="Save Settings">
                </p>
            </form>
        </div>
    </div>

    <!-- Sync Status -->
    <div class="rtg-card" style="margin-top:20px;">
        <div class="rtg-card-header" style="display:flex;align-items:center;justify-content:space-between;">
            <h2>Sync Status</h2>
            <button type="button" id="rtg-roamer-sync-btn" class="button button-primary">Sync Now</button>
        </div>
        <div class="rtg-card-body">
            <div id="rtg-roamer-sync-status">
                <?php if ( $stats && isset( $stats['status'] ) ) : ?>
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px;margin-bottom:16px;">
                        <div>
                            <strong style="color:#86868b;font-size:12px;text-transform:uppercase;">Status</strong><br>
                            <span style="font-size:18px;font-weight:600;color:<?php echo $stats['status'] === 'success' ? '#5ec095' : '#ef4444'; ?>;">
                                <?php echo esc_html( ucfirst( $stats['status'] ) ); ?>
                            </span>
                        </div>
                        <div>
                            <strong style="color:#86868b;font-size:12px;text-transform:uppercase;">Last Sync</strong><br>
                            <?php
                            $sync_time = $stats['time'] ?? '';
                            if ( $sync_time ) {
                                $relative = human_time_diff( strtotime( $sync_time ), current_time( 'timestamp' ) ) . ' ago';
                            } else {
                                $relative = 'N/A';
                            }
                            ?>
                            <span style="font-size:18px;font-weight:600;" title="<?php echo esc_attr( $sync_time ); ?>"><?php echo esc_html( $relative ); ?></span>
                        </div>
                        <?php if ( $stats['status'] === 'success' ) :
                            $linked_count = count( $mapping['matched'] );
                            $total_guide  = $linked_count + count( $mapping['unlinked'] );
                            $coverage_pct = $total_guide > 0 ? round( $linked_count / $total_guide * 100 ) : 0;
                        ?>
                            <div>
                                <strong style="color:#86868b;font-size:12px;text-transform:uppercase;">Coverage</strong><br>
                                <span style="font-size:18px;font-weight:600;color:#3b82f6;"><?php echo $linked_count . '/' . $total_guide; ?> (<?php echo $coverage_pct; ?>%)</span>
                            </div>
                            <div>
                                <strong style="color:#86868b;font-size:12px;text-transform:uppercase;">Matched</strong><br>
                                <span style="font-size:18px;font-weight:600;color:#5ec095;"><?php echo intval( $stats['matched'] ); ?></span>
                            </div>
                            <div>
                                <strong style="color:#86868b;font-size:12px;text-transform:uppercase;">Ambiguous</strong><br>
                                <span style="font-size:18px;font-weight:600;color:#f59e0b;"><?php echo intval( $stats['skipped'] ); ?></span>
                            </div>
                            <div>
                                <strong style="color:#86868b;font-size:12px;text-transform:uppercase;">Unmatched</strong><br>
                                <span style="font-size:18px;font-weight:600;color:#94a3b8;"><?php echo intval( $stats['unmatched'] ); ?></span>
                            </div>
                            <div>
                                <strong style="color:#86868b;font-size:12px;text-transform:uppercase;">Total Roamer</strong><br>
                                <span style="font-size:18px;font-weight:600;"><?php echo intval( $stats['total_roamer'] ); ?></span>
                            </div>
                        <?php elseif ( ! empty( $stats['message'] ) ) : ?>
                            <div style="grid-column:span 4;">
                                <p style="color:#ef4444;"><?php echo esc_html( $stats['message'] ); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else : ?>
                    <p style="color:#86868b;">No sync has been run yet. Click "Sync Now" to fetch data from Rivian Roamer.</p>
                <?php endif; ?>
            </div>
            <div id="rtg-roamer-sync-spinner" style="display:none;padding:12px 0;">
                <span class="spinner is-active" style="float:none;margin:0 8px 0 0;"></span>
                Syncing with Rivian Roamer...
            </div>
        </div>
    </div>

    <!-- Matched Tires (collapsed by default) -->
    <div class="rtg-card" style="margin-top:20px;">
        <div class="rtg-card-header" style="display:flex;align-items:center;justify-content:space-between;cursor:pointer;" id="rtg-linked-toggle">
            <h2>Linked Tires (<?php echo count( $mapping['matched'] ); ?>)</h2>
            <span class="dashicons dashicons-arrow-down-alt2" id="rtg-linked-arrow" style="font-size:20px;width:20px;height:20px;color:#86868b;transition:transform 0.2s;"></span>
        </div>
        <div id="rtg-linked-section" style="display:none;">
            <div class="rtg-card-body" style="padding:0;">
                <?php if ( ! empty( $mapping['matched'] ) ) : ?>
                    <table class="wp-list-table widefat striped" style="border:none;">
                        <thead>
                            <tr>
                                <th>Tire</th>
                                <th>Size</th>
                                <th>Load Range</th>
                                <th>Roamer ID</th>
                                <th>mi/kWh</th>
                                <th>Distance</th>
                                <th>Vehicles</th>
                                <th title="When this tire's Roamer data last changed — the sync itself runs every five minutes but only writes when the feed's numbers move.">Data Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $mapping['matched'] as $tire ) :
                                $synced_rel = $tire['roamer_synced_at'] ? human_time_diff( strtotime( $tire['roamer_synced_at'] ), current_time( 'timestamp' ) ) . ' ago' : 'N/A';
                            ?>
                                <tr>
                                    <td><strong><?php echo esc_html( $tire['brand'] . ' ' . $tire['model'] ); ?></strong></td>
                                    <td><?php echo esc_html( $tire['size'] ); ?></td>
                                    <td><?php echo esc_html( $tire['load_range'] ?: '-' ); ?></td>
                                    <td><code style="font-size:11px;"><?php echo esc_html( $tire['roamer_tire_id'] ); ?></code></td>
                                    <td><strong><?php echo esc_html( number_format( $tire['roamer_efficiency'], 2 ) ); ?></strong></td>
                                    <td><?php echo esc_html( number_format( floatval( $tire['roamer_total_km'] ?? 0 ) * 0.621371, 0 ) ); ?> mi</td>
                                    <td><?php echo intval( $tire['roamer_vehicle_count'] ); ?></td>
                                    <td title="<?php echo esc_attr( $tire['roamer_synced_at'] ?: '' ); ?>"><?php echo esc_html( $synced_rel ); ?></td>
                                    <td>
                                        <button type="button" class="button button-small rtg-roamer-unlink" data-tire-id="<?php echo esc_attr( $tire['tire_id'] ); ?>">Unlink</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p style="padding:16px;color:#86868b;">No tires have been linked to Roamer data yet. Run a sync to auto-match.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Ambiguous Matches (from last sync) -->
    <?php if ( $stats && ! empty( $stats['ambiguous_list'] ) ) : ?>
        <div class="rtg-card" style="margin-top:20px;">
            <div class="rtg-card-header">
                <h2 style="color:#f59e0b;">Ambiguous Matches — Manual Review Required (<?php echo count( $stats['ambiguous_list'] ); ?>)</h2>
            </div>
            <div class="rtg-card-body" style="padding:0;">
                <p style="padding:16px 16px 0;color:#86868b;">
                    These Roamer tires matched multiple entries in your guide (different load ratings for the same tire/size).
                    Choose which tire to assign the data to. Picking one marked <em>already linked</em> merges this ID into its existing Roamer IDs.
                </p>
                <table class="wp-list-table widefat striped" style="border:none;">
                    <thead>
                        <tr>
                            <th>Roamer Tire</th>
                            <th>Size</th>
                            <th>mi/kWh</th>
                            <th>Distance</th>
                            <th>Assign To</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $stats['ambiguous_list'] as $amb ) :
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
                                <td><?php echo esc_html( $amb['size'] ); ?></td>
                                <td><strong><?php echo esc_html( number_format( $amb['efficiency'], 2 ) ); ?></strong></td>
                                <td><?php echo esc_html( number_format( floatval( $amb['total_km'] ?? 0 ) * 0.621371, 0 ) ); ?> mi</td>
                                <?php if ( empty( $candidates_info ) ) : ?>
                                <td colspan="2">
                                    <span style="color:#86868b;font-size:13px;">No candidate tires found in the guide.</span>
                                </td>
                                <?php else : ?>
                                <td>
                                    <select class="rtg-roamer-assign-select" data-roamer-id="<?php echo esc_attr( $amb['roamer_tire_id'] ); ?>">
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
                                <td>
                                    <button type="button" class="button button-small button-primary rtg-roamer-assign-btn" data-roamer-id="<?php echo esc_attr( $amb['roamer_tire_id'] ); ?>" disabled>Assign</button>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- Unmatched Roamer Tires (from last sync) -->
    <?php if ( $stats && ! empty( $stats['unmatched_list'] ) ) :
        // Sort unmatched by total distance descending so most impactful are first.
        $unmatched_sorted = $stats['unmatched_list'];
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
        <div class="rtg-card" style="margin-top:20px;">
            <div class="rtg-card-header" style="display:flex;align-items:center;justify-content:space-between;">
                <h2>Unmatched Roamer Tires (<?php echo count( $stats['unmatched_list'] ); ?>)</h2>
                <div id="rtg-unmatched-assign-bar" style="display:none;align-items:center;gap:8px;">
                    <span id="rtg-unmatched-selected-count" style="font-size:13px;color:#86868b;">0 selected</span>
                    <select id="rtg-unmatched-assign-tire" class="regular-text" style="max-width:350px;">
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
                    <button type="button" id="rtg-unmatched-assign-btn" class="button button-primary" disabled>Assign</button>
                    <button type="button" id="rtg-unmatched-hide-btn" class="button" style="color:#d63638;" title="Permanently hide selected tires from the unmatched list">Hide</button>
                </div>
            </div>
            <div class="rtg-card-body" style="padding:0;">
                <p style="padding:16px 16px 0;color:#86868b;">
                    These tires exist on Rivian Roamer but aren't in your guide. Select one or more to manually assign to a guide tire. When multiple are selected, efficiency is averaged weighted by total distance. Assigning to a tire marked <em>already linked</em> merges the selection into its existing Roamer IDs — use that when Roamer lists the same tire under more than one ID.
                </p>
                <table class="wp-list-table widefat striped" style="border:none;">
                    <thead>
                        <tr>
                            <th style="width:30px;"><input type="checkbox" id="rtg-unmatched-select-all"></th>
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
                                <td><input type="checkbox" class="rtg-unmatched-cb" value="<?php echo esc_attr( $tire['roamer_tire_id'] ); ?>" data-name="<?php echo esc_attr( $tire['name'] ); ?>"></td>
                                <td><strong><?php echo esc_html( $tire['name'] ); ?></strong></td>
                                <td><?php echo esc_html( $tire['size'] ); ?></td>
                                <td><?php echo esc_html( number_format( $tire['efficiency'], 2 ) ); ?></td>
                                <td><?php echo esc_html( number_format( floatval( $tire['total_km'] ?? 0 ) * 0.621371, 0 ) ); ?> mi</td>
                                <td><code style="font-size:11px;"><?php echo esc_html( $tire['roamer_tire_id'] ); ?></code></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- Hidden Roamer Tires (collapsed by default) -->
    <?php
    $hidden_ids = get_option( RTG_Roamer_Sync::HIDDEN_OPTION, array() );
    if ( ! empty( $hidden_ids ) ) : ?>
        <div class="rtg-card" style="margin-top:20px;">
            <div class="rtg-card-header" style="display:flex;align-items:center;justify-content:space-between;cursor:pointer;" id="rtg-hidden-toggle">
                <h2>Hidden Roamer Tires (<?php echo count( $hidden_ids ); ?>)</h2>
                <span class="dashicons dashicons-arrow-down-alt2" id="rtg-hidden-arrow" style="font-size:20px;width:20px;height:20px;color:#86868b;transition:transform 0.2s;"></span>
            </div>
            <div id="rtg-hidden-section" style="display:none;">
                <div id="rtg-hidden-restore-bar" style="display:none;align-items:center;gap:8px;padding:12px 24px 0;">
                    <span id="rtg-hidden-selected-count" style="font-size:13px;color:#86868b;">0 selected</span>
                    <button type="button" id="rtg-hidden-restore-btn" class="button">Restore</button>
                </div>
                <div class="rtg-card-body" style="padding:0;">
                    <p style="padding:16px 16px 0;color:#86868b;">
                        These Roamer tire IDs have been permanently hidden and excluded from future syncs. Select one or more and click Restore to unhide them.
                    </p>
                    <table class="wp-list-table widefat striped" style="border:none;">
                        <thead>
                            <tr>
                                <th style="width:30px;"><input type="checkbox" id="rtg-hidden-select-all"></th>
                                <th>Roamer ID</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $hidden_ids as $hid ) : ?>
                                <tr>
                                    <td><input type="checkbox" class="rtg-hidden-cb" value="<?php echo esc_attr( $hid ); ?>"></td>
                                    <td><code style="font-size:11px;"><?php echo esc_html( $hid ); ?></code></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Unlinked Guide Tires -->
    <?php
    $unlinked_total    = count( $mapping['unlinked'] );
    $unlinked_per_page = 20;
    $unlinked_page     = isset( $_GET['unlinked_page'] ) ? max( 1, intval( $_GET['unlinked_page'] ) ) : 1;
    $unlinked_pages    = max( 1, ceil( $unlinked_total / $unlinked_per_page ) );
    $unlinked_offset   = ( $unlinked_page - 1 ) * $unlinked_per_page;
    $unlinked_slice    = array_slice( $mapping['unlinked'], $unlinked_offset, $unlinked_per_page );
    ?>
    <div class="rtg-card" style="margin-top:20px;">
        <div class="rtg-card-header" style="display:flex;align-items:center;justify-content:space-between;cursor:pointer;" id="rtg-unlinked-toggle">
            <h2>Unlinked Guide Tires (<?php echo $unlinked_total; ?>)</h2>
            <span class="dashicons dashicons-arrow-down-alt2" id="rtg-unlinked-arrow" style="font-size:20px;width:20px;height:20px;color:#86868b;transition:transform 0.2s;"></span>
        </div>
        <div id="rtg-unlinked-section" style="display:none;">
        <div class="rtg-card-body" style="padding:0;">
            <?php if ( $unlinked_total > 0 ) : ?>
                <p style="padding:16px 16px 0;color:#86868b;">
                    These tires in your guide don't have Roamer data linked. They may not have data available, or you can manually assign a Roamer ID on the tire edit page.
                </p>
                <table class="wp-list-table widefat striped" style="border:none;">
                    <thead>
                        <tr>
                            <th>Tire</th>
                            <th>Size</th>
                            <th>Load Range</th>
                            <th>ID</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $unlinked_slice as $tire ) : ?>
                            <tr>
                                <td>
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-tire-edit&id=' . $tire['tire_id'] ) ); ?>">
                                        <strong><?php echo esc_html( $tire['brand'] . ' ' . $tire['model'] ); ?></strong>
                                    </a>
                                </td>
                                <td><?php echo esc_html( $tire['size'] ); ?></td>
                                <td><?php echo esc_html( $tire['load_range'] ?: '-' ); ?></td>
                                <td><code style="font-size:11px;"><?php echo esc_html( $tire['tire_id'] ); ?></code></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if ( $unlinked_pages > 1 ) : ?>
                    <div style="padding:12px 16px;display:flex;align-items:center;justify-content:space-between;border-top:1px solid rgba(51,65,85,0.4);">
                        <span style="font-size:13px;color:#86868b;">
                            Showing <?php echo $unlinked_offset + 1; ?>–<?php echo min( $unlinked_offset + $unlinked_per_page, $unlinked_total ); ?> of <?php echo $unlinked_total; ?>
                        </span>
                        <div style="display:flex;gap:4px;">
                            <?php for ( $p = 1; $p <= $unlinked_pages; $p++ ) :
                                $url = add_query_arg( 'unlinked_page', $p, admin_url( 'admin.php?page=rtg-roamer-sync' ) ) . '#unlinked-tires';
                            ?>
                                <?php if ( $p === $unlinked_page ) : ?>
                                    <span style="padding:4px 10px;background:#3b82f6;color:#fff;border-radius:4px;font-size:13px;font-weight:600;"><?php echo $p; ?></span>
                                <?php else : ?>
                                    <a href="<?php echo esc_url( $url ); ?>" style="padding:4px 10px;background:rgba(51,65,85,0.4);color:#e2e8f0;border-radius:4px;font-size:13px;text-decoration:none;"><?php echo $p; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else : ?>
                <p style="padding:16px;color:#5ec095;">All tires in your guide have Roamer data linked.</p>
            <?php endif; ?>
        </div>
        </div>
    </div>
</div>
