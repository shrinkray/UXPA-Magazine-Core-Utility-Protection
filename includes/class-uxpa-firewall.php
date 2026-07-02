<?php
/**
 * Class UXPA_Firewall
 *
 * High-efficiency early-exit hooks to block author enumeration and malicious scans.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class UXPA_Firewall {

    public function __construct() {
        add_action( 'init', array( $this, 'kill_author_enumeration' ), 1 );
    }

    /**
     * Terminate user enumeration scans before WordPress runs heavy database tasks.
     */
    public function kill_author_enumeration() {
        // Only run for non-logged in users
        if ( is_user_logged_in() ) {
            return;
        }

        // If author parameter is set, drop the request
        if ( isset( $_GET['author'] ) ) {
            // Check if blocking is enabled (default is enabled)
            $is_enabled = get_option( 'uxpa_firewall_author_block_enabled', '1' );
            if ( $is_enabled === '1' ) {
                status_header( 403 );
                nocache_headers();
                die( 'Resource locked.' );
            }
        }
    }
}
