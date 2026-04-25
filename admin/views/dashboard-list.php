<?php
/**
 * Accelvia DataForge – Dashboard List View
 *
 * @package Accelvia_DataForge
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$accelvia_df_dashboards     = Accelvia_DF_DB::get_dashboards();
$accelvia_df_dash_count     = Accelvia_DF_DB::count_dashboards();
?>

<div class="accelvia-df-wrap">
    <div class="accelvia-df-header">
        <div class="accelvia-df-header-left">
            <h1><?php esc_html_e( 'DataForge Dashboards', 'accelvia-dataforge' ); ?></h1>
            <span class="accelvia-df-badge-count"><?php echo esc_html( $accelvia_df_dash_count ); ?> <?php esc_html_e( 'dashboards', 'accelvia-dataforge' ); ?></span>
        </div>
        <div class="accelvia-df-header-actions">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=accelvia-df-dashboard-builder' ) ); ?>" class="ac-btn">
                <span class="dashicons dashicons-plus-alt2" style="margin-right:6px;line-height:1.4;"></span>
                <?php esc_html_e( 'New Dashboard', 'accelvia-dataforge' ); ?>
            </a>
        </div>
    </div>

    <?php if ( empty( $accelvia_df_dashboards ) ) : ?>
        <div class="ac-card accelvia-df-empty-state">
            <div class="accelvia-df-empty-icon">📋</div>
            <h2><?php esc_html_e( 'No Dashboards Yet', 'accelvia-dataforge' ); ?></h2>
            <p><?php esc_html_e( 'Arrange multiple charts into a single, shareable dashboard.', 'accelvia-dataforge' ); ?></p>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=accelvia-df-dashboard-builder' ) ); ?>" class="ac-btn">
                <?php esc_html_e( 'Create Your First Dashboard', 'accelvia-dataforge' ); ?>
            </a>
        </div>
    <?php else : ?>
        <div class="accelvia-df-chart-grid" id="accelvia-df-dashboard-grid">
            <?php foreach ( $accelvia_df_dashboards as $accelvia_df_dash ) : ?>
                <?php
                $accelvia_df_dash_layout    = json_decode( $accelvia_df_dash->layout_json, true );
                $accelvia_df_dash_chart_ct  = is_array( $accelvia_df_dash_layout ) ? count( $accelvia_df_dash_layout ) : 0;
                $accelvia_df_dash_shortcode = '[accelvia_dashboard id="' . esc_attr( $accelvia_df_dash->id ) . '"]';
                $accelvia_df_dash_edit_url  = admin_url( 'admin.php?page=accelvia-df-dashboard-builder&dashboard_id=' . $accelvia_df_dash->id );
                ?>
                <div class="ac-card accelvia-df-chart-card" data-dashboard-id="<?php echo esc_attr( $accelvia_df_dash->id ); ?>">
                    <div class="accelvia-df-chart-card-header">
                        <span class="accelvia-df-type-icon">📋</span>
                        <div class="accelvia-df-chart-meta">
                            <h3><?php echo esc_html( $accelvia_df_dash->title ); ?></h3>
                            <div class="accelvia-df-badge-row">
                                <span class="ac-badge"><?php echo esc_html( $accelvia_df_dash_chart_ct ); ?> <?php esc_html_e( 'charts', 'accelvia-dataforge' ); ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="accelvia-df-shortcode-row">
                        <code class="accelvia-df-shortcode-display"><?php echo esc_html( $accelvia_df_dash_shortcode ); ?></code>
                        <button type="button" class="accelvia-df-copy-btn" data-shortcode="<?php echo esc_attr( $accelvia_df_dash_shortcode ); ?>" title="<?php esc_attr_e( 'Copy Shortcode', 'accelvia-dataforge' ); ?>">
                            <span class="dashicons dashicons-clipboard"></span>
                        </button>
                    </div>

                    <div class="accelvia-df-chart-card-footer">
                        <span class="accelvia-df-date"><?php echo esc_html( gmdate( 'M j, Y', strtotime( $accelvia_df_dash->created_at ) ) ); ?></span>
                        <div class="accelvia-df-actions">
                            <a href="<?php echo esc_url( $accelvia_df_dash_edit_url ); ?>" class="ac-btn outline accelvia-df-btn-sm" title="<?php esc_attr_e( 'Edit', 'accelvia-dataforge' ); ?>">
                                <span class="dashicons dashicons-edit"></span>
                            </a>
                            <button type="button" class="ac-btn outline accelvia-df-btn-sm accelvia-df-delete-dashboard-btn" data-dashboard-id="<?php echo esc_attr( $accelvia_df_dash->id ); ?>" title="<?php esc_attr_e( 'Delete', 'accelvia-dataforge' ); ?>">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
