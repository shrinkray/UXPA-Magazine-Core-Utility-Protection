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
        if ( isset( $_POST['uxpa_save_firewall'] ) && isset( $_POST['uxpa_firewall_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['uxpa_firewall_nonce'] ) ), 'uxpa-save-firewall' ) ) {
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
        if ( isset( $_POST['to_form_submit'] ) && isset( $_POST['to_form_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['to_form_nonce'] ) ), 'to_form_submit' ) ) {
            $options = array(
                'show_reorder_interfaces' => isset( $_POST['show_reorder_interfaces'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['show_reorder_interfaces'] ) ) : array(),
                'capability'              => isset( $_POST['capability'] ) ? sanitize_text_field( wp_unslash( $_POST['capability'] ) ) : 'manage_options',
                'autosort'                => isset( $_POST['autosort'] ) ? sanitize_key( wp_unslash( $_POST['autosort'] ) ) : '0',
                'adminsort'               => isset( $_POST['adminsort'] ) ? sanitize_key( wp_unslash( $_POST['adminsort'] ) ) : '0'
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
        $current_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'term-ordering';
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

            <div class="settings-container-two-columns" style="display: flex; gap: 30px; margin-top: 20px; align-items: flex-start;">
                <div class="main-settings-content" style="flex: 3; min-width: 0;">
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
                                $switcher = UXPA_Taxonomy_Switcher::get_instance();
                                $switcher->render_page_content();
                            }
                            break;
                        case 'date-updater':
                            if ( class_exists( 'UXPA_Bulk_Date_Updater' ) ) {
                                $updater = UXPA_Bulk_Date_Updater::get_instance();
                                $updater->render_page_content();
                            }
                            break;
                        case 'shortcode-info':
                            $this->render_shortcode_info_tab();
                            break;
                    }
                    ?>
                </div>

                <div class="sidebar-settings-guide" style="flex: 1; min-width: 280px; max-width: 360px; background: #fff; border: 1px solid #ccd0d4; padding: 20px; border-radius: 4px; box-shadow: 0 1px 1px rgba(0,0,0,.04); box-sizing: border-box;">
                    <?php $this->render_sidebar_guide( $current_tab ); ?>
                </div>
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
            $ordering = UXPA_Taxonomy_Order::get_instance();
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

        <?php
        $taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );
        ?>
        <h3 style="margin-top: 30px;"><?php esc_html_e( 'Interactive Shortcode Generator', 'uxpa-core-utility' ); ?></h3>
        <p><?php esc_html_e( 'Configure options below to generate a customized shortcode for copy-pasting into your pages.', 'uxpa-core-utility' ); ?></p>
        
        <table class="form-table" style="max-width: 800px;">
            <tr>
                <th scope="row"><label for="sc_tax_name"><?php esc_html_e( 'Taxonomy Name (name)', 'uxpa-core-utility' ); ?></label></th>
                <td>
                    <select id="sc_tax_name">
                        <?php foreach ( $taxonomies as $tax ) : ?>
                            <option value="<?php echo esc_attr( $tax->name ); ?>" <?php selected( $tax->name, 'category' ); ?>>
                                <?php echo esc_html( $tax->label ) . ' (' . esc_html( $tax->name ) . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="sc_hide_empty"><?php esc_html_e( 'Hide Empty Terms (hide_empty)', 'uxpa-core-utility' ); ?></label></th>
                <td>
                    <input type="checkbox" id="sc_hide_empty" value="true" />
                    <span class="description"><?php esc_html_e( 'Hide terms that do not contain any posts.', 'uxpa-core-utility' ); ?></span>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="sc_search_bar"><?php esc_html_e( 'Search Filter Bar (search_bar)', 'uxpa-core-utility' ); ?></label></th>
                <td>
                    <input type="checkbox" id="sc_search_bar" value="1" />
                    <span class="description"><?php esc_html_e( 'Display a live text-search input box above the list.', 'uxpa-core-utility' ); ?></span>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="sc_show_count"><?php esc_html_e( 'Show Counts (show_count)', 'uxpa-core-utility' ); ?></label></th>
                <td>
                    <input type="checkbox" id="sc_show_count" value="true" />
                    <span class="description"><?php esc_html_e( 'Display term or associated post counts.', 'uxpa-core-utility' ); ?></span>
                </td>
            </tr>
            <tr id="sc_count_type_row" style="display: none;">
                <th scope="row"><label for="sc_count_type"><?php esc_html_e( 'Count Type (count_type)', 'uxpa-core-utility' ); ?></label></th>
                <td>
                    <select id="sc_count_type">
                        <option value="terms"><?php esc_html_e( 'Count Child Terms (terms)', 'uxpa-core-utility' ); ?></option>
                        <option value="post"><?php esc_html_e( 'Count Associated Posts (post)', 'uxpa-core-utility' ); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="sc_include"><?php esc_html_e( 'Limit to Term IDs (include)', 'uxpa-core-utility' ); ?></label></th>
                <td>
                    <input type="text" id="sc_include" placeholder="<?php echo esc_attr_x( 'e.g. 12, 15, 23', 'shortcode include placeholder', 'uxpa-core-utility' ); ?>" class="regular-text" />
                    <span class="description"><?php esc_html_e( 'Comma-separated IDs of terms to show (leave empty for all).', 'uxpa-core-utility' ); ?></span>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="sc_exclude"><?php esc_html_e( 'Exclude Term IDs (exclude)', 'uxpa-core-utility' ); ?></label></th>
                <td>
                    <input type="text" id="sc_exclude" placeholder="<?php echo esc_attr_x( 'e.g. 3, 5, 8', 'shortcode exclude placeholder', 'uxpa-core-utility' ); ?>" class="regular-text" />
                    <span class="description"><?php esc_html_e( 'Comma-separated IDs of terms to hide.', 'uxpa-core-utility' ); ?></span>
                </td>
            </tr>
        </table>

        <div style="margin-top: 20px; max-width: 800px; padding: 15px; background: #fff; border: 1px solid #c3c4c7; border-left: 4px solid #2271b1; box-shadow: 0 1px 1px rgba(0,0,0,.04); position: relative;">
            <h4 style="margin-top: 0; font-size: 14px;"><?php esc_html_e( 'Generated Shortcode:', 'uxpa-core-utility' ); ?></h4>
            <div style="display: flex; align-items: center; gap: 10px;">
                <code id="sc_output_code" style="font-size: 14px; font-family: monospace; background: #f0f0f1; padding: 6px 10px; border-radius: 4px; flex-grow: 1; border: 1px solid #dcdcde;">[taxonomy_list name="category"]</code>
                <button type="button" id="sc_copy_btn" class="button button-secondary"><?php esc_html_e( 'Copy to Clipboard', 'uxpa-core-utility' ); ?></button>
            </div>
            <span id="sc_copy_status" style="position: absolute; right: 150px; bottom: 20px; color: #46b450; font-weight: bold; display: none;"><?php esc_html_e( 'Copied!', 'uxpa-core-utility' ); ?></span>
        </div>

        <p style="margin-top: 20px;"><?php echo wp_kses_post( __( 'Replaces plugin: <strong>Taxonomy List</strong>', 'uxpa-core-utility' ) ); ?></p>

        <script type="text/javascript">
            jQuery(document).ready(function($) {
                function updateShortcode() {
                    var name = $('#sc_tax_name').val();
                    var hideEmpty = $('#sc_hide_empty').is(':checked');
                    var searchBar = $('#sc_search_bar').is(':checked');
                    var showCount = $('#sc_show_count').is(':checked');
                    var countType = $('#sc_count_type').val();
                    var include = $('#sc_include').val().trim();
                    var exclude = $('#sc_exclude').val().trim();

                    if (showCount) {
                        $('#sc_count_type_row').show();
                    } else {
                        $('#sc_count_type_row').hide();
                    }

                    var shortcode = '[taxonomy_list';
                    
                    if (name && name !== 'category') {
                        shortcode += ' name="' + name + '"';
                    } else {
                        shortcode += ' name="category"';
                    }

                    if (hideEmpty) {
                        shortcode += ' hide_empty="true"';
                    }

                    if (searchBar) {
                        shortcode += ' search_bar="1"';
                    }

                    if (showCount) {
                        shortcode += ' show_count="true"';
                        if (countType && countType !== 'terms') {
                            shortcode += ' count_type="' + countType + '"';
                        }
                    }

                    if (include) {
                        include = include.replace(/\s*,\s*/g, ',');
                        shortcode += ' include="' + include + '"';
                    }

                    if (exclude) {
                        exclude = exclude.replace(/\s*,\s*/g, ',');
                        shortcode += ' exclude="' + exclude + '"';
                    }

                    shortcode += ']';

                    $('#sc_output_code').text(shortcode);
                }

                // Attach events
                $('#sc_tax_name, #sc_hide_empty, #sc_search_bar, #sc_show_count, #sc_count_type, #sc_include, #sc_exclude').on('change keyup input', updateShortcode);

                // Copy to clipboard
                $('#sc_copy_btn').on('click', function(e) {
                    e.preventDefault();
                    var codeText = $('#sc_output_code').text();
                    
                    function showStatus() {
                        $('#sc_copy_status').fadeIn(200).delay(1500).fadeOut(200);
                    }

                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(codeText).then(function() {
                            showStatus();
                        });
                    } else {
                        var $temp = $('<input>');
                        $('body').append($temp);
                        $temp.val(codeText).select();
                        document.execCommand('copy');
                        $temp.remove();
                        showStatus();
                    }
                });

                // Run once on load
                updateShortcode();
            });
        </script>
        <?php
    }

    /**
     * Render sidebar guide for the current active tab.
     */
    private function render_sidebar_guide( $tab ) {
        switch ( $tab ) {
            case 'term-ordering':
                ?>
                <h3 style="margin-top: 0; font-size: 16px; border-bottom: 1px solid #eee; padding-bottom: 8px;">
                    <span class="dashicons dashicons-editor-ol" style="vertical-align: text-bottom; margin-right: 4px;"></span>
                    <?php esc_html_e( 'Term Ordering Guide', 'uxpa-core-utility' ); ?>
                </h3>
                <p><strong><?php esc_html_e( 'Why sort terms?', 'uxpa-core-utility' ); ?></strong></p>
                <p><?php esc_html_e( 'By default, WordPress queries sort terms alphabetically. Custom sorting lets you define the exact sequence of categories or tags on your frontend (e.g., ordering post sections on your home page).', 'uxpa-core-utility' ); ?></p>
                <p><strong><?php esc_html_e( 'Tips:', 'uxpa-core-utility' ); ?></strong></p>
                <ul style="list-style-type: disc; padding-left: 20px; margin: 10px 0;">
                    <li><?php esc_html_e( 'Keep Auto Sort enabled to apply this order automatically across all theme queries.', 'uxpa-core-utility' ); ?></li>
                    <li><?php esc_html_e( 'Use Admin Sort to see your custom order reflected inside the WP Admin taxonomy tables.', 'uxpa-core-utility' ); ?></li>
                </ul>
                <p><strong><?php esc_html_e( 'How to sort:', 'uxpa-core-utility' ); ?></strong></p>
                <p><?php esc_html_e( 'Go to any post type menu (like Posts or Products) and select the "Taxonomy Order" option to drag and drop terms.', 'uxpa-core-utility' ); ?></p>
                <?php
                break;

            case 'term-switcher':
                ?>
                <h3 style="margin-top: 0; font-size: 16px; border-bottom: 1px solid #eee; padding-bottom: 8px;">
                    <span class="dashicons dashicons-randomize" style="vertical-align: text-bottom; margin-right: 4px;"></span>
                    <?php esc_html_e( 'Term Switcher Guide', 'uxpa-core-utility' ); ?>
                </h3>
                <p><strong><?php esc_html_e( 'When to switch?', 'uxpa-core-utility' ); ?></strong></p>
                <p><?php esc_html_e( 'Useful when you need to merge tags into categories, reorganize post taxonomies, or clean up imported layouts from external tools.', 'uxpa-core-utility' ); ?></p>
                <p><strong><?php esc_html_e( 'Tips:', 'uxpa-core-utility' ); ?></strong></p>
                <ul style="list-style-type: disc; padding-left: 20px; margin: 10px 0;">
                    <li><?php esc_html_e( 'You can input specific term IDs separated by commas to migrate selective tags only.', 'uxpa-core-utility' ); ?></li>
                    <li><?php esc_html_e( 'Utilize the autocomplete Parent box to switch only the sub-terms of a specific parent term.', 'uxpa-core-utility' ); ?></li>
                </ul>
                <p style="color: #d63638; font-weight: 600;">
                    <span class="dashicons dashicons-warning" style="vertical-align: text-bottom; font-size: 16px; width: 16px; height: 16px;"></span>
                    <?php esc_html_e( 'Backup your database before executing bulk taxonomy switches.', 'uxpa-core-utility' ); ?>
                </p>
                <?php
                break;

            case 'date-updater':
                ?>
                <h3 style="margin-top: 0; font-size: 16px; border-bottom: 1px solid #eee; padding-bottom: 8px;">
                    <span class="dashicons dashicons-calendar-alt" style="vertical-align: text-bottom; margin-right: 4px;"></span>
                    <?php esc_html_e( 'Date Updater Guide', 'uxpa-core-utility' ); ?>
                </h3>
                <p><strong><?php esc_html_e( 'Why randomize dates?', 'uxpa-core-utility' ); ?></strong></p>
                <p><?php esc_html_e( 'Refreshing post updates notifies search engines (like Google) that your archive is active. Spreading the modification timestamps makes the content look organically updated.', 'uxpa-core-utility' ); ?></p>
                <p><strong><?php esc_html_e( 'Tips:', 'uxpa-core-utility' ); ?></strong></p>
                <ul style="list-style-type: disc; padding-left: 20px; margin: 10px 0;">
                    <li><?php esc_html_e( 'Updating the "Modified Date" only is highly recommended. It preserves the original post publication order.', 'uxpa-core-utility' ); ?></li>
                    <li><?php esc_html_e( 'Select a specific custom post type or category to only refresh dates for specific topics.', 'uxpa-core-utility' ); ?></li>
                </ul>
                <?php
                break;

            case 'shortcode-info':
                ?>
                <h3 style="margin-top: 0; font-size: 16px; border-bottom: 1px solid #eee; padding-bottom: 8px;">
                    <span class="dashicons dashicons-shortcode" style="vertical-align: text-bottom; margin-right: 4px;"></span>
                    <?php esc_html_e( 'Shortcode List Guide', 'uxpa-core-utility' ); ?>
                </h3>
                <p><strong><?php esc_html_e( 'About the Shortcode:', 'uxpa-core-utility' ); ?></strong></p>
                <p><?php esc_html_e( 'Use [taxonomy_list] to generate dynamic index layouts on pages or posts. It includes search bar filtering and column configurations.', 'uxpa-core-utility' ); ?></p>
                <p><strong><?php esc_html_e( 'Usage Tips:', 'uxpa-core-utility' ); ?></strong></p>
                <ul style="list-style-type: disc; padding-left: 20px; margin: 10px 0;">
                    <li><?php esc_html_e( 'Specify count_type="post" to let users see how many posts are assigned to each term.', 'uxpa-core-utility' ); ?></li>
                    <li><?php esc_html_e( 'Add search_bar="1" to output a live search filter box at the top of the term list.', 'uxpa-core-utility' ); ?></li>
                </ul>
                <?php
                break;

            case 'firewall':
                ?>
                <h3 style="margin-top: 0; font-size: 16px; border-bottom: 1px solid #eee; padding-bottom: 8px;">
                    <span class="dashicons dashicons-shield" style="vertical-align: text-bottom; margin-right: 4px;"></span>
                    <?php esc_html_e( 'Bot Firewall Guide', 'uxpa-core-utility' ); ?>
                </h3>
                <p><strong><?php esc_html_e( 'What does it block?', 'uxpa-core-utility' ); ?></strong></p>
                <p><?php esc_html_e( 'Malicious bots crawl WordPress sites scanning for ?author=N parameters to list valid user logins. Once found, they target those accounts with brute-force login attempts.', 'uxpa-core-utility' ); ?></p>
                <p><strong><?php esc_html_e( 'Tips:', 'uxpa-core-utility' ); ?></strong></p>
                <ul style="list-style-type: disc; padding-left: 20px; margin: 10px 0;">
                    <li><?php esc_html_e( 'Keep this enabled for solid site hardening.', 'uxpa-core-utility' ); ?></li>
                    <li><?php esc_html_e( 'Logged-in administrators and editors can still browse author archives without interruption.', 'uxpa-core-utility' ); ?></li>
                </ul>
                <?php
                break;
        }
    }
}
