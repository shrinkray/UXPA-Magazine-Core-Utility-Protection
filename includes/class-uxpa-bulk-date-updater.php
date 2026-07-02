<?php
/**
 * Class UXPA_Bulk_Date_Updater
 *
 * Implements the dashboard interface to bulk randomize post/page/comment dates.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class UXPA_Bulk_Date_Updater {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    /**
     * Enqueue assets only on the plugin's settings page.
     */
    public function enqueue_assets( $hook ) {
        $tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
        if ( 'settings_page_uxpa-core-utility' !== $hook || $tab !== 'date-updater' ) {
            return;
        }

        wp_enqueue_script( 'momentjs', 'https://cdn.jsdelivr.net/momentjs/latest/moment.min.js', array(), '2.29.4', true );
        wp_enqueue_script( 'daterangepicker', 'https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js', array( 'jquery', 'momentjs' ), '3.1', true );
        wp_enqueue_style( 'daterangepicker', 'https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css', array(), '3.1' );
    }

    /**
     * Render the admin page and handle submissions.
     */
    public function render_page_content() {
        global $wpdb;
        $settings_saved = 0;
        
        $subtab = isset( $_GET['subtab'] ) ? sanitize_key( wp_unslash( $_GET['subtab'] ) ) : 'posts';
        $type = $subtab;

        // Extra Check for valid tabs
        $allowed_tabs = array( 'posts', 'pages', 'comments' );
        
        // Get custom public post types to allow them as tabs too
        $custom_post_types = get_post_types( array( 'public' => true, '_builtin' => false ), 'objects' );
        foreach ( $custom_post_types as $cpt ) {
            $allowed_tabs[] = $cpt->name;
        }

        if ( ! in_array( $subtab, $allowed_tabs, true ) ) {
            $subtab = 'posts';
            $type = 'posts';
        }

        $now = current_time( 'timestamp', 0 );

        // Handle Form Submission
        if ( isset( $_POST['tb_refresh'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['tb_refresh'] ) ), 'tb-refresh' ) && current_user_can( 'manage_options' ) ) {
            
            if ( $subtab === 'comments' ) {
                $settings_saved = $this->handle_comments_update();
            } else {
                $field = isset( $_POST['field'] ) ? sanitize_key( wp_unslash( $_POST['field'] ) ) : 'modified';
                if ( $field !== 'date_both' ) {
                    $field = ( $field === 'published' ) ? 'post_date' : 'post_modified';
                }

                $ids = array();

                if ( $type === 'posts' ) {
                    $params = array(
                        'numberposts' => -1,
                        'post_status' => 'publish',
                        'fields'      => 'ids'
                    );

                    if ( isset( $_POST['categories'] ) && is_array( $_POST['categories'] ) ) {
                        $params['cat'] = implode( ',', array_map( 'intval', $_POST['categories'] ) );
                    }

                    if ( isset( $_POST['tags'] ) && is_array( $_POST['tags'] ) ) {
                        $params['tag'] = implode( ',', array_map( 'sanitize_text_field', $_POST['tags'] ) );
                    }

                    $ids = get_posts( $params );

                } else if ( $type === 'pages' ) {
                    if ( isset( $_POST['pages'] ) && is_array( $_POST['pages'] ) ) {
                        $ids = array_map( 'intval', $_POST['pages'] );
                    } else {
                        $pages_ = get_pages();
                        $ids    = wp_list_pluck( $pages_, 'ID' );
                    }
                } else {
                    // Custom post type
                    $params = array(
                        'numberposts' => -1,
                        'post_status' => 'publish',
                        'fields'      => 'ids',
                        'post_type'   => $type
                    );

                    if ( isset( $_POST['tax'] ) && is_array( $_POST['tax'] ) ) {
                        foreach ( $_POST['tax'] as $tax => $terms ) {
                            if ( is_array( $terms ) && ! empty( $terms ) ) {
                                $params['tax_query'][] = array(
                                    'taxonomy' => sanitize_key( $tax ),
                                    'field'    => 'term_id',
                                    'terms'    => array_map( 'intval', $terms )
                                );
                            }
                        }

                        if ( isset( $params['tax_query'] ) ) {
                            $relation = isset( $_POST['tax_relation'] ) && $_POST['tax_relation'] === 'AND' ? 'AND' : 'OR';
                            $params['tax_query']['relation'] = $relation;
                        }
                    }

                    $ids = get_posts( $params );
                }

                list( $from, $to ) = $this->get_from_and_to_dates();

                foreach ( $ids as $id ) {
                    $time = rand( $from, $to );
                    $time_str = date( 'Y-m-d H:i:s', $time );
                    $time_gmt = get_gmt_from_date( $time_str );

                    if ( $field === 'date_both' ) {
                        $wpdb->query(
                            $wpdb->prepare(
                                "UPDATE $wpdb->posts SET post_date = %s, post_date_gmt = %s, post_modified = %s, post_modified_gmt = %s WHERE ID = %d",
                                $time_str,
                                $time_gmt,
                                $time_str,
                                $time_gmt,
                                $id
                            )
                        );
                    } else {
                        $wpdb->query(
                            $wpdb->prepare(
                                "UPDATE $wpdb->posts SET $field = %s, {$field}_gmt = %s WHERE ID = %d",
                                $time_str,
                                $time_gmt,
                                $id
                            )
                        );
                    }
                    clean_post_cache( $id );
                }
                $settings_saved = count( $ids );
            }
        }

        ?>
        <div class="bulk-date-updater-settings">
            <h2><?php esc_html_e( 'Bulk Post Update Date', 'uxpa-core-utility' ); ?></h2>
            <p><?php esc_html_e( 'Change the Post Update date for all posts in one click. This will help your blog in search engines and your blog will look alive. Do this every week or month.', 'uxpa-core-utility' ); ?></p>
            
            <?php if ( $settings_saved > 0 ) : ?>
                <div id="message" class="updated fade">
                    <p><strong><?php echo esc_html( sprintf( _n( '%d item update date refreshed.', '%d items update dates refreshed.', $settings_saved, 'uxpa-core-utility' ), $settings_saved ) ); ?></strong></p>
                </div>
            <?php endif; ?>

            <h2 class="nav-tab-wrapper">
                <a href="?page=uxpa-core-utility&tab=date-updater&subtab=posts" class="nav-tab <?php echo $subtab === 'posts' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-admin-post" style="margin-top: 4px;"></span> <?php esc_html_e( 'Posts', 'uxpa-core-utility' ); ?>
                </a>
                <a href="?page=uxpa-core-utility&tab=date-updater&subtab=pages" class="nav-tab <?php echo $subtab === 'pages' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-admin-page" style="margin-top: 4px;"></span> <?php esc_html_e( 'Pages', 'uxpa-core-utility' ); ?>
                </a>
                
                <?php
                if ( $custom_post_types ) {
                    foreach ( $custom_post_types as $post_type ) {
                        ?>
                        <a href="?page=uxpa-core-utility&tab=date-updater&subtab=<?php echo esc_attr( $post_type->name ); ?>" class="nav-tab <?php echo $subtab === $post_type->name ? 'nav-tab-active' : ''; ?>">
                            <?php if ( strpos( $post_type->menu_icon, 'dashicons' ) !== false ) : ?>
                                <span class="dashicons <?php echo esc_attr( $post_type->menu_icon ); ?>" style="margin-top: 4px;"></span>
                            <?php endif; ?>
                            <?php echo esc_html( $post_type->label ); ?>
                        </a>
                        <?php
                    }
                }
                ?>
                <a href="?page=uxpa-core-utility&tab=date-updater&subtab=comments" class="nav-tab <?php echo $subtab === 'comments' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-admin-comments" style="margin-top: 4px;"></span> <?php esc_html_e( 'Post Comments', 'uxpa-core-utility' ); ?>
                </a>
            </h2>

            <form method="post" action="?page=uxpa-core-utility&tab=date-updater&subtab=<?php echo esc_attr( $subtab ); ?>">
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><label for="distribute"><?php esc_html_e( 'Distribute into Last', 'uxpa-core-utility' ); ?></label></th>
                        <td>
                            <select id="distribute" name="distribute" style="min-width: 300px;">
                                <option value="<?php echo strtotime( '-1 hour', $now ); ?>"><?php esc_html_e( '1 Hour', 'uxpa-core-utility' ); ?></option>
                                <option value="<?php echo strtotime( '-1 day', $now ); ?>"><?php esc_html_e( '1 Day', 'uxpa-core-utility' ); ?></option>
                                <option value="<?php echo strtotime( '-15 days', $now ); ?>"><?php esc_html_e( '15 Days', 'uxpa-core-utility' ); ?></option>
                                <option value="<?php echo strtotime( '-1 month', $now ); ?>"><?php esc_html_e( '1 Month', 'uxpa-core-utility' ); ?></option>
                                <option value="<?php echo strtotime( '-2 month', $now ); ?>"><?php esc_html_e( '2 Months', 'uxpa-core-utility' ); ?></option>
                                <option value="<?php echo strtotime( '-3 month', $now ); ?>"><?php esc_html_e( '3 Months', 'uxpa-core-utility' ); ?></option>
                                <option value="<?php echo strtotime( '-6 month', $now ); ?>"><?php esc_html_e( '6 Months', 'uxpa-core-utility' ); ?></option>
                                <option value="0"><?php esc_html_e( 'Custom Range', 'uxpa-core-utility' ); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e( 'Select range of date in which you want to spread the dates', 'uxpa-core-utility' ); ?></p>
                        </td>
                    </tr>

                    <tr id="range_row" valign="top" style="display: none;">
                        <th scope="row"><label for="range"><?php esc_html_e( 'Custom Date Range', 'uxpa-core-utility' ); ?></label></th>
                        <td>
                            <input type="text" id="range" name="range" style="min-width: 300px;" value="<?php echo esc_attr( date( 'm/d/Y', strtotime( '-3 days', $now ) ) . ' - ' . date( 'm/d/Y', $now ) ); ?>" />
                            <p class="description"><?php esc_html_e( 'Select range of date in which you want to spread the dates', 'uxpa-core-utility' ); ?></p>
                        </td>
                    </tr>

                    <?php
                    // Tab-specific filters
                    if ( $subtab === 'posts' ) {
                        ?>
                        <tr valign="top">
                            <th scope="row"><label for="categories"><?php esc_html_e( 'Select Categories', 'uxpa-core-utility' ); ?></label></th>
                            <td>
                                <select multiple="multiple" id="categories" name="categories[]" style="min-width: 300px; height: 120px;">
                                    <?php
                                    $categories = get_categories( array( 'orderby' => 'name', 'hide_empty' => false ) );
                                    foreach ( $categories as $category ) {
                                        echo '<option value="' . esc_attr( $category->term_id ) . '">' . esc_html( $category->name . ' (' . $category->category_count . ')' ) . '</option>';
                                    }
                                    ?>
                                </select>
                                <p class="description"><?php esc_html_e( 'Will apply to all posts if no category is selected. Hold Ctrl/Cmd to select multiple.', 'uxpa-core-utility' ); ?></p>
                            </td>
                        </tr>
                        <?php
                        $total_tags = wp_count_terms( 'post_tag' );
                        if ( $total_tags < 500 ) {
                            ?>
                            <tr valign="top">
                                <th scope="row"><label for="tags"><?php esc_html_e( 'Select Tags', 'uxpa-core-utility' ); ?></label></th>
                                <td>
                                    <select multiple="multiple" id="tags" name="tags[]" style="min-width: 300px; height: 120px;">
                                        <?php
                                        $tags = get_tags( array( 'orderby' => 'name', 'hide_empty' => false ) );
                                        foreach ( $tags as $tag ) {
                                            echo '<option value="' . esc_attr( $tag->slug ) . '">' . esc_html( $tag->name . ' (' . $tag->count . ')' ) . '</option>';
                                        }
                                        ?>
                                    </select>
                                    <p class="description"><?php esc_html_e( 'Will apply to all posts if no tag is selected. Hold Ctrl/Cmd to select multiple.', 'uxpa-core-utility' ); ?></p>
                                </td>
                            </tr>
                            <?php
                        }
                    } else if ( $subtab === 'pages' ) {
                        ?>
                        <tr valign="top">
                            <th scope="row"><label for="pages"><?php esc_html_e( 'Select Pages', 'uxpa-core-utility' ); ?></label></th>
                            <td>
                                <select multiple="multiple" id="pages" name="pages[]" style="min-width: 300px; height: 120px;">
                                    <?php
                                    $pages = get_pages( array( 'sort_column' => 'post_title' ) );
                                    foreach ( $pages as $page ) {
                                        echo '<option value="' . esc_attr( $page->ID ) . '">' . esc_html( $page->post_title ) . '</option>';
                                    }
                                    ?>
                                </select>
                                <p class="description"><?php esc_html_e( 'Will apply to all pages if no page is selected. Hold Ctrl/Cmd to select multiple.', 'uxpa-core-utility' ); ?></p>
                            </td>
                        </tr>
                        <?php
                    } else if ( $subtab !== 'comments' ) {
                        // Custom post types
                        $tax_args = array(
                            'public'      => $subtab === 'web-story' ? false : true,
                            '_builtin'    => false,
                            'object_type' => array( $subtab )
                        );
                        $taxonomies = get_taxonomies( $tax_args, 'objects' );

                        if ( $taxonomies ) {
                            foreach ( $taxonomies as $taxonomy ) {
                                ?>
                                <tr valign="top">
                                    <th scope="row"><label for="tax_<?php echo esc_attr( $taxonomy->name ); ?>"><?php echo esc_html( sprintf( __( 'Select %s', 'uxpa-core-utility' ), $taxonomy->label ) ); ?></label></th>
                                    <td>
                                        <select multiple="multiple" id="tax_<?php echo esc_attr( $taxonomy->name ); ?>" name="tax[<?php echo esc_attr( $taxonomy->name ); ?>][]" style="min-width: 300px; height: 120px;">
                                            <?php
                                            $terms = get_terms( array( 'taxonomy' => $taxonomy->name, 'orderby' => 'name', 'hide_empty' => false ) );
                                            if ( ! is_wp_error( $terms ) ) {
                                                foreach ( $terms as $term ) {
                                                    echo '<option value="' . esc_attr( $term->term_id ) . '">' . esc_html( $term->name . ' (' . $term->count . ')' ) . '</option>';
                                                }
                                            }
                                            ?>
                                        </select>
                                        <p class="description"><?php echo esc_html( sprintf( __( 'Will apply to all items if no %s is selected. Hold Ctrl/Cmd to select multiple.', 'uxpa-core-utility' ), $taxonomy->labels->singular_name ) ); ?></p>
                                    </td>
                                </tr>
                                <?php
                            }

                            if ( count( $taxonomies ) > 1 ) {
                                ?>
                                <tr id="tax_relation_row" valign="top">
                                    <th scope="row"><label><?php esc_html_e( 'Taxonomies relation', 'uxpa-core-utility' ); ?></label></th>
                                    <td>
                                        <input type="radio" id="OR" name="tax_relation" value="OR" checked />
                                        <label for="OR"><?php esc_html_e( 'OR', 'uxpa-core-utility' ); ?></label>
                                        &nbsp;&nbsp;
                                        <input type="radio" id="AND" name="tax_relation" value="AND" />
                                        <label for="AND"><?php esc_html_e( 'AND', 'uxpa-core-utility' ); ?></label>
                                        <p class="description"><?php esc_html_e( 'OR will include items matching any taxonomy. AND requires matching all.', 'uxpa-core-utility' ); ?></p>
                                    </td>
                                </tr>
                                <?php
                            }
                        }
                    }

                    if ( $subtab !== 'comments' ) {
                        ?>
                        <tr id="field_row" valign="top">
                            <th scope="row"><label><?php esc_html_e( 'Date field to update', 'uxpa-core-utility' ); ?></label></th>
                            <td>
                                <input type="radio" id="published" name="field" value="published" />
                                <label for="published"><?php esc_html_e( 'Published Date', 'uxpa-core-utility' ); ?></label>
                                &nbsp;&nbsp;
                                <input type="radio" id="modified" name="field" value="modified" checked />
                                <label for="modified"><?php esc_html_e( 'Modified Date', 'uxpa-core-utility' ); ?></label>
                                &nbsp;&nbsp;
                                <input type="radio" id="date_both" name="field" value="date_both" />
                                <label for="date_both"><?php esc_html_e( 'Both Dates Equal', 'uxpa-core-utility' ); ?></label>
                                <p class="description"><?php esc_html_e( 'Updating modified date is recommended.', 'uxpa-core-utility' ); ?></p>
                            </td>
                        </tr>
                    <?php } ?>
                </table>

                <p class="submit">
                    <input name="tb_refresh" type="hidden" value="<?php echo esc_attr( wp_create_nonce( 'tb-refresh' ) ); ?>" />
                    <input class="button-primary" name="do" type="submit" value="<?php esc_attr_e( 'Update Dates', 'uxpa-core-utility' ); ?>" />
                </p>
            </form>
			<p>Replaces plugin: <strong>Bulk Post Date Changer</strong></p>
        </div>

        <script>
            jQuery(document).ready(function($) {
                if ($('#range').length) {
                    $('#range').daterangepicker({
                        maxDate: '<?php echo date( "m/d/Y" ); ?>',
                        locale: {
                            format: 'MM/DD/YYYY'
                        }
                    });
                }

                $('#distribute').change(function() {
                    if ($(this).val() == 0) {
                        $('#range_row').fadeIn();
                    } else {
                        $('#range_row').fadeOut();
                    }
                });
            });
        </script>
        <?php
    }

    /**
     * Get from and to timestamps based on select box or custom range.
     */
    private function get_from_and_to_dates() {
        $from = isset( $_POST['distribute'] ) ? intval( $_POST['distribute'] ) : 0;
        $to   = current_time( 'timestamp', 0 );
        $now  = current_time( 'timestamp', 0 );

        if ( $from === 0 && isset( $_POST['range'] ) ) {
            $range = explode( ' - ', sanitize_text_field( $_POST['range'] ) );
            if ( count( $range ) === 2 ) {
                $from = strtotime( $range[0], $now );
                $to   = strtotime( $range[1], $now );
            } else {
                $from = strtotime( '-3 hours', $now );
            }
        }

        return array( $from, $to );
    }

    /**
     * Handle updating dates for approved comments of published posts.
     */
    private function handle_comments_update() {
        global $wpdb;
        $comments = $wpdb->get_results( "SELECT c.comment_ID, c.comment_post_ID, c.comment_date, p.post_date 
            FROM $wpdb->comments c 
            JOIN $wpdb->posts p ON c.comment_post_ID = p.ID 
            WHERE c.comment_approved = 1 AND p.post_status = 'publish'" );

        list( $from, $to ) = $this->get_from_and_to_dates();
        $total = 0;

        foreach ( $comments as $comment ) {
            $total++;
            $post_date = strtotime( $comment->post_date );
            $_from = ( $from < $post_date ) ? $post_date : $from;
            $_to   = ( $to < $post_date ) ? $post_date + 60 : $to;

            $time = rand( $_from, $_to );
            $time_str = date( 'Y-m-d H:i:s', $time );

            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE $wpdb->comments SET comment_date = %s, comment_date_gmt = %s WHERE comment_ID = %d",
                    $time_str,
                    get_gmt_from_date( $time_str ),
                    $comment->comment_ID
                )
            );
            clean_comment_cache( $comment->comment_ID );
        }

        return $total;
    }
}
