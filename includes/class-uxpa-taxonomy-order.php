<?php
/**
 * Class UXPA_Taxonomy_Order
 *
 * Handles taxonomy term ordering via drag & drop and filters term queries to apply ordering.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class UXPA_Taxonomy_Order {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
        add_action( 'admin_menu', array( $this, 'admin_menu' ), 99 );
        add_filter( 'terms_clauses', array( $this, 'apply_order_filter' ), 10, 3 );
        add_filter( 'get_terms_orderby', array( $this, 'get_terms_orderby' ), 1, 2 );
        add_action( 'wp_ajax_update-taxonomy-order', array( $this, 'save_ajax_order' ) );

        if ( is_admin() ) {
            $this->check_table_column();
        }
    }

    /**
     * Check if term_order column exists in terms table, and create it if not.
     */
    public function check_table_column() {
        global $wpdb;
        $query = "SHOW COLUMNS FROM $wpdb->terms LIKE 'term_order'";
        $result = $wpdb->query( $query );
        if ( $result == 0 ) {
            $wpdb->query( "ALTER TABLE $wpdb->terms ADD `term_order` INT( 4 ) NULL DEFAULT '0'" );
        }
    }

    /**
     * Get settings, merging with defaults.
     */
    public function get_settings() {
        $settings = get_option( 'tto_options' );
        $defaults = array(
            'show_reorder_interfaces' => array(),
            'capability'              => 'manage_options',
            'autosort'                => '1',
            'adminsort'               => '1'
        );
        return wp_parse_args( $settings, $defaults );
    }

    /**
     * Enqueue CSS and JS assets.
     */
    public function admin_enqueue_scripts( $hook ) {
        if ( strpos( $hook, 'to-interface' ) === false && strpos( $hook, 'settings_page_to-options' ) === false ) {
            return;
        }

        wp_enqueue_style( 'uxpa-to-css', plugins_url( '../assets/css/taxonomy-order.css', __FILE__ ), array(), '1.0' );
        wp_enqueue_script( 'jquery' );
        wp_enqueue_script( 'jquery-ui-sortable' );
        wp_enqueue_script( 'uxpa-to-js', plugins_url( '../assets/js/taxonomy-order.js', __FILE__ ), array( 'jquery', 'jquery-ui-sortable' ), '1.0', false );
    }

    public function admin_menu() {
        $options = $this->get_settings();
        $capability = ! empty( $options['capability'] ) ? $options['capability'] : 'manage_options';

        // Add sorting interface submenu to eligible post types
        $post_types = get_post_types();
        foreach ( $post_types as $post_type ) {
            $post_type_taxonomies = get_object_taxonomies( $post_type );

            // Filter down to hierarchical taxonomies only
            foreach ( $post_type_taxonomies as $key => $taxonomy_name ) {
                $taxonomy_info = get_taxonomy( $taxonomy_name );
                if ( empty( $taxonomy_info->hierarchical ) || $taxonomy_info->hierarchical !== true ) {
                    unset( $post_type_taxonomies[ $key ] );
                }
            }

            if ( count( $post_type_taxonomies ) == 0 ) {
                continue;
            }

            if ( isset( $options['show_reorder_interfaces'][ $post_type ] ) && $options['show_reorder_interfaces'][ $post_type ] === 'hide' ) {
                continue;
            }

            if ( $post_type === 'post' ) {
                add_submenu_page( 'edit.php', __( 'Taxonomy Order', 'uxpa-core-utility' ), __( 'Taxonomy Order', 'uxpa-core-utility' ), $capability, 'to-interface-' . $post_type, array( $this, 'render_interface_page' ) );
            } elseif ( $post_type === 'attachment' ) {
                add_submenu_page( 'upload.php', __( 'Taxonomy Order', 'uxpa-core-utility' ), __( 'Taxonomy Order', 'uxpa-core-utility' ), $capability, 'to-interface-' . $post_type, array( $this, 'render_interface_page' ) );
            } else {
                add_submenu_page( 'edit.php?post_type=' . $post_type, __( 'Taxonomy Order', 'uxpa-core-utility' ), __( 'Taxonomy Order', 'uxpa-core-utility' ), $capability, 'to-interface-' . $post_type, array( $this, 'render_interface_page' ) );
            }
        }
    }

    /**
     * Render the interface sorting page.
     */
    public function render_interface_page() {
        global $wpdb;

        $taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_key( $_GET['taxonomy'] ) : '';
        $post_type = isset( $_GET['post_type'] ) ? sanitize_key( $_GET['post_type'] ) : '';

        if ( empty( $post_type ) ) {
            $screen = get_current_screen();
            if ( isset( $screen->post_type ) && ! empty( $screen->post_type ) ) {
                $post_type = $screen->post_type;
            } else {
                $post_type = ( $screen->parent_file === 'upload.php' ) ? 'attachment' : 'post';
            }
        }

        $post_type_data = get_post_type_object( $post_type );

        if ( ! taxonomy_exists( $taxonomy ) ) {
            $taxonomy = '';
        }

        ?>
        <div class="wrap">
            <h2><?php esc_html_e( 'Taxonomy Order', 'uxpa-core-utility' ); ?></h2>
            <div id="ajax-response"></div>

            <noscript>
                <div class="error message">
                    <p><?php esc_html_e( 'This plugin requires JavaScript for drag-and-drop sorting.', 'uxpa-core-utility' ); ?></p>
                </div>
            </noscript>

            <?php
            $current_section_parent_file = ( $post_type === 'attachment' ) ? 'upload.php' : 'edit.php';
            ?>

            <form action="<?php echo esc_attr( $current_section_parent_file ); ?>" method="get" id="to_form">
                <input type="hidden" name="page" value="to-interface-<?php echo esc_attr( $post_type ); ?>" />
                <?php
                if ( ! in_array( $post_type, array( 'post', 'attachment' ), true ) ) {
                    echo '<input type="hidden" name="post_type" value="' . esc_attr( $post_type ) . '" />';
                }

                $post_type_taxonomies = get_object_taxonomies( $post_type );
                foreach ( $post_type_taxonomies as $key => $taxonomy_name ) {
                    $taxonomy_info = get_taxonomy( $taxonomy_name );
                    if ( ! $taxonomy_info || $taxonomy_info->hierarchical !== true ) {
                        unset( $post_type_taxonomies[ $key ] );
                    }
                }

                if ( $taxonomy === '' || ! taxonomy_exists( $taxonomy ) ) {
                    reset( $post_type_taxonomies );
                    $taxonomy = current( $post_type_taxonomies );
                }

                if ( count( $post_type_taxonomies ) > 1 ) {
                    ?>
                    <h2 class="subtitle"><?php echo esc_html( ucfirst( $post_type_data->labels->name ) ); ?> <?php esc_html_e( 'Taxonomies', 'uxpa-core-utility' ); ?></h2>
                    <table cellspacing="0" class="wp-list-taxonomy">
                        <thead>
                            <tr>
                                <th class="column-cb check-column" scope="col">&nbsp;</th>
                                <th scope="col"><?php esc_html_e( 'Taxonomy Title', 'uxpa-core-utility' ); ?></th>
                                <th scope="col"><?php esc_html_e( 'Total Terms', 'uxpa-core-utility' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $alternate = false;
                            foreach ( $post_type_taxonomies as $post_type_taxonomy ) {
                                $taxonomy_info = get_taxonomy( $post_type_taxonomy );
                                $alternate = ! $alternate;
                                $taxonomy_terms = get_terms( array( 'hide_empty' => 0, 'taxonomy' => $post_type_taxonomy ) );
                                ?>
                                <tr class="<?php echo $alternate ? 'alternate' : ''; ?>">
                                    <th class="check-column" scope="row">
                                        <input type="radio" onclick="to_change_taxonomy(this)" value="<?php echo esc_attr( $post_type_taxonomy ); ?>" <?php checked( $post_type_taxonomy, $taxonomy ); ?> name="taxonomy" />
                                    </th>
                                    <td><b><?php echo esc_html( $taxonomy_info->label ); ?></b> (<?php echo esc_html( $taxonomy_info->labels->singular_name ); ?>)</td>
                                    <td><?php echo is_wp_error( $taxonomy_terms ) ? 0 : count( $taxonomy_terms ); ?></td>
                                </tr>
                                <?php
                            }
                            ?>
                        </tbody>
                    </table>
                    <br />
                    <?php
                }
                ?>

                <div id="order-terms">
                    <div id="nav-menu-header">
                        <div class="major-publishing-actions">
                            <div class="alignright actions">
                                <p class="actions">
                                    <span class="spinner" style="float: left; margin: 4px 10px 0 0;"></span>
                                    <a href="javascript:;" class="save-order button-primary"><?php esc_html_e( 'Update', 'uxpa-core-utility' ); ?></a>
                                </p>
                            </div>
                            <div class="clear"></div>
                        </div>
                    </div>

                    <div id="post-body">
                        <ul class="sortable" id="tto_sortable">
                            <?php $this->list_terms( $taxonomy ); ?>
                        </ul>
                        <div class="clear"></div>
                    </div>

                    <div id="nav-menu-footer">
                        <div class="major-publishing-actions">
                            <div class="alignright actions">
                                <span class="spinner" style="float: left; margin: 4px 10px 0 0;"></span>
                                <a href="javascript:;" class="save-order button-primary"><?php esc_html_e( 'Update', 'uxpa-core-utility' ); ?></a>
                            </div>
                            <div class="clear"></div>
                        </div>
                    </div>
                </div>
            </form>

            <script type="text/javascript">
                jQuery(document).ready(function($) {
                    $("ul.sortable").sortable({
                        'tolerance': 'intersect',
                        'cursor': 'pointer',
                        'items': '> li',
                        'axis': 'y',
                        'placeholder': 'placeholder',
                        'nested': 'ul'
                    });

                    $(".save-order").bind("click", function() {
                        var $button = $(this);
                        $button.parent().find('.spinner').addClass('is-active');

                        var mySortable = new Array();
                        $(".sortable").each(function() {
                            var serialized = $(this).sortable("serialize");
                            var parent_tag = $(this).parent().get(0).tagName.toLowerCase();
                            if (parent_tag === 'li') {
                                var tag_id = $(this).parent().attr('id');
                                mySortable[tag_id] = serialized;
                            } else {
                                mySortable[0] = serialized;
                            }
                        });

                        var serialize_data = JSON.stringify(convArrToObj(mySortable));

                        $.post(ajaxurl, {
                            action: 'update-taxonomy-order',
                            order: serialize_data,
                            nonce: '<?php echo esc_attr( wp_create_nonce( 'update-taxonomy-order' ) ); ?>'
                        }, function() {
                            $("#ajax-response").html('<div class="message updated fade"><p><?php esc_html_e( 'Items Order Updated', 'uxpa-core-utility' ); ?></p></div>');
                            $("#ajax-response div").delay(3000).hide("slow");
                            $('.spinner').removeClass('is-active');
                        });
                    });
                });
            </script>
        </div>
        <?php
    }

    /**
     * Output list of terms hierarchically.
     */
    public function list_terms( $taxonomy ) {
        $args = array(
            'orderby'    => 'term_order',
            'depth'      => 0,
            'child_of'   => 0,
            'hide_empty' => 0,
            'taxonomy'   => $taxonomy,
        );
        $taxonomy_terms = get_terms( $args );

        if ( ! is_wp_error( $taxonomy_terms ) && count( $taxonomy_terms ) > 0 ) {
            $walker = new UXPA_TO_Terms_Walker();
            echo $walker->walk( $taxonomy_terms, $args['depth'], $args );
        }
    }

    /**
     * AJAX Save handler.
     */
    public function save_ajax_order() {
        global $wpdb;

        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'update-taxonomy-order' ) ) {
            die();
        }

        $data = isset( $_POST['order'] ) ? stripslashes( sanitize_text_field( wp_unslash( $_POST['order'] ) ) ) : "";
        $unserialised_data = json_decode( $data, true );

        if ( is_array( $unserialised_data ) ) {
            foreach ( $unserialised_data as $key => $values ) {
                $items = explode( "&", $values );
                foreach ( $items as $item_key => $item_ ) {
                    $items[ $item_key ] = trim( str_replace( "item[]=", "", $item_ ) );
                }

                if ( is_array( $items ) && count( $items ) > 0 ) {
                    foreach ( $items as $item_key => $term_id ) {
                        $wpdb->update( $wpdb->terms, array( 'term_order' => ( $item_key + 1 ) ), array( 'term_id' => intval( $term_id ) ) );
                    }
                    clean_term_cache( $items );
                }
            }
        }

        do_action( 'tto/update-order' );
        wp_cache_flush();
        die();
    }

    /**
     * Apply terms_clauses ordering.
     */
    public function apply_order_filter( $clauses, $taxonomies, $args ) {
        if ( apply_filters( 'to/get_terms_orderby/ignore', false, $clauses['orderby'], $args ) ) {
            return $clauses;
        }

        $options = $this->get_settings();
        $ignore_term_order = isset( $args['ignore_term_order'] ) ? $args['ignore_term_order'] : false;

        if ( is_admin() ) {
            if ( isset( $_GET['orderby'] ) && sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) !== 'term_order' ) {
                return $clauses;
            }

            if ( isset( $options['adminsort'] ) && $options['adminsort'] === "1" && $ignore_term_order !== true ) {
                if ( $clauses['orderby'] === 'ORDER BY t.name' ) {
                    $clauses['orderby'] = 'ORDER BY t.term_order ' . $clauses['order'] . ', t.name';
                } else {
                    $clauses['orderby'] = 'ORDER BY t.term_order';
                }
            }
            return $clauses;
        }

        if ( isset( $options['autosort'] ) && $options['autosort'] === "1" && $ignore_term_order !== true ) {
            $clauses['orderby'] = 'ORDER BY t.term_order';
            return $clauses;
        }

        $rest_route = $GLOBALS['wp']->query_vars['rest_route'] ?? '';
        $is_admin_rest = (
            strpos( $rest_route, '/wp/v2/' ) === 0 ||
            strpos( $rest_route, '/wp-block' ) === 0
        );

        if ( $is_admin_rest && isset( $options['adminsort'] ) && $options['adminsort'] === "1" && defined( 'REST_REQUEST' ) && REST_REQUEST ) {
            $clauses['orderby'] = 'ORDER BY t.term_order';
            return $clauses;
        }

        return $clauses;
    }

    /**
     * Filter get_terms_orderby.
     */
    public function get_terms_orderby( $orderby, $args ) {
        if ( apply_filters( 'to/get_terms_orderby/ignore', false, $orderby, $args ) ) {
            return $orderby;
        }

        if ( isset( $args['orderby'] ) && $args['orderby'] === 'term_order' && $orderby !== 'term_order' ) {
            return 't.term_order';
        }

        return $orderby;
    }

    public function render_options_page_content() {
        $options = $this->get_settings();
        ?>
        <div class="taxonomy-terms-order-settings">
            <h2><?php esc_html_e( 'Taxonomy Terms Order - Settings', 'uxpa-core-utility' ); ?></h2>
            <form id="form_data" method="post" action="">
                <?php wp_nonce_field( 'to_form_submit', 'to_form_nonce' ); ?>
                <input type="hidden" name="to_form_submit" value="true" />

                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><label><?php esc_html_e( 'Show / Hide re-order interface', 'uxpa-core-utility' ); ?></label></th>
                        <td>
                            <div class="pt-list" style="display: flex; flex-wrap: wrap;">
                                <?php
                                $post_types = get_post_types( array(), 'objects' );
                                foreach ( $post_types as $post_type ) {
                                    $post_type_taxonomies = get_object_taxonomies( $post_type->name );
                                    foreach ( $post_type_taxonomies as $key => $taxonomy_name ) {
                                        $taxonomy_info = get_taxonomy( $taxonomy_name );
                                        if ( empty( $taxonomy_info->hierarchical ) || $taxonomy_info->hierarchical !== true ) {
                                            unset( $post_type_taxonomies[ $key ] );
                                        }
                                    }

                                    if ( count( $post_type_taxonomies ) == 0 ) {
                                        continue;
                                    }

                                    $current_val = isset( $options['show_reorder_interfaces'][ $post_type->name ] ) ? $options['show_reorder_interfaces'][ $post_type->name ] : 'show';
                                    ?>
                                    <div style="flex-basis: 33%; margin-bottom: 10px;">
                                        <select name="show_reorder_interfaces[<?php echo esc_attr( $post_type->name ); ?>]">
                                            <option value="show" <?php selected( $current_val, 'show' ); ?>><?php esc_html_e( 'Show', 'uxpa-core-utility' ); ?></option>
                                            <option value="hide" <?php selected( $current_val, 'hide' ); ?>><?php esc_html_e( 'Hide', 'uxpa-core-utility' ); ?></option>
                                        </select>
                                        &nbsp;&nbsp;<?php echo esc_html( $post_type->label ); ?>
                                    </div>
                                    <?php
                                }
                                ?>
                            </div>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row"><label for="role"><?php esc_html_e( 'Minimum Level to use this plugin', 'uxpa-core-utility' ); ?></label></th>
                        <td>
                            <select id="role" name="capability">
                                <option value="read" <?php selected( $options['capability'], 'read' ); ?>><?php esc_html_e( 'Subscriber', 'uxpa-core-utility' ); ?></option>
                                <option value="edit_posts" <?php selected( $options['capability'], 'edit_posts' ); ?>><?php esc_html_e( 'Contributor', 'uxpa-core-utility' ); ?></option>
                                <option value="publish_posts" <?php selected( $options['capability'], 'publish_posts' ); ?>><?php esc_html_e( 'Author', 'uxpa-core-utility' ); ?></option>
                                <option value="publish_pages" <?php selected( $options['capability'], 'publish_pages' ); ?>><?php esc_html_e( 'Editor', 'uxpa-core-utility' ); ?></option>
                                <option value="manage_options" <?php selected( $options['capability'], 'manage_options' ); ?>><?php esc_html_e( 'Administrator', 'uxpa-core-utility' ); ?></option>
                            </select>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row"><label for="autosort"><?php esc_html_e( 'Auto Sort', 'uxpa-core-utility' ); ?></label></th>
                        <td>
                            <select id="autosort" name="autosort">
                                <option value="0" <?php selected( $options['autosort'], '0' ); ?>><?php esc_html_e( 'OFF', 'uxpa-core-utility' ); ?></option>
                                <option value="1" <?php selected( $options['autosort'], '1' ); ?>><?php esc_html_e( 'ON', 'uxpa-core-utility' ); ?></option>
                            </select>
                            <span class="description"><?php esc_html_e( 'Automatically sort queries on the frontend.', 'uxpa-core-utility' ); ?></span>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row"><label for="adminsort"><?php esc_html_e( 'Admin Sort', 'uxpa-core-utility' ); ?></label></th>
                        <td>
                            <select id="adminsort" name="adminsort">
                                <option value="0" <?php selected( $options['adminsort'], '0' ); ?>><?php esc_html_e( 'OFF', 'uxpa-core-utility' ); ?></option>
                                <option value="1" <?php selected( $options['adminsort'], '1' ); ?>><?php esc_html_e( 'ON', 'uxpa-core-utility' ); ?></option>
                            </select>
                            <span class="description"><?php esc_html_e( 'Change the order of terms inside WP Admin tables.', 'uxpa-core-utility' ); ?></span>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e( 'Save Settings', 'uxpa-core-utility' ); ?>" />
                </p>
            </form>
        </div>
        <?php
    }
}

