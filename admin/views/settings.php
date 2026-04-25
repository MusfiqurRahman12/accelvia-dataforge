<?php
/**
 * Accelvia DataForge – Settings View
 *
 * @package Accelvia_DataForge
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Handle form save
if ( isset( $_POST['accelvia_df_save_settings'] ) && check_admin_referer( 'accelvia_df_settings_nonce' ) ) {
    update_option( 'accelvia_df_default_theme', isset( $_POST['default_theme'] ) ? sanitize_text_field( wp_unslash( $_POST['default_theme'] ) ) : 'light' );
    update_option( 'accelvia_df_default_animation', isset( $_POST['default_animation'] ) ? 'yes' : 'no' );
    update_option( 'accelvia_df_default_height', isset( $_POST['default_height'] ) ? absint( $_POST['default_height'] ) : 350 );
    update_option( 'accelvia_df_max_csv_size', isset( $_POST['max_csv_size'] ) ? absint( $_POST['max_csv_size'] ) * 1048576 : 2097152 );
    update_option( 'accelvia_df_max_data_points', isset( $_POST['max_data_points'] ) ? absint( $_POST['max_data_points'] ) : 1000 );
    wp_safe_redirect( admin_url( 'admin.php?page=accelvia-df-settings&settings_updated=true' ) );
    exit;
}

$accelvia_df_current_theme     = get_option( 'accelvia_df_default_theme', 'light' );
$accelvia_df_current_animation = get_option( 'accelvia_df_default_animation', 'yes' );
$accelvia_df_current_height    = (int) get_option( 'accelvia_df_default_height', 350 );
$accelvia_df_current_csv_size  = (int) get_option( 'accelvia_df_max_csv_size', 2097152 );
$accelvia_df_current_csv_mb    = round( $accelvia_df_current_csv_size / 1048576, 1 );
$accelvia_df_current_max_data  = (int) get_option( 'accelvia_df_max_data_points', 1000 );
?>

<div class="accelvia-df-wrap">
    <div class="accelvia-df-header">
        <h1><?php esc_html_e( 'DataForge Settings', 'accelvia-dataforge' ); ?></h1>
    </div>

    <div class="ac-card" style="max-width:600px;">
        <h2><?php esc_html_e( 'Default Chart Settings', 'accelvia-dataforge' ); ?></h2>
        <form method="post">
            <?php wp_nonce_field( 'accelvia_df_settings_nonce' ); ?>

            <div class="ac-form-group">
                <label for="accelvia-df-default-theme"><?php esc_html_e( 'Default Theme', 'accelvia-dataforge' ); ?></label>
                <select id="accelvia-df-default-theme" name="default_theme" class="accelvia-df-select">
                    <option value="light" <?php selected( $accelvia_df_current_theme, 'light' ); ?>><?php esc_html_e( 'Light', 'accelvia-dataforge' ); ?></option>
                    <option value="dark" <?php selected( $accelvia_df_current_theme, 'dark' ); ?>><?php esc_html_e( 'Dark', 'accelvia-dataforge' ); ?></option>
                </select>
            </div>

            <div class="ac-form-group">
                <label for="accelvia-df-default-height"><?php esc_html_e( 'Default Chart Height (px)', 'accelvia-dataforge' ); ?></label>
                <input type="number" id="accelvia-df-default-height" name="default_height" value="<?php echo esc_attr( $accelvia_df_current_height ); ?>" min="200" max="800" step="10" />
            </div>

            <div class="ac-form-group">
                <label class="accelvia-df-toggle">
                    <input type="checkbox" name="default_animation" <?php checked( $accelvia_df_current_animation, 'yes' ); ?> />
                    <span><?php esc_html_e( 'Enable animations by default', 'accelvia-dataforge' ); ?></span>
                </label>
            </div>

            <hr style="border-color:var(--ac-border);margin:20px 0;" />

            <h2><?php esc_html_e( 'CSV Import Settings', 'accelvia-dataforge' ); ?></h2>

            <div class="ac-form-group">
                <label for="accelvia-df-max-csv-size"><?php esc_html_e( 'Max CSV File Size (MB)', 'accelvia-dataforge' ); ?></label>
                <input type="number" id="accelvia-df-max-csv-size" name="max_csv_size" value="<?php echo esc_attr( $accelvia_df_current_csv_mb ); ?>" min="1" max="50" step="1" />
            </div>

            <div class="ac-form-group">
                <label for="accelvia-df-max-data-points"><?php esc_html_e( 'Max Data Points Per Series', 'accelvia-dataforge' ); ?></label>
                <input type="number" id="accelvia-df-max-data-points" name="max_data_points" value="<?php echo esc_attr( $accelvia_df_current_max_data ); ?>" min="10" max="10000" step="10" />
            </div>

            <button type="submit" name="accelvia_df_save_settings" class="ac-btn">
                <?php esc_html_e( 'Save Settings', 'accelvia-dataforge' ); ?>
            </button>
        </form>
    </div>

    <div class="ac-card" style="max-width:600px;margin-top:20px;">
        <h2><?php esc_html_e( 'About DataForge', 'accelvia-dataforge' ); ?></h2>
        <p style="color:var(--ac-text-muted);">
            <?php
            /* translators: %s: Plugin version number */
            printf( esc_html__( 'Version %s — Powered by ApexCharts (MIT License).', 'accelvia-dataforge' ), esc_html( ACCELVIA_DF_VERSION ) );
            ?>
        </p>
        <p style="color:var(--ac-text-muted);">
            <?php esc_html_e( 'All chart data is stored locally in your WordPress database. No external services required.', 'accelvia-dataforge' ); ?>
        </p>
    </div>
</div>
