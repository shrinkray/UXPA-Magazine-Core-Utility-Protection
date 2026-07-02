<?php
/**
 * Plugin Name: UXPA Magazine Core Utility & Protection
 * Description: Consolidation of taxonomy/post utilities and high-efficiency firewall hooks.
 * Version: 1.0.0
 * Author: Greg Miller for UXPA
 * Author URI: https://shrinkraylabs.com
 * Text Domain: uxpa-core-utility
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Define Plugin Constants
define( 'UXPA_CORE_UTILITY_PATH', plugin_dir_path( __FILE__ ) );
define( 'UXPA_CORE_UTILITY_URL', plugin_dir_url( __FILE__ ) );

// Load Components
require_once UXPA_CORE_UTILITY_PATH . 'includes/class-uxpa-firewall.php';
require_once UXPA_CORE_UTILITY_PATH . 'includes/class-uxpa-bulk-date-updater.php';
require_once UXPA_CORE_UTILITY_PATH . 'includes/class-uxpa-taxonomy-order.php';
require_once UXPA_CORE_UTILITY_PATH . 'includes/class-uxpa-taxonomy-switcher.php';
require_once UXPA_CORE_UTILITY_PATH . 'includes/class-uxpa-taxonomy-list.php';

// Initialize Components
add_action( 'plugins_loaded', 'uxpa_core_utility_init' );

function uxpa_core_utility_init() {
    new UXPA_Firewall();
    new UXPA_Bulk_Date_Updater();
    new UXPA_Taxonomy_Order();
    new UXPA_Taxonomy_Switcher();
    new UXPA_Taxonomy_List();
}

// Plugin Activation Hook (to make sure terms order table column is check-created immediately)
register_activation_hook( __FILE__, 'uxpa_core_utility_activate' );

function uxpa_core_utility_activate() {
    global $wpdb;
    // Perform Taxonomy Terms Order DB Alteration check early
    $query = "SHOW COLUMNS FROM $wpdb->terms LIKE 'term_order'";
    $result = $wpdb->query( $query );
    if ( $result == 0 ) {
        $wpdb->query( "ALTER TABLE $wpdb->terms ADD `term_order` INT( 4 ) NULL DEFAULT '0'" );
    }
}
