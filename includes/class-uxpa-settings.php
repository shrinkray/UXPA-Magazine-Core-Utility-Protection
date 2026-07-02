<?php
/**
 * Class UXPA_Settings
 *
 * Unified settings controller and tabbed administration page.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class UXPA_Settings {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_init', array( $this, 'save_settings' ) );
    }

    /**
     * Register settings page under Settings.
     */
    public function register_menu() {
        add_options_page(
            __( 'UXPA Core Utility', 'uxpa-core-utility' ),
            __( 'UXPA Core Utility', 'uxpa-core-utility' ),
            'manage_options',
            'uxpa-core-utility',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Save settings for Firewall and Term Ordering tabs.
     */
    public function save_settings() {
        if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Save Firewall Settings
        if ( isset( $_POST['uxpa_save_firewall'] ) && wp_verify_nonce( $_POST['uxpa_firewall_nonce'], 'uxpa-save-firewall' ) ) {
            $author_block = isset( $_POST['author_block'] ) ? '1' : '0';
            update_option( 'uxpa_firewall_author_block_enabled', $author_block );
            
            add_settings_error(
                'uxpa-settings-messages',
                'uxpa_settings_updated',
                __( 'Firewall settings saved successfully.', 'uxpa-core-utility' ),
                'updated'
            );
        }

        // Save Term Ordering Settings
        if ( isset( $_POST['to_form_submit'] ) && wp_verify_nonce( $_POST['to_form_nonce'], 'to_form_submit' ) ) {
            $options = array(
                'show_reorder_interfaces' => isset( $_POST['show_reorder_interfaces'] ) ? array_map( 'sanitize_text_field', $_POST['show_reorder_interfaces'] ) : array(),
                'capability'              => isset( $_POST['capability'] ) ? sanitize_text_field( wp_unslash( $_POST['capability'] ) ) : 'manage_options',
                'autosort'                => isset( $_POST['autosort'] ) ? sanitize_key( $_POST['autosort'] ) : '0',
                'adminsort'               => isset( $_POST['adminsort'] ) ? sanitize_key( $_POST['adminsort'] ) : '0'
            );
            update_option( 'tto_options', $options );

            add_settings_error(
                'uxpa-settings-messages',
                'uxpa_settings_updated',
                __( 'Term ordering settings saved successfully.', 'uxpa-core-utility' ),
                'updated'
            );
        }
    }

    /**
     * Render settings page.
     */
    public function render_settings_page() {
        $current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'term-ordering';
        $tabs = array(
            'term-ordering'  => __( 'Term Ordering', 'uxpa-core-utility' ),
            'term-switcher'  => __( 'Term Switcher', 'uxpa-core-utility' ),
            'date-updater'   => __( 'Bulk Date Updater', 'uxpa-core-utility' ),
            'shortcode-info' => __( 'Shortcode Info', 'uxpa-core-utility' ),
            'firewall'       => __( 'Bot Firewall', 'uxpa-core-utility' )
        );

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'UXPA Magazine Core Utility & Protection', 'uxpa-core-utility' ); ?></h1>
            <p class="description"><?php esc_html_e( 'Consolidated utility suite for security and taxonomy management.', 'uxpa-core-utility' ); ?></p>
            <hr />

            <?php settings_errors( 'uxpa-settings-messages' ); ?>

            <h2 class="nav-tab-wrapper">
                <?php
                foreach ( $tabs as $tab_key => $tab_name ) {
                    $active = ( $current_tab === $tab_key ) ? 'nav-tab-active' : '';
                    echo '<a href="?page=uxpa-core-utility&tab=' . esc_attr( $tab_key ) . '" class="nav-tab ' . esc_attr( $active ) . '">' . esc_html( $tab_name ) . '</a>';
                }
                ?>
            </h2>

            <div class="tab-content" style="margin-top: 20px;">
                <?php
                switch ( $current_tab ) {
                    case 'firewall':
                        $this->render_firewall_tab();
                        break;
                    case 'term-ordering':
                        $this->render_term_ordering_tab();
                        break;
                    case 'term-switcher':
                        if ( class_exists( 'UXPA_Taxonomy_Switcher' ) ) {
                            $switcher = new UXPA_Taxonomy_Switcher();
                            $switcher->render_page_content();
                        }
                        break;
                    case 'date-updater':
                        if ( class_exists( 'UXPA_Bulk_Date_Updater' ) ) {
                            $updater = new UXPA_Bulk_Date_Updater();
                            $updater->render_page_content();
                        }
                        break;
                    case 'shortcode-info':
                        $this->render_shortcode_info_tab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render Firewall settings tab.
     */
    private function render_firewall_tab() {
        $enabled = get_option( 'uxpa_firewall_author_block_enabled', '1' );
        ?>
        <h2><?php esc_html_e( 'Bot Firewall settings', 'uxpa-core-utility' ); ?></h2>
        <form method="post" action="">
            <?php wp_nonce_field( 'uxpa-save-firewall', 'uxpa_firewall_nonce' ); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="author_block"><?php esc_html_e( 'Block Author Enumeration Scans', 'uxpa-core-utility' ); ?></label></th>
                    <td>
                        <input type="checkbox" id="author_block" name="author_block" value="1" <?php checked( $enabled, '1' ); ?> />
                        <span class="description"><?php esc_html_e( 'Block scans that list site usernames via ?author=N parameters. Non-logged-in users will receive a 403 Forbidden page.', 'uxpa-core-utility' ); ?></span>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="uxpa_save_firewall" class="button-primary" value="<?php esc_attr_e( 'Save Settings', 'uxpa-core-utility' ); ?>" />
            </p>
        </form>
        <?php
    }

    /**
     * Render Term Ordering settings tab.
     */
    private function render_term_ordering_tab() {
        if ( class_exists( 'UXPA_Taxonomy_Order' ) ) {
            $ordering = new UXPA_Taxonomy_Order();
            $ordering->render_options_page_content();
        }
    }

    /**
     * Render Shortcode Info tab.
     */
    private function render_shortcode_info_tab() {
        ?>
        <h2><?php esc_html_e( 'Taxonomy List Shortcode Help', 'uxpa-core-utility' ); ?></h2>
        <p><?php esc_html_e( 'Use the shortcode [taxonomy_list] to display a beautiful dynamic term list on the frontend.', 'uxpa-core-utility' ); ?></p>
        
        <table class="widefat fixed striped" style="margin-top: 15px; max-width: 800px;">
            <thead>
                <tr>
                    <th style="width: 25%;"><b><?php esc_html_e( 'Attribute', 'uxpa-core-utility' ); ?></b></th>
                    <th style="width: 25%;"><b><?php esc_html_e( 'Default', 'uxpa-core-utility' ); ?></b></th>
                    <th><b><?php esc_html_e( 'Description', 'uxpa-core-utility' ); ?></b></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>name</code></td>
                    <td><code>category</code></td>
                    <td><?php esc_html_e( 'The slug of the taxonomy to display.', 'uxpa-core-utility' ); ?></td>
                </tr>
                <tr>
                    <td><code>hide_empty</code></td>
                    <td><code>false</code></td>
                    <td><?php esc_html_e( 'Whether to hide terms with no posts.', 'uxpa-core-utility' ); ?></td>
                </tr>
                <tr>
                    <td><code>search_bar</code></td>
                    <td><code>0</code></td>
                    <td><?php esc_html_e( 'Set to 1 to show a dynamic search filter bar above the list.', 'uxpa-core-utility' ); ?></td>
                </tr>
                <tr>
                    <td><code>show_count</code></td>
                    <td><code>false</code></td>
                    <td><?php esc_html_e( 'Set to true to display counts of terms/posts.', 'uxpa-core-utility' ); ?></td>
                </tr>
                <tr>
                    <td><code>count_type</code></td>
                    <td><code>terms</code></td>
                    <td><?php esc_html_e( 'Use "terms" to count child terms, or "post" to count associated posts.', 'uxpa-core-utility' ); ?></td>
                </tr>
                <tr>
                    <td><code>exclude</code></td>
                    <td><i><?php esc_html_e( 'None', 'uxpa-core-utility' ); ?></i></td>
                    <td><?php esc_html_e( 'Comma-separated list of term IDs to exclude.', 'uxpa-core-utility' ); ?></td>
                </tr>
                <tr>
                    <td><code>include</code></td>
                    <td><i><?php esc_html_e( 'None', 'uxpa-core-utility' ); ?></i></td>
                    <td><?php esc_html_e( 'Comma-separated list of term IDs to include.', 'uxpa-core-utility' ); ?></td>
                </tr>
            </tbody>
        </table>

        <h3 style="margin-top: 25px;"><?php esc_html_e( 'Usage Example:', 'uxpa-core-utility' ); ?></h3>
        <pre style="background: #f4f4f4; padding: 10px; border-left: 4px solid #007cba; max-width: 800px;"><code>[taxonomy_list name="category" search_bar="1" show_count="true" count_type="post"]</code></pre>
        <?php
    }
}
