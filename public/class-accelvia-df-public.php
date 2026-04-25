<?php
/**
 * Accelvia DataForge – Public/Frontend Controller
 *
 * Handles shortcode registration, Gutenberg block registration,
 * and conditional asset loading for frontend chart rendering.
 * Phase 3: Dashboard shortcode, advanced shortcode parameters.
 *
 * @package Accelvia_DataForge
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Accelvia_DF_Public {

    /**
     * Track whether we've enqueued frontend assets in this request.
     *
     * @var bool
     */
    private $assets_enqueued = false;

    /**
     * Constructor — register shortcodes and Gutenberg blocks.
     */
    public function __construct() {
        add_shortcode( 'accelvia_chart', array( $this, 'render_chart_shortcode' ) );
        add_shortcode( 'accelvia_dashboard', array( $this, 'render_dashboard_shortcode' ) );
        add_action( 'init', array( $this, 'register_gutenberg_block' ) );
    }

    /**
     * Enqueue frontend assets (only when a chart is on the page).
     */
    private function enqueue_frontend_assets() {
        if ( $this->assets_enqueued ) {
            return;
        }

        wp_enqueue_style(
            'accelvia-df-public-css',
            ACCELVIA_DF_URL . 'public/assets/accelvia-df-public.css',
            array(),
            ACCELVIA_DF_VERSION
        );

        wp_enqueue_script(
            'apexcharts',
            ACCELVIA_DF_URL . 'assets/js/apexcharts.min.js',
            array(),
            '5.10.6',
            true
        );

        wp_enqueue_script(
            'accelvia-df-public-js',
            ACCELVIA_DF_URL . 'public/assets/accelvia-df-public.js',
            array( 'apexcharts' ),
            ACCELVIA_DF_VERSION,
            true
        );

        $this->assets_enqueued = true;
    }

    /**
     * Render chart shortcode with advanced parameter support.
     *
     * Usage: [accelvia_chart id="5" height="400" theme="dark" colors="#ff6384,#36a2eb" title="show" align="center"]
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output.
     */
    public function render_chart_shortcode( $atts ) {
        $atts = shortcode_atts( array(
            'id'        => 0,
            'theme'     => '',
            'height'    => '',
            'width'     => '',
            'colors'    => '',
            'title'     => '',
            'animation' => '',
            'class'     => '',
            'align'     => '',
        ), $atts, 'accelvia_chart' );

        $chart_id = absint( $atts['id'] );
        if ( ! $chart_id ) {
            return '';
        }

        return $this->render_chart( $chart_id, $atts );
    }

    /**
     * Render dashboard shortcode.
     *
     * Usage: [accelvia_dashboard id="1"]
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output.
     */
    public function render_dashboard_shortcode( $atts ) {
        $atts = shortcode_atts( array(
            'id'    => 0,
            'theme' => '',
        ), $atts, 'accelvia_dashboard' );

        $dashboard_id = absint( $atts['id'] );
        if ( ! $dashboard_id ) {
            return '';
        }

        return $this->render_dashboard( $dashboard_id, $atts['theme'] );
    }

    /**
     * Register Gutenberg blocks for chart and dashboard embedding.
     */
    public function register_gutenberg_block() {
        if ( ! function_exists( 'register_block_type' ) ) {
            return;
        }

        register_block_type( 'accelvia/dataforge-chart', array(
            'attributes'      => array(
                'chartId'   => array( 'type' => 'number', 'default' => 0 ),
                'theme'     => array( 'type' => 'string', 'default' => '' ),
                'height'    => array( 'type' => 'string', 'default' => '' ),
                'width'     => array( 'type' => 'string', 'default' => '' ),
                'colors'    => array( 'type' => 'string', 'default' => '' ),
                'title'     => array( 'type' => 'string', 'default' => '' ),
                'animation' => array( 'type' => 'string', 'default' => '' ),
                'align'     => array( 'type' => 'string', 'default' => '' ),
            ),
            'render_callback' => array( $this, 'render_gutenberg_chart_block' ),
        ) );

        register_block_type( 'accelvia/dataforge-dashboard', array(
            'attributes'      => array(
                'dashboardId' => array( 'type' => 'number', 'default' => 0 ),
                'theme'       => array( 'type' => 'string', 'default' => '' ),
            ),
            'render_callback' => array( $this, 'render_gutenberg_dashboard_block' ),
        ) );
    }

    /**
     * Render callback for the chart Gutenberg block.
     *
     * @param array $attributes Block attributes.
     * @return string HTML output.
     */
    public function render_gutenberg_chart_block( $attributes ) {
        $chart_id = absint( $attributes['chartId'] ?? 0 );

        if ( ! $chart_id ) {
            return '<p class="accelvia-df-block-placeholder">' . esc_html__( 'Select a chart in the block settings.', 'accelvia-dataforge' ) . '</p>';
        }

        $atts = array(
            'id'        => $chart_id,
            'theme'     => sanitize_text_field( $attributes['theme'] ?? '' ),
            'height'    => sanitize_text_field( $attributes['height'] ?? '' ),
            'width'     => sanitize_text_field( $attributes['width'] ?? '' ),
            'colors'    => sanitize_text_field( $attributes['colors'] ?? '' ),
            'title'     => sanitize_text_field( $attributes['title'] ?? '' ),
            'animation' => sanitize_text_field( $attributes['animation'] ?? '' ),
            'align'     => sanitize_text_field( $attributes['align'] ?? '' ),
        );

        return $this->render_chart( $chart_id, $atts );
    }

    /**
     * Render callback for the dashboard Gutenberg block.
     *
     * @param array $attributes Block attributes.
     * @return string HTML output.
     */
    public function render_gutenberg_dashboard_block( $attributes ) {
        $dashboard_id = absint( $attributes['dashboardId'] ?? 0 );
        $theme        = sanitize_text_field( $attributes['theme'] ?? '' );

        if ( ! $dashboard_id ) {
            return '<p class="accelvia-df-block-placeholder">' . esc_html__( 'Select a dashboard in the block settings.', 'accelvia-dataforge' ) . '</p>';
        }

        return $this->render_dashboard( $dashboard_id, $theme );
    }

    /**
     * Core chart renderer with advanced parameter support.
     *
     * @param int   $chart_id Chart ID.
     * @param array $atts     Shortcode/block attributes.
     * @return string HTML output.
     */
    private function render_chart( $chart_id, $atts = array() ) {
        $chart = Accelvia_DF_DB::get_chart( $chart_id );

        if ( ! $chart || 'publish' !== $chart->status ) {
            return '<!-- Accelvia DataForge: Chart not found -->';
        }

        // Parse config
        $config = json_decode( $chart->config_json, true );
        if ( ! is_array( $config ) ) {
            return '<!-- Accelvia DataForge: Invalid chart config -->';
        }

        // Apply model transformations and filters
        $config = Accelvia_DF_Chart_Model::to_apexcharts_options( $config, $chart_id );

        // ---- Apply shortcode overrides ----

        // Theme
        $theme = is_array( $atts ) ? ( $atts['theme'] ?? '' ) : $atts;
        if ( ! empty( $theme ) && in_array( $theme, array( 'light', 'dark' ), true ) ) {
            $config['theme']['mode'] = $theme;
        }

        // Height
        if ( ! empty( $atts['height'] ) ) {
            $config['chart']['height'] = absint( $atts['height'] );
        }

        // Colors
        if ( ! empty( $atts['colors'] ) ) {
            $color_list = array_map( 'trim', explode( ',', $atts['colors'] ) );
            $color_list = array_filter( $color_list, function ( $c ) {
                return preg_match( '/^#[0-9a-fA-F]{3,8}$/', $c );
            } );
            if ( ! empty( $color_list ) ) {
                $config['colors'] = array_values( $color_list );
            }
        }

        // Animation
        if ( ! empty( $atts['animation'] ) ) {
            $config['chart']['animations']['enabled'] = ( 'off' !== strtolower( $atts['animation'] ) );
        }

        // Ensure responsive width
        $config['chart']['width'] = '100%';

        // Enqueue assets
        $this->enqueue_frontend_assets();

        // Generate unique container ID
        $container_id = 'accelvia-df-chart-' . $chart_id . '-' . wp_rand( 1000, 9999 );

        // Build wrapper classes
        $theme_class = ! empty( $config['theme']['mode'] ) ? 'accelvia-df-theme-' . $config['theme']['mode'] : '';
        $align_class = '';
        if ( ! empty( $atts['align'] ) && in_array( $atts['align'], array( 'left', 'center', 'right' ), true ) ) {
            $align_class = 'accelvia-df-align-' . $atts['align'];
        }
        $custom_class = ! empty( $atts['class'] ) ? sanitize_html_class( $atts['class'] ) : '';

        $wrapper_classes = trim( "accelvia-df-chart-wrapper {$theme_class} {$align_class} {$custom_class}" );

        // Inline width override
        $wrapper_style = '';
        if ( ! empty( $atts['width'] ) ) {
            $wrapper_style = ' style="max-width:' . esc_attr( $atts['width'] ) . ';"';
        }

        $output = '';
        $output .= '<div class="' . esc_attr( $wrapper_classes ) . '"' . $wrapper_style . '>';
        
        // Chart title inside wrapper
        if ( ! empty( $atts['title'] ) && 'show' === strtolower( $atts['title'] ) ) {
            $output .= '<h3 class="accelvia-df-chart-title">' . esc_html( $chart->title ) . '</h3>';
        }

        $output .= '<div id="' . esc_attr( $container_id ) . '" ';
        $output .= 'class="accelvia-df-chart" ';
        $output .= 'data-chart-config="' . esc_attr( wp_json_encode( $config ) ) . '">';
        $output .= '<div class="accelvia-df-loading-skeleton"></div>';
        $output .= '</div>';
        $output .= '</div>';

        return $output;
    }

    /**
     * Core dashboard renderer.
     *
     * @param int    $dashboard_id Dashboard ID.
     * @param string $theme        Theme override.
     * @return string HTML output.
     */
    private function render_dashboard( $dashboard_id, $theme = '' ) {
        $dashboard = Accelvia_DF_DB::get_dashboard( $dashboard_id );

        if ( ! $dashboard || 'publish' !== $dashboard->status ) {
            return '<!-- Accelvia DataForge: Dashboard not found -->';
        }

        $layout = json_decode( $dashboard->layout_json, true );
        if ( ! is_array( $layout ) || empty( $layout ) ) {
            return '<!-- Accelvia DataForge: Dashboard has no charts -->';
        }

        // Sort by row_order
        usort( $layout, function ( $a, $b ) {
            return ( $a['row_order'] ?? 0 ) - ( $b['row_order'] ?? 0 );
        } );

        $this->enqueue_frontend_assets();
        $this->enqueue_dashboard_assets();

        // Collect all category labels across charts for the filter dropdown
        $all_categories = array();
        foreach ( $layout as $widget ) {
            $chart_id = absint( $widget['chart_id'] ?? 0 );
            if ( ! $chart_id ) {
                continue;
            }
            $chart = Accelvia_DF_DB::get_chart( $chart_id );
            if ( $chart ) {
                $config = json_decode( $chart->config_json, true );
                if ( is_array( $config ) ) {
                    if ( ! empty( $config['xaxis']['categories'] ) ) {
                        $all_categories = array_merge( $all_categories, $config['xaxis']['categories'] );
                    }
                    if ( ! empty( $config['labels'] ) ) {
                        $all_categories = array_merge( $all_categories, $config['labels'] );
                    }
                }
            }
        }
        $all_categories = array_unique( $all_categories );

        // Theme class
        $theme_class = '';
        if ( ! empty( $theme ) && in_array( $theme, array( 'light', 'dark' ), true ) ) {
            $theme_class = ' accelvia-df-theme-' . $theme;
        }

        $output = '<div class="accelvia-df-dashboard-wrapper' . esc_attr( $theme_class ) . '">';

        // Dashboard title
        $output .= '<h2 class="accelvia-df-dashboard-title">' . esc_html( $dashboard->title ) . '</h2>';

        // Filter bar
        $output .= '<div class="accelvia-df-filter-bar">';

        // Date range filter
        $output .= '<div class="accelvia-df-filter-group">';
        $output .= '<label for="accelvia-df-filter-date-from">' . esc_html__( 'From', 'accelvia-dataforge' ) . '</label>';
        $output .= '<input type="date" id="accelvia-df-filter-date-from" />';
        $output .= '<span class="accelvia-df-filter-sep">—</span>';
        $output .= '<label for="accelvia-df-filter-date-to">' . esc_html__( 'To', 'accelvia-dataforge' ) . '</label>';
        $output .= '<input type="date" id="accelvia-df-filter-date-to" />';
        $output .= '</div>';

        // Category dropdown
        if ( ! empty( $all_categories ) ) {
            $output .= '<div class="accelvia-df-filter-group">';
            $output .= '<label for="accelvia-df-filter-category">' . esc_html__( 'Category', 'accelvia-dataforge' ) . '</label>';
            $output .= '<select id="accelvia-df-filter-category">';
            $output .= '<option value="">' . esc_html__( 'All', 'accelvia-dataforge' ) . '</option>';
            foreach ( $all_categories as $cat ) {
                $output .= '<option value="' . esc_attr( $cat ) . '">' . esc_html( $cat ) . '</option>';
            }
            $output .= '</select>';
            $output .= '</div>';
        }

        // Reset button
        $output .= '<button type="button" class="accelvia-df-filter-reset">' . esc_html__( 'Reset', 'accelvia-dataforge' ) . '</button>';
        $output .= '</div>'; // .filter-bar

        // Dashboard grid
        $output .= '<div class="accelvia-df-dashboard-grid">';

        foreach ( $layout as $widget ) {
            $chart_id = absint( $widget['chart_id'] ?? 0 );
            $col_span = min( 12, max( 1, absint( $widget['col_span'] ?? 6 ) ) );

            if ( ! $chart_id ) {
                continue;
            }

            $grid_style = sprintf( 'grid-column: span %d;', $col_span );

            $atts = array(
                'theme'     => $theme,
                'title'     => 'show',
                'height'    => '',
                'width'     => '',
                'colors'    => '',
                'animation' => '',
                'class'     => '',
                'align'     => '',
            );

            $output .= '<div class="accelvia-df-dashboard-widget" style="' . esc_attr( $grid_style ) . '">';
            $output .= $this->render_chart( $chart_id, $atts );
            $output .= '</div>';
        }

        $output .= '</div>'; // .grid
        $output .= '</div>'; // .wrapper

        return $output;
    }

    /**
     * Enqueue dashboard-specific frontend assets.
     */
    private function enqueue_dashboard_assets() {
        static $enqueued = false;
        if ( $enqueued ) {
            return;
        }

        wp_enqueue_style(
            'accelvia-df-dashboard-css',
            ACCELVIA_DF_URL . 'public/assets/accelvia-df-dashboard.css',
            array( 'accelvia-df-public-css' ),
            ACCELVIA_DF_VERSION
        );

        wp_enqueue_script(
            'accelvia-df-dashboard-js',
            ACCELVIA_DF_URL . 'public/assets/accelvia-df-dashboard.js',
            array( 'apexcharts' ),
            ACCELVIA_DF_VERSION,
            true
        );

        $enqueued = true;
    }
}

