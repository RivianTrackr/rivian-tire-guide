<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RTG_Admin {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'admin_init', array( $this, 'handle_actions' ) );
    }

    public function register_menu() {
        add_menu_page(
            'Tire Guide',
            'Tire Guide',
            'manage_options',
            'rtg-tires',
            array( $this, 'render_list_page' ),
            'dashicons-car',
            30
        );

        add_submenu_page(
            'rtg-tires',
            'All Tires',
            'All Tires',
            'manage_options',
            'rtg-tires',
            array( $this, 'render_list_page' )
        );

        add_submenu_page(
            'rtg-tires',
            'Add New Tire',
            'Add New',
            'manage_options',
            'rtg-tire-edit',
            array( $this, 'render_edit_page' )
        );

        add_submenu_page(
            'rtg-tires',
            'Ratings',
            'Ratings',
            'manage_options',
            'rtg-ratings',
            array( $this, 'render_ratings_page' )
        );

        // Reviews management page with pending count badge.
        $pending = RTG_Database::get_review_status_counts();
        $badge   = $pending['pending'] > 0
            ? ' <span class="awaiting-mod">' . intval( $pending['pending'] ) . '</span>'
            : '';

        add_submenu_page(
            'rtg-tires',
            'Reviews',
            'Reviews' . $badge,
            'manage_options',
            'rtg-reviews',
            array( $this, 'render_reviews_page' )
        );

        add_submenu_page(
            'rtg-tires',
            'Stock Wheels',
            'Stock Wheels',
            'manage_options',
            'rtg-wheels',
            array( $this, 'render_wheels_page' )
        );

        add_submenu_page(
            null,
            'Add / Edit Wheel',
            '',
            'manage_options',
            'rtg-wheel-edit',
            array( $this, 'render_wheel_edit_page' )
        );

        add_submenu_page(
            'rtg-tires',
            'Import / Export',
            'Import / Export',
            'manage_options',
            'rtg-import',
            array( $this, 'render_import_page' )
        );

        add_submenu_page(
            'rtg-tires',
            'Settings',
            'Settings',
            'manage_options',
            'rtg-settings',
            array( $this, 'render_settings_page' )
        );
    }

    public function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'rtg-' ) === false && strpos( $hook, 'rtg_' ) === false ) {
            return;
        }

        $suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : (
            file_exists( RTG_PLUGIN_DIR . 'admin/css/admin-styles.min.css' ) ? '.min' : ''
        );

        wp_enqueue_style(
            'rtg-admin-styles',
            RTG_PLUGIN_URL . 'admin/css/admin-styles' . $suffix . '.css',
            array(),
            RTG_VERSION
        );

        wp_enqueue_script(
            'rtg-admin-scripts',
            RTG_PLUGIN_URL . 'admin/js/admin-scripts.js',
            array( 'jquery' ),
            RTG_VERSION,
            true
        );
    }

    public function handle_actions() {
        // Handle tire save.
        if ( isset( $_POST['rtg_tire_save'] ) ) {
            $this->handle_tire_save();
        }

        // Handle tire delete.
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && isset( $_GET['tire_id'] ) ) {
            $this->handle_tire_delete();
        }

        // Handle bulk actions from list table.
        if ( isset( $_POST['rtg_bulk_action'] ) && $_POST['rtg_bulk_action'] === 'delete' ) {
            $this->handle_bulk_delete();
        }

        // Handle wheel save.
        if ( isset( $_POST['rtg_wheel_save'] ) ) {
            $this->handle_wheel_save();
        }

        // Handle wheel delete.
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete_wheel' && isset( $_GET['wheel_id'] ) ) {
            $this->handle_wheel_delete();
        }

        // Handle settings save.
        if ( isset( $_POST['rtg_save_settings'] ) ) {
            $this->handle_settings_save();
        }

        // Handle CSV import.
        if ( isset( $_POST['rtg_csv_import'] ) ) {
            $this->handle_csv_import();
        }

        // Handle CSV export.
        if ( isset( $_GET['rtg_export'] ) && $_GET['rtg_export'] === 'csv' ) {
            $this->handle_csv_export();
        }

        // Handle rating delete.
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete_rating' && isset( $_GET['rating_id'] ) ) {
            $this->handle_rating_delete();
        }

        // Handle review moderation.
        if ( isset( $_GET['action'] ) && in_array( $_GET['action'], array( 'approve_review', 'reject_review' ), true ) && isset( $_GET['rating_id'] ) ) {
            $this->handle_review_status();
        }
    }

    // --- Dropdown Options ---

    /**
     * Default dropdown options for tire fields.
     */
    private static $default_dropdowns = array(
        'brands'        => array( 'BFGoodrich', 'Continental', 'Cooper', 'Falken', 'Firestone', 'General', 'Goodyear', 'Hankook', 'Kumho', 'Michelin', 'Nitto', 'Pirelli', 'Toyo', 'Yokohama' ),
        'categories'    => array( 'All-Season', 'All-Terrain', 'Highway', 'Mud-Terrain', 'Performance', 'Rugged Terrain', 'Winter' ),
        'sizes'         => array( '275/65R18', '275/60R20', '275/55R22' ),
        'diameters'     => array( '18"', '20"', '22"' ),
        'load_ranges'   => array( 'SL', 'HL', 'XL', 'RF', 'D', 'E', 'F' ),
        'speed_ratings' => array( 'L', 'M', 'N', 'P', 'Q', 'R', 'S', 'T', 'U', 'H', 'V', 'W', 'Y', 'Z' ),
        'load_indexes'  => array(
            100 => 1764, 101 => 1819, 102 => 1874, 103 => 1929, 104 => 1984,
            105 => 2039, 106 => 2094, 107 => 2149, 108 => 2205, 109 => 2271,
            110 => 2337, 111 => 2403, 112 => 2469, 113 => 2535, 114 => 2601,
            115 => 2679, 116 => 2756, 117 => 2833, 118 => 2910, 119 => 2998,
            120 => 3086, 121 => 3197, 122 => 3307, 123 => 3417, 124 => 3527,
            125 => 3638, 126 => 3748,
        ),
    );

    /**
     * Get dropdown options for a field, with defaults fallback.
     */
    public static function get_dropdown_options( $field ) {
        $saved = get_option( 'rtg_dropdown_options', array() );
        if ( ! empty( $saved[ $field ] ) && is_array( $saved[ $field ] ) ) {
            return $saved[ $field ];
        }
        return self::$default_dropdowns[ $field ] ?? array();
    }

    /**
     * Get load index → max load map (associative: index => lbs).
     */
    public static function get_load_index_map() {
        $saved = get_option( 'rtg_dropdown_options', array() );
        if ( ! empty( $saved['load_indexes'] ) && is_array( $saved['load_indexes'] ) ) {
            return $saved['load_indexes'];
        }
        return self::$default_dropdowns['load_indexes'];
    }

    // --- Page Renderers ---

    public function render_list_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        require_once RTG_PLUGIN_DIR . 'admin/views/tire-list.php';
    }

    public function render_edit_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        require_once RTG_PLUGIN_DIR . 'admin/views/tire-edit.php';
    }

    public function render_wheels_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        require_once RTG_PLUGIN_DIR . 'admin/views/wheel-list.php';
    }

    public function render_wheel_edit_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        require_once RTG_PLUGIN_DIR . 'admin/views/wheel-edit.php';
    }

    public function render_ratings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        require_once RTG_PLUGIN_DIR . 'admin/views/ratings-list.php';
    }

    public function render_reviews_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        require_once RTG_PLUGIN_DIR . 'admin/views/reviews-list.php';
    }

    public function render_import_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        require_once RTG_PLUGIN_DIR . 'admin/views/import-export.php';
    }

    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        require_once RTG_PLUGIN_DIR . 'admin/views/settings.php';
    }

    // --- Action Handlers ---

    private function handle_tire_save() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        check_admin_referer( 'rtg_tire_save', 'rtg_tire_nonce' );

        $editing_id = isset( $_POST['editing_id'] ) ? intval( $_POST['editing_id'] ) : 0;

        // Strip WordPress magic quotes so characters like " are stored correctly.
        $post = wp_unslash( $_POST );

        $data = array(
            'tire_id'          => sanitize_text_field( $post['tire_id'] ?? '' ),
            'size'             => sanitize_text_field( $post['size'] ?? '' ),
            'diameter'         => sanitize_text_field( $post['diameter'] ?? '' ),
            'brand'            => sanitize_text_field( $post['brand'] ?? '' ),
            'model'            => sanitize_text_field( $post['model'] ?? '' ),
            'category'         => sanitize_text_field( $post['category'] ?? '' ),
            'price'            => floatval( $post['price'] ?? 0 ),
            'mileage_warranty' => intval( $post['mileage_warranty'] ?? 0 ),
            'weight_lb'        => floatval( $post['weight_lb'] ?? 0 ),
            'three_pms'        => sanitize_text_field( $post['three_pms'] ?? 'No' ),
            'tread'            => sanitize_text_field( $post['tread'] ?? '' ),
            'load_index'       => sanitize_text_field( $post['load_index'] ?? '' ),
            'max_load_lb'      => intval( $post['max_load_lb'] ?? 0 ),
            'load_range'       => sanitize_text_field( $post['load_range'] ?? '' ),
            'speed_rating'     => sanitize_text_field( $post['speed_rating'] ?? '' ),
            'psi'              => sanitize_text_field( $post['psi'] ?? '' ),
            'utqg'             => sanitize_text_field( $post['utqg'] ?? '' ),
            'tags'             => sanitize_text_field( $post['tags'] ?? '' ),
            'link'             => esc_url_raw( $post['link'] ?? '' ),
            'image'            => esc_url_raw( $post['image'] ?? '' ),
            'bundle_link'      => esc_url_raw( $post['bundle_link'] ?? '' ),
            'review_link'      => esc_url_raw( $post['review_link'] ?? '' ),
            'sort_order'       => intval( $post['sort_order'] ?? 0 ),
        );

        // Auto-calculate efficiency score and grade.
        $efficiency = RTG_Database::calculate_efficiency( $data );
        $data['efficiency_score'] = $efficiency['efficiency_score'];
        $data['efficiency_grade'] = $efficiency['efficiency_grade'];

        // Validate tire_id.
        if ( empty( $data['tire_id'] ) ) {
            $data['tire_id'] = RTG_Database::get_next_tire_id();
        }

        if ( $editing_id > 0 ) {
            // Update existing.
            $existing = RTG_Database::get_tire_by_id( $editing_id );
            if ( ! $existing ) {
                wp_redirect( admin_url( 'admin.php?page=rtg-tires&message=error' ) );
                exit;
            }
            RTG_Database::update_tire( $existing['tire_id'], $data );
            wp_redirect( admin_url( 'admin.php?page=rtg-tires&message=updated' ) );
        } else {
            // Check uniqueness.
            if ( RTG_Database::tire_id_exists( $data['tire_id'] ) ) {
                wp_redirect( admin_url( 'admin.php?page=rtg-tire-edit&message=duplicate_id' ) );
                exit;
            }
            RTG_Database::insert_tire( $data );
            wp_redirect( admin_url( 'admin.php?page=rtg-tires&message=added' ) );
        }
        exit;
    }

    private function handle_tire_delete() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        $tire_id = sanitize_text_field( $_GET['tire_id'] );
        check_admin_referer( 'rtg_delete_' . $tire_id );

        RTG_Database::delete_tire( $tire_id );
        wp_redirect( admin_url( 'admin.php?page=rtg-tires&message=deleted' ) );
        exit;
    }

    private function handle_bulk_delete() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        check_admin_referer( 'rtg_bulk_action', 'rtg_bulk_nonce' );

        $tire_ids = array_map( 'sanitize_text_field', $_POST['tire_ids'] ?? array() );
        if ( ! empty( $tire_ids ) ) {
            RTG_Database::delete_tires( $tire_ids );
        }

        wp_redirect( admin_url( 'admin.php?page=rtg-tires&message=bulk_deleted' ) );
        exit;
    }

    private function handle_settings_save() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        check_admin_referer( 'rtg_save_settings', 'rtg_settings_nonce' );

        // Theme colors — sanitize hex values.
        $theme_colors = array();
        $raw_colors = $_POST['rtg_colors'] ?? array();
        $valid_keys = array( 'accent', 'accent_hover', 'bg_primary', 'bg_card', 'bg_input', 'bg_deep', 'text_primary', 'text_light', 'text_muted', 'text_heading', 'border' );
        foreach ( $valid_keys as $key ) {
            $val = isset( $raw_colors[ $key ] ) ? trim( $raw_colors[ $key ] ) : '';
            if ( preg_match( '/^#[0-9a-fA-F]{6}$/', $val ) ) {
                $theme_colors[ $key ] = strtolower( $val );
            }
        }

        $settings = array(
            'rows_per_page'          => intval( $_POST['rows_per_page'] ?? 12 ),
            'cdn_prefix'             => esc_url_raw( $_POST['cdn_prefix'] ?? '' ),
            'compare_slug'           => sanitize_title( $_POST['compare_slug'] ?? 'tire-compare' ),
            'server_side_pagination' => ! empty( $_POST['server_side_pagination'] ),
            'theme_colors'           => $theme_colors,
        );

        update_option( 'rtg_settings', $settings );

        // Save dropdown options (one value per line in textareas).
        $dropdown_fields = array( 'brands', 'categories', 'sizes', 'diameters', 'load_ranges', 'speed_ratings' );
        $dropdowns = array();
        foreach ( $dropdown_fields as $field ) {
            $raw = wp_unslash( $_POST[ 'rtg_dd_' . $field ] ?? '' );
            $lines = array_filter( array_map( 'trim', explode( "\n", sanitize_textarea_field( $raw ) ) ), 'strlen' );
            if ( ! empty( $lines ) ) {
                $dropdowns[ $field ] = array_values( $lines );
            }
        }

        // Save load index map (format: "119 = 2998" per line).
        $li_raw   = wp_unslash( $_POST['rtg_dd_load_indexes'] ?? '' );
        $li_lines = array_filter( array_map( 'trim', explode( "\n", sanitize_textarea_field( $li_raw ) ) ), 'strlen' );
        $li_map   = array();
        foreach ( $li_lines as $line ) {
            if ( strpos( $line, '=' ) !== false ) {
                list( $idx, $lbs ) = array_map( 'trim', explode( '=', $line, 2 ) );
                $idx = intval( $idx );
                $lbs = intval( $lbs );
                if ( $idx > 0 && $lbs > 0 ) {
                    $li_map[ $idx ] = $lbs;
                }
            }
        }
        if ( ! empty( $li_map ) ) {
            ksort( $li_map );
            $dropdowns['load_indexes'] = $li_map;
        }

        update_option( 'rtg_dropdown_options', $dropdowns );

        // Flush rewrite rules if compare slug changed.
        update_option( 'rtg_flush_rewrite', 1 );

        wp_redirect( admin_url( 'admin.php?page=rtg-settings&message=saved' ) );
        exit;
    }

    private function handle_rating_delete() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        $rating_id = intval( $_GET['rating_id'] );
        check_admin_referer( 'rtg_delete_rating_' . $rating_id );

        RTG_Database::delete_rating( $rating_id );

        // Redirect back to whichever page initiated the delete.
        $page = isset( $_GET['page'] ) && $_GET['page'] === 'rtg-reviews' ? 'rtg-reviews' : 'rtg-ratings';
        $redirect_args = array( 'page' => $page, 'message' => 'deleted' );
        if ( $page === 'rtg-reviews' && ! empty( $_GET['status'] ) ) {
            $redirect_args['status'] = sanitize_text_field( $_GET['status'] );
        }
        wp_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
        exit;
    }

    private function handle_review_status() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        $rating_id = intval( $_GET['rating_id'] );
        $action    = sanitize_text_field( $_GET['action'] );
        $status    = $action === 'approve_review' ? 'approved' : 'rejected';

        check_admin_referer( 'rtg_review_' . $action . '_' . $rating_id );

        RTG_Database::update_review_status( $rating_id, $status );

        // Preserve current tab in redirect.
        $redirect_args = array( 'page' => 'rtg-reviews', 'message' => $status );
        if ( ! empty( $_GET['status'] ) ) {
            $redirect_args['status'] = sanitize_text_field( $_GET['status'] );
        }

        wp_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
        exit;
    }

    // --- Wheel Handlers ---

    private function handle_wheel_save() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        check_admin_referer( 'rtg_wheel_save', 'rtg_wheel_nonce' );

        $editing_id = isset( $_POST['editing_id'] ) ? intval( $_POST['editing_id'] ) : 0;

        $post = wp_unslash( $_POST );

        $vehicles_arr = isset( $post['vehicles'] ) && is_array( $post['vehicles'] )
            ? array_map( 'sanitize_text_field', $post['vehicles'] )
            : array();

        $data = array(
            'name'       => sanitize_text_field( $post['wheel_name'] ?? '' ),
            'stock_size' => sanitize_text_field( $post['stock_size'] ?? '' ),
            'alt_sizes'  => sanitize_text_field( $post['alt_sizes'] ?? '' ),
            'image'      => esc_url_raw( $post['wheel_image'] ?? '' ),
            'vehicles'   => implode( ', ', $vehicles_arr ),
            'sort_order' => intval( $post['sort_order'] ?? 0 ),
        );

        if ( $editing_id > 0 ) {
            $existing = RTG_Database::get_wheel( $editing_id );
            if ( ! $existing ) {
                wp_redirect( admin_url( 'admin.php?page=rtg-wheels&message=error' ) );
                exit;
            }
            RTG_Database::update_wheel( $editing_id, $data );
            wp_redirect( admin_url( 'admin.php?page=rtg-wheels&message=updated' ) );
        } else {
            RTG_Database::insert_wheel( $data );
            wp_redirect( admin_url( 'admin.php?page=rtg-wheels&message=added' ) );
        }
        exit;
    }

    private function handle_wheel_delete() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        $wheel_id = intval( $_GET['wheel_id'] );
        check_admin_referer( 'rtg_delete_wheel_' . $wheel_id );

        RTG_Database::delete_wheel( $wheel_id );
        wp_redirect( admin_url( 'admin.php?page=rtg-wheels&message=deleted' ) );
        exit;
    }

    // --- CSV Import / Export ---

    /**
     * CSV column order — matches the database columns used for tire data.
     */
    private static $csv_columns = array(
        'tire_id', 'size', 'diameter', 'brand', 'model', 'category',
        'price', 'mileage_warranty', 'weight_lb', 'three_pms', 'tread',
        'load_index', 'max_load_lb', 'load_range', 'speed_rating', 'psi',
        'utqg', 'tags', 'link', 'image', 'bundle_link', 'review_link', 'sort_order',
    );

    private function handle_csv_export() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        check_admin_referer( 'rtg_csv_export' );

        $tires = RTG_Database::get_all_tires();

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="rivian-tire-guide-' . gmdate( 'Y-m-d' ) . '.csv"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $output = fopen( 'php://output', 'w' );

        // Header row.
        fputcsv( $output, self::$csv_columns );

        // Data rows.
        foreach ( $tires as $tire ) {
            $row = array();
            foreach ( self::$csv_columns as $col ) {
                $row[] = isset( $tire[ $col ] ) ? $tire[ $col ] : '';
            }
            fputcsv( $output, $row );
        }

        fclose( $output );
        exit;
    }

    private function handle_csv_import() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        check_admin_referer( 'rtg_csv_import', 'rtg_import_nonce' );

        if ( empty( $_FILES['rtg_csv_file']['tmp_name'] ) ) {
            wp_redirect( admin_url( 'admin.php?page=rtg-import&message=no_file' ) );
            exit;
        }

        $file = $_FILES['rtg_csv_file'];

        // Validate file type.
        $ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
        if ( $ext !== 'csv' ) {
            wp_redirect( admin_url( 'admin.php?page=rtg-import&message=invalid_type' ) );
            exit;
        }

        // Limit file size to 2MB.
        if ( $file['size'] > 2 * 1024 * 1024 ) {
            wp_redirect( admin_url( 'admin.php?page=rtg-import&message=too_large' ) );
            exit;
        }

        $handle = fopen( $file['tmp_name'], 'r' );
        if ( ! $handle ) {
            wp_redirect( admin_url( 'admin.php?page=rtg-import&message=read_error' ) );
            exit;
        }

        // Read header row.
        $header = fgetcsv( $handle );
        if ( ! $header ) {
            fclose( $handle );
            wp_redirect( admin_url( 'admin.php?page=rtg-import&message=empty_file' ) );
            exit;
        }

        // Normalize headers.
        $header = array_map( function ( $h ) {
            return strtolower( trim( $h ) );
        }, $header );

        // Build column index map.
        $col_map = array();
        foreach ( self::$csv_columns as $col ) {
            $index = array_search( $col, $header, true );
            if ( false !== $index ) {
                $col_map[ $col ] = $index;
            }
        }

        // Must have at least brand and model columns.
        if ( ! isset( $col_map['brand'] ) || ! isset( $col_map['model'] ) ) {
            fclose( $handle );
            wp_redirect( admin_url( 'admin.php?page=rtg-import&message=missing_columns' ) );
            exit;
        }

        $imported = 0;
        $updated  = 0;
        $skipped  = 0;
        $mode     = sanitize_text_field( wp_unslash( $_POST['import_mode'] ?? 'skip' ) );

        while ( ( $row = fgetcsv( $handle ) ) !== false ) {
            if ( empty( array_filter( $row ) ) ) {
                continue; // Skip blank rows.
            }

            $data = array();
            foreach ( self::$csv_columns as $col ) {
                if ( isset( $col_map[ $col ] ) && isset( $row[ $col_map[ $col ] ] ) ) {
                    $data[ $col ] = trim( $row[ $col_map[ $col ] ] );
                } else {
                    $data[ $col ] = '';
                }
            }

            // Sanitize.
            $data = $this->sanitize_tire_import( $data );

            // Auto-generate tire_id if blank.
            if ( empty( $data['tire_id'] ) ) {
                $data['tire_id'] = RTG_Database::get_next_tire_id();
            }

            // Auto-calculate efficiency.
            $efficiency = RTG_Database::calculate_efficiency( $data );
            $data['efficiency_score'] = $efficiency['efficiency_score'];
            $data['efficiency_grade'] = $efficiency['efficiency_grade'];

            // Check for duplicates.
            if ( RTG_Database::tire_id_exists( $data['tire_id'] ) ) {
                if ( $mode === 'update' ) {
                    RTG_Database::update_tire( $data['tire_id'], $data );
                    $updated++;
                } else {
                    $skipped++;
                }
            } else {
                RTG_Database::insert_tire( $data );
                $imported++;
            }
        }

        fclose( $handle );

        $args = array(
            'page'     => 'rtg-import',
            'message'  => 'imported',
            'imported' => $imported,
            'updated'  => $updated,
            'skipped'  => $skipped,
        );
        wp_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
        exit;
    }

    private function sanitize_tire_import( $data ) {
        $text_fields = array(
            'tire_id', 'size', 'diameter', 'brand', 'model', 'category',
            'three_pms', 'tread', 'load_index', 'load_range', 'speed_rating',
            'psi', 'utqg', 'tags',
        );
        foreach ( $text_fields as $field ) {
            $data[ $field ] = sanitize_text_field( $data[ $field ] ?? '' );
        }

        $data['price']            = floatval( $data['price'] ?? 0 );
        $data['mileage_warranty'] = intval( $data['mileage_warranty'] ?? 0 );
        $data['weight_lb']        = floatval( $data['weight_lb'] ?? 0 );
        $data['max_load_lb']      = intval( $data['max_load_lb'] ?? 0 );
        $data['sort_order']       = intval( $data['sort_order'] ?? 0 );

        $data['link']        = esc_url_raw( $data['link'] ?? '' );
        $data['image']       = esc_url_raw( $data['image'] ?? '' );
        $data['bundle_link'] = esc_url_raw( $data['bundle_link'] ?? '' );
        $data['review_link'] = esc_url_raw( $data['review_link'] ?? '' );

        return $data;
    }
}
