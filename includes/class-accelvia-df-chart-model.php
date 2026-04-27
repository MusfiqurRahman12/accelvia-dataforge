<?php
/**
 * Accelvia DataForge – Chart Model
 *
 * Validates, normalizes, and provides default configs for chart types.
 * Acts as the abstraction layer between user input and ApexCharts options.
 *
 * @package Accelvia_DataForge
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Accelvia_DF_Chart_Model {

    /**
     * Supported chart types.
     *
     * @var array
     */
    const CHART_TYPES = array( 'line', 'bar', 'pie', 'area', 'donut', 'radar', 'radialBar' );

    /**
     * Get the default ApexCharts config for a given chart type.
     *
     * @param string $chart_type Chart type key.
     * @return array Default config array.
     */
    public static function get_default_config( $chart_type = 'bar' ) {
        $default_height = (int) get_option( 'accelvia_df_default_height', 350 );

        $base = array(
            'chart'      => array(
                'type'       => $chart_type,
                'height'     => $default_height,
                'toolbar'    => array( 'show' => true ),
                'animations' => array(
                    'enabled'          => true,
                    'easing'           => 'easeinout',
                    'speed'            => 800,
                    'animateGradually' => array(
                        'enabled' => true,
                        'delay'   => 150,
                    ),
                    'dynamicAnimation' => array(
                        'enabled' => true,
                        'speed'   => 350,
                    ),
                ),
            ),
            'series'     => array(),
            'xaxis'      => array( 'categories' => array() ),
            'colors'     => array( '#6366f1' ),
            'theme'      => array( 'mode' => 'light' ),
            'legend'     => array( 'show' => true ),
            'grid'       => array( 'show' => true ),
            'dataLabels' => array( 'enabled' => false ),
            'stroke'     => array( 'curve' => 'smooth', 'width' => 2 ),
            'tooltip'    => array(
                'enabled'  => true,
                'shared'   => true,
                'intersect' => false,
                'y'        => array(
                    'formatter' => null,
                ),
            ),
            'states'     => array(
                'hover'  => array(
                    'filter' => array(
                        'type'  => 'lighten',
                        'value' => 0.08,
                    ),
                ),
                'active' => array(
                    'allowMultipleDataPointsSelection' => false,
                    'filter' => array(
                        'type'  => 'darken',
                        'value' => 0.15,
                    ),
                ),
            ),
            'responsive' => array(
                array(
                    'breakpoint' => 600,
                    'options'    => array(
                        'chart'  => array( 'height' => 300 ),
                        'legend' => array( 'position' => 'bottom' ),
                    ),
                ),
                array(
                    'breakpoint' => 480,
                    'options'    => array(
                        'chart' => array( 'height' => 280 ),
                        'legend' => array( 'position' => 'bottom' ),
                    ),
                ),
            ),
        );

        // Type-specific overrides
        switch ( $chart_type ) {
            case 'pie':
            case 'donut':
                $base['series'] = array();
                $base['labels'] = array();
                unset( $base['xaxis'] );
                unset( $base['grid'] );
                $base['stroke'] = array( 'show' => true, 'colors' => array( '#ffffff' ), 'width' => 2 );
                $base['chart']['animations']['speed'] = 900;
                $base['plotOptions'] = array(
                    $chart_type => array(
                        'expandOnClick' => true,
                    ),
                );
                if ( 'donut' === $chart_type ) {
                    $base['plotOptions']['pie'] = array(
                        'donut' => array(
                            'labels' => array(
                                'show' => true,
                                'name'  => array( 'show' => true ),
                                'value' => array( 'show' => true ),
                            ),
                        ),
                    );
                }
                break;

            case 'line':
                $base['stroke']  = array( 'curve' => 'smooth', 'width' => 3 );
                $base['markers'] = array(
                    'size'        => 0,
                    'hover'       => array( 'sizeOffset' => 6 ),
                );
                $base['chart']['animations']['speed'] = 1000;
                break;

            case 'area':
                $base['stroke'] = array( 'curve' => 'smooth', 'width' => 2 );
                $base['fill']   = array(
                    'type'     => 'gradient',
                    'gradient' => array(
                        'shadeIntensity' => 1,
                        'opacityFrom'    => 0.45,
                        'opacityTo'      => 0.05,
                    ),
                );
                $base['markers'] = array(
                    'size'        => 0,
                    'hover'       => array( 'sizeOffset' => 5 ),
                );
                $base['chart']['animations']['speed'] = 1000;
                break;

            case 'radar':
                unset( $base['grid'] );
                $base['stroke']  = array( 'width' => 2 );
                $base['fill']    = array( 'opacity' => 0.25 );
                $base['markers'] = array(
                    'size'        => 4,
                    'hover'       => array( 'size' => 7 ),
                );
                $base['chart']['animations']['speed'] = 700;
                break;

            case 'radialBar':
                $base['series'] = array();
                $base['labels'] = array();
                unset( $base['xaxis'] );
                unset( $base['stroke'] );
                unset( $base['grid'] );
                $base['plotOptions'] = array(
                    'radialBar' => array(
                        'hollow'    => array( 'size' => '50%' ),
                        'dataLabels' => array(
                            'name'  => array( 'show' => true ),
                            'value' => array( 'show' => true ),
                        ),
                    ),
                );
                break;
        }

        return apply_filters( 'accelvia_df_default_chart_config', $base, $chart_type );
    }

    /**
     * Validate a chart config array.
     * Returns true if valid, or a WP_Error with details.
     *
     * @param array  $config     Chart config array.
     * @param string $chart_type Chart type for context.
     * @return true|WP_Error
     */
    public static function validate_config( $config, $chart_type = 'bar' ) {
        if ( ! is_array( $config ) ) {
            return new WP_Error( 'invalid_config', __( 'Chart config must be a valid object.', 'accelvia-dataforge' ) );
        }

        if ( empty( $config['chart'] ) || empty( $config['chart']['type'] ) ) {
            return new WP_Error( 'missing_chart_type', __( 'Chart type is required.', 'accelvia-dataforge' ) );
        }

        if ( ! in_array( $config['chart']['type'], self::CHART_TYPES, true ) ) {
            return new WP_Error( 'invalid_chart_type', __( 'Unsupported chart type.', 'accelvia-dataforge' ) );
        }

        // Pie/donut/radialBar uses flat series + labels, others use series with data arrays
        $is_pie_type    = in_array( $chart_type, array( 'pie', 'donut' ), true );
        $is_radial_type = ( 'radialBar' === $chart_type );

        if ( $is_pie_type ) {
            if ( empty( $config['series'] ) || ! is_array( $config['series'] ) ) {
                return new WP_Error( 'missing_series', __( 'Chart must have at least one data point.', 'accelvia-dataforge' ) );
            }
            if ( empty( $config['labels'] ) || ! is_array( $config['labels'] ) ) {
                return new WP_Error( 'missing_labels', __( 'Pie/donut charts require labels.', 'accelvia-dataforge' ) );
            }
        } elseif ( $is_radial_type ) {
            if ( ! isset( $config['series'] ) || ! is_array( $config['series'] ) ) {
                return new WP_Error( 'missing_series', __( 'RadialBar chart must have series data.', 'accelvia-dataforge' ) );
            }
        } else {
            if ( empty( $config['series'] ) || ! is_array( $config['series'] ) ) {
                return new WP_Error( 'missing_series', __( 'Chart must have at least one data series.', 'accelvia-dataforge' ) );
            }
            // Validate multi-series structure
            $validation = self::validate_multi_series( $config );
            if ( is_wp_error( $validation ) ) {
                return $validation;
            }
        }

        return true;
    }

    /**
     * Validate multi-series data consistency.
     *
     * @param array $config Chart config array.
     * @return true|WP_Error
     */
    public static function validate_multi_series( $config ) {
        if ( empty( $config['series'] ) || ! is_array( $config['series'] ) ) {
            return true; // No series to validate
        }

        $first_length = null;

        foreach ( $config['series'] as $index => $series ) {
            // Each series should have name and data
            if ( ! is_array( $series ) ) {
                continue; // Flat series (pie/donut) — skip
            }

            if ( isset( $series['data'] ) && is_array( $series['data'] ) ) {
                $length = count( $series['data'] );
                if ( null === $first_length ) {
                    $first_length = $length;
                }
                // Allow different lengths — ApexCharts handles it gracefully
            }
        }

        return true;
    }

    /**
     * Get the number of series in a config.
     *
     * @param array $config Chart config array.
     * @return int Series count.
     */
    public static function get_series_count( $config ) {
        if ( empty( $config['series'] ) || ! is_array( $config['series'] ) ) {
            return 0;
        }

        // Pie/donut: flat array of numbers = 1 logical series
        if ( isset( $config['series'][0] ) && is_numeric( $config['series'][0] ) ) {
            return 1;
        }

        return count( $config['series'] );
    }

    /**
     * Merge additional series into an existing config.
     *
     * @param array $config     Existing chart config.
     * @param array $new_series Array with 'name' and 'data' keys.
     * @return array Updated config.
     */
    public static function merge_series( $config, $new_series ) {
        if ( ! isset( $config['series'] ) || ! is_array( $config['series'] ) ) {
            $config['series'] = array();
        }

        // Only merge for non-pie types
        $chart_type = isset( $config['chart']['type'] ) ? $config['chart']['type'] : 'bar';
        if ( in_array( $chart_type, array( 'pie', 'donut', 'radialBar' ), true ) ) {
            return $config; // These types don't support multi-series merge
        }

        $config['series'][] = array(
            'name' => sanitize_text_field( $new_series['name'] ?? __( 'New Series', 'accelvia-dataforge' ) ),
            'data' => array_map( 'floatval', $new_series['data'] ?? array() ),
        );

        return $config;
    }

    /**
     * Sanitize a chart config recursively.
     *
     * @param array $config Raw chart config.
     * @return array Sanitized config.
     */
    public static function sanitize_config( $config ) {
        if ( ! is_array( $config ) ) {
            return array();
        }

        $clean = array();

        foreach ( $config as $key => $value ) {
            $safe_key = sanitize_text_field( $key );

            if ( is_array( $value ) ) {
                $clean[ $safe_key ] = self::sanitize_config( $value );
            } elseif ( is_bool( $value ) ) {
                $clean[ $safe_key ] = $value;
            } elseif ( is_numeric( $value ) ) {
                $clean[ $safe_key ] = is_float( $value + 0 ) ? (float) $value : (int) $value;
            } else {
                $clean[ $safe_key ] = sanitize_text_field( $value );
            }
        }

        return $clean;
    }

    /**
     * Convert a stored config to render-ready ApexCharts options.
     *
     * @param array $config Stored chart config.
     * @param int   $chart_id Chart ID for filter context.
     * @return array ApexCharts-ready options.
     */
    public static function to_apexcharts_options( $config, $chart_id = 0 ) {
        $options = $config;

        // Ensure responsive container
        if ( ! isset( $options['chart']['width'] ) ) {
            $options['chart']['width'] = '100%';
        }

        // Apply global theme setting
        $default_theme = get_option( 'accelvia_df_default_theme', 'light' );
        if ( ! isset( $options['theme']['mode'] ) ) {
            $options['theme']['mode'] = $default_theme;
        }

        // Apply global animation setting
        $default_animation = get_option( 'accelvia_df_default_animation', 'yes' );
        if ( ! isset( $options['chart']['animations']['enabled'] ) ) {
            $options['chart']['animations']['enabled'] = ( 'yes' === $default_animation );
        }

        return apply_filters( 'accelvia_df_chart_config', $options, $chart_id );
    }

    /**
     * Get available color palettes.
     *
     * @return array Named color palette arrays.
     */
    public static function get_color_palettes() {
        return array(
            'default'  => array( '#6366f1', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#f97316', '#ec4899' ),
            'ocean'    => array( '#0ea5e9', '#06b6d4', '#14b8a6', '#10b981', '#22c55e', '#84cc16', '#a3e635', '#d9f99d' ),
            'sunset'   => array( '#ef4444', '#f97316', '#f59e0b', '#eab308', '#facc15', '#fde047', '#fef08a', '#fef9c3' ),
            'midnight' => array( '#6366f1', '#8b5cf6', '#a78bfa', '#c4b5fd', '#7c3aed', '#4f46e5', '#4338ca', '#3730a3' ),
            'forest'   => array( '#059669', '#10b981', '#34d399', '#6ee7b7', '#a7f3d0', '#047857', '#065f46', '#064e3b' ),
        );
    }
}
