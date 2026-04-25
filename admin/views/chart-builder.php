<?php
/**
 * Accelvia DataForge – Chart Builder View
 *
 * Split-panel chart builder with live ApexCharts preview.
 * Supports multi-series data entry, CSV import, and all 7 chart types.
 *
 * @package Accelvia_DataForge
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$accelvia_df_editing    = isset( $_GET['chart_id'] ) ? absint( $_GET['chart_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$accelvia_df_page_title = $accelvia_df_editing ? __( 'Edit Chart', 'accelvia-dataforge' ) : __( 'Create New Chart', 'accelvia-dataforge' );

$accelvia_df_chart_types = array(
    'bar'       => array( 'label' => __( 'Bar', 'accelvia-dataforge' ), 'icon' => '📊' ),
    'line'      => array( 'label' => __( 'Line', 'accelvia-dataforge' ), 'icon' => '📈' ),
    'area'      => array( 'label' => __( 'Area', 'accelvia-dataforge' ), 'icon' => '📉' ),
    'pie'       => array( 'label' => __( 'Pie', 'accelvia-dataforge' ), 'icon' => '🥧' ),
    'donut'     => array( 'label' => __( 'Donut', 'accelvia-dataforge' ), 'icon' => '🍩' ),
    'radar'     => array( 'label' => __( 'Radar', 'accelvia-dataforge' ), 'icon' => '🎯' ),
    'radialBar' => array( 'label' => __( 'Radial', 'accelvia-dataforge' ), 'icon' => '⭕' ),
);

$accelvia_df_palettes = Accelvia_DF_Chart_Model::get_color_palettes();
?>

<div class="accelvia-df-wrap">
    <div class="accelvia-df-header">
        <div class="accelvia-df-header-left">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=accelvia-df-charts' ) ); ?>" class="accelvia-df-back-link">
                <span class="dashicons dashicons-arrow-left-alt2"></span>
            </a>
            <h1><?php echo esc_html( $accelvia_df_page_title ); ?></h1>
        </div>
        <div class="accelvia-df-header-actions">
            <button type="button" id="accelvia-df-save-chart" class="ac-btn">
                <span class="dashicons dashicons-saved" style="margin-right:6px;line-height:1.4;"></span>
                <?php esc_html_e( 'Save Chart', 'accelvia-dataforge' ); ?>
            </button>
        </div>
    </div>

    <!-- Success toast (hidden by default) -->
    <div id="accelvia-df-toast" class="accelvia-df-toast" style="display:none;">
        <span class="accelvia-df-toast-message"></span>
        <code class="accelvia-df-toast-shortcode"></code>
        <button type="button" class="accelvia-df-copy-btn" data-shortcode="">
            <span class="dashicons dashicons-clipboard"></span>
        </button>
    </div>

    <div class="accelvia-df-builder">
        <!-- LEFT PANEL: Configuration -->
        <div class="accelvia-df-builder-config">
            <input type="hidden" id="accelvia-df-chart-id" value="<?php echo esc_attr( $accelvia_df_editing ); ?>" />
            <input type="hidden" id="accelvia-df-data-source" value="manual" />

            <!-- Chart Title -->
            <div class="ac-card">
                <div class="ac-form-group">
                    <label for="accelvia-df-chart-title"><?php esc_html_e( 'Chart Title', 'accelvia-dataforge' ); ?></label>
                    <input type="text" id="accelvia-df-chart-title" placeholder="<?php esc_attr_e( 'e.g. Monthly Revenue', 'accelvia-dataforge' ); ?>" />
                </div>
            </div>

            <!-- Chart Type Selector -->
            <div class="ac-card">
                <h2><?php esc_html_e( 'Chart Type', 'accelvia-dataforge' ); ?></h2>
                <div class="accelvia-df-type-selector">
                    <?php foreach ( $accelvia_df_chart_types as $accelvia_df_type_key => $accelvia_df_type_info ) : ?>
                        <button type="button"
                                class="accelvia-df-type-btn<?php echo 'bar' === $accelvia_df_type_key ? ' active' : ''; ?>"
                                data-type="<?php echo esc_attr( $accelvia_df_type_key ); ?>">
                            <span class="accelvia-df-type-icon"><?php echo esc_html( $accelvia_df_type_info['icon'] ); ?></span>
                            <span class="accelvia-df-type-label"><?php echo esc_html( $accelvia_df_type_info['label'] ); ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Data Source Tabs -->
            <div class="ac-card">
                <div class="accelvia-df-data-tabs">
                    <button type="button" class="accelvia-df-data-tab active" data-tab="manual">
                        <span class="dashicons dashicons-edit" style="margin-right:4px;font-size:14px;line-height:1.6;"></span>
                        <?php esc_html_e( 'Manual Entry', 'accelvia-dataforge' ); ?>
                    </button>
                    <button type="button" class="accelvia-df-data-tab" data-tab="csv">
                        <span class="dashicons dashicons-upload" style="margin-right:4px;font-size:14px;line-height:1.6;"></span>
                        <?php esc_html_e( 'CSV Import', 'accelvia-dataforge' ); ?>
                    </button>
                </div>

                <!-- Manual Data Entry Panel -->
                <div id="accelvia-df-panel-manual" class="accelvia-df-data-panel active">
                    <div class="accelvia-df-series-container" id="accelvia-df-series-container">
                        <!-- Series panels will be populated by JS -->
                    </div>
                    <div class="accelvia-df-series-actions">
                        <button type="button" id="accelvia-df-add-series" class="ac-btn outline accelvia-df-btn-sm">
                            <span class="dashicons dashicons-plus-alt2" style="margin-right:4px;line-height:1.4;"></span>
                            <?php esc_html_e( 'Add Series', 'accelvia-dataforge' ); ?>
                        </button>
                    </div>
                </div>

                <!-- CSV Import Panel -->
                <div id="accelvia-df-panel-csv" class="accelvia-df-data-panel">
                    <div class="accelvia-df-csv-upload-zone" id="accelvia-df-csv-dropzone">
                        <div class="accelvia-df-csv-upload-icon">📄</div>
                        <p><?php esc_html_e( 'Drag & drop a CSV file here, or click to browse', 'accelvia-dataforge' ); ?></p>
                        <input type="file" id="accelvia-df-csv-file" accept=".csv" style="display:none;" />
                        <button type="button" id="accelvia-df-csv-browse" class="ac-btn outline accelvia-df-btn-sm">
                            <?php esc_html_e( 'Browse Files', 'accelvia-dataforge' ); ?>
                        </button>
                        <span class="accelvia-df-csv-max-size">
                            <?php
                            $accelvia_df_max_csv = (int) get_option( 'accelvia_df_max_csv_size', 2097152 );
                            /* translators: %s: Maximum file size in MB */
                            printf( esc_html__( 'Max: %s MB', 'accelvia-dataforge' ), esc_html( number_format( $accelvia_df_max_csv / 1048576, 1 ) ) );
                            ?>
                        </span>
                    </div>

                    <!-- CSV Column Mapping (hidden until CSV parsed) -->
                    <div id="accelvia-df-csv-mapping" class="accelvia-df-csv-mapping" style="display:none;">
                        <h3><?php esc_html_e( 'Column Mapping', 'accelvia-dataforge' ); ?></h3>
                        <div class="accelvia-df-csv-mapping-grid" id="accelvia-df-csv-mapping-grid">
                            <!-- Populated by JS -->
                        </div>

                        <h3><?php esc_html_e( 'Preview', 'accelvia-dataforge' ); ?></h3>
                        <div class="accelvia-df-csv-preview-table-wrap">
                            <table class="accelvia-df-csv-preview-table" id="accelvia-df-csv-preview-table">
                                <!-- Populated by JS -->
                            </table>
                        </div>

                        <div class="accelvia-df-csv-actions">
                            <button type="button" id="accelvia-df-csv-apply" class="ac-btn">
                                <span class="dashicons dashicons-yes-alt" style="margin-right:4px;line-height:1.4;"></span>
                                <?php esc_html_e( 'Apply to Chart', 'accelvia-dataforge' ); ?>
                            </button>
                            <button type="button" id="accelvia-df-csv-cancel" class="ac-btn outline">
                                <?php esc_html_e( 'Cancel', 'accelvia-dataforge' ); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Color Palette -->
            <div class="ac-card">
                <h2><?php esc_html_e( 'Color Palette', 'accelvia-dataforge' ); ?></h2>
                <div class="accelvia-df-palette-selector">
                    <?php foreach ( $accelvia_df_palettes as $accelvia_df_palette_key => $accelvia_df_palette_colors ) : ?>
                        <button type="button"
                                class="accelvia-df-palette-btn<?php echo 'default' === $accelvia_df_palette_key ? ' active' : ''; ?>"
                                data-palette="<?php echo esc_attr( $accelvia_df_palette_key ); ?>">
                            <span class="accelvia-df-palette-label"><?php echo esc_html( ucfirst( $accelvia_df_palette_key ) ); ?></span>
                            <span class="accelvia-df-palette-preview">
                                <?php foreach ( array_slice( $accelvia_df_palette_colors, 0, 4 ) as $accelvia_df_color ) : ?>
                                    <span class="accelvia-df-palette-dot" style="background:<?php echo esc_attr( $accelvia_df_color ); ?>;"></span>
                                <?php endforeach; ?>
                            </span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Chart Options -->
            <div class="ac-card">
                <h2><?php esc_html_e( 'Options', 'accelvia-dataforge' ); ?></h2>
                <div class="accelvia-df-options-grid">
                    <label class="accelvia-df-toggle">
                        <input type="checkbox" id="accelvia-df-opt-legend" checked />
                        <span><?php esc_html_e( 'Show Legend', 'accelvia-dataforge' ); ?></span>
                    </label>
                    <label class="accelvia-df-toggle">
                        <input type="checkbox" id="accelvia-df-opt-grid" checked />
                        <span><?php esc_html_e( 'Show Grid', 'accelvia-dataforge' ); ?></span>
                    </label>
                    <label class="accelvia-df-toggle">
                        <input type="checkbox" id="accelvia-df-opt-animation" checked />
                        <span><?php esc_html_e( 'Animations', 'accelvia-dataforge' ); ?></span>
                    </label>
                    <label class="accelvia-df-toggle">
                        <input type="checkbox" id="accelvia-df-opt-datalabels" />
                        <span><?php esc_html_e( 'Data Labels', 'accelvia-dataforge' ); ?></span>
                    </label>
                    <label class="accelvia-df-toggle">
                        <input type="checkbox" id="accelvia-df-opt-toolbar" checked />
                        <span><?php esc_html_e( 'Toolbar', 'accelvia-dataforge' ); ?></span>
                    </label>
                </div>
            </div>
        </div>

        <!-- RIGHT PANEL: Live Preview -->
        <div class="accelvia-df-builder-preview">
            <div class="ac-card accelvia-df-preview-card">
                <div class="accelvia-df-preview-header">
                    <h2><?php esc_html_e( 'Live Preview', 'accelvia-dataforge' ); ?></h2>
                    <span class="accelvia-df-preview-badge"><?php esc_html_e( 'Real-time', 'accelvia-dataforge' ); ?></span>
                </div>
                <div id="accelvia-df-chart-preview" class="accelvia-df-chart-preview">
                    <div class="accelvia-df-preview-placeholder">
                        <span class="accelvia-df-preview-placeholder-icon">📊</span>
                        <p><?php esc_html_e( 'Add data to see your chart come to life.', 'accelvia-dataforge' ); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
