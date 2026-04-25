<?php
/**
 * Accelvia DataForge – Admin Controller
 *
 * Handles admin menu registration, script enqueuing, AJAX handlers,
 * and view rendering for the chart management interface.
 *
 * @package Accelvia_DataForge
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Accelvia_DF_Admin {

    /**
     * Constructor — register all admin hooks.
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menus' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

        // AJAX handlers — Phase 1
        add_action( 'wp_ajax_accelvia_df_save_chart', array( $this, 'ajax_save_chart' ) );
        add_action( 'wp_ajax_accelvia_df_delete_chart', array( $this, 'ajax_delete_chart' ) );

        // AJAX handlers — Phase 2
        add_action( 'wp_ajax_accelvia_df_duplicate_chart', array( $this, 'ajax_duplicate_chart' ) );
        add_action( 'wp_ajax_accelvia_df_export_chart', array( $this, 'ajax_export_chart' ) );
        add_action( 'wp_ajax_accelvia_df_import_chart', array( $this, 'ajax_import_chart' ) );
        add_action( 'wp_ajax_accelvia_df_parse_csv', array( $this, 'ajax_parse_csv' ) );

        // AJAX handlers — Phase 3
        add_action( 'wp_ajax_accelvia_df_save_dashboard', array( $this, 'ajax_save_dashboard' ) );
        add_action( 'wp_ajax_accelvia_df_delete_dashboard', array( $this, 'ajax_delete_dashboard' ) );

        // Admin notices
        add_action( 'admin_notices', array( $this, 'admin_notices' ) );
    }

    /**
     * Register admin menus and submenus.
     */
    public function register_menus() {
        add_menu_page(
            __( 'DataForge', 'accelvia-dataforge' ),
            __( 'DataForge', 'accelvia-dataforge' ),
            'manage_options',
            'accelvia-df-charts',
            array( $this, 'render_chart_list' ),
            'dashicons-chart-bar',
            26
        );

        add_submenu_page(
            'accelvia-df-charts',
            __( 'All Charts', 'accelvia-dataforge' ),
            __( 'All Charts', 'accelvia-dataforge' ),
            'manage_options',
            'accelvia-df-charts',
            array( $this, 'render_chart_list' )
        );

        add_submenu_page(
            'accelvia-df-charts',
            __( 'Add New Chart', 'accelvia-dataforge' ),
            __( 'Add New', 'accelvia-dataforge' ),
            'manage_options',
            'accelvia-df-chart-builder',
            array( $this, 'render_chart_builder' )
        );

        add_submenu_page(
            'accelvia-df-charts',
            __( 'Dashboards', 'accelvia-dataforge' ),
            __( 'Dashboards', 'accelvia-dataforge' ),
            'manage_options',
            'accelvia-df-dashboards',
            array( $this, 'render_dashboard_list' )
        );

        add_submenu_page(
            'accelvia-df-charts',
            __( 'New Dashboard', 'accelvia-dataforge' ),
            __( 'New Dashboard', 'accelvia-dataforge' ),
            'manage_options',
            'accelvia-df-dashboard-builder',
            array( $this, 'render_dashboard_builder' )
        );

        add_submenu_page(
            'accelvia-df-charts',
            __( 'Settings', 'accelvia-dataforge' ),
            __( 'Settings', 'accelvia-dataforge' ),
            'manage_options',
            'accelvia-df-settings',
            array( $this, 'render_settings' )
        );
    }

    /**
     * Conditionally enqueue admin scripts and styles.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_scripts( $hook ) {
        // Only load on DataForge admin pages
        if ( strpos( $hook, 'accelvia-df' ) === false ) {
            return;
        }

        // Styles
        wp_enqueue_style(
            'accelvia-df-admin-css',
            ACCELVIA_DF_URL . 'admin/assets/accelvia-df-admin.css',
            array(),
            ACCELVIA_DF_VERSION
        );

        // ApexCharts (vendored locally)
        wp_enqueue_script(
            'apexcharts',
            ACCELVIA_DF_URL . 'assets/js/apexcharts.min.js',
            array(),
            '5.10.6',
            true
        );

        // Admin JS
        wp_enqueue_script(
            'accelvia-df-admin-js',
            ACCELVIA_DF_URL . 'admin/assets/accelvia-df-admin.js',
            array( 'jquery', 'apexcharts', 'wp-color-picker' ),
            ACCELVIA_DF_VERSION,
            true
        );

        // Color picker styles
        wp_enqueue_style( 'wp-color-picker' );

        // Dashboard builder: jQuery UI Sortable (bundled with WordPress core)
        if ( strpos( $hook, 'accelvia-df-dashboard' ) !== false ) {
            wp_enqueue_script( 'jquery-ui-sortable' );
        }

        // Localize script data
        $localize_data = array(
            'ajax_url'       => admin_url( 'admin-ajax.php' ),
            'nonce'          => wp_create_nonce( 'accelvia_df_nonce' ),
            'color_palettes' => Accelvia_DF_Chart_Model::get_color_palettes(),
            'chart_types'    => Accelvia_DF_Chart_Model::CHART_TYPES,
            'max_csv_size'   => (int) get_option( 'accelvia_df_max_csv_size', 2097152 ),
            'i18n'           => array(
                'save_success'      => __( 'Chart saved successfully!', 'accelvia-dataforge' ),
                'save_error'        => __( 'Failed to save chart.', 'accelvia-dataforge' ),
                'delete_confirm'    => __( 'Are you sure you want to delete this chart?', 'accelvia-dataforge' ),
                'delete_success'    => __( 'Chart deleted.', 'accelvia-dataforge' ),
                'copied'            => __( 'Shortcode copied!', 'accelvia-dataforge' ),
                'no_data'           => __( 'Add some data to see a preview.', 'accelvia-dataforge' ),
                'invalid_config'    => __( 'Invalid chart configuration.', 'accelvia-dataforge' ),
                'duplicate_success' => __( 'Chart duplicated!', 'accelvia-dataforge' ),
                'duplicate_error'   => __( 'Failed to duplicate chart.', 'accelvia-dataforge' ),
                'export_error'      => __( 'Failed to export chart.', 'accelvia-dataforge' ),
                'import_success'    => __( 'Chart imported successfully!', 'accelvia-dataforge' ),
                'import_error'      => __( 'Failed to import chart.', 'accelvia-dataforge' ),
                'csv_error'         => __( 'Failed to parse CSV file.', 'accelvia-dataforge' ),
                'csv_success'       => __( 'CSV parsed successfully!', 'accelvia-dataforge' ),
                'csv_no_file'       => __( 'Please select a CSV file.', 'accelvia-dataforge' ),
                'csv_too_large'     => __( 'File exceeds the maximum size limit.', 'accelvia-dataforge' ),
                'series_name'       => __( 'Series', 'accelvia-dataforge' ),
                'add_series'        => __( 'Add Series', 'accelvia-dataforge' ),
                'remove_series'     => __( 'Remove this series?', 'accelvia-dataforge' ),
                'dash_save_success' => __( 'Dashboard saved!', 'accelvia-dataforge' ),
                'dash_save_error'   => __( 'Failed to save dashboard.', 'accelvia-dataforge' ),
                'dash_delete_confirm' => __( 'Are you sure you want to delete this dashboard?', 'accelvia-dataforge' ),
                'dash_no_charts'    => __( 'Add at least one chart to the dashboard.', 'accelvia-dataforge' ),
            ),
        );

        // If editing an existing chart, pass its data
        if ( strpos( $hook, 'accelvia-df-chart-builder' ) !== false && isset( $_GET['chart_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $chart_id = absint( $_GET['chart_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $chart    = Accelvia_DF_DB::get_chart( $chart_id );
            if ( $chart ) {
                $localize_data['editing_chart'] = array(
                    'id'          => $chart->id,
                    'title'       => $chart->title,
                    'chart_type'  => $chart->chart_type,
                    'data_source' => $chart->data_source,
                    'config'      => json_decode( $chart->config_json, true ),
                );
            }
        }

        // If editing an existing dashboard, pass its data
        if ( strpos( $hook, 'accelvia-df-dashboard-builder' ) !== false && isset( $_GET['dashboard_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $dashboard_id = absint( $_GET['dashboard_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $dashboard    = Accelvia_DF_DB::get_dashboard( $dashboard_id );
            if ( $dashboard ) {
                $localize_data['editing_dashboard'] = array(
                    'id'     => $dashboard->id,
                    'title'  => $dashboard->title,
                    'layout' => json_decode( $dashboard->layout_json, true ),
                );
            }
        }

        // Dashboard builder: pass all chart configs for live previews
        if ( strpos( $hook, 'accelvia-df-dashboard' ) !== false ) {
            $accelvia_df_all_charts = Accelvia_DF_DB::get_charts( array( 'per_page' => 100 ) );
            $chart_configs = array();
            foreach ( $accelvia_df_all_charts as $accelvia_df_c ) {
                $config = json_decode( $accelvia_df_c->config_json, true );
                if ( is_array( $config ) ) {
                    $chart_configs[ $accelvia_df_c->id ] = Accelvia_DF_Chart_Model::to_apexcharts_options( $config, $accelvia_df_c->id );
                }
            }
            $localize_data['chart_configs'] = $chart_configs;
        }

        wp_localize_script( 'accelvia-df-admin-js', 'accelvia_df', $localize_data );
    }

    /**
     * Display admin notices after actions.
     */
    public function admin_notices() {
        $screen = get_current_screen();
        if ( ! $screen || strpos( $screen->id, 'accelvia-df' ) === false ) {
            return;
        }

        if ( isset( $_GET['settings_updated'] ) && $_GET['settings_updated'] === 'true' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'accelvia-dataforge' ) . '</p></div>';
        }
    }

    /**
     * AJAX: Save chart.
     */
    public function ajax_save_chart() {
        check_ajax_referer( 'accelvia_df_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'accelvia-dataforge' ) ) );
        }

        $title       = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
        $chart_type  = isset( $_POST['chart_type'] ) ? sanitize_text_field( wp_unslash( $_POST['chart_type'] ) ) : 'bar';
        $config_raw  = isset( $_POST['config'] ) ? wp_unslash( $_POST['config'] ) : '{}'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $chart_id    = isset( $_POST['chart_id'] ) ? absint( $_POST['chart_id'] ) : 0;
        $data_source = isset( $_POST['data_source'] ) ? sanitize_text_field( wp_unslash( $_POST['data_source'] ) ) : 'manual';

        // Decode and validate config
        $config = json_decode( $config_raw, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            wp_send_json_error( array( 'message' => __( 'Invalid JSON config.', 'accelvia-dataforge' ) ) );
        }

        // Sanitize the config
        $config = Accelvia_DF_Chart_Model::sanitize_config( $config );

        // Validate
        $validation = Accelvia_DF_Chart_Model::validate_config( $config, $chart_type );
        if ( is_wp_error( $validation ) ) {
            wp_send_json_error( array( 'message' => $validation->get_error_message() ) );
        }

        // Save
        $data = array(
            'title'       => $title,
            'chart_type'  => $chart_type,
            'config_json' => wp_json_encode( $config ),
            'data_source' => $data_source,
            'status'      => 'publish',
        );

        if ( $chart_id > 0 ) {
            $data['id'] = $chart_id;
        }

        $result = Accelvia_DF_DB::save_chart( $data );

        if ( false === $result ) {
            wp_send_json_error( array( 'message' => __( 'Database error.', 'accelvia-dataforge' ) ) );
        }

        wp_send_json_success( array(
            'chart_id'  => $result,
            'shortcode' => '[accelvia_chart id="' . $result . '"]',
            'message'   => __( 'Chart saved!', 'accelvia-dataforge' ),
        ) );
    }

    /**
     * AJAX: Delete chart.
     */
    public function ajax_delete_chart() {
        check_ajax_referer( 'accelvia_df_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'accelvia-dataforge' ) ) );
        }

        $chart_id = isset( $_POST['chart_id'] ) ? absint( $_POST['chart_id'] ) : 0;
        if ( ! $chart_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid chart ID.', 'accelvia-dataforge' ) ) );
        }

        $result = Accelvia_DF_DB::delete_chart( $chart_id );

        if ( $result ) {
            wp_send_json_success( array( 'message' => __( 'Chart deleted.', 'accelvia-dataforge' ) ) );
        }

        wp_send_json_error( array( 'message' => __( 'Failed to delete chart.', 'accelvia-dataforge' ) ) );
    }

    /**
     * AJAX: Duplicate chart.
     */
    public function ajax_duplicate_chart() {
        check_ajax_referer( 'accelvia_df_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'accelvia-dataforge' ) ) );
        }

        $chart_id = isset( $_POST['chart_id'] ) ? absint( $_POST['chart_id'] ) : 0;
        if ( ! $chart_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid chart ID.', 'accelvia-dataforge' ) ) );
        }

        $new_id = Accelvia_DF_DB::duplicate_chart( $chart_id );

        if ( false === $new_id ) {
            wp_send_json_error( array( 'message' => __( 'Failed to duplicate chart.', 'accelvia-dataforge' ) ) );
        }

        $chart = Accelvia_DF_DB::get_chart( $new_id );

        wp_send_json_success( array(
            'chart_id'    => $new_id,
            'title'       => $chart->title,
            'chart_type'  => $chart->chart_type,
            'data_source' => $chart->data_source,
            'shortcode'   => '[accelvia_chart id="' . $new_id . '"]',
            'created_at'  => gmdate( 'M j, Y', strtotime( $chart->created_at ) ),
            'edit_url'    => admin_url( 'admin.php?page=accelvia-df-chart-builder&chart_id=' . $new_id ),
            'message'     => __( 'Chart duplicated!', 'accelvia-dataforge' ),
        ) );
    }

    /**
     * AJAX: Export chart as JSON.
     */
    public function ajax_export_chart() {
        check_ajax_referer( 'accelvia_df_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'accelvia-dataforge' ) ) );
        }

        $chart_id = isset( $_POST['chart_id'] ) ? absint( $_POST['chart_id'] ) : 0;
        if ( ! $chart_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid chart ID.', 'accelvia-dataforge' ) ) );
        }

        $export = Accelvia_DF_DB::get_chart_export( $chart_id );

        if ( false === $export ) {
            wp_send_json_error( array( 'message' => __( 'Chart not found.', 'accelvia-dataforge' ) ) );
        }

        wp_send_json_success( array(
            'export_data' => $export,
            'filename'    => sanitize_file_name( $export['chart']['title'] ) . '-dataforge.json',
        ) );
    }

    /**
     * AJAX: Import chart from JSON file.
     */
    public function ajax_import_chart() {
        check_ajax_referer( 'accelvia_df_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'accelvia-dataforge' ) ) );
        }

        $json_raw = isset( $_POST['import_data'] ) ? wp_unslash( $_POST['import_data'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

        $import_data = json_decode( $json_raw, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            wp_send_json_error( array( 'message' => __( 'Invalid JSON file.', 'accelvia-dataforge' ) ) );
        }

        $result = Accelvia_DF_DB::import_chart( $import_data );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        $chart = Accelvia_DF_DB::get_chart( $result );

        wp_send_json_success( array(
            'chart_id'    => $result,
            'title'       => $chart->title,
            'chart_type'  => $chart->chart_type,
            'data_source' => $chart->data_source,
            'shortcode'   => '[accelvia_chart id="' . $result . '"]',
            'created_at'  => gmdate( 'M j, Y', strtotime( $chart->created_at ) ),
            'edit_url'    => admin_url( 'admin.php?page=accelvia-df-chart-builder&chart_id=' . $result ),
            'message'     => __( 'Chart imported!', 'accelvia-dataforge' ),
        ) );
    }

    /**
     * AJAX: Parse CSV file and return column data for mapping.
     */
    public function ajax_parse_csv() {
        check_ajax_referer( 'accelvia_df_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'accelvia-dataforge' ) ) );
        }

        if ( empty( $_FILES['csv_file'] ) ) {
            wp_send_json_error( array( 'message' => __( 'No file uploaded.', 'accelvia-dataforge' ) ) );
        }

        $file = $_FILES['csv_file']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated

        // Validate the file
        $validation = Accelvia_DF_CSV_Parser::validate_file( $file );
        if ( is_wp_error( $validation ) ) {
            wp_send_json_error( array( 'message' => $validation->get_error_message() ) );
        }

        // Parse the CSV
        $parsed = Accelvia_DF_CSV_Parser::parse_file( $file['tmp_name'] );
        if ( is_wp_error( $parsed ) ) {
            wp_send_json_error( array( 'message' => $parsed->get_error_message() ) );
        }

        // Detect column types
        $columns = Accelvia_DF_CSV_Parser::detect_columns( $parsed );

        // Return preview data (first 5 rows)
        $preview_rows = array_slice( $parsed['rows'], 0, 5 );

        wp_send_json_success( array(
            'headers'      => $parsed['headers'],
            'columns'      => $columns,
            'preview_rows' => $preview_rows,
            'row_count'    => $parsed['row_count'],
            'col_count'    => $parsed['col_count'],
            'full_data'    => $parsed, // Send full data for applying to chart
        ) );
    }

    /**
     * Render the chart list page.
     */
    public function render_chart_list() {
        include ACCELVIA_DF_DIR . 'admin/views/chart-list.php';
    }

    /**
     * Render the chart builder page.
     */
    public function render_chart_builder() {
        include ACCELVIA_DF_DIR . 'admin/views/chart-builder.php';
    }

    /**
     * Render the dashboard list page.
     */
    public function render_dashboard_list() {
        include ACCELVIA_DF_DIR . 'admin/views/dashboard-list.php';
    }

    /**
     * Render the dashboard builder page.
     */
    public function render_dashboard_builder() {
        include ACCELVIA_DF_DIR . 'admin/views/dashboard-builder.php';
    }

    /**
     * Render the settings page.
     */
    public function render_settings() {
        include ACCELVIA_DF_DIR . 'admin/views/settings.php';
    }

    // =========================================================================
    // Phase 3 AJAX — Dashboards
    // =========================================================================

    /**
     * AJAX: Save dashboard.
     */
    public function ajax_save_dashboard() {
        check_ajax_referer( 'accelvia_df_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'accelvia-dataforge' ) ) );
        }

        $title        = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
        $layout_raw   = isset( $_POST['layout'] ) ? wp_unslash( $_POST['layout'] ) : '[]'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $dashboard_id = isset( $_POST['dashboard_id'] ) ? absint( $_POST['dashboard_id'] ) : 0;

        if ( empty( $title ) ) {
            wp_send_json_error( array( 'message' => __( 'Dashboard title is required.', 'accelvia-dataforge' ) ) );
        }

        $layout = json_decode( $layout_raw, true );
        if ( ! is_array( $layout ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid layout data.', 'accelvia-dataforge' ) ) );
        }

        // Sanitize layout widgets
        $clean_layout = array();
        foreach ( $layout as $widget ) {
            if ( ! is_array( $widget ) || empty( $widget['chart_id'] ) ) {
                continue;
            }
            $clean_layout[] = array(
                'chart_id'  => absint( $widget['chart_id'] ),
                'col_start' => absint( $widget['col_start'] ?? 1 ),
                'col_span'  => min( 12, max( 1, absint( $widget['col_span'] ?? 6 ) ) ),
                'row_order' => absint( $widget['row_order'] ?? 0 ),
            );
        }

        $data = array(
            'title'       => $title,
            'layout_json' => wp_json_encode( $clean_layout ),
            'status'      => 'publish',
        );

        if ( $dashboard_id > 0 ) {
            $data['id'] = $dashboard_id;
        }

        $result = Accelvia_DF_DB::save_dashboard( $data );

        if ( false === $result ) {
            wp_send_json_error( array( 'message' => __( 'Database error.', 'accelvia-dataforge' ) ) );
        }

        wp_send_json_success( array(
            'dashboard_id' => $result,
            'shortcode'    => '[accelvia_dashboard id="' . $result . '"]',
            'message'      => __( 'Dashboard saved!', 'accelvia-dataforge' ),
        ) );
    }

    /**
     * AJAX: Delete dashboard.
     */
    public function ajax_delete_dashboard() {
        check_ajax_referer( 'accelvia_df_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'accelvia-dataforge' ) ) );
        }

        $dashboard_id = isset( $_POST['dashboard_id'] ) ? absint( $_POST['dashboard_id'] ) : 0;
        if ( ! $dashboard_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid dashboard ID.', 'accelvia-dataforge' ) ) );
        }

        $result = Accelvia_DF_DB::delete_dashboard( $dashboard_id );

        if ( $result ) {
            wp_send_json_success( array( 'message' => __( 'Dashboard deleted.', 'accelvia-dataforge' ) ) );
        }

        wp_send_json_error( array( 'message' => __( 'Failed to delete dashboard.', 'accelvia-dataforge' ) ) );
    }
}
