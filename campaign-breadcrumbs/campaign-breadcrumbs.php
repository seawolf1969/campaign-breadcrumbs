<?php
/**
 * Plugin Name: Campaign Breadcrumbs
 * Description: Adds a campaign-home Post selector to Posts and renders a breadcrumb trail that points to the selected campaign-home Post.
 * Version: 1.1.0
 * Author: GitHub Copilot
 * License: GPL-2.0+
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Campaign_Breadcrumbs_Addon {

    protected static $instance = null;

    private $rendered_post_ids = array();

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ) );
        add_action( 'save_post', array( $this, 'save_campaign_home_post_meta' ) );
        add_action( 'quick_edit_custom_box', array( $this, 'render_quick_edit_box' ), 10, 3 );
        add_action( 'add_inline_data', array( $this, 'add_inline_data_payload' ), 10, 2 );
        add_action( 'the_post', array( $this, 'render_breadcrumbs_before_post_elements' ) );
        add_action( 'responsive_entry_top', array( $this, 'render_breadcrumbs_before_post_elements' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_breadcrumb_styles' ) );
    }

    public function register_meta_box() {
        add_meta_box(
            'campaign_breadcrumbs_meta_box',
            __( 'Campaign breadcrumb home post', 'campaign-breadcrumbs' ),
            array( $this, 'render_meta_box' ),
            'post',
            'side',
            'default'
        );
    }

    public function render_meta_box( $post ) {
        wp_nonce_field( 'campaign_breadcrumbs_save', 'campaign_breadcrumbs_nonce' );

        $selected_post_id = (int) get_post_meta( $post->ID, '_campaign_breadcrumbs_home_post_id', true );

        $campaign_posts = get_posts(
            array(
                'post_type'   => 'post',
                'tag'         => 'campaign-home',
                'post_status' => 'publish',
                'numberposts' => -1,
                'orderby'     => 'title',
                'order'       => 'ASC',
            )
        );

        echo '<p>';
        echo '<label for="campaign_breadcrumbs_home_post">' . esc_html__( 'Choose the campaign-home Post that should represent this post home', 'campaign-breadcrumbs' ) . '</label>';
        echo '</p>';

        echo '<select name="campaign_breadcrumbs_home_post" id="campaign_breadcrumbs_home_post" class="widefat">';
        echo '<option value="0">' . esc_html__( 'No campaign Post selected', 'campaign-breadcrumbs' ) . '</option>';

        foreach ( $campaign_posts as $campaign_post ) {
            $selected = selected( $selected_post_id, (int) $campaign_post->ID, false );
            echo '<option value="' . esc_attr( $campaign_post->ID ) . '" ' . $selected . '>' . esc_html( get_the_title( $campaign_post ) ) . '</option>';
        }

        echo '</select>';
    }

    public function render_quick_edit_box( $column_name, $post_type, $taxonomy ) {
        if ( 'post' !== $post_type || 'campaign_breadcrumb' !== $column_name ) {
            return;
        }

        $campaign_posts = get_posts(
            array(
                'post_type'   => 'post',
                'tag'         => 'campaign-home',
                'post_status' => 'publish',
                'numberposts' => -1,
                'orderby'     => 'title',
                'order'       => 'ASC',
            )
        );

        echo '<label class="inline-edit-campaign-breadcrumb">';
        echo '<span class="title">' . esc_html__( 'Campaign Breadcrumb', 'campaign-breadcrumbs' ) . '</span>';
        echo '<select name="campaign_breadcrumbs_home_post" class="widefat">';
        echo '<option value="0">' . esc_html__( 'No campaign Post selected', 'campaign-breadcrumbs' ) . '</option>';

        foreach ( $campaign_posts as $campaign_post ) {
            echo '<option value="' . esc_attr( $campaign_post->ID ) . '">' . esc_html( get_the_title( $campaign_post ) ) . '</option>';
        }

        echo '</select>';
        echo '</label>';
    }

    public function add_inline_data_payload( $post, $post_type_object ) {
        if ( ! $post || 'post' !== $post->post_type ) {
            return;
        }

        $selected_post_id = (int) get_post_meta( $post->ID, '_campaign_breadcrumbs_home_post_id', true );
        echo '<div class="campaign_breadcrumbs_home_post">' . esc_html( $selected_post_id ) . '</div>';
    }

    public function save_campaign_home_post_meta( $post_id ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! isset( $_POST['_inline_edit'] ) ) {
            if ( ! isset( $_POST['campaign_breadcrumbs_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['campaign_breadcrumbs_nonce'] ) ), 'campaign_breadcrumbs_save' ) ) {
                return;
            }
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        if ( ! isset( $_POST['campaign_breadcrumbs_home_post'] ) ) {
            delete_post_meta( $post_id, '_campaign_breadcrumbs_home_post_id' );
            return;
        }

        $selected_post_id = absint( wp_unslash( $_POST['campaign_breadcrumbs_home_post'] ) );

        if ( $selected_post_id > 0 ) {
            update_post_meta( $post_id, '_campaign_breadcrumbs_home_post_id', $selected_post_id );
        } else {
            delete_post_meta( $post_id, '_campaign_breadcrumbs_home_post_id' );
        }
    }

    public function enqueue_breadcrumb_styles() {
        wp_register_style(
            'campaign-breadcrumbs-style',
            plugins_url( 'assets/css/campaign-breadcrumbs.css', __FILE__ ),
            array(),
            '1.1.0'
        );
        wp_enqueue_style( 'campaign-breadcrumbs-style' );
    }

    public function render_breadcrumbs_before_post_elements( $post = null ) {
        if ( is_admin() || ! is_singular( 'post' ) || ! is_main_query() ) {
            return;
        }

        if ( ! $post ) {
            $post = get_post();
        }

        if ( ! $post || 'post' !== $post->post_type || (int) $post->ID !== (int) get_queried_object_id() || isset( $this->rendered_post_ids[ $post->ID ] ) ) {
            return;
        }

        $markup = $this->get_breadcrumb_markup_for_post( $post );

        if ( '' === $markup ) {
            return;
        }

        echo $markup;
        $this->rendered_post_ids[ $post->ID ] = true;
    }

    public function get_breadcrumb_markup_for_current_post() {
        $post = get_post();

        if ( ! $post || 'post' !== $post->post_type ) {
            return '';
        }

        return $this->get_breadcrumb_markup_for_post( $post );
    }

    public function get_breadcrumb_markup_for_post( $post ) {
        if ( ! $post || 'post' !== $post->post_type ) {
            return '';
        }

        $selected_post_id = (int) get_post_meta( $post->ID, '_campaign_breadcrumbs_home_post_id', true );

        if ( $selected_post_id <= 0 ) {
            return '';
        }

        $selected_post = get_post( $selected_post_id );

        if ( ! $selected_post || 'publish' !== get_post_status( $selected_post ) || 'post' !== $selected_post->post_type ) {
            return '';
        }

        if ( ! has_tag( 'campaign-home', $selected_post ) ) {
            return '';
        }

        return $this->build_breadcrumb_markup( $post, $selected_post );
    }

    private function build_breadcrumb_markup( $post, $selected_post ) {
        $home_url = home_url( '/' );
        $home_text = get_bloginfo( 'name' );

        $trail = array(
            array(
                'title' => $home_text,
                'url'   => $home_url,
            ),
            array(
                'title' => get_the_title( $selected_post ),
                'url'   => get_permalink( $selected_post ),
            ),
            array(
                'title' => get_the_title( $post ),
                'url'   => get_permalink( $post ),
            ),
        );

        $markup = '<nav class="campaign-breadcrumbs" aria-label="Breadcrumb">
            <ol class="campaign-breadcrumbs__list">';

        foreach ( $trail as $index => $item ) {
            $is_current = ( $index === count( $trail ) - 1 );

            if ( $is_current ) {
                $markup .= '<li class="campaign-breadcrumbs__item is-current" aria-current="page">' . esc_html( $item['title'] ) . '</li>';
            } else {
                $markup .= '<li class="campaign-breadcrumbs__item"><a href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['title'] ) . '</a></li>';
                $markup .= '<li class="campaign-breadcrumbs__separator" aria-hidden="true">/</li>';
            }
        }

        $markup .= '</ol>
        </nav>';

        return $markup;
    }
}

function campaign_breadcrumbs_get_markup_for_current_post() {
    return Campaign_Breadcrumbs_Addon::get_instance()->get_breadcrumb_markup_for_current_post();
}

Campaign_Breadcrumbs_Addon::get_instance();
