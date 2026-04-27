<?php
/**
 * Accelvia DataForge – Dashboard Builder View
 *
 * 12-column CSS grid drag-and-drop dashboard layout builder.
 *
 * @package Accelvia_DataForge
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$accelvia_df_dash_editing = isset( $_GET['dashboard_id'] ) ? absint( $_GET['dashboard_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$accelvia_df_dash_title   = $accelvia_df_dash_editing ? __( 'Edit Dashboard', 'accelvia-dataforge' ) : __( 'Create New Dashboard', 'accelvia-dataforge' );

// Get all charts for the sidebar picker
$accelvia_df_all_charts = Accelvia_DF_DB::get_charts( array( 'per_page' => 100 ) );

$accelvia_df_type_icons = array(
    'bar'       => '📊',
    'line'      => '📈',
    'area'      => '📉',
    'pie'       => '🥧',
    'donut'     => '🍩',
    'radar'     => '🎯',
    'radialBar' => '⭕',
);
?>

<div class="accelvia-df-wrap">
    <div class="accelvia-df-header">
        <div class="accelvia-df-header-left">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=accelvia-df-dashboards' ) ); ?>" class="accelvia-df-back-link">
                <span class="dashicons dashicons-arrow-left-alt2"></span>
            </a>
            <h1><?php echo esc_html( $accelvia_df_dash_title ); ?></h1>
        </div>
        <div class="accelvia-df-header-actions">
            <button type="button" id="accelvia-df-save-dashboard" class="ac-btn">
                <span class="dashicons dashicons-saved" style="margin-right:6px;line-height:1.4;"></span>
                <?php esc_html_e( 'Save Dashboard', 'accelvia-dataforge' ); ?>
            </button>
        </div>
    </div>

    <!-- Success toast -->
    <div id="accelvia-df-dash-toast" class="accelvia-df-toast" style="display:none;">
        <span class="accelvia-df-toast-message"></span>
        <code class="accelvia-df-toast-shortcode"></code>
        <button type="button" class="accelvia-df-copy-btn" data-shortcode="">
            <span class="dashicons dashicons-clipboard"></span>
        </button>
    </div>

    <input type="hidden" id="accelvia-df-dashboard-id" value="<?php echo esc_attr( $accelvia_df_dash_editing ); ?>" />

    <div class="accelvia-df-dash-builder">
        <!-- LEFT: Grid Layout Canvas -->
        <div class="accelvia-df-dash-canvas">
            <div class="ac-card">
                <div class="accelvia-df-dash-grid-header">
                    <h2><?php esc_html_e( 'Layout', 'accelvia-dataforge' ); ?></h2>
                    <span class="accelvia-df-preview-badge"><?php esc_html_e( '12-column grid', 'accelvia-dataforge' ); ?></span>
                </div>

                <!-- Grid drop zone -->
                <div class="accelvia-df-dash-grid" id="accelvia-df-dash-grid">
                    <div class="accelvia-df-dash-grid-empty" id="accelvia-df-grid-empty">
                        <span style="font-size:40px;opacity:0.5;">📋</span>
                        <p><?php esc_html_e( 'Drag charts here to build your dashboard layout.', 'accelvia-dataforge' ); ?></p>
                    </div>
                    <!-- Widgets will be inserted here by JS -->
                </div>
            </div>
        </div>

        <!-- RIGHT: Chart Picker Sidebar -->
        <div class="accelvia-df-dash-sidebar">
            <div class="ac-card">
                <div class="ac-form-group">
                    <label for="accelvia-df-dashboard-title"><?php esc_html_e( 'Dashboard Title', 'accelvia-dataforge' ); ?></label>
                    <input type="text" id="accelvia-df-dashboard-title" placeholder="<?php esc_attr_e( 'e.g. Sales Overview', 'accelvia-dataforge' ); ?>" />
                </div>
                <div class="ac-form-group" style="margin-top:16px;">
                    <label for="accelvia-df-dashboard-height"><?php esc_html_e( 'Widget Height', 'accelvia-dataforge' ); ?></label>
                    <input type="text" id="accelvia-df-dashboard-height" placeholder="auto" value="auto" />
                    <p style="font-size:12px;color:var(--ac-text-muted);margin:4px 0 0 0;"><?php esc_html_e( 'Pixels (e.g. 350) or "auto"', 'accelvia-dataforge' ); ?></p>
                </div>
                <div class="ac-form-group" style="margin-top:16px;">
                    <label for="accelvia-df-dashboard-width"><?php esc_html_e( 'Dashboard Width', 'accelvia-dataforge' ); ?></label>
                    <input type="text" id="accelvia-df-dashboard-width" placeholder="100%" value="100%" />
                    <p style="font-size:12px;color:var(--ac-text-muted);margin:4px 0 0 0;"><?php esc_html_e( 'Percentage or pixels (e.g. 100%, 1200px)', 'accelvia-dataforge' ); ?></p>
                </div>
            </div>

            <div class="ac-card">
                <h2><?php esc_html_e( 'Available Charts', 'accelvia-dataforge' ); ?></h2>
                <p style="color:var(--ac-text-muted);font-size:12px;margin-bottom:12px;">
                    <?php esc_html_e( 'Drag charts onto the grid below, or click to add.', 'accelvia-dataforge' ); ?>
                </p>

                <?php if ( empty( $accelvia_df_all_charts ) ) : ?>
                    <p style="color:var(--ac-text-muted);text-align:center;padding:20px 0;">
                        <?php esc_html_e( 'No charts created yet.', 'accelvia-dataforge' ); ?>
                        <br>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=accelvia-df-chart-builder' ) ); ?>">
                            <?php esc_html_e( 'Create one first →', 'accelvia-dataforge' ); ?>
                        </a>
                    </p>
                <?php else : ?>
                    <div class="accelvia-df-chart-picker-list" id="accelvia-df-chart-picker">
                        <?php foreach ( $accelvia_df_all_charts as $accelvia_df_pick_chart ) : ?>
                            <div class="accelvia-df-chart-picker-item"
                                 draggable="true"
                                 data-chart-id="<?php echo esc_attr( $accelvia_df_pick_chart->id ); ?>"
                                 data-chart-title="<?php echo esc_attr( $accelvia_df_pick_chart->title ); ?>"
                                 data-chart-type="<?php echo esc_attr( $accelvia_df_pick_chart->chart_type ); ?>">
                                <span class="accelvia-df-picker-icon"><?php echo esc_html( $accelvia_df_type_icons[ $accelvia_df_pick_chart->chart_type ] ?? '📊' ); ?></span>
                                <div class="accelvia-df-picker-info">
                                    <span class="accelvia-df-picker-title"><?php echo esc_html( $accelvia_df_pick_chart->title ); ?></span>
                                    <span class="accelvia-df-picker-type"><?php echo esc_html( ucfirst( $accelvia_df_pick_chart->chart_type ) ); ?></span>
                                </div>
                                <button type="button" class="accelvia-df-picker-add" title="<?php esc_attr_e( 'Add to dashboard', 'accelvia-dataforge' ); ?>">
                                    <span class="dashicons dashicons-plus-alt2"></span>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
