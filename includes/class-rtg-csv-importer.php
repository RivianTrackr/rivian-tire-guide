<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RTG_CSV_Importer {

    /**
     * CSV header to database column mapping.
     */
    private static $header_map = array(
        'Tire ID'           => 'tire_id',
        'Size'              => 'size',
        'Diameter'          => 'diameter',
        'Brand'             => 'brand',
        'Model'             => 'model',
        'Category'          => 'category',
        'Price'             => 'price',
        'Mileage Warranty'  => 'mileage_warranty',
        'Weight (lb)'       => 'weight_lb',
        '3PMS'              => 'three_pms',
        'Tread'             => 'tread',
        'Load Index'        => 'load_index',
        'Max Load (lb)'     => 'max_load_lb',
        'Load Range'        => 'load_range',
        'Speed (mph)'       => 'speed_rating',
        'PSI'               => 'psi',
        'UTQG'              => 'utqg',
        'Tags'              => 'tags',
        'Link'              => 'link',
        'Image'             => 'image',
        'Efficiency Score'  => 'efficiency_score',
        'Efficiency Grade'  => 'efficiency_grade',
        'Bundle Link'       => 'bundle_link',
    );

    /**
     * Import tires from a CSV file.
     *
     * @param string $file_path Path to CSV file.
     * @param bool   $update_existing Whether to update existing tires by tire_id.
     * @return array ['inserted' => int, 'updated' => int, 'skipped' => int, 'errors' => array]
     */
    public static function import( $file_path, $update_existing = true ) {
        $result = array(
            'inserted' => 0,
            'updated'  => 0,
            'skipped'  => 0,
            'errors'   => array(),
        );

        if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
            $result['errors'][] = 'File not found or not readable.';
            return $result;
        }

        $handle = fopen( $file_path, 'r' );
        if ( ! $handle ) {
            $result['errors'][] = 'Could not open file.';
            return $result;
        }

        // Read header row.
        $raw_headers = fgetcsv( $handle );
        if ( ! $raw_headers ) {
            fclose( $handle );
            $result['errors'][] = 'CSV file is empty or has no header row.';
            return $result;
        }

        // Clean headers: remove BOM, trim whitespace, normalize multi-line headers.
        $headers = array_map( function ( $h ) {
            $h = preg_replace( '/\x{FEFF}/u', '', $h ); // Remove BOM.
            $h = preg_replace( '/\s+/', ' ', $h );       // Collapse whitespace/newlines.
            return trim( $h );
        }, $raw_headers );

        // Map header positions to DB columns.
        $col_map = array();
        foreach ( $headers as $index => $header ) {
            // Try exact match first.
            if ( isset( self::$header_map[ $header ] ) ) {
                $col_map[ $index ] = self::$header_map[ $header ];
                continue;
            }
            // Try case-insensitive match.
            foreach ( self::$header_map as $csv_header => $db_col ) {
                if ( strcasecmp( $header, $csv_header ) === 0 ) {
                    $col_map[ $index ] = $db_col;
                    break;
                }
            }
        }

        // Verify we have at minimum tire_id, brand, model.
        $mapped_cols = array_values( $col_map );
        if ( ! in_array( 'tire_id', $mapped_cols, true ) ) {
            fclose( $handle );
            $result['errors'][] = 'CSV must contain a "Tire ID" column.';
            return $result;
        }

        $row_num = 1; // Header was row 1.
        while ( ( $row = fgetcsv( $handle ) ) !== false ) {
            $row_num++;

            // Skip empty rows.
            if ( empty( array_filter( $row, function ( $v ) { return trim( $v ) !== ''; } ) ) ) {
                continue;
            }

            $data = self::map_row( $row, $col_map );

            if ( empty( $data['tire_id'] ) ) {
                $result['errors'][] = "Row {$row_num}: Missing Tire ID, skipped.";
                $result['skipped']++;
                continue;
            }

            $data = self::sanitize_tire_data( $data );

            // Auto-calculate efficiency score and grade from tire data.
            $efficiency = RTG_Database::calculate_efficiency( $data );
            $data['efficiency_score'] = $efficiency['efficiency_score'];
            $data['efficiency_grade'] = $efficiency['efficiency_grade'];

            // Check if tire exists.
            $existing = RTG_Database::get_tire( $data['tire_id'] );

            if ( $existing ) {
                if ( $update_existing ) {
                    $update_data = $data;
                    unset( $update_data['tire_id'] ); // Don't update the key.
                    RTG_Database::update_tire( $data['tire_id'], $update_data );
                    $result['updated']++;
                } else {
                    $result['skipped']++;
                }
            } else {
                $insert_result = RTG_Database::insert_tire( $data );
                if ( $insert_result ) {
                    $result['inserted']++;
                } else {
                    $result['errors'][] = "Row {$row_num} ({$data['tire_id']}): Insert failed.";
                }
            }
        }

        fclose( $handle );
        return $result;
    }

    /**
     * Map a CSV row to an associative array using the column map.
     */
    private static function map_row( $row, $col_map ) {
        $data = array();
        foreach ( $col_map as $csv_index => $db_col ) {
            $data[ $db_col ] = isset( $row[ $csv_index ] ) ? $row[ $csv_index ] : '';
        }
        return $data;
    }

    /**
     * Sanitize tire data for database insertion.
     */
    private static function sanitize_tire_data( $data ) {
        $sanitized = array();

        foreach ( $data as $key => $value ) {
            $value = is_string( $value ) ? trim( $value ) : $value;

            switch ( $key ) {
                case 'tire_id':
                    $sanitized[ $key ] = sanitize_text_field( $value );
                    break;

                case 'price':
                    $sanitized[ $key ] = floatval( $value );
                    break;

                case 'weight_lb':
                    $sanitized[ $key ] = floatval( $value );
                    break;

                case 'mileage_warranty':
                case 'max_load_lb':
                case 'efficiency_score':
                case 'sort_order':
                    $sanitized[ $key ] = intval( $value );
                    break;

                case 'link':
                case 'image':
                case 'bundle_link':
                    $sanitized[ $key ] = esc_url_raw( $value );
                    break;

                case 'efficiency_grade':
                    $grade = strtoupper( sanitize_text_field( $value ) );
                    $sanitized[ $key ] = in_array( $grade, array( 'A', 'B', 'C', 'D', 'E', 'F' ), true ) ? $grade : '';
                    break;

                case 'diameter':
                    // Preserve the quotes in diameter values like '33"'.
                    $sanitized[ $key ] = sanitize_text_field( $value );
                    break;

                default:
                    $sanitized[ $key ] = sanitize_text_field( $value );
                    break;
            }
        }

        return $sanitized;
    }
}
