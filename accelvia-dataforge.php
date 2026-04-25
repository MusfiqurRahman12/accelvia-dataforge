<?php
/**
 * Plugin Name:       Accelvia DataForge – Charts & Dashboards
 * Plugin URI:        https://accelviateams.com/accelvia-dataforge
 * Description:       Create beautiful, interactive charts and dashboards directly in WordPress. Powered by ApexCharts. No external server required.
 * Version:           1.2.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            musfiqurrahman
 * Author URI:        https://accelviateams.com/musfiqurrahman
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       accelvia-dataforge
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Plugin constants
define( 'ACCELVIA_DF_VERSION', '1.2.0' );
define( 'ACCELVIA_DF_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACCELVIA_DF_URL', plugin_dir_url( __FILE__ ) );
define( 'ACCELVIA_DF_BASENAME', plugin_basename( __FILE__ ) );

// Load dependencies
require_once ACCELVIA_DF_DIR . 'includes/class-accelvia-df-db.php';
require_once ACCELVIA_DF_DIR . 'includes/class-accelvia-df-chart-model.php';
require_once ACCELVIA_DF_DIR . 'includes/class-accelvia-df-csv-parser.php';
require_once ACCELVIA_DF_DIR . 'includes/class-accelvia-df-data-normalizer.php';
require_once ACCELVIA_DF_DIR . 'api/class-accelvia-df-rest.php';
require_once ACCELVIA_DF_DIR . 'modules/class-accelvia-df-dashboard-widget.php';
require_once ACCELVIA_DF_DIR . 'admin/class-accelvia-df-admin.php';
require_once ACCELVIA_DF_DIR . 'public/class-accelvia-df-public.php';

// Activation Hook
register_activation_hook( __FILE__, array( 'Accelvia_DF_DB', 'create_tables' ) );

// Deactivation Hook
register_deactivation_hook( __FILE__, 'accelvia_df_deactivate' );

/**
 * Plugin deactivation cleanup.
 */
function accelvia_df_deactivate() {
    // Future: clear any scheduled events
    flush_rewrite_rules();
}

/**
 * Initialize the plugin on plugins_loaded.
 */
function accelvia_df_init() {
    // Check for DB version upgrades
    Accelvia_DF_DB::check_version();

    // Load text domain for translations
    load_plugin_textdomain( 'accelvia-dataforge', false, dirname( ACCELVIA_DF_BASENAME ) . '/languages/' );

    // Initialize admin and modules
    if ( is_admin() ) {
        new Accelvia_DF_Admin();
        Accelvia_DF_Dashboard_Widget::init();
    }

    // Initialize public
    new Accelvia_DF_Public();
}
add_action( 'plugins_loaded', 'accelvia_df_init' );

// Initialize REST API
add_action( 'rest_api_init', array( 'Accelvia_DF_REST', 'register_routes' ) );
