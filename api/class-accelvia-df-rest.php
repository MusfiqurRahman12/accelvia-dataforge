<?php
/**
 * Accelvia DataForge – REST API Controller
 *
 * Provides WP REST API endpoints for chart and dashboard CRUD.
 * Public read endpoints for external embedding; authenticated write endpoints.
 *
 * @package Accelvia_DataForge
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Accelvia_DF_REST {

    const NAMESPACE = 'accelvia-df/v1';

    /**
     * Register all REST routes.
     */
    public static function register_routes() {
        // Charts — Public read.
        register_rest_route( self::NAMESPACE, '/charts', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( __CLASS__, 'get_charts' ),
            'permission_callback' => array( __CLASS__, 'check_admin_permission' ),
        ) );

        register_rest_route( self::NAMESPACE, '/charts/(?P<id>\d+)', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'get_chart' ),
                'permission_callback' => '__return_true', // Public
            ),
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( __CLASS__, 'update_chart' ),
                'permission_callback' => array( __CLASS__, 'check_admin_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array( __CLASS__, 'delete_chart' ),
                'permission_callback' => array( __CLASS__, 'check_admin_permission' ),
            ),
        ) );

        register_rest_route( self::NAMESPACE, '/charts', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( __CLASS__, 'create_chart' ),
            'permission_callback' => array( __CLASS__, 'check_admin_permission' ),
        ) );

        // Dashboards — Public read.
        register_rest_route( self::NAMESPACE, '/dashboards', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( __CLASS__, 'get_dashboards' ),
            'permission_callback' => array( __CLASS__, 'check_admin_permission' ),
        ) );

        register_rest_route( self::NAMESPACE, '/dashboards/(?P<id>\d+)', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'get_dashboard' ),
                'permission_callback' => '__return_true', // Public
            ),
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( __CLASS__, 'update_dashboard' ),
                'permission_callback' => array( __CLASS__, 'check_admin_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array( __CLASS__, 'delete_dashboard' ),
                'permission_callback' => array( __CLASS__, 'check_admin_permission' ),
            ),
        ) );

        register_rest_route( self::NAMESPACE, '/dashboards', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( __CLASS__, 'create_dashboard' ),
            'permission_callback' => array( __CLASS__, 'check_admin_permission' ),
        ) );
    }

    /**
     * Permission check for admin-only endpoints.
     *
     * @return bool|WP_Error
     */
    public static function check_admin_permission() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error(
                'rest_forbidden',
                __( 'You do not have permission to access this resource.', 'accelvia-dataforge' ),
                array( 'status' => 403 )
            );
        }
        return true;
    }

    // =========================================================================
    // Chart Endpoints
    // =========================================================================

    /**
     * GET /charts — List all charts (authenticated).
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public static function get_charts( $request ) {
        $args = array(
            'per_page' => $request->get_param( 'per_page' ) ? absint( $request->get_param( 'per_page' ) ) : 20,
            'page'     => $request->get_param( 'page' ) ? absint( $request->get_param( 'page' ) ) : 1,
            'type'     => $request->get_param( 'type' ) ? sanitize_text_field( $request->get_param( 'type' ) ) : '',
        );

        $charts = Accelvia_DF_DB::get_charts( $args );
        $total  = Accelvia_DF_DB::count_charts();

        $data = array();
        foreach ( $charts as $chart ) {
            $data[] = self::prepare_chart_response( $chart );
        }

        $response = rest_ensure_response( $data );
        $response->header( 'X-WP-Total', $total );
        $response->header( 'X-WP-TotalPages', ceil( $total / $args['per_page'] ) );

        return $response;
    }

    /**
     * GET /charts/<id> — Get single chart (public).
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public static function get_chart( $request ) {
        $id = absint( $request->get_param( 'id' ) );

        // Check cache first
        $cache_key = 'chart_' . $id;
        $cached    = Accelvia_DF_DB::cache_get( $cache_key );
        if ( false !== $cached ) {
            return rest_ensure_response( $cached );
        }

        $chart = Accelvia_DF_DB::get_chart( $id );
        if ( ! $chart || 'publish' !== $chart->status ) {
            return new WP_Error( 'not_found', __( 'Chart not found.', 'accelvia-dataforge' ), array( 'status' => 404 ) );
        }

        $data = self::prepare_chart_response( $chart );

        // Cache for 5 minutes
        Accelvia_DF_DB::cache_set( $cache_key, $data, 300 );

        return rest_ensure_response( $data );
    }

    /**
     * POST /charts — Create a chart (authenticated).
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public static function create_chart( $request ) {
        $body = $request->get_json_params();

        $title      = sanitize_text_field( $body['title'] ?? '' );
        $chart_type = sanitize_text_field( $body['chart_type'] ?? 'bar' );
        $config     = isset( $body['config'] ) ? $body['config'] : array();

        if ( empty( $title ) ) {
            return new WP_Error( 'missing_title', __( 'Chart title is required.', 'accelvia-dataforge' ), array( 'status' => 400 ) );
        }

        $config = Accelvia_DF_Chart_Model::sanitize_config( $config );
        $validation = Accelvia_DF_Chart_Model::validate_config( $config, $chart_type );
        if ( is_wp_error( $validation ) ) {
            return new WP_Error( 'invalid_config', $validation->get_error_message(), array( 'status' => 400 ) );
        }

        $result = Accelvia_DF_DB::save_chart( array(
            'title'       => $title,
            'chart_type'  => $chart_type,
            'config_json' => wp_json_encode( $config ),
            'data_source' => sanitize_text_field( $body['data_source'] ?? 'manual' ),
            'status'      => 'publish',
        ) );

        if ( false === $result ) {
            return new WP_Error( 'db_error', __( 'Failed to create chart.', 'accelvia-dataforge' ), array( 'status' => 500 ) );
        }

        $chart = Accelvia_DF_DB::get_chart( $result );
        return rest_ensure_response( self::prepare_chart_response( $chart ) );
    }

    /**
     * PUT /charts/<id> — Update a chart (authenticated).
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public static function update_chart( $request ) {
        $id   = absint( $request->get_param( 'id' ) );
        $body = $request->get_json_params();

        $chart = Accelvia_DF_DB::get_chart( $id );
        if ( ! $chart ) {
            return new WP_Error( 'not_found', __( 'Chart not found.', 'accelvia-dataforge' ), array( 'status' => 404 ) );
        }

        $data = array( 'id' => $id );

        if ( isset( $body['title'] ) ) {
            $data['title'] = sanitize_text_field( $body['title'] );
        }
        if ( isset( $body['chart_type'] ) ) {
            $data['chart_type'] = sanitize_text_field( $body['chart_type'] );
        }
        if ( isset( $body['config'] ) ) {
            $config = Accelvia_DF_Chart_Model::sanitize_config( $body['config'] );
            $data['config_json'] = wp_json_encode( $config );
        }
        if ( isset( $body['data_source'] ) ) {
            $data['data_source'] = sanitize_text_field( $body['data_source'] );
        }
        if ( isset( $body['status'] ) ) {
            $data['status'] = sanitize_text_field( $body['status'] );
        }

        $result = Accelvia_DF_DB::save_chart( $data );
        if ( false === $result ) {
            return new WP_Error( 'db_error', __( 'Failed to update chart.', 'accelvia-dataforge' ), array( 'status' => 500 ) );
        }

        // Bust cache
        Accelvia_DF_DB::cache_delete( 'chart_' . $id );

        $chart = Accelvia_DF_DB::get_chart( $id );
        return rest_ensure_response( self::prepare_chart_response( $chart ) );
    }

    /**
     * DELETE /charts/<id> — Delete a chart (authenticated).
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public static function delete_chart( $request ) {
        $id = absint( $request->get_param( 'id' ) );

        $chart = Accelvia_DF_DB::get_chart( $id );
        if ( ! $chart ) {
            return new WP_Error( 'not_found', __( 'Chart not found.', 'accelvia-dataforge' ), array( 'status' => 404 ) );
        }

        $result = Accelvia_DF_DB::delete_chart( $id );
        if ( ! $result ) {
            return new WP_Error( 'db_error', __( 'Failed to delete chart.', 'accelvia-dataforge' ), array( 'status' => 500 ) );
        }

        Accelvia_DF_DB::cache_delete( 'chart_' . $id );

        return rest_ensure_response( array( 'deleted' => true, 'id' => $id ) );
    }

    // =========================================================================
    // Dashboard Endpoints
    // =========================================================================

    /**
     * GET /dashboards — List all dashboards (authenticated).
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public static function get_dashboards( $request ) {
        $args = array(
            'per_page' => $request->get_param( 'per_page' ) ? absint( $request->get_param( 'per_page' ) ) : 20,
            'page'     => $request->get_param( 'page' ) ? absint( $request->get_param( 'page' ) ) : 1,
        );

        $dashboards = Accelvia_DF_DB::get_dashboards( $args );
        $total      = Accelvia_DF_DB::count_dashboards();

        $data = array();
        foreach ( $dashboards as $dashboard ) {
            $data[] = self::prepare_dashboard_response( $dashboard );
        }

        $response = rest_ensure_response( $data );
        $response->header( 'X-WP-Total', $total );
        $response->header( 'X-WP-TotalPages', ceil( $total / $args['per_page'] ) );

        return $response;
    }

    /**
     * GET /dashboards/<id> — Get single dashboard with chart data (public).
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public static function get_dashboard( $request ) {
        $id = absint( $request->get_param( 'id' ) );

        // Check cache
        $cache_key = 'dashboard_' . $id;
        $cached    = Accelvia_DF_DB::cache_get( $cache_key );
        if ( false !== $cached ) {
            return rest_ensure_response( $cached );
        }

        $dashboard = Accelvia_DF_DB::get_dashboard( $id );
        if ( ! $dashboard || 'publish' !== $dashboard->status ) {
            return new WP_Error( 'not_found', __( 'Dashboard not found.', 'accelvia-dataforge' ), array( 'status' => 404 ) );
        }

        $data = self::prepare_dashboard_response( $dashboard, true );

        Accelvia_DF_DB::cache_set( $cache_key, $data, 300 );

        return rest_ensure_response( $data );
    }

    /**
     * POST /dashboards — Create a dashboard (authenticated).
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public static function create_dashboard( $request ) {
        $body = $request->get_json_params();

        $title = sanitize_text_field( $body['title'] ?? '' );
        if ( empty( $title ) ) {
            return new WP_Error( 'missing_title', __( 'Dashboard title is required.', 'accelvia-dataforge' ), array( 'status' => 400 ) );
        }

        $layout = isset( $body['layout'] ) ? $body['layout'] : array();
        $layout = self::sanitize_layout( $layout );

        $result = Accelvia_DF_DB::save_dashboard( array(
            'title'       => $title,
            'layout_json' => wp_json_encode( $layout ),
            'status'      => 'publish',
        ) );

        if ( false === $result ) {
            return new WP_Error( 'db_error', __( 'Failed to create dashboard.', 'accelvia-dataforge' ), array( 'status' => 500 ) );
        }

        $dashboard = Accelvia_DF_DB::get_dashboard( $result );
        return rest_ensure_response( self::prepare_dashboard_response( $dashboard ) );
    }

    /**
     * PUT /dashboards/<id> — Update a dashboard (authenticated).
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public static function update_dashboard( $request ) {
        $id   = absint( $request->get_param( 'id' ) );
        $body = $request->get_json_params();

        $dashboard = Accelvia_DF_DB::get_dashboard( $id );
        if ( ! $dashboard ) {
            return new WP_Error( 'not_found', __( 'Dashboard not found.', 'accelvia-dataforge' ), array( 'status' => 404 ) );
        }

        $data = array( 'id' => $id );

        if ( isset( $body['title'] ) ) {
            $data['title'] = sanitize_text_field( $body['title'] );
        }
        if ( isset( $body['layout'] ) ) {
            $data['layout_json'] = wp_json_encode( self::sanitize_layout( $body['layout'] ) );
        }
        if ( isset( $body['status'] ) ) {
            $data['status'] = sanitize_text_field( $body['status'] );
        }

        $result = Accelvia_DF_DB::save_dashboard( $data );
        if ( false === $result ) {
            return new WP_Error( 'db_error', __( 'Failed to update dashboard.', 'accelvia-dataforge' ), array( 'status' => 500 ) );
        }

        Accelvia_DF_DB::cache_delete( 'dashboard_' . $id );

        $dashboard = Accelvia_DF_DB::get_dashboard( $id );
        return rest_ensure_response( self::prepare_dashboard_response( $dashboard ) );
    }

    /**
     * DELETE /dashboards/<id> — Delete a dashboard (authenticated).
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public static function delete_dashboard( $request ) {
        $id = absint( $request->get_param( 'id' ) );

        $dashboard = Accelvia_DF_DB::get_dashboard( $id );
        if ( ! $dashboard ) {
            return new WP_Error( 'not_found', __( 'Dashboard not found.', 'accelvia-dataforge' ), array( 'status' => 404 ) );
        }

        $result = Accelvia_DF_DB::delete_dashboard( $id );
        if ( ! $result ) {
            return new WP_Error( 'db_error', __( 'Failed to delete dashboard.', 'accelvia-dataforge' ), array( 'status' => 500 ) );
        }

        Accelvia_DF_DB::cache_delete( 'dashboard_' . $id );

        return rest_ensure_response( array( 'deleted' => true, 'id' => $id ) );
    }

    // =========================================================================
    // Response Helpers
    // =========================================================================

    /**
     * Prepare a chart row for API response.
     *
     * @param object $chart Database row.
     * @return array
     */
    private static function prepare_chart_response( $chart ) {
        $config = json_decode( $chart->config_json, true );

        return array(
            'id'          => (int) $chart->id,
            'title'       => $chart->title,
            'chart_type'  => $chart->chart_type,
            'data_source' => $chart->data_source,
            'config'      => is_array( $config ) ? $config : array(),
            'status'      => $chart->status,
            'shortcode'   => '[accelvia_chart id="' . $chart->id . '"]',
            'created_at'  => $chart->created_at,
            'updated_at'  => $chart->updated_at,
        );
    }

    /**
     * Prepare a dashboard row for API response.
     *
     * @param object $dashboard  Database row.
     * @param bool   $embed_charts Whether to embed full chart configs.
     * @return array
     */
    private static function prepare_dashboard_response( $dashboard, $embed_charts = false ) {
        $layout = json_decode( $dashboard->layout_json, true );
        if ( ! is_array( $layout ) ) {
            $layout = array();
        }

        $data = array(
            'id'         => (int) $dashboard->id,
            'title'      => $dashboard->title,
            'layout'     => $layout,
            'status'     => $dashboard->status,
            'shortcode'  => '[accelvia_dashboard id="' . $dashboard->id . '"]',
            'created_at' => $dashboard->created_at,
            'updated_at' => $dashboard->updated_at,
        );

        // Embed full chart configs for the public endpoint
        if ( $embed_charts ) {
            $data['charts'] = array();
            foreach ( $layout as $widget ) {
                $chart_id = absint( $widget['chart_id'] ?? 0 );
                if ( $chart_id ) {
                    $chart = Accelvia_DF_DB::get_chart( $chart_id );
                    if ( $chart && 'publish' === $chart->status ) {
                        $data['charts'][ $chart_id ] = self::prepare_chart_response( $chart );
                    }
                }
            }
        }

        return $data;
    }

    /**
     * Sanitize a dashboard layout array.
     *
     * @param array $layout Raw layout array.
     * @return array Sanitized layout.
     */
    private static function sanitize_layout( $layout ) {
        if ( ! is_array( $layout ) ) {
            return array();
        }

        $clean = array();
        foreach ( $layout as $widget ) {
            if ( ! is_array( $widget ) || empty( $widget['chart_id'] ) ) {
                continue;
            }

            $clean[] = array(
                'chart_id'  => absint( $widget['chart_id'] ),
                'col_start' => absint( $widget['col_start'] ?? 1 ),
                'col_span'  => min( 12, max( 1, absint( $widget['col_span'] ?? 6 ) ) ),
                'row_order' => absint( $widget['row_order'] ?? 0 ),
            );
        }

        // Sort by row_order
        usort( $clean, function ( $a, $b ) {
            return $a['row_order'] - $b['row_order'];
        } );

        return $clean;
    }
}
