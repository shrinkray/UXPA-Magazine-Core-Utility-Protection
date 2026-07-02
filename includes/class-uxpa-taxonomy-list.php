<?php
/**
 * Class UXPA_Taxonomy_List
 *
 * Implements the [taxonomy_list] shortcode, supporting search filtering, hide_empty, and hierarchical rendering.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class UXPA_Taxonomy_List {

    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_shortcode( 'taxonomy_list', array( $this, 'render_shortcode' ) );
    }

    /**
     * Enqueue taxonomy list assets on the frontend.
     */
    public function enqueue_assets() {
        wp_register_style( 'uxpa-taxonomy-list-css', plugins_url( '../assets/css/taxonomy-list.css', __FILE__ ), array(), '1.0' );
        wp_register_script( 'uxpa-taxonomy-list-js', plugins_url( '../assets/js/taxonomy-list.js', __FILE__ ), array( 'jquery' ), '1.0', true );
    }

    /**
     * Render the shortcode.
     */
    public function render_shortcode( $atts ) {
        $atts = shortcode_atts( array(
            'name'       => 'category',
            'hide_empty' => false,
            'exclude'    => '',
            'include'    => '',
            'order'      => 'ASC',
            'orderby'    => 'id',
            'search_bar' => 0,
            'show_count' => false,
            'count_type' => 'terms'
        ), $atts );

        // Enqueue registered assets when shortcode is active
        wp_enqueue_style( 'uxpa-taxonomy-list-css' );
        wp_enqueue_script( 'uxpa-taxonomy-list-js' );

        $terms = get_terms( array(
            'taxonomy'   => $atts['name'],
            'hide_empty' => filter_var( $atts['hide_empty'], FILTER_VALIDATE_BOOLEAN ),
            'orderby'    => $atts['orderby'],
            'order'      => $atts['order'],
            'parent'     => 0
        ) );

        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            return '';
        }

        $html = '';
        if ( intval( $atts['search_bar'] ) === 1 ) {
            $html .= '<div class="taxonomy-search-filter">
                        <input type="text" placeholder="' . esc_attr__( 'Search...', 'uxpa-core-utility' ) . '" id="tax-search-filter"/>
                      </div>';
        }

        $html .= '<div class="taxonomy-list">';

        $exclude_ids = ! empty( $atts['exclude'] ) ? array_map( 'intval', explode( ',', $atts['exclude'] ) ) : array();
        $include_ids = ! empty( $atts['include'] ) ? array_map( 'intval', explode( ',', $atts['include'] ) ) : array();

        foreach ( $terms as $term ) {
            if ( ! empty( $exclude_ids ) && in_array( $term->term_id, $exclude_ids, true ) ) {
                continue;
            }

            if ( ! empty( $include_ids ) && ! in_array( $term->term_id, $include_ids, true ) ) {
                continue;
            }

            $image = '';
            if ( function_exists( 'get_woocommerce_term_meta' ) ) {
                $thumbnail_id = get_woocommerce_term_meta( $term->term_id, 'thumbnail_id', true );
                if ( $thumbnail_id ) {
                    $image = wp_get_attachment_url( $thumbnail_id );
                }
            }

            $html .= '<div class="taxonomy-list-item" data-taxname="' . esc_attr( strtolower( $term->name ) ) . '">';
            $term_link = get_term_link( $term );

            $has_children = $this->has_child_terms( $atts['name'], $term->term_id, filter_var( $atts['hide_empty'], FILTER_VALIDATE_BOOLEAN ) );
            if ( $has_children ) {
                $html .= '<div class="tax-arrow">&#9658;</div>'; // Arrow symbol
            }

            $child_terms = get_terms( array(
                'taxonomy'   => $atts['name'],
                'hide_empty' => filter_var( $atts['hide_empty'], FILTER_VALIDATE_BOOLEAN ),
                'orderby'    => $atts['orderby'],
                'order'      => $atts['order'],
                'parent'     => $term->term_id
            ) );

            $html .= '<div class="tax-details">
                        <div class="tax-name">';

            if ( $image ) {
                $html .= '  <div class="tax-image">
                                <img src="' . esc_url( $image ) . '" width="50" alt="" />
                            </div>';
            }

            $html .= '      <div class="tax-title">
                                <a href="' . esc_url( $term_link ) . '" title="' . esc_attr( $term->description ) . '">' . esc_html( $term->name ) . '</a>
                            </div>';

            $show_count_bool = filter_var( $atts['show_count'], FILTER_VALIDATE_BOOLEAN );
            if ( $atts['count_type'] === 'post' && $show_count_bool ) {
                $html .= '<div class="tax-child-count">(' . $this->get_posts_count( $term ) . ')</div>';
            } elseif ( $atts['count_type'] === 'terms' && ! is_wp_error( $child_terms ) && count( $child_terms ) > 0 && $show_count_bool ) {
                $html .= '<div class="tax-child-count">(' . count( $child_terms ) . ')</div>';
            }

            $html .= '  </div>'; // End tax-name
            $html .= '  <div class="tax-desc">' . esc_html( $term->description ) . '</div>';
            $html .= '</div>'; // End tax-details

            if ( ! is_wp_error( $child_terms ) && ! empty( $child_terms ) ) {
                foreach ( $child_terms as $child_term ) {
                    $child_image = '';
                    if ( function_exists( 'get_woocommerce_term_meta' ) ) {
                        $thumbnail_id = get_woocommerce_term_meta( $child_term->term_id, 'thumbnail_id', true );
                        if ( $thumbnail_id ) {
                            $child_image = wp_get_attachment_url( $thumbnail_id );
                        }
                    }

                    $html .= '<div class="tax-child-list-item" style="display: none;">';
                    $child_term_link = get_term_link( $child_term );
                    $html .= '<div class="tax-name">';

                    if ( $child_image ) {
                        $html .= '<div class="tax-image"><img src="' . esc_url( $child_image ) . '" width="50" alt="" /></div>';
                    }

                    $html .= '  <div class="tax-title">
                                    <a href="' . esc_url( $child_term_link ) . '" title="' . esc_attr( $child_term->description ) . '">' . esc_html( $child_term->name ) . '</a>
                                </div>';

                    if ( $atts['count_type'] === 'post' && $show_count_bool ) {
                        $html .= '<div class="tax-child-count">(' . $this->get_posts_count( $child_term ) . ')</div>';
                    }

                    $html .= '</div>'; // End tax-name
                    $html .= '<div class="tax-desc">' . esc_html( $child_term->description ) . '</div>';
                    $html .= '</div>'; // End tax-child-list-item
                }
            }

            $html .= '</div>'; // End taxonomy-list-item
        }

        $html .= '</div>'; // End taxonomy-list
        return $html;
    }

    /**
     * Helper to get post count in term efficiently.
     */
    private function get_posts_count( $term ) {
        $tax = get_taxonomy( $term->taxonomy );
        if ( ! $tax || empty( $tax->object_type ) ) {
            return 0;
        }

        $args = array(
            'post_type'      => $tax->object_type[0],
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'tax_query'      => array(
                array(
                    'taxonomy' => $term->taxonomy,
                    'field'    => 'id',
                    'terms'    => array( $term->term_id )
                )
            )
        );

        $query = new WP_Query( $args );
        return (int) $query->post_count;
    }

    /**
     * Check if a term has children.
     */
    private function has_child_terms( $taxonomy, $parent_id, $hide_empty ) {
        $children = get_terms( array(
            'taxonomy'   => $taxonomy,
            'hide_empty' => $hide_empty,
            'parent'     => $parent_id,
            'number'     => 1, // Only need to check if at least 1 child exists
            'fields'     => 'ids'
        ) );

        return ( ! is_wp_error( $children ) && ! empty( $children ) );
    }
}
