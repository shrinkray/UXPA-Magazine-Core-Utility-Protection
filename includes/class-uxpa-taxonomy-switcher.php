<?php
/**
 * Class UXPA_Taxonomy_Switcher
 *
 * Implements a fast tool page to switch terms from one taxonomy to another.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class UXPA_Taxonomy_Switcher {

    private $notices = array();

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_page' ) );
        add_action( 'admin_init', array( $this, 'handle_switcher_action' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_ajax_taxonomy_switcher_search_term_handler', array( $this, 'ajax_term_results' ) );
    }

    /**
     * Enqueue JS for Taxonomy Switcher and pass taxonomy metadata inline.
     */
    public function enqueue_assets( $hook ) {
        if ( 'tools_page_taxonomy-switcher' !== $hook ) {
            return;
        }

        wp_enqueue_script( 'uxpa-taxonomy-switcher', plugins_url( '../assets/js/taxonomy-switcher.js', __FILE__ ), array(), '1.0', true );

        $taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );
        $tax_data = array();
        foreach ( $taxonomies as $tax ) {
            $tax_data[] = array(
                'taxonomy'     => $tax->name,
                'hierarchical' => $tax->hierarchical ? 'true' : 'false',
            );
        }

        wp_add_inline_script(
            'uxpa-taxonomy-switcher',
            'const tsTaxData = ' . json_encode( $tax_data ),
            'before'
        );
    }

    /**
     * Add the page under Tools.
     */
    public function add_page() {
        add_management_page(
            __( 'Taxonomy Switcher', 'uxpa-core-utility' ),
            __( 'Taxonomy Switcher', 'uxpa-core-utility' ),
            'manage_options',
            'taxonomy-switcher',
            array( $this, 'render_page' )
        );
    }

    /**
     * Handle AJAX term searches for limit-by-parent feature.
     */
    public function ajax_term_results() {
        if ( ! ( isset( $_REQUEST['nonce'], $_REQUEST['search'] ) && wp_verify_nonce( $_REQUEST['nonce'], 'taxonomy-switcher-ajax-nonce' ) ) ) {
            wp_send_json_error( array( 'html' => '<ul><li>' . esc_html__( 'Security check failed', 'uxpa-core-utility' ) . '</li></ul>' ) );
        }

        $taxonomy = isset( $_REQUEST['tax_name'] ) ? sanitize_key( $_REQUEST['tax_name'] ) : 'category';
        $search_string = sanitize_text_field( $_REQUEST['search'] );

        if ( empty( $search_string ) ) {
            wp_send_json_error( array( 'html' => '<ul><li>' . esc_html__( 'Please try again', 'uxpa-core-utility' ) . '</li></ul>' ) );
        }

        $terms = get_terms( array(
            'taxonomy'     => $taxonomy,
            'number'       => 10,
            'name__like'   => $search_string,
            'get'          => 'all',
            'hide_empty'   => false,
        ) );

        if ( empty( $terms ) || is_wp_error( $terms ) ) {
            wp_send_json_error( array( 'html' => '<ul><li>' . esc_html__( 'No terms found', 'uxpa-core-utility' ) . '</li></ul>' ) );
        }

        $items = '';
        foreach ( $terms as $term ) {
            $children = get_terms( array(
                'taxonomy'   => $term->taxonomy,
                'parent'     => $term->term_id,
                'hide_empty' => false,
            ) );

            if ( ! $children || is_wp_error( $children ) ) {
                continue;
            }

            $parent_name = $term->parent ? get_term_by( 'id', $term->parent, $term->taxonomy )->name . ' &rarr; ' : '';
            $items .= '<li><a data-slug="' . esc_attr( $term->slug ) . '" data-termid="' . esc_attr( $term->term_id ) . '" href="#">' . esc_html( $parent_name . $term->name ) . '</a></li>';
        }

        if ( empty( $items ) ) {
            wp_send_json_error( array( 'html' => '<ul><li>' . esc_html__( 'No terms with children found', 'uxpa-core-utility' ) . '</li></ul>' ) );
        }

        wp_send_json_success( array( 'html' => sprintf( '<ol>%s</ol>', $items ) ) );
    }

    /**
     * Intercept and process taxonomy term switching.
     */
    public function handle_switcher_action() {
        if (
            ! isset( $_GET['taxonomy_switcher'] )
            || $_GET['taxonomy_switcher'] != 1
            || ! current_user_can( 'manage_options' )
            || ! isset( $_GET['from_tax'] )
            || empty( $_GET['from_tax'] )
            || ! isset( $_GET['to_tax'] )
            || empty( $_GET['to_tax'] )
        ) {
            return;
        }

        // Verify Nonce
        if ( ! isset( $_GET['taxonomy_switcher_nonce'] ) || ! wp_verify_nonce( $_GET['taxonomy_switcher_nonce'], 'taxonomy-switcher-action' ) ) {
            return;
        }

        $from_tax = sanitize_key( $_GET['from_tax'] );
        $to_tax   = sanitize_key( $_GET['to_tax'] );
        $parent_id = isset( $_GET['parent'] ) ? absint( $_GET['parent'] ) : 0;
        $specific_terms = isset( $_GET['terms'] ) ? wp_parse_id_list( $_GET['terms'] ) : array();

        $args = array(
            'hide_empty' => false,
            'fields'     => 'ids',
            'child_of'   => $parent_id,
            'include'    => $specific_terms,
        );

        $term_ids = get_terms( $from_tax, $args );

        if ( empty( $term_ids ) || is_wp_error( $term_ids ) ) {
            $this->notices[] = __( 'No terms to be switched. Check if the term exists in your "from" taxonomy.', 'uxpa-core-utility' );
            set_transient( 'uxpa_taxonomy_switcher_notices', $this->notices, 45 );
            wp_redirect( esc_url_raw( add_query_arg( array( 'page' => 'taxonomy-switcher' ), admin_url( 'tools.php' ) ) ) );
            exit;
        }

        global $wpdb;

        $term_ids_clean = array_map( 'absint', $term_ids );
        $term_ids_list = implode( ', ', $term_ids_clean );

        // Update taxonomy for terms
        $wpdb->query( $wpdb->prepare( "
            UPDATE `{$wpdb->term_taxonomy}`
            SET `taxonomy` = %s
            WHERE `taxonomy` = %s AND `term_id` IN ( {$term_ids_list} )
        ", $to_tax, $from_tax ) );

        // Update parents if limit by parent is active
        if ( $parent_id > 0 ) {
            $wpdb->query( $wpdb->prepare( "
                UPDATE `{$wpdb->term_taxonomy}`
                SET `parent` = 0
                WHERE `parent` = %d AND `term_id` IN ( {$term_ids_list} )
            ", $parent_id ) );
        }

        // Update post menu item references
        $post_ids = $wpdb->get_col( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_menu_item_object_id' AND meta_value IN ( {$term_ids_list} );" );
        if ( ! empty( $post_ids ) ) {
            update_postmeta_cache( $post_ids );
            foreach ( $post_ids as $post_id ) {
                $type   = get_post_meta( $post_id, '_menu_item_type', true );
                $object = get_post_meta( $post_id, '_menu_item_object', true );
                if ( 'taxonomy' === $type && $from_tax === $object ) {
                    update_post_meta( $post_id, '_menu_item_object', $to_tax );
                    clean_post_cache( $post_id );
                }
            }
        }

        clean_term_cache( $term_ids_clean, $from_tax );
        clean_term_cache( $term_ids_clean, $to_tax );

        $count = count( $term_ids_clean );
        $count_name = sprintf( _n( '1 term', '%d terms', $count, 'uxpa-core-utility' ), $count );

        $this->notices[] = sprintf( __( 'Switching %s with the taxonomy \'%s\' to the taxonomy \'%s\'', 'uxpa-core-utility' ), $count_name, $from_tax, $to_tax );
        if ( $parent_id > 0 ) {
            $this->notices[] = sprintf( __( 'Limiting the switch by the parent term ID of %d', 'uxpa-core-utility' ), $parent_id );
        } elseif ( ! empty( $specific_terms ) ) {
            $this->notices[] = sprintf( __( 'Limiting the switch to these terms: %s', 'uxpa-core-utility' ), implode( ', ', $specific_terms ) );
        }
        $this->notices[] = sprintf( __( 'Taxonomies switched for %s!', 'uxpa-core-utility' ), $count_name );

        set_transient( 'uxpa_taxonomy_switcher_notices', $this->notices, 45 );
        wp_redirect( esc_url_raw( add_query_arg( array( 'page' => 'taxonomy-switcher' ), admin_url( 'tools.php' ) ) ) );
        exit;
    }

    /**
     * Render the admin management interface page.
     */
    public function render_page() {
        $registered_taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );
        $transient_notices = get_transient( 'uxpa_taxonomy_switcher_notices' );
        if ( $transient_notices ) {
            delete_transient( 'uxpa_taxonomy_switcher_notices' );
            ?>
            <div id="message" class="updated">
                <p><?php echo implode( '</p><p>', array_map( 'esc_html', $transient_notices ) ); ?></p>
            </div>
            <?php
        }
        ?>
        <div id="wds-taxonomy-switcher" class="wrap taxonomy-switcher">
            <h2><?php esc_html_e( 'Taxonomy Switcher', 'uxpa-core-utility' ); ?></h2>

            <form method="get" action="">
                <input type="hidden" name="taxonomy_switcher" value="1" />
                <input type="hidden" name="page" value="taxonomy-switcher" />
                <input type="hidden" id="taxonomy_switcher_nonce" name="taxonomy_switcher_nonce" value="<?php echo esc_attr( wp_create_nonce( 'taxonomy-switcher-action' ) ); ?>" />
                <input type="hidden" id="taxonomy_switcher_ajax_nonce" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'taxonomy-switcher-ajax-nonce' ) ); ?>" />

                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="from_tax"><?php esc_html_e( 'Taxonomy to switch from:', 'uxpa-core-utility' ); ?></label></th>
                            <td>
                                <select name="from_tax" id="from_tax">
                                    <?php
                                    foreach ( $registered_taxonomies as $slug => $tax ) {
                                        echo '<option value="' . esc_attr( $slug ) . '">' . esc_html( $tax->labels->name ) . '</option>';
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="to_tax"><?php esc_html_e( 'Taxonomy to switch to:', 'uxpa-core-utility' ); ?></label></th>
                            <td>
                                <select name="to_tax" id="to_tax">
                                    <?php
                                    foreach ( $registered_taxonomies as $slug => $tax ) {
                                        echo '<option value="' . esc_attr( $slug ) . '">' . esc_html( $tax->labels->name ) . '</option>';
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="taxonomy-switcher-terms"><?php esc_html_e( 'Comma separated list of term IDs to switch (optional):', 'uxpa-core-utility' ); ?></label></th>
                            <td>
                                <input placeholder="e.g. 1,2,13" class="regular-text" type="text" id="taxonomy-switcher-terms" name="terms" value="" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="taxonomy-switcher-parent"><?php esc_html_e( 'Limit switch to child terms of a specific parent (optional):', 'uxpa-core-utility' ); ?></label></th>
                            <td>
                                <input class="regular-text" type="text" id="taxonomy-switcher-parent" name="parent" value="" placeholder="<?php esc_attr_e( 'Start typing parent term name...', 'uxpa-core-utility' ); ?>" />
                                <span class="taxonomy-switcher-spinner spinner"></span>
                                <p class="taxonomy-switcher-ajax-results-help" style="display:none;"><?php esc_html_e( 'Select a term:', 'uxpa-core-utility' ); ?></p>
                                <div class="taxonomy-switcher-ajax-results-posts"></div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php submit_button( __( 'Switch Taxonomies', 'uxpa-core-utility' ) ); ?>
            </form>
        </div>
        <?php
    }
}
