<?php
/**
 * Accelvia DataForge – CSV Parser
 *
 * Handles CSV file validation, parsing, and column detection.
 * Uses WP_Filesystem for file reading (WordPress.org compliant).
 * Temporary files are cleaned up after parsing — never stored permanently.
 *
 * @package Accelvia_DataForge
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Accelvia_DF_CSV_Parser {

    /**
     * Allowed MIME types for CSV upload.
     *
     * @var array
     */
    const ALLOWED_MIMES = array(
        'csv' => 'text/csv',
    );

    /**
     * Default maximum file size in bytes (2MB).
     *
     * @var int
     */
    const DEFAULT_MAX_SIZE = 2097152;

    /**
     * Validate an uploaded CSV file.
     *
     * @param array $file $_FILES array entry.
     * @return true|WP_Error True if valid, WP_Error otherwise.
     */
    public static function validate_file( $file ) {
        // Check for upload errors
        if ( ! isset( $file['tmp_name'] ) || empty( $file['tmp_name'] ) ) {
            return new WP_Error( 'no_file', __( 'No file was uploaded.', 'accelvia-dataforge' ) );
        }

        if ( ! empty( $file['error'] ) ) {
            return new WP_Error( 'upload_error', __( 'File upload error.', 'accelvia-dataforge' ) );
        }

        // Check extension
        $filetype = wp_check_filetype( $file['name'], self::ALLOWED_MIMES );
        if ( empty( $filetype['ext'] ) || 'csv' !== $filetype['ext'] ) {
            return new WP_Error( 'invalid_type', __( 'Only CSV files are allowed.', 'accelvia-dataforge' ) );
        }

        // Check file size
        $max_size = (int) get_option( 'accelvia_df_max_csv_size', self::DEFAULT_MAX_SIZE );
        if ( $file['size'] > $max_size ) {
            return new WP_Error(
                'file_too_large',
                sprintf(
                    /* translators: %s: Maximum file size in MB */
                    __( 'File exceeds the maximum size of %s MB.', 'accelvia-dataforge' ),
                    number_format( $max_size / 1048576, 1 )
                )
            );
        }

        return true;
    }

    /**
     * Parse a CSV file and return structured data.
     *
     * @param string $file_path Absolute path to the CSV file.
     * @return array|WP_Error Array with 'headers' and 'rows', or WP_Error.
     */
    public static function parse_file( $file_path ) {
        // Initialize WP_Filesystem
        global $wp_filesystem;
        if ( empty( $wp_filesystem ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        if ( ! $wp_filesystem->exists( $file_path ) ) {
            return new WP_Error( 'file_not_found', __( 'CSV file not found.', 'accelvia-dataforge' ) );
        }

        $content = $wp_filesystem->get_contents( $file_path );
        if ( false === $content ) {
            return new WP_Error( 'read_error', __( 'Unable to read CSV file.', 'accelvia-dataforge' ) );
        }

        // Parse CSV content
        $lines = preg_split( '/\r\n|\r|\n/', $content );
        if ( empty( $lines ) ) {
            return new WP_Error( 'empty_file', __( 'CSV file is empty.', 'accelvia-dataforge' ) );
        }

        // Max data points setting
        $max_rows = (int) get_option( 'accelvia_df_max_data_points', 1000 );

        $headers = array();
        $rows    = array();

        foreach ( $lines as $index => $line ) {
            $line = trim( $line );
            if ( empty( $line ) ) {
                continue;
            }

            // Parse the CSV line
            $parsed = str_getcsv( $line );

            if ( 0 === $index || empty( $headers ) ) {
                // First non-empty line = headers
                $headers = array_map( 'sanitize_text_field', $parsed );
                continue;
            }

            // Data rows
            if ( count( $rows ) >= $max_rows ) {
                break;
            }

            // Pad or trim to match header count
            $row = array_pad( $parsed, count( $headers ), '' );
            $row = array_slice( $row, 0, count( $headers ) );
            $rows[] = array_map( 'sanitize_text_field', $row );
        }

        if ( empty( $headers ) ) {
            return new WP_Error( 'no_headers', __( 'CSV file has no valid header row.', 'accelvia-dataforge' ) );
        }

        if ( empty( $rows ) ) {
            return new WP_Error( 'no_data', __( 'CSV file has no data rows.', 'accelvia-dataforge' ) );
        }

        return array(
            'headers'   => $headers,
            'rows'      => $rows,
            'row_count' => count( $rows ),
            'col_count' => count( $headers ),
        );
    }

    /**
     * Detect which columns are numeric vs text.
     *
     * @param array $parsed_data Parsed CSV data from parse_file().
     * @return array Column analysis with type info.
     */
    public static function detect_columns( $parsed_data ) {
        $headers = $parsed_data['headers'];
        $rows    = $parsed_data['rows'];
        $columns = array();

        foreach ( $headers as $col_index => $header ) {
            $numeric_count = 0;
            $text_count    = 0;
            $sample_values = array();

            foreach ( $rows as $row_index => $row ) {
                $value = isset( $row[ $col_index ] ) ? trim( $row[ $col_index ] ) : '';

                if ( $row_index < 5 ) {
                    $sample_values[] = $value;
                }

                if ( '' === $value ) {
                    continue;
                }

                if ( is_numeric( $value ) ) {
                    $numeric_count++;
                } else {
                    $text_count++;
                }
            }

            $total = $numeric_count + $text_count;
            $is_numeric = ( $total > 0 ) && ( $numeric_count / $total > 0.8 );

            $columns[] = array(
                'index'   => $col_index,
                'name'    => $header,
                'type'    => $is_numeric ? 'numeric' : 'text',
                'samples' => $sample_values,
            );
        }

        return $columns;
    }

    /**
     * Convert parsed CSV data to chart-ready format using column mapping.
     *
     * @param array  $parsed_data Parsed CSV from parse_file().
     * @param int    $label_col   Index of the column to use as labels.
     * @param array  $value_cols  Array of column indices to use as value series.
     * @param array  $series_names Optional. Names for each series.
     * @return array Chart-ready data with 'labels' and 'series' arrays.
     */
    public static function to_chart_data( $parsed_data, $label_col, $value_cols, $series_names = array() ) {
        $rows   = $parsed_data['rows'];
        $headers = $parsed_data['headers'];
        $labels = array();
        $series = array();

        // Initialize series arrays
        foreach ( $value_cols as $i => $col_index ) {
            $name = ! empty( $series_names[ $i ] )
                ? sanitize_text_field( $series_names[ $i ] )
                : ( isset( $headers[ $col_index ] ) ? $headers[ $col_index ] : 'Series ' . ( $i + 1 ) );

            $series[] = array(
                'name' => $name,
                'data' => array(),
            );
        }

        // Populate data
        foreach ( $rows as $row ) {
            $label = isset( $row[ $label_col ] ) ? $row[ $label_col ] : '';
            $labels[] = $label;

            foreach ( $value_cols as $i => $col_index ) {
                $value = isset( $row[ $col_index ] ) ? $row[ $col_index ] : 0;
                $series[ $i ]['data'][] = is_numeric( $value ) ? floatval( $value ) : 0;
            }
        }

        $result = array(
            'labels' => $labels,
            'series' => $series,
        );

        return apply_filters( 'accelvia_df_csv_parsed', $result, $parsed_data );
    }
}