/**
 * Custom Walker to build the drag & drop hierarchy list.
 */
class UXPA_TO_Terms_Walker extends Walker {

    public $db_fields = array( 'parent' => 'parent', 'id' => 'term_id' );

    public function start_lvl( &$output, $depth = 0, $args = array() ) {
        $indent = str_repeat( "\t", $depth );
        $output .= "\n$indent<ul class='children sortable'>\n";
    }

    public function end_lvl( &$output, $depth = 0, $args = array() ) {
        $indent = str_repeat( "\t", $depth );
        $output .= "$indent</ul>\n";
    }

    public function start_el( &$output, $data_object, $depth = 0, $args = array(), $current_object_id = 0 ) {
        $term = $data_object;
        $indent = $depth ? str_repeat( "\t", $depth ) : '';

        $currentScreen = get_current_screen();
        $term_link = isset( $currentScreen->post_type ) ? get_edit_term_link( $term, $term->taxonomy, $currentScreen->post_type ) : get_edit_term_link( $term );

        $output .= $indent . '<li class="term_type_li" id="item_' . $term->term_id . '">';
        $output .= '<div class="item">';
        $output .= '<span class="title">' . esc_html( apply_filters( 'to/term_title', $term->name, $term ) ) . ' </span>';
        $output .= '<span class="options ui-sortable-handle">';
        $output .= '<a href="' . esc_url( $term_link ) . '"><span class="dashicons dashicons-edit"></span></a>';
        $output .= '</span>';
        $output .= '</div>';
    }

    public function end_el( &$output, $data_object, $depth = 0, $args = array() ) {
        $output .= "</li>\n";
    }
}
