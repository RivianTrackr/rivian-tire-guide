<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$settings = get_option( 'rtg_settings', array() );

// Handle settings save.
if ( isset( $_POST['rtg_catalog_settings_save'] ) ) {
    check_admin_referer( 'rtg_catalog_settings', 'rtg_catalog_settings_nonce' );

    $settings['catalog_sync_enabled']   = ! empty( $_POST['catalog_sync_enabled'] );
    $settings['catalog_notify_enabled'] = ! empty( $_POST['catalog_notify_enabled'] );
    $settings['catalog_fixture_url']    = esc_url_raw( wp_unslash( $_POST['catalog_fixture_url'] ?? '' ) );

    // --- CJ credentials and query ---
    $settings['cj_enabled']     = ! empty( $_POST['cj_enabled'] );
    $settings['cj_company_id']  = preg_replace( '/[^0-9]/', '', wp_unslash( $_POST['cj_company_id'] ?? '' ) );
    $settings['cj_advertisers'] = sanitize_textarea_field( wp_unslash( $_POST['cj_advertisers'] ?? '' ) );
    $settings['cj_limit']       = max( 1, min( 1000, intval( $_POST['cj_limit'] ?? RTG_Catalog_Source_CJ::DEFAULT_LIMIT ) ) );

    // The query document is GraphQL, so it must not be run through a sanitizer
    // that mangles braces or quotes; it is never output unescaped.
    $settings['cj_query'] = trim( (string) wp_unslash( $_POST['cj_query'] ?? '' ) );

    // The token field renders empty, so an empty submission means "leave it
    // alone" rather than "clear it" — otherwise every unrelated settings save
    // would wipe the credential. Clearing is explicit, via the checkbox.
    $posted_pat = trim( (string) wp_unslash( $_POST['cj_pat'] ?? '' ) );
    if ( ! empty( $_POST['cj_pat_clear'] ) ) {
        $settings['cj_pat'] = '';
    } elseif ( '' !== $posted_pat ) {
        $settings['cj_pat'] = $posted_pat;
    }

    // Clamp to the range the load index table actually covers, so a typo can't
    // silently disqualify every tire or admit every tire.
    $posted_index = intval( $_POST['catalog_min_load_index'] ?? RTG_Tire_Qualifier::DEFAULT_MIN_LOAD_INDEX );
    $settings['catalog_min_load_index'] = max( 100, min( 126, $posted_index ) );

    update_option( 'rtg_settings', $settings );
    echo '<div class="notice notice-success is-dismissible"><p>Tire Discovery settings saved.</p></div>';
}

$sync_enabled   = $settings['catalog_sync_enabled'] ?? true;
$notify_enabled = $settings['catalog_notify_enabled'] ?? true;
$fixture_url    = $settings['catalog_fixture_url'] ?? '';
$min_load_index = isset( $settings['catalog_min_load_index'] )
    ? intval( $settings['catalog_min_load_index'] )
    : RTG_Tire_Qualifier::DEFAULT_MIN_LOAD_INDEX;

$cj_enabled      = $settings['cj_enabled'] ?? true;
$cj_company_id   = RTG_Catalog_Source_CJ::get_company_id();
$cj_advertisers  = $settings['cj_advertisers'] ?? '';
$cj_limit        = intval( $settings['cj_limit'] ?? RTG_Catalog_Source_CJ::DEFAULT_LIMIT );
$cj_query        = $settings['cj_query'] ?? '';
$cj_has_pat      = '' !== RTG_Catalog_Source_CJ::get_pat();
$cj_pat_constant = RTG_Catalog_Source_CJ::pat_is_constant();
$cj_configured   = RTG_Catalog_Source_CJ::is_configured();

// Default advertiser list, shown as the placeholder so the expected shape is
// obvious without pre-filling the field.
$cj_advertiser_placeholder = '';
foreach ( RTG_Catalog_Source_CJ::DEFAULT_ADVERTISERS as $adv_id => $adv_name ) {
    $cj_advertiser_placeholder .= $adv_id . '|' . $adv_name . "\n";
}

$stats  = RTG_Catalog_Sync::get_stats();
$counts = RTG_Candidates::get_counts();

$status_filter = isset( $_GET['candidate_status'] )
    ? sanitize_text_field( wp_unslash( $_GET['candidate_status'] ) )
    : RTG_Candidates::STATUS_NEW;
$size_filter = isset( $_GET['candidate_size'] )
    ? sanitize_text_field( wp_unslash( $_GET['candidate_size'] ) )
    : '';

$candidates = RTG_Candidates::query( array(
    'status' => $status_filter,
    'size'   => $size_filter,
) );

