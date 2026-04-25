<?php
/**
 * Accelvia DataForge – WP Admin Dashboard Widget
 *
 * Displays at-a-glance plugin stats on the WordPress admin dashboard home.
 *
 * @package Accelvia_DataForge
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Accelvia_DF_Dashboard_Widget {

    /**
     * Register the dashboard widget.
     */
    public static function init() {
        add_action( 'wp_dashboard_setup', array( __CLASS__, 'register_widget' ) );
    }

    /**
     * Add the widget to the WordPress dashboard.
     */
    public static function register_widget() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        wp_add_dashboard_widget(
            'accelvia_df_dashboard_widget',
            __( '📊 DataForge Overview', 'accelvia-dataforge' ),
            array( __CLASS__, 'render_widget' )
        );
    }

    /**
     * Render the dashboard widget content.
     */
    public static function render_widget() {
        $chart_count     = Accelvia_DF_DB::count_charts();
        $dashboard_count = Accelvia_DF_DB::count_dashboards();

        // Get charts by type for the mini breakdown
        $charts     = Accelvia_DF_DB::get_charts( array( 'per_page' => 100 ) );
        $type_counts = array();
        foreach ( $charts as $chart ) {
            $type = $chart->chart_type;
            $type_counts[ $type ] = isset( $type_counts[ $type ] ) ? $type_counts[ $type ] + 1 : 1;
        }

        // Get most recent chart
        $recent = Accelvia_DF_DB::get_charts( array( 'per_page' => 1, 'page' => 1 ) );
        $recent_chart = ! empty( $recent ) ? $recent[0] : null;

        $type_icons = array(
            'bar'       => '📊',
            'line'      => '📈',
            'area'      => '📉',
            'pie'       => '🥧',
            'donut'     => '🍩',
            'radar'     => '🎯',
            'radialBar' => '⭕',
        );
        ?>
        <style>
            .accelvia-df-widget { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
            .accelvia-df-widget-stats { display: flex; gap: 16px; margin-bottom: 16px; }
            .accelvia-df-widget-stat {
                flex: 1;
                background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
                border-radius: 10px;
                padding: 16px;
                color: #fff;
                text-align: center;
            }
            .accelvia-df-widget-stat-number { font-size: 28px; font-weight: 700; line-height: 1.2; }
            .accelvia-df-widget-stat-label { font-size: 12px; opacity: 0.85; margin-top: 4px; }
            .accelvia-df-widget-stat.dashboards { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
            .accelvia-df-widget-types { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; }
            .accelvia-df-widget-type-badge {
                display: inline-flex;
                align-items: center;
                gap: 4px;
                background: #f3f4f6;
                border-radius: 20px;
                padding: 4px 12px;
                font-size: 12px;
                color: #374151;
            }
            .accelvia-df-widget-recent {
                background: #f9fafb;
                border-radius: 8px;
                padding: 12px;
                margin-bottom: 12px;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .accelvia-df-widget-recent-title { font-weight: 600; font-size: 13px; color: #111827; }
            .accelvia-df-widget-recent-meta { font-size: 11px; color: #6b7280; }
            .accelvia-df-widget-cta {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                background: #6366f1;
                color: #fff;
                padding: 8px 16px;
                border-radius: 6px;
                text-decoration: none;
                font-size: 13px;
                font-weight: 500;
                transition: background 0.2s;
            }
            .accelvia-df-widget-cta:hover { background: #4f46e5; color: #fff; }
        </style>

        <div class="accelvia-df-widget">
            <div class="accelvia-df-widget-stats">
                <div class="accelvia-df-widget-stat">
                    <div class="accelvia-df-widget-stat-number"><?php echo esc_html( $chart_count ); ?></div>
                    <div class="accelvia-df-widget-stat-label"><?php esc_html_e( 'Charts', 'accelvia-dataforge' ); ?></div>
                </div>
                <div class="accelvia-df-widget-stat dashboards">
                    <div class="accelvia-df-widget-stat-number"><?php echo esc_html( $dashboard_count ); ?></div>
                    <div class="accelvia-df-widget-stat-label"><?php esc_html_e( 'Dashboards', 'accelvia-dataforge' ); ?></div>
                </div>
            </div>

            <?php if ( ! empty( $type_counts ) ) : ?>
                <div class="accelvia-df-widget-types">
                    <?php foreach ( $type_counts as $type => $count ) : ?>
                        <span class="accelvia-df-widget-type-badge">
                            <?php echo esc_html( $type_icons[ $type ] ?? '📊' ); ?>
                            <?php echo esc_html( ucfirst( $type ) ); ?>
                            <strong><?php echo esc_html( $count ); ?></strong>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ( $recent_chart ) : ?>
                <div class="accelvia-df-widget-recent">
                    <div>
                        <div class="accelvia-df-widget-recent-title">
                            <?php echo esc_html( $type_icons[ $recent_chart->chart_type ] ?? '📊' ); ?>
                            <?php echo esc_html( $recent_chart->title ); ?>
                        </div>
                        <div class="accelvia-df-widget-recent-meta">
                            <?php
                            /* translators: %s: Date of most recent chart */
                            printf( esc_html__( 'Last created: %s', 'accelvia-dataforge' ), esc_html( gmdate( 'M j, Y', strtotime( $recent_chart->created_at ) ) ) );
                            ?>
                        </div>
                    </div>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=accelvia-df-chart-builder&chart_id=' . $recent_chart->id ) ); ?>" class="button button-small">
                        <?php esc_html_e( 'Edit', 'accelvia-dataforge' ); ?>
                    </a>
                </div>
            <?php endif; ?>

            <a href="<?php echo esc_url( admin_url( 'admin.php?page=accelvia-df-chart-builder' ) ); ?>" class="accelvia-df-widget-cta">
                <span class="dashicons dashicons-plus-alt2" style="font-size:16px;line-height:1.3;"></span>
                <?php esc_html_e( 'Create New Chart', 'accelvia-dataforge' ); ?>
            </a>
        </div>
        <?php
    }
}
