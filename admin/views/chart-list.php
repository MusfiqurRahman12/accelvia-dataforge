<?php
/**
 * Accelvia DataForge – Chart List View
 *
 * @package Accelvia_DataForge
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$accelvia_df_charts = Accelvia_DF_DB::get_charts();
$accelvia_df_count  = Accelvia_DF_DB::count_charts();

$accelvia_df_type_icons = array(
    'line'      => '📈',
    'bar'       => '📊',
    'pie'       => '🥧',
    'area'      => '📉',
    'donut'     => '🍩',
    'radar'     => '🎯',
    'radialBar' => '⭕',
);

$accelvia_df_source_labels = array(
    'manual' => __( 'Manual', 'accelvia-dataforge' ),
    'csv'    => __( 'CSV', 'accelvia-dataforge' ),
    'import' => __( 'Imported', 'accelvia-dataforge' ),
);
?>

<div class="accelvia-df-wrap">
    <div class="accelvia-df-header">
        <div class="accelvia-df-header-left">
            <h1><?php esc_html_e( 'DataForge Charts', 'accelvia-dataforge' ); ?></h1>
            <span class="accelvia-df-badge-count"><?php echo esc_html( $accelvia_df_count ); ?> <?php esc_html_e( 'charts', 'accelvia-dataforge' ); ?></span>
        </div>
        <div class="accelvia-df-header-actions" style="display:flex;gap:8px;">
            <button type="button" id="accelvia-df-import-btn" class="ac-btn outline">
                <span class="dashicons dashicons-upload" style="margin-right:6px;line-height:1.4;"></span>
                <?php esc_html_e( 'Import', 'accelvia-dataforge' ); ?>
            </button>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=accelvia-df-chart-builder' ) ); ?>" class="ac-btn">
                <span class="dashicons dashicons-plus-alt2" style="margin-right:6px;line-height:1.4;"></span>
                <?php esc_html_e( 'Add New Chart', 'accelvia-dataforge' ); ?>
            </a>
        </div>
    </div>

    <!-- Import Modal (hidden) -->
    <div id="accelvia-df-import-modal" class="accelvia-df-modal" style="display:none;">
        <div class="accelvia-df-modal-backdrop"></div>
        <div class="accelvia-df-modal-content">
            <div class="accelvia-df-modal-header">
                <h2><?php esc_html_e( 'Import Chart', 'accelvia-dataforge' ); ?></h2>
                <button type="button" class="accelvia-df-modal-close">&times;</button>
            </div>
            <div class="accelvia-df-modal-body">
                <p style="color:var(--ac-text-muted);margin-bottom:16px;">
                    <?php esc_html_e( 'Upload a JSON file exported from Accelvia DataForge.', 'accelvia-dataforge' ); ?>
                </p>
                <div class="accelvia-df-csv-upload-zone" id="accelvia-df-import-dropzone">
                    <div class="accelvia-df-csv-upload-icon">📦</div>
                    <p><?php esc_html_e( 'Drag & drop a JSON file, or click to browse', 'accelvia-dataforge' ); ?></p>
                    <input type="file" id="accelvia-df-import-file" accept=".json" style="display:none;" />
                    <button type="button" id="accelvia-df-import-browse" class="ac-btn outline accelvia-df-btn-sm">
                        <?php esc_html_e( 'Browse Files', 'accelvia-dataforge' ); ?>
                    </button>
                </div>
                <div id="accelvia-df-import-status" style="display:none;margin-top:12px;"></div>
            </div>
        </div>
    </div>

    <?php if ( empty( $accelvia_df_charts ) ) : ?>
        <div class="ac-card accelvia-df-empty-state">
            <div class="accelvia-df-empty-icon">📊</div>
            <h2><?php esc_html_e( 'No Charts Yet', 'accelvia-dataforge' ); ?></h2>
            <p><?php esc_html_e( 'Create your first chart and bring your data to life.', 'accelvia-dataforge' ); ?></p>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=accelvia-df-chart-builder' ) ); ?>" class="ac-btn">
                <?php esc_html_e( 'Create Your First Chart', 'accelvia-dataforge' ); ?>
            </a>
        </div>
    <?php else : ?>
        <div class="accelvia-df-chart-grid" id="accelvia-df-chart-grid">
            <?php foreach ( $accelvia_df_charts as $accelvia_df_chart ) : ?>
                <?php
                $accelvia_df_type_icon    = isset( $accelvia_df_type_icons[ $accelvia_df_chart->chart_type ] ) ? $accelvia_df_type_icons[ $accelvia_df_chart->chart_type ] : '📊';
                $accelvia_df_shortcode    = '[accelvia_chart id="' . esc_attr( $accelvia_df_chart->id ) . '"]';
                $accelvia_df_edit_url     = admin_url( 'admin.php?page=accelvia-df-chart-builder&chart_id=' . $accelvia_df_chart->id );
                $accelvia_df_source_label = isset( $accelvia_df_source_labels[ $accelvia_df_chart->data_source ] ) ? $accelvia_df_source_labels[ $accelvia_df_chart->data_source ] : $accelvia_df_source_labels['manual'];
                ?>
                <div class="ac-card accelvia-df-chart-card" data-chart-id="<?php echo esc_attr( $accelvia_df_chart->id ); ?>">
                    <div class="accelvia-df-chart-card-header">
                        <span class="accelvia-df-type-icon"><?php echo esc_html( $accelvia_df_type_icon ); ?></span>
                        <div class="accelvia-df-chart-meta">
                            <h3><?php echo esc_html( $accelvia_df_chart->title ); ?></h3>
                            <div class="accelvia-df-badge-row">
                                <span class="ac-badge"><?php echo esc_html( ucfirst( $accelvia_df_chart->chart_type ) ); ?></span>
                                <span class="ac-badge source"><?php echo esc_html( $accelvia_df_source_label ); ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="accelvia-df-shortcode-row">
                        <code class="accelvia-df-shortcode-display"><?php echo esc_html( $accelvia_df_shortcode ); ?></code>
                        <button type="button" class="accelvia-df-copy-btn" data-shortcode="<?php echo esc_attr( $accelvia_df_shortcode ); ?>" title="<?php esc_attr_e( 'Copy Shortcode', 'accelvia-dataforge' ); ?>">
                            <span class="dashicons dashicons-clipboard"></span>
                        </button>
                    </div>

                    <div class="accelvia-df-chart-card-footer">
                        <span class="accelvia-df-date"><?php echo esc_html( gmdate( 'M j, Y', strtotime( $accelvia_df_chart->created_at ) ) ); ?></span>
                        <div class="accelvia-df-actions">
                            <button type="button" class="ac-btn outline accelvia-df-btn-sm accelvia-df-duplicate-btn" data-chart-id="<?php echo esc_attr( $accelvia_df_chart->id ); ?>" title="<?php esc_attr_e( 'Duplicate', 'accelvia-dataforge' ); ?>">
                                <span class="dashicons dashicons-admin-page"></span>
                            </button>
                            <button type="button" class="ac-btn outline accelvia-df-btn-sm accelvia-df-export-btn" data-chart-id="<?php echo esc_attr( $accelvia_df_chart->id ); ?>" title="<?php esc_attr_e( 'Export', 'accelvia-dataforge' ); ?>">
                                <span class="dashicons dashicons-download"></span>
                            </button>
                            <a href="<?php echo esc_url( $accelvia_df_edit_url ); ?>" class="ac-btn outline accelvia-df-btn-sm" title="<?php esc_attr_e( 'Edit', 'accelvia-dataforge' ); ?>">
                                <span class="dashicons dashicons-edit"></span>
                            </a>
                            <button type="button" class="ac-btn outline accelvia-df-btn-sm accelvia-df-delete-btn" data-chart-id="<?php echo esc_attr( $accelvia_df_chart->id ); ?>" title="<?php esc_attr_e( 'Delete', 'accelvia-dataforge' ); ?>">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
