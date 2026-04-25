<?php
/**
 * Accelvia DataForge – Database Layer
 *
 * Handles all custom table creation, migrations, and CRUD operations
 * for charts, dashboards, and cache.
 *
 * @package Accelvia_DataForge
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Accelvia_DF_DB {

    const DB_VERSION = '1.2.0';

    /**
     * Create all custom database tables.
     * Called on plugin activation and version upgrades.
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $charts_table     = $wpdb->prefix . 'accelvia_df_charts';
        $dashboards_table = $wpdb->prefix . 'accelvia_df_dashboards';
        $cache_table      = $wpdb->prefix . 'accelvia_df_cache';

        $sql = "CREATE TABLE {$charts_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL DEFAULT '',
            chart_type varchar(50) NOT NULL DEFAULT 'bar',
            config_json longtext NOT NULL,
            data_source varchar(50) NOT NULL DEFAULT 'manual',
            status varchar(20) NOT NULL DEFAULT 'publish',
            author_id bigint(20) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY  (id),
            KEY status (status),
            KEY chart_type (chart_type)
        ) $charset_collate;

        CREATE TABLE {$dashboards_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL DEFAULT '',
            layout_json longtext NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'publish',
            author_id bigint(20) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY  (id)
        ) $charset_collate;

        CREATE TABLE {$cache_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            cache_key varchar(255) NOT NULL DEFAULT '',
            cache_data longtext NOT NULL,
            expires_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY  (id),
            UNIQUE KEY cache_key (cache_key)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        update_option( 'accelvia_df_db_version', self::DB_VERSION );
    }

    /**
     * Check if a DB schema upgrade is needed and run it.
     * Called on plugins_loaded to handle upgrades without re-activation.
     */
    public static function check_version() {
        if ( get_option( 'accelvia_df_db_version' ) !== self::DB_VERSION ) {
            self::create_tables();
        }
    }

    /**
     * Get a single chart by ID.
     *
     * @param int $id Chart ID.
     * @return object|null Chart row object or null.
     */
    public static function get_chart( $id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'accelvia_df_charts';

        return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare( "SELECT * FROM %i WHERE id = %d", $table, $id )
        );
    }

    /**
     * Get charts with optional filtering and pagination.
     *
     * @param array $args {
     *     Optional. Query arguments.
     *     @type string $status   Filter by status. Default 'publish'.
     *     @type string $type     Filter by chart_type. Default ''.
     *     @type int    $per_page Number of results per page. Default 20.
     *     @type int    $page     Page number. Default 1.
     *     @type string $orderby  Column to order by. Default 'created_at'.
     *     @type string $order    ASC or DESC. Default 'DESC'.
     * }
     * @return array Array of chart row objects.
     */
    public static function get_charts( $args = array() ) {
        global $wpdb;
        $table = $wpdb->prefix . 'accelvia_df_charts';

        $defaults = array(
            'status'   => '',
            'type'     => '',
            'per_page' => 20,
            'page'     => 1,
            'orderby'  => 'created_at',
            'order'    => 'DESC',
        );
        $args = wp_parse_args( $args, $defaults );

        // Whitelist orderby columns
        $allowed_orderby = array( 'id', 'title', 'chart_type', 'created_at', 'updated_at' );
        $orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
        $order   = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

        $where_clauses = array();
        $where_values  = array();

        if ( ! empty( $args['status'] ) ) {
            $where_clauses[] = 'status = %s';
            $where_values[]  = $args['status'];
        }

        if ( ! empty( $args['type'] ) ) {
            $where_clauses[] = 'chart_type = %s';
            $where_values[]  = $args['type'];
        }

        $where_sql = '';
        if ( ! empty( $where_clauses ) ) {
            $where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );
        }

        $offset = ( absint( $args['page'] ) - 1 ) * absint( $args['per_page'] );
        $limit  = absint( $args['per_page'] );

        // Build the query — orderby/order are whitelisted, not user input
        $query = "SELECT * FROM {$table} {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        $prepare_values = array_merge( $where_values, array( $limit, $offset ) );

        return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare( $query, $prepare_values ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        );
    }

    /**
     * Count charts with optional filtering.
     *
     * @param string $status Filter by status. Default ''.
     * @return int Count of charts.
     */
    public static function count_charts( $status = '' ) {
        global $wpdb;
        $table = $wpdb->prefix . 'accelvia_df_charts';

        if ( ! empty( $status ) ) {
            return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE status = %s", $table, $status )
            );
        }

        return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare( "SELECT COUNT(*) FROM %i", $table )
        );
    }

    /**
     * Save a chart (insert or update).
     *
     * @param array $data {
     *     Chart data.
     *     @type int    $id          Optional. Chart ID for update.
     *     @type string $title       Chart title.
     *     @type string $chart_type  Chart type (line, bar, pie, area, donut, radar, radialBar).
     *     @type string $config_json JSON config string.
     *     @type string $data_source Data source type (manual, csv, import). Default 'manual'.
     *     @type string $status      Status. Default 'publish'.
     * }
     * @return int|false Chart ID on success, false on failure.
     */
    public static function save_chart( $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'accelvia_df_charts';
        $now   = gmdate( 'Y-m-d H:i:s' );

        // Validate data source
        $allowed_sources = array( 'manual', 'csv', 'import' );
        $data_source     = isset( $data['data_source'] ) ? sanitize_text_field( $data['data_source'] ) : 'manual';
        if ( ! in_array( $data_source, $allowed_sources, true ) ) {
            $data_source = 'manual';
        }

        $chart_data = array(
            'title'       => sanitize_text_field( $data['title'] ?? '' ),
            'chart_type'  => sanitize_text_field( $data['chart_type'] ?? 'bar' ),
            'config_json' => $data['config_json'] ?? '{}',
            'data_source' => $data_source,
            'status'      => sanitize_text_field( $data['status'] ?? 'publish' ),
            'updated_at'  => $now,
        );

        $format = array( '%s', '%s', '%s', '%s', '%s', '%s' );

        // Update existing chart
        if ( ! empty( $data['id'] ) ) {
            $result = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $table,
                $chart_data,
                array( 'id' => absint( $data['id'] ) ),
                $format,
                array( '%d' )
            );

            if ( false !== $result ) {
                do_action( 'accelvia_df_chart_saved', absint( $data['id'] ), $chart_data );
                return absint( $data['id'] );
            }
            return false;
        }

        // Insert new chart
        $chart_data['author_id']  = get_current_user_id();
        $chart_data['created_at'] = $now;

        $result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $table,
            $chart_data,
            array_merge( $format, array( '%d', '%s' ) )
        );

        if ( false !== $result ) {
            $new_id = (int) $wpdb->insert_id;
            do_action( 'accelvia_df_chart_saved', $new_id, $chart_data );
            return $new_id;
        }

        return false;
    }

    /**
     * Delete a chart by ID.
     *
     * @param int $id Chart ID.
     * @return bool True on success, false on failure.
     */
    public static function delete_chart( $id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'accelvia_df_charts';

        $result = $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $table,
            array( 'id' => absint( $id ) ),
            array( '%d' )
        );

        if ( false !== $result ) {
            do_action( 'accelvia_df_chart_deleted', absint( $id ) );
            return true;
        }

        return false;
    }

    /**
     * Duplicate a chart by ID.
     *
     * @param int $id Chart ID to duplicate.
     * @return int|false New chart ID on success, false on failure.
     */
    public static function duplicate_chart( $id ) {
        $chart = self::get_chart( absint( $id ) );
        if ( ! $chart ) {
            return false;
        }

        $data = array(
            'title'       => sprintf(
                /* translators: %s: Original chart title */
                __( 'Copy of %s', 'accelvia-dataforge' ),
                $chart->title
            ),
            'chart_type'  => $chart->chart_type,
            'config_json' => $chart->config_json,
            'data_source' => $chart->data_source,
            'status'      => 'publish',
        );

        $new_id = self::save_chart( $data );

        if ( $new_id ) {
            do_action( 'accelvia_df_chart_duplicated', $new_id, absint( $id ) );
        }

        return $new_id;
    }

    /**
     * Get chart data in export-ready format.
     * Strips internal IDs and server-specific data.
     *
     * @param int $id Chart ID.
     * @return array|false Export data or false if not found.
     */
    public static function get_chart_export( $id ) {
        $chart = self::get_chart( absint( $id ) );
        if ( ! $chart ) {
            return false;
        }

        $config = json_decode( $chart->config_json, true );
        if ( ! is_array( $config ) ) {
            $config = array();
        }

        return array(
            'plugin'     => 'accelvia-dataforge',
            'version'    => ACCELVIA_DF_VERSION,
            'exported_at' => gmdate( 'Y-m-d H:i:s' ),
            'chart'      => array(
                'title'       => $chart->title,
                'chart_type'  => $chart->chart_type,
                'data_source' => $chart->data_source,
                'config'      => $config,
            ),
        );
    }

    /**
     * Import a chart from exported JSON data.
     *
     * @param array $import_data Validated import data.
     * @return int|WP_Error New chart ID or WP_Error.
     */
    public static function import_chart( $import_data ) {
        // Validate structure
        if ( empty( $import_data['chart'] ) || ! is_array( $import_data['chart'] ) ) {
            return new WP_Error( 'invalid_import', __( 'Invalid import file structure.', 'accelvia-dataforge' ) );
        }

        // Verify it's from our plugin
        if ( empty( $import_data['plugin'] ) || 'accelvia-dataforge' !== $import_data['plugin'] ) {
            return new WP_Error( 'wrong_plugin', __( 'This file was not exported by Accelvia DataForge.', 'accelvia-dataforge' ) );
        }

        $chart_data = $import_data['chart'];

        // Validate chart type
        $chart_type = sanitize_text_field( $chart_data['chart_type'] ?? 'bar' );
        if ( ! in_array( $chart_type, Accelvia_DF_Chart_Model::CHART_TYPES, true ) ) {
            return new WP_Error( 'invalid_type', __( 'Imported chart has an unsupported type.', 'accelvia-dataforge' ) );
        }

        // Sanitize config
        $config = isset( $chart_data['config'] ) ? Accelvia_DF_Chart_Model::sanitize_config( $chart_data['config'] ) : array();

        $data = array(
            'title'       => sanitize_text_field( $chart_data['title'] ?? __( 'Imported Chart', 'accelvia-dataforge' ) ),
            'chart_type'  => $chart_type,
            'config_json' => wp_json_encode( $config ),
            'data_source' => 'import',
            'status'      => 'publish',
        );

        $new_id = self::save_chart( $data );

        if ( false === $new_id ) {
            return new WP_Error( 'db_error', __( 'Failed to save imported chart.', 'accelvia-dataforge' ) );
        }

        do_action( 'accelvia_df_chart_imported', $new_id, $import_data );

        return $new_id;
    }

    // =========================================================================
    // Dashboard CRUD
    // =========================================================================

    /**
     * Get a single dashboard by ID.
     *
     * @param int $id Dashboard ID.
     * @return object|null Dashboard row or null.
     */
    public static function get_dashboard( $id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'accelvia_df_dashboards';

        return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare( "SELECT * FROM %i WHERE id = %d", $table, absint( $id ) )
        );
    }

    /**
     * Get dashboards with optional pagination.
     *
     * @param array $args Query arguments.
     * @return array Array of dashboard row objects.
     */
    public static function get_dashboards( $args = array() ) {
        global $wpdb;
        $table = $wpdb->prefix . 'accelvia_df_dashboards';

        $defaults = array(
            'status'   => '',
            'per_page' => 20,
            'page'     => 1,
            'orderby'  => 'created_at',
            'order'    => 'DESC',
        );
        $args = wp_parse_args( $args, $defaults );

        $allowed_orderby = array( 'id', 'title', 'created_at', 'updated_at' );
        $orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
        $order   = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

        $where_clauses = array();
        $where_values  = array();

        if ( ! empty( $args['status'] ) ) {
            $where_clauses[] = 'status = %s';
            $where_values[]  = $args['status'];
        }

        $where_sql = '';
        if ( ! empty( $where_clauses ) ) {
            $where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );
        }

        $offset = ( absint( $args['page'] ) - 1 ) * absint( $args['per_page'] );
        $limit  = absint( $args['per_page'] );

        $query = "SELECT * FROM {$table} {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $prepare_values = array_merge( $where_values, array( $limit, $offset ) );

        return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare( $query, $prepare_values ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        );
    }

    /**
     * Count dashboards.
     *
     * @param string $status Optional status filter.
     * @return int Count.
     */
    public static function count_dashboards( $status = '' ) {
        global $wpdb;
        $table = $wpdb->prefix . 'accelvia_df_dashboards';

        if ( ! empty( $status ) ) {
            return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE status = %s", $table, $status )
            );
        }

        return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare( "SELECT COUNT(*) FROM %i", $table )
        );
    }

    /**
     * Save a dashboard (insert or update).
     *
     * @param array $data Dashboard data.
     * @return int|false Dashboard ID on success, false on failure.
     */
    public static function save_dashboard( $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'accelvia_df_dashboards';
        $now   = gmdate( 'Y-m-d H:i:s' );

        $dashboard_data = array(
            'title'       => sanitize_text_field( $data['title'] ?? '' ),
            'layout_json' => $data['layout_json'] ?? '[]',
            'status'      => sanitize_text_field( $data['status'] ?? 'publish' ),
            'updated_at'  => $now,
        );

        $format = array( '%s', '%s', '%s', '%s' );

        if ( ! empty( $data['id'] ) ) {
            $result = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $table,
                $dashboard_data,
                array( 'id' => absint( $data['id'] ) ),
                $format,
                array( '%d' )
            );

            if ( false !== $result ) {
                do_action( 'accelvia_df_dashboard_saved', absint( $data['id'] ), $dashboard_data );
                return absint( $data['id'] );
            }
            return false;
        }

        $dashboard_data['author_id']  = get_current_user_id();
        $dashboard_data['created_at'] = $now;

        $result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $table,
            $dashboard_data,
            array_merge( $format, array( '%d', '%s' ) )
        );

        if ( false !== $result ) {
            $new_id = (int) $wpdb->insert_id;
            do_action( 'accelvia_df_dashboard_saved', $new_id, $dashboard_data );
            return $new_id;
        }

        return false;
    }

    /**
     * Delete a dashboard by ID.
     *
     * @param int $id Dashboard ID.
     * @return bool True on success.
     */
    public static function delete_dashboard( $id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'accelvia_df_dashboards';

        $result = $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $table,
            array( 'id' => absint( $id ) ),
            array( '%d' )
        );

        if ( false !== $result ) {
            do_action( 'accelvia_df_dashboard_deleted', absint( $id ) );
            return true;
        }

        return false;
    }

    // =========================================================================
    // Cache Helpers
    // =========================================================================

    /**
     * Get a cached value.
     *
     * @param string $key Cache key.
     * @return mixed|false Cached data or false if expired/missing.
     */
    public static function cache_get( $key ) {
        global $wpdb;
        $table = $wpdb->prefix . 'accelvia_df_cache';

        $row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT cache_data, expires_at FROM %i WHERE cache_key = %s",
                $table,
                sanitize_text_field( $key )
            )
        );

        if ( ! $row ) {
            return false;
        }

        if ( strtotime( $row->expires_at ) < time() ) {
            self::cache_delete( $key );
            return false;
        }

        return json_decode( $row->cache_data, true );
    }

    /**
     * Set a cached value.
     *
     * @param string $key  Cache key.
     * @param mixed  $data Data to cache (will be JSON encoded).
     * @param int    $ttl  Time to live in seconds. Default 300 (5 min).
     * @return bool True on success.
     */
    public static function cache_set( $key, $data, $ttl = 300 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'accelvia_df_cache';

        $safe_key   = sanitize_text_field( $key );
        $cache_data = wp_json_encode( $data );
        $expires_at = gmdate( 'Y-m-d H:i:s', time() + absint( $ttl ) );

        // Use REPLACE to handle upsert
        $result = $wpdb->replace( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $table,
            array(
                'cache_key'  => $safe_key,
                'cache_data' => $cache_data,
                'expires_at' => $expires_at,
            ),
            array( '%s', '%s', '%s' )
        );

        return false !== $result;
    }

    /**
     * Delete a cached value.
     *
     * @param string $key Cache key.
     * @return bool True on success.
     */
    public static function cache_delete( $key ) {
        global $wpdb;
        $table = $wpdb->prefix . 'accelvia_df_cache';

        return false !== $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $table,
            array( 'cache_key' => sanitize_text_field( $key ) ),
            array( '%s' )
        );
    }

    /**
     * Flush all expired cache entries.
     *
     * @return int Number of deleted rows.
     */
    public static function cache_flush_expired() {
        global $wpdb;
        $table = $wpdb->prefix . 'accelvia_df_cache';
        $now   = gmdate( 'Y-m-d H:i:s' );

        return (int) $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare( "DELETE FROM %i WHERE expires_at < %s", $table, $now )
        );
    }
}
