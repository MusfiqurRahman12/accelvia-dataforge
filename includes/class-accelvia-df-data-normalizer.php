<?php
/**
 * Accelvia DataForge – Data Normalizer
 *
 * Abstraction layer between raw data sources (manual entry, CSV, import)
 * and the final ApexCharts-ready chart configuration.
 *
 * @package Accelvia_DataForge
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Accelvia_DF_Data_Normalizer {

    /**
     * Normalize manual data entry into multi-series chart format.
     *
     * @param array $labels      Array of label strings.
     * @param array $series_data Array of series, each with 'name' and 'data' keys.
     * @return array Normalized data with 'labels' and 'series'.
     */
    public static function normalize_manual( $labels, $series_data ) {
        $clean_labels = array();
        $clean_series = array();

        // Sanitize labels
        foreach ( $labels as $label ) {
            $clean_labels[] = sanitize_text_field( $label );
        }

        // Sanitize series
        foreach ( $series_data as $s ) {
            $name = isset( $s['name'] ) ? sanitize_text_field( $s['name'] ) : __( 'Series', 'accelvia-dataforge' );
            $data = array();

            if ( isset( $s['data'] ) && is_array( $s['data'] ) ) {
                foreach ( $s['data'] as $val ) {
                    $data[] = is_numeric( $val ) ? floatval( $val ) : 0;
                }
            }

            // Pad data to match labels count
            while ( count( $data ) < count( $clean_labels ) ) {
                $data[] = 0;
            }
            $data = array_slice( $data, 0, count( $clean_labels ) );

            $clean_series[] = array(
                'name' => $name,
                'data' => $data,
            );
        }

        return array(
            'labels' => $clean_labels,
            'series' => $clean_series,
        );
    }

    /**
     * Normalize CSV parser output into chart format.
     *
     * @param array $csv_chart_data Output from Accelvia_DF_CSV_Parser::to_chart_data().
     * @return array Normalized data with 'labels' and 'series'.
     */
    public static function normalize_csv( $csv_chart_data ) {
        // CSV parser already returns clean format, just validate structure
        $labels = isset( $csv_chart_data['labels'] ) ? $csv_chart_data['labels'] : array();
        $series = isset( $csv_chart_data['series'] ) ? $csv_chart_data['series'] : array();

        return self::normalize_manual( $labels, $series );
    }

    /**
     * Normalize an imported JSON chart config.
     *
     * @param array $config The imported chart config array.
     * @return array|WP_Error Normalized data or WP_Error.
     */
    public static function normalize_import( $config ) {
        if ( ! is_array( $config ) ) {
            return new WP_Error( 'invalid_import', __( 'Import data is not valid.', 'accelvia-dataforge' ) );
        }

        // Must have chart type
        if ( empty( $config['chart']['type'] ) ) {
            return new WP_Error( 'missing_type', __( 'Imported chart has no chart type.', 'accelvia-dataforge' ) );
        }

        // Validate chart type
        if ( ! in_array( $config['chart']['type'], Accelvia_DF_Chart_Model::CHART_TYPES, true ) ) {
            return new WP_Error( 'invalid_type', __( 'Imported chart has an unsupported chart type.', 'accelvia-dataforge' ) );
        }

        // Sanitize through the existing model sanitizer
        return Accelvia_DF_Chart_Model::sanitize_config( $config );
    }

    /**
     * Convert normalized data into an ApexCharts-ready config.
     *
     * @param array  $normalized_data Data with 'labels' and 'series' keys.
     * @param string $chart_type      Chart type (bar, line, pie, etc.).
     * @param array  $options         Additional chart options (colors, legend, etc.).
     * @return array ApexCharts config array.
     */
    public static function to_series_config( $normalized_data, $chart_type = 'bar', $options = array() ) {
        $labels = $normalized_data['labels'];
        $series = $normalized_data['series'];
        $is_pie = in_array( $chart_type, array( 'pie', 'donut' ), true );

        $config = Accelvia_DF_Chart_Model::get_default_config( $chart_type );

        // Apply options
        if ( ! empty( $options['colors'] ) ) {
            $config['colors'] = $options['colors'];
        }
        if ( isset( $options['legend'] ) ) {
            $config['legend']['show'] = (bool) $options['legend'];
        }
        if ( isset( $options['grid'] ) && ! $is_pie ) {
            $config['grid']['show'] = (bool) $options['grid'];
        }
        if ( isset( $options['animation'] ) ) {
            $config['chart']['animations']['enabled'] = (bool) $options['animation'];
        }
        if ( isset( $options['dataLabels'] ) ) {
            $config['dataLabels']['enabled'] = (bool) $options['dataLabels'];
        }
        if ( isset( $options['toolbar'] ) ) {
            $config['chart']['toolbar']['show'] = (bool) $options['toolbar'];
        }
        if ( isset( $options['height'] ) ) {
            $config['chart']['height'] = absint( $options['height'] );
        }

        // Set data based on chart type
        if ( $is_pie ) {
            // Pie/donut: flat series array + labels
            $config['series'] = ! empty( $series[0]['data'] ) ? $series[0]['data'] : array();
            $config['labels'] = $labels;
        } else {
            // Categorical charts: series with name + data
            $config['series'] = $series;
            $config['xaxis']  = array( 'categories' => $labels );
        }

        return apply_filters( 'accelvia_df_normalized_config', $config, $normalized_data, $chart_type );
    }
}