$dd_sizes = RTG_Admin::get_dropdown_options( 'sizes' );

$next_run = wp_next_scheduled( RTG_Catalog_Sync::CRON_HOOK );
?>

<div class="rtg-wrap">

    <div class="rtg-page-header">
        <h1 class="rtg-page-title">Tire Discovery</h1>
    </div>

    <p style="margin:0 0 20px;color:var(--rtg-text-muted);max-width:820px;">
        Watches affiliate catalogs for tires in Rivian fitments that aren't in the guide yet, so new
        arrivals surface on their own instead of waiting on a manual search. Every product seen is
        remembered — dismiss one and it won't come back.
    </p>

    <!-- Counts -->
    <div class="rtg-stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));">
        <div class="rtg-stat-card">
            <div class="rtg-stat-value" style="color: var(--rtg-success);"><?php echo esc_html( $counts[ RTG_Candidates::STATUS_NEW ] ); ?></div>
            <div class="rtg-stat-label">Awaiting Review</div>
        </div>
        <div class="rtg-stat-card">
            <div class="rtg-stat-value" style="color: var(--rtg-warning-text);"><?php echo esc_html( $counts[ RTG_Candidates::STATUS_REJECTED ] ); ?></div>
            <div class="rtg-stat-label">Near Misses</div>
        </div>
        <div class="rtg-stat-card">
            <div class="rtg-stat-value"><?php echo esc_html( $counts[ RTG_Candidates::STATUS_EXISTING ] ); ?></div>
            <div class="rtg-stat-label">Already in Guide</div>
        </div>
        <div class="rtg-stat-card">
            <div class="rtg-stat-value"><?php echo esc_html( $counts[ RTG_Candidates::STATUS_IMPORTED ] ); ?></div>
            <div class="rtg-stat-label">Added from Queue</div>
        </div>
        <div class="rtg-stat-card">
            <div class="rtg-stat-value" style="color: var(--rtg-text-muted);"><?php echo esc_html( $counts[ RTG_Candidates::STATUS_DISMISSED ] ); ?></div>
            <div class="rtg-stat-label">Dismissed</div>
        </div>
    </div>

    <!-- Sync status -->
    <div class="rtg-card" style="margin-bottom:20px;">
        <div class="rtg-card-header" style="display:flex;align-items:center;justify-content:space-between;">
            <h2>Discovery Status</h2>
            <button type="button" id="rtg-catalog-sync-btn" class="rtg-btn rtg-btn-primary">Run Discovery Now</button>
        </div>
        <div class="rtg-card-body">
            <div id="rtg-catalog-sync-status" style="display:none;margin-bottom:12px;"></div>

            <?php if ( $stats && isset( $stats['status'] ) ) : ?>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px;">
                    <div>
                        <strong style="color:var(--rtg-text-muted);font-size:12px;text-transform:uppercase;">Status</strong><br>
                        <span style="font-size:18px;font-weight:600;color:<?php echo 'success' === $stats['status'] ? 'var(--rtg-success)' : 'var(--rtg-error)'; ?>;">
                            <?php echo esc_html( ucfirst( $stats['status'] ) ); ?>
                        </span>
                    </div>
                    <div>
                        <strong style="color:var(--rtg-text-muted);font-size:12px;text-transform:uppercase;">Last Run</strong><br>
                        <?php
                        $run_time = $stats['time'] ?? '';
                        $relative = $run_time
                            ? human_time_diff( strtotime( $run_time ), current_time( 'timestamp' ) ) . ' ago'
                            : 'N/A';
                        ?>
                        <span style="font-size:18px;font-weight:600;" title="<?php echo esc_attr( $run_time ); ?>"><?php echo esc_html( $relative ); ?></span>
                    </div>
                    <div>
                        <strong style="color:var(--rtg-text-muted);font-size:12px;text-transform:uppercase;">Products Seen</strong><br>
                        <span style="font-size:18px;font-weight:600;"><?php echo esc_html( intval( $stats['fetched'] ?? 0 ) ); ?></span>
                    </div>
                    <div>
                        <strong style="color:var(--rtg-text-muted);font-size:12px;text-transform:uppercase;">Newly Surfaced</strong><br>
                        <span style="font-size:18px;font-weight:600;color:var(--rtg-success);"><?php echo esc_html( intval( $stats['newly_surfaced'] ?? 0 ) ); ?></span>
                    </div>
                    <div>
                        <strong style="color:var(--rtg-text-muted);font-size:12px;text-transform:uppercase;">Next Run</strong><br>
                        <span style="font-size:18px;font-weight:600;">
                            <?php echo $next_run ? esc_html( 'in ' . human_time_diff( time(), $next_run ) ) : 'Not scheduled'; ?>
                        </span>
                    </div>
                </div>

                <?php if ( ! empty( $stats['errors'] ) ) : ?>
                    <div class="rtg-notice rtg-notice-warning" style="margin-top:16px;">
                        <span>
                            <?php foreach ( $stats['errors'] as $error ) : ?>
                                <strong><?php echo esc_html( $error['source'] ); ?>:</strong> <?php echo esc_html( $error['message'] ); ?><br>
                            <?php endforeach; ?>
                        </span>
                    </div>
                <?php endif; ?>
            <?php else : ?>
                <p style="color:var(--rtg-text-muted);margin:0;">Discovery hasn't run yet. Use <strong>Run Discovery Now</strong> to try it against the configured source.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filter tabs -->
    <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:16px;">
        <?php
        $tabs = array(
            RTG_Candidates::STATUS_NEW       => 'Awaiting Review (' . $counts[ RTG_Candidates::STATUS_NEW ] . ')',
            RTG_Candidates::STATUS_REJECTED  => 'Near Misses (' . $counts[ RTG_Candidates::STATUS_REJECTED ] . ')',
            RTG_Candidates::STATUS_EXISTING  => 'Already in Guide (' . $counts[ RTG_Candidates::STATUS_EXISTING ] . ')',
            RTG_Candidates::STATUS_DISMISSED => 'Dismissed (' . $counts[ RTG_Candidates::STATUS_DISMISSED ] . ')',
            RTG_Candidates::STATUS_IMPORTED  => 'Added (' . $counts[ RTG_Candidates::STATUS_IMPORTED ] . ')',
        );
        foreach ( $tabs as $key => $label ) :
            $url = add_query_arg(
                array(
                    'page'             => 'rtg-tire-discovery',
                    'candidate_status' => $key,
                    'candidate_size'   => $size_filter,
                ),
                admin_url( 'admin.php' )
            );
            $class = $status_filter === $key ? 'rtg-btn rtg-btn-primary' : 'rtg-btn rtg-btn-secondary';
        ?>
            <a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $class ); ?>" style="text-decoration:none;"><?php echo esc_html( $label ); ?></a>
        <?php endforeach; ?>
    </div>

    <form method="get" style="margin-bottom:20px;">
        <input type="hidden" name="page" value="rtg-tire-discovery">
        <input type="hidden" name="candidate_status" value="<?php echo esc_attr( $status_filter ); ?>">
        <div class="rtg-search-box" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
            <label for="candidate_size" style="font-size:13px;color:var(--rtg-text-muted);">Size</label>
            <select name="candidate_size" id="candidate_size" class="rtg-select">
                <option value="">All sizes</option>
                <?php foreach ( $dd_sizes as $size_option ) : ?>
                    <option value="<?php echo esc_attr( $size_option ); ?>" <?php selected( $size_filter, $size_option ); ?>><?php echo esc_html( $size_option ); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="rtg-btn rtg-btn-secondary">Filter</button>
        </div>
    </form>

    <!-- Candidates table -->
    <div class="rtg-card">
        <div class="rtg-table-wrapper">
            <?php if ( empty( $candidates ) ) : ?>
                <div class="rtg-empty-state" style="padding:60px 20px;text-align:center;">
                    <h2 style="font-size:20px;font-weight:600;margin:0 0 8px;">Nothing here</h2>
                    <p style="color:var(--rtg-text-muted);max-width:500px;margin:0 auto;line-height:1.6;">
                        <?php if ( RTG_Candidates::STATUS_NEW === $status_filter ) : ?>
                            No tires are waiting for review. New arrivals show up here after a discovery run.
                        <?php else : ?>
                            No candidates match this view.
                        <?php endif; ?>
                    </p>
                </div>
            <?php else : ?>
                <table class="rtg-table rtg-table-compact">
                    <thead>
                        <tr>
                            <th>Tire</th>
                            <th>Size</th>
                            <th>Load</th>
                            <th>Speed</th>
                            <th>Price</th>
                            <th>Retailer</th>
                            <th>First Seen</th>
                            <?php if ( RTG_Candidates::STATUS_REJECTED === $status_filter ) : ?>
                                <th>Why Not</th>
                            <?php endif; ?>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $candidates as $candidate ) :
                        $add_url = add_query_arg(
                            array(
                                'page'           => 'rtg-tire-edit',
                                'from_candidate' => $candidate['id'],
                            ),
                            admin_url( 'admin.php' )
                        );
                    ?>
                        <tr data-candidate-id="<?php echo esc_attr( $candidate['id'] ); ?>">
                            <td>
                                <strong><?php echo esc_html( trim( $candidate['brand'] . ' ' . $candidate['model'] ) ); ?></strong>
                                <?php if ( ! empty( $candidate['matched_tire_id'] ) ) : ?>
                                    <br><span class="rtg-badge rtg-badge-muted">in guide as <?php echo esc_html( $candidate['matched_tire_id'] ); ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="font-family:var(--rtg-font-mono, monospace);"><?php echo esc_html( $candidate['size'] ); ?></td>
                            <td><?php echo esc_html( $candidate['load_index'] ?: '—' ); ?></td>
                            <td><?php echo esc_html( $candidate['speed_rating'] ?: '—' ); ?></td>
                            <td><?php echo $candidate['price'] > 0 ? '$' . esc_html( number_format( $candidate['price'], 2 ) ) : '—'; ?></td>
                            <td><?php echo esc_html( $candidate['advertiser_name'] ?: $candidate['source'] ); ?></td>
                            <td title="<?php echo esc_attr( $candidate['first_seen_at'] ); ?>">
                                <?php echo esc_html( human_time_diff( strtotime( $candidate['first_seen_at'] ), current_time( 'timestamp' ) ) ); ?> ago
                            </td>
                            <?php if ( RTG_Candidates::STATUS_REJECTED === $status_filter ) : ?>
                                <td style="font-size:12px;color:var(--rtg-text-muted);">
                                    <?php
                                    $labels = array();
                                    foreach ( (array) $candidate['fail_reasons'] as $reason ) {
                                        $labels[] = $reason['label'] ?? '';
                                    }
                                    echo esc_html( implode( '; ', array_filter( $labels ) ) );
                                    ?>
                                </td>
                            <?php endif; ?>
                            <td style="text-align:right;white-space:nowrap;">
                                <?php if ( ! empty( $candidate['link'] ) ) : ?>
                                    <a href="<?php echo esc_url( $candidate['link'] ); ?>" target="_blank" rel="noopener noreferrer nofollow" class="rtg-btn rtg-btn-secondary" style="text-decoration:none;">Listing</a>
                                <?php endif; ?>

                                <?php if ( RTG_Candidates::STATUS_IMPORTED !== $status_filter && RTG_Candidates::STATUS_EXISTING !== $status_filter ) : ?>
                                    <a href="<?php echo esc_url( $add_url ); ?>" class="rtg-btn rtg-btn-primary" style="text-decoration:none;">Add to Guide</a>
                                <?php endif; ?>

                                <?php if ( RTG_Candidates::STATUS_DISMISSED === $status_filter ) : ?>
                                    <button type="button" class="rtg-btn rtg-btn-secondary rtg-candidate-action" data-status="<?php echo esc_attr( RTG_Candidates::STATUS_NEW ); ?>">Restore</button>
                                <?php elseif ( RTG_Candidates::STATUS_IMPORTED !== $status_filter ) : ?>
                                    <button type="button" class="rtg-btn rtg-btn-danger rtg-candidate-action" data-status="<?php echo esc_attr( RTG_Candidates::STATUS_DISMISSED ); ?>">Dismiss</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Settings -->
    <div class="rtg-card" style="margin-top:20px;">
        <div class="rtg-card-header">
            <h2>Discovery Settings</h2>
        </div>
        <div class="rtg-card-body">
            <form method="post">
                <?php wp_nonce_field( 'rtg_catalog_settings', 'rtg_catalog_settings_nonce' ); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="catalog_sync_enabled">Enable Discovery</label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="catalog_sync_enabled" id="catalog_sync_enabled" value="1" <?php checked( $sync_enabled ); ?>>
                                Check affiliate catalogs once a day
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="catalog_notify_enabled">Email Digest</label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="catalog_notify_enabled" id="catalog_notify_enabled" value="1" <?php checked( $notify_enabled ); ?>>
                                Email me when a qualifying tire is found
                            </label>
                            <p class="description">Only newly surfaced tires are included — a run that finds nothing new sends nothing.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="catalog_min_load_index">Minimum Load Index</label></th>
                        <td>
                            <input type="number" name="catalog_min_load_index" id="catalog_min_load_index" value="<?php echo esc_attr( $min_load_index ); ?>" min="100" max="126" class="small-text">
                            <p class="description">A tire below this is filed under Near Misses rather than the review queue. R1 needs 116, R2 needs 112.</p>
                        </td>
                    </tr>
                </table>

                <h3 style="margin:24px 0 8px;">CJ Affiliate</h3>
                <p class="description" style="max-width:820px;margin-bottom:8px;">
                    Both Tire Rack and SimpleTire run their affiliate programs on CJ, so one connection
                    covers both. Discovery sends one request per tire size, scoped to the advertisers below.
                    <?php if ( $cj_configured ) : ?>
                        <strong style="color:var(--rtg-success);">Configured.</strong>
                    <?php else : ?>
                        <strong style="color:var(--rtg-warning-text);">Not configured — discovery falls back to the JSON feed.</strong>
                    <?php endif; ?>
                </p>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="cj_enabled">Use CJ</label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="cj_enabled" id="cj_enabled" value="1" <?php checked( $cj_enabled ); ?>>
                                Pull candidates from the CJ Product Search API
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cj_company_id">Company ID (CID)</label></th>
                        <td>
                            <input type="text" name="cj_company_id" id="cj_company_id" value="<?php echo esc_attr( $cj_company_id ); ?>" class="regular-text" inputmode="numeric">
                            <p class="description">From CJ &rarr; Account &rarr; Account Information.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cj_pat">Personal Access Token</label></th>
                        <td>
                            <?php if ( $cj_pat_constant ) : ?>
                                <p style="margin:0;color:var(--rtg-success);"><strong>Set in wp-config.php</strong> via <code>RTG_CJ_PAT</code>. This field is ignored while that constant is defined.</p>
                            <?php else : ?>
                                <input type="password" name="cj_pat" id="cj_pat" value="" class="regular-text" autocomplete="off"
                                    placeholder="<?php echo $cj_has_pat ? esc_attr( 'Saved — leave blank to keep' ) : esc_attr( 'Paste your CJ token' ); ?>">
                                <?php if ( $cj_has_pat ) : ?>
                                    <label style="margin-left:12px;">
                                        <input type="checkbox" name="cj_pat_clear" value="1"> Clear saved token
                                    </label>
                                <?php endif; ?>
                                <p class="description">
                                    Never displayed once saved. Better still, keep it out of the database entirely by adding
                                    <code>define( 'RTG_CJ_PAT', '...' );</code> to <code>wp-config.php</code> — that takes precedence over this field.
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cj_advertisers">Advertisers</label></th>
                        <td>
                            <textarea name="cj_advertisers" id="cj_advertisers" rows="3" class="large-text code" placeholder="<?php echo esc_attr( $cj_advertiser_placeholder ); ?>"><?php echo esc_textarea( $cj_advertisers ); ?></textarea>
                            <p class="description">One per line, as <code>advertiserId|Name</code>. Leave blank for the defaults shown. Only advertisers you have joined return products.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cj_limit">Records per size</label></th>
                        <td>
                            <input type="number" name="cj_limit" id="cj_limit" value="<?php echo esc_attr( $cj_limit ); ?>" min="1" max="1000" class="small-text">
                            <p class="description">How many products to request per tire size. Five sizes means five requests per run.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cj_query">GraphQL query</label></th>
                        <td>
                            <textarea name="cj_query" id="cj_query" rows="10" class="large-text code" spellcheck="false" placeholder="<?php echo esc_attr( RTG_Catalog_Source_CJ::DEFAULT_QUERY ); ?>"><?php echo esc_textarea( $cj_query ); ?></textarea>
                            <p class="description">
                                Leave blank to use the shipped query. If Test Connection reports a GraphQL error naming a field,
                                correct it here rather than waiting on a plugin update — the response mapping accepts several
                                field spellings, so only the query itself usually needs changing.
                            </p>
                            <p style="margin-top:10px;">
                                <button type="button" id="rtg-cj-test-btn" class="rtg-btn rtg-btn-secondary">Test Connection</button>
                            </p>
                            <div id="rtg-cj-test-result" style="display:none;margin-top:10px;"></div>
                        </td>
                    </tr>
                </table>

                <h3 style="margin:24px 0 8px;">JSON Feed</h3>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="catalog_fixture_url">JSON Feed URL</label></th>
                        <td>
                            <input type="url" name="catalog_fixture_url" id="catalog_fixture_url" value="<?php echo esc_attr( $fixture_url ); ?>" class="regular-text" style="width:100%;max-width:600px;" placeholder="<?php echo esc_attr( 'Leave blank to use the bundled sample' ); ?>">
                            <p class="description">
                                An optional extra source — a JSON document of products to check, useful for a retailer with no
                                machine-readable feed. With CJ configured and this blank, the bundled sample stays out of the way
                                so demo rows can't mix into a queue holding real finds.
                            </p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" name="rtg_catalog_settings_save" class="button button-primary" value="Save Settings">
                </p>
            </form>
        </div>
    </div>

</div>
