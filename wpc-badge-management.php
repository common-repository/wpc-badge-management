<?php
/*
Plugin Name: WPC Badge Management for WooCommerce
Plugin URI: https://wpclever.net/
Description: WPC Badge Management is a powerful plugin that simplifies badge management in online shops.
Version: 3.0.3
Author: WPClever
Author URI: https://wpclever.net
Text Domain: wpc-badge-management
Domain Path: /languages/
Requires Plugins: woocommerce
Requires at least: 4.0
Tested up to: 6.6
WC requires at least: 3.0
WC tested up to: 9.1
*/

defined( 'ABSPATH' ) || exit;

! defined( 'WPCBM_VERSION' ) && define( 'WPCBM_VERSION', '3.0.3' );
! defined( 'WPCBM_LITE' ) && define( 'WPCBM_LITE', __FILE__ );
! defined( 'WPCBM_FILE' ) && define( 'WPCBM_FILE', __FILE__ );
! defined( 'WPCBM_URI' ) && define( 'WPCBM_URI', plugin_dir_url( __FILE__ ) );
! defined( 'WPCBM_DIR' ) && define( 'WPCBM_DIR', plugin_dir_path( __FILE__ ) );
! defined( 'WPCBM_SUPPORT' ) && define( 'WPCBM_SUPPORT', 'https://wpclever.net/support?utm_source=support&utm_medium=wpcbm&utm_campaign=wporg' );
! defined( 'WPCBM_REVIEWS' ) && define( 'WPCBM_REVIEWS', 'https://wordpress.org/support/plugin/wpc-badge-management/reviews/?filter=5' );
! defined( 'WPCBM_CHANGELOG' ) && define( 'WPCBM_CHANGELOG', 'https://wordpress.org/plugins/wpc-badge-management/#developers' );
! defined( 'WPCBM_DISCUSSION' ) && define( 'WPCBM_DISCUSSION', 'https://wordpress.org/support/plugin/wpc-badge-management' );
! defined( 'WPC_URI' ) && define( 'WPC_URI', WPCBM_URI );

include 'includes/dashboard/wpc-dashboard.php';
include 'includes/kit/wpc-kit.php';
include 'includes/hpos.php';

if ( ! function_exists( 'wpcbm_init' ) ) {
	add_action( 'plugins_loaded', 'wpcbm_init', 11 );

	function wpcbm_init() {
		// load text-domain
		load_plugin_textdomain( 'wpc-badge-management', false, basename( __DIR__ ) . '/languages/' );

		if ( ! function_exists( 'WC' ) || ! version_compare( WC()->version, '3.0', '>=' ) ) {
			add_action( 'admin_notices', 'wpcbm_notice_wc' );

			return null;
		}

		include_once 'includes/class-shortcode.php';

		if ( ! class_exists( 'WPCleverWpcbm' ) && class_exists( 'WC_Product' ) ) {
			class WPCleverWpcbm {
				private $badges = [];
				protected static $settings = [];
				protected static $instance = null;

				public static function instance() {
					if ( is_null( self::$instance ) ) {
						self::$instance = new self();
					}

					return self::$instance;
				}

				function __construct() {
					self::$settings = (array) get_option( 'wpcbm_settings', [] );

					// init
					add_action( 'init', [ $this, 'init' ] );

					// meta box
					add_action( 'add_meta_boxes', [ $this, 'badge_meta_box' ] );
					add_action( 'save_post_wpc_product_badge', [ $this, 'badge_save_fields' ] );

					// taxonomy
					add_action( 'wpc-badge-group_add_form_fields', [ $this, 'group_show_fields' ] );
					add_action( 'wpc-badge-group_edit_form_fields', [ $this, 'group_show_fields' ] );
					add_action( 'create_wpc-badge-group', [ $this, 'group_save_fields' ] );
					add_action( 'edited_wpc-badge-group', [ $this, 'group_save_fields' ] );

					// enqueue
					add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
					add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

					// settings page
					add_action( 'admin_init', [ $this, 'register_settings' ] );
					add_action( 'admin_menu', [ $this, 'admin_menu' ] );

					// settings link
					add_filter( 'plugin_action_links', [ $this, 'action_links' ], 10, 2 );
					add_filter( 'plugin_row_meta', [ $this, 'row_meta' ], 10, 2 );

					// ajax
					add_action( 'wp_ajax_wpcbm_activate', [ $this, 'ajax_activate' ] );
					add_action( 'wp_ajax_wpcbm_search_badges', [ $this, 'ajax_search_badges' ] );
					add_action( 'wp_ajax_wpcbm_add_conditional', [ $this, 'ajax_add_conditional' ] );
					add_action( 'wp_ajax_wpcbm_search_term', [ $this, 'ajax_search_term' ] );
					add_action( 'wp_ajax_wpcbm_add_time', [ $this, 'ajax_add_time' ] );

					// product data
					add_filter( 'woocommerce_product_data_tabs', [ $this, 'product_data_tabs' ] );
					add_action( 'woocommerce_product_data_panels', [ $this, 'product_data_panels' ] );
					add_action( 'woocommerce_process_product_meta', [ $this, 'product_save_fields' ] );

					// quick view popup
					if ( class_exists( 'WPCleverWoosq' ) ) {
						add_action( 'woosq_before_thumbnails', [ $this, 'render_quickview_image_start' ], - 1 );
						add_action( 'woosq_after_thumbnails', [ $this, 'render_quickview_image_end' ], 9999 );
						add_action( 'woosq_before_title', [ $this, 'render_quickview_before_title' ] );
						add_action( 'woosq_after_title', [ $this, 'render_quickview_after_title' ] );
						add_action( 'woosq_before_rating', [ $this, 'render_quickview_before_rating' ] );
						add_action( 'woosq_after_rating', [ $this, 'render_quickview_after_rating' ] );
						add_action( 'woosq_before_price', [ $this, 'render_quickview_before_price' ] );
						add_action( 'woosq_after_price', [ $this, 'render_quickview_after_price' ] );
						add_action( 'woosq_before_excerpt', [ $this, 'render_quickview_before_excerpt' ] );
						add_action( 'woosq_after_excerpt', [ $this, 'render_quickview_after_excerpt' ] );
						add_action( 'woosq_before_meta', [ $this, 'render_quickview_before_meta' ] );
						add_action( 'woosq_after_meta', [ $this, 'render_quickview_after_meta' ] );
						add_action( 'woosq_before_add_to_cart', [ $this, 'render_quickview_before_add_to_cart' ] );
						add_action( 'woosq_after_add_to_cart', [ $this, 'render_quickview_after_add_to_cart' ] );
					}

					// archive page
					add_action( 'woocommerce_before_shop_loop_item_title', [
						$this,
						'render_archive_image_start'
					], - 1 );
					add_action( 'woocommerce_before_shop_loop_item_title', [
						$this,
						'render_archive_image_end'
					], 9999 );
					add_action( 'woocommerce_before_shop_loop_item', [ $this, 'render_archive_before_image' ], 9 );
					add_action( 'woocommerce_shop_loop_item_title', [ $this, 'render_archive_before_title' ], 9 );
					add_action( 'woocommerce_shop_loop_item_title', [ $this, 'render_archive_after_title' ], 11 );
					add_action( 'woocommerce_after_shop_loop_item_title', [ $this, 'render_archive_after_rating' ], 6 );
					add_action( 'woocommerce_after_shop_loop_item_title', [ $this, 'render_archive_after_price' ], 11 );
					add_action( 'woocommerce_after_shop_loop_item', [ $this, 'render_archive_before_add_to_cart' ], 9 );
					add_action( 'woocommerce_after_shop_loop_item', [ $this, 'render_archive_after_add_to_cart' ], 11 );

					// single page
					add_action( 'woocommerce_single_product_summary', [ $this, 'render_single_before_title' ], 4 );
					add_action( 'woocommerce_single_product_summary', [ $this, 'render_single_after_title' ], 6 );
					add_action( 'woocommerce_single_product_summary', [ $this, 'render_single_after_rating' ], 11 );
					add_action( 'woocommerce_single_product_summary', [ $this, 'render_single_after_excerpt' ], 21 );
					add_action( 'woocommerce_single_product_summary', [
						$this,
						'render_single_before_add_to_cart'
					], 29 );
					add_action( 'woocommerce_single_product_summary', [
						$this,
						'render_single_after_add_to_cart'
					], 31 );
					add_action( 'woocommerce_single_product_summary', [ $this, 'render_single_after_meta' ], 41 );
					add_action( 'woocommerce_single_product_summary', [ $this, 'render_single_after_sharing' ], 51 );

					// columns
					add_filter( 'manage_edit-wpc_product_badge_columns', [ $this, 'columns' ] );
					add_action( 'manage_wpc_product_badge_posts_custom_column', [ $this, 'columns_content' ], 10, 2 );
					add_filter( 'manage_edit-wpc_product_badge_sortable_columns', [ $this, 'sortable_columns' ] );
					add_filter( 'request', [ $this, 'request' ] );

					// dropdown cats
					add_filter( 'wp_dropdown_cats', [ $this, 'dropdown_cats_multiple' ], 10, 2 );
				}

				function init() {
					// register post type
					$labels = [
						'name'          => _x( 'Badges', 'Post Type General Name', 'wpc-badge-management' ),
						'singular_name' => _x( 'Badge', 'Post Type Singular Name', 'wpc-badge-management' ),
						'add_new_item'  => esc_html__( 'Add New Badge', 'wpc-badge-management' ),
						'add_new'       => esc_html__( 'Add New', 'wpc-badge-management' ),
						'edit_item'     => esc_html__( 'Edit Badge', 'wpc-badge-management' ),
						'update_item'   => esc_html__( 'Update Badge', 'wpc-badge-management' ),
						'search_items'  => esc_html__( 'Search Badge', 'wpc-badge-management' ),
					];

					$args = [
						'label'               => esc_html__( 'Badge', 'wpc-badge-management' ),
						'labels'              => $labels,
						'supports'            => [ 'title', 'excerpt' ],
						'hierarchical'        => false,
						'public'              => false,
						'show_ui'             => true,
						'show_in_menu'        => true,
						'show_in_nav_menus'   => true,
						'show_in_admin_bar'   => true,
						'menu_position'       => 28,
						'menu_icon'           => 'dashicons-awards',
						'can_export'          => true,
						'has_archive'         => false,
						'exclude_from_search' => true,
						'publicly_queryable'  => false,
						'capability_type'     => 'post',
						'show_in_rest'        => false,
					];

					register_post_type( 'wpc_product_badge', $args );

					// register taxonomy
					$labels = [
						'name'              => _x( 'Groups (deprecated)', 'taxonomy general name', 'wpc-badge-management' ),
						'singular_name'     => _x( 'Group (deprecated)', 'taxonomy singular name', 'wpc-badge-management' ),
						'search_items'      => esc_html__( 'Search Groups', 'wpc-badge-management' ),
						'all_items'         => esc_html__( 'All Groups', 'wpc-badge-management' ),
						'parent_item'       => esc_html__( 'Parent Group', 'wpc-badge-management' ),
						'parent_item_colon' => esc_html__( 'Parent Group:', 'wpc-badge-management' ),
						'edit_item'         => esc_html__( 'Edit Group', 'wpc-badge-management' ),
						'update_item'       => esc_html__( 'Update Group', 'wpc-badge-management' ),
						'add_new_item'      => esc_html__( 'Add New Group', 'wpc-badge-management' ),
						'new_item_name'     => esc_html__( 'New Group Name', 'wpc-badge-management' ),
						'menu_name'         => esc_html__( 'Group (deprecated)', 'wpc-badge-management' ),
					];

					$args = [
						'hierarchical'      => false,
						'labels'            => $labels,
						'public'            => false,
						'show_ui'           => false,
						'show_admin_column' => false,
						'query_var'         => true,
					];

					$labels_new = [
						'name'              => _x( 'Groups', 'taxonomy general name', 'wpc-badge-management' ),
						'singular_name'     => _x( 'Group', 'taxonomy singular name', 'wpc-badge-management' ),
						'search_items'      => esc_html__( 'Search Groups', 'wpc-badge-management' ),
						'all_items'         => esc_html__( 'All Groups', 'wpc-badge-management' ),
						'parent_item'       => esc_html__( 'Parent Group', 'wpc-badge-management' ),
						'parent_item_colon' => esc_html__( 'Parent Group:', 'wpc-badge-management' ),
						'edit_item'         => esc_html__( 'Edit Group', 'wpc-badge-management' ),
						'update_item'       => esc_html__( 'Update Group', 'wpc-badge-management' ),
						'add_new_item'      => esc_html__( 'Add New Group', 'wpc-badge-management' ),
						'new_item_name'     => esc_html__( 'New Group Name', 'wpc-badge-management' ),
						'menu_name'         => esc_html__( 'Group', 'wpc-badge-management' ),
					];

					$args_new = [
						'hierarchical'      => true,
						'labels'            => $labels_new,
						'public'            => false,
						'show_ui'           => true,
						'show_admin_column' => true,
						'query_var'         => true,
						'meta_box_cb'       => [ $this, 'group_meta_box' ],
					];

					register_taxonomy( 'wpc_group_badge', [ 'wpc_product_badge' ], $args );
					register_taxonomy( 'wpc-badge-group', [ 'wpc_product_badge' ], $args_new );

					// shortcode
					add_shortcode( 'wpcbm', [ $this, 'shortcode_badges' ] );
					add_shortcode( 'wpcbm_badges', [ $this, 'shortcode_badges' ] );
					add_shortcode( 'wpcbm_badge', [ $this, 'shortcode_badge' ] );

					// build badges data
					$args = [
						'fields'         => 'ids',
						'post_type'      => 'wpc_product_badge',
						'post_status'    => 'publish',
						'posts_per_page' => - 1,
						'meta_query'     => [
							'relation' => 'OR',
							[
								'key'     => 'wpcbm_activate',
								'compare' => 'NOT EXISTS'
							],
							[
								'key'     => 'wpcbm_activate',
								'value'   => 'off',
								'compare' => '!='
							]
						]
					];

					$posts = get_posts( $args );

					if ( ! empty( $posts ) ) {
						foreach ( $posts as $post_id ) {
							$this->badges[ 'b' . $post_id ] = $this->badge_data( $post_id );
						}
					}
				}

				function shortcode_badge( $attrs ) {
					$attrs = shortcode_atts( [
						'id'   => null,
						'flat' => true
					], $attrs );

					if ( ! ( $badge_id = $attrs['id'] ) || ! isset( $this->badges[ 'b' . $badge_id ] ) ) {
						return '';
					}

					ob_start();
					self::render_badge( $this->badges[ 'b' . $badge_id ], false, $attrs['flat'] );

					return ob_get_clean();
				}

				function shortcode_badges( $attrs ) {
					$attrs = shortcode_atts( [
						'product'  => null,
						'flat'     => true,
						'position' => ''
					], $attrs );

					if ( $attrs['product'] ) {
						if ( ! is_a( $attrs['product'], 'WC_Product' ) ) {
							$product = wc_get_product( absint( $attrs['product'] ) );
						} else {
							$product = $attrs['product'];
						}
					} else {
						global $product;
					}

					if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
						return '';
					}

					ob_start();
					self::render_badges( $product, $attrs['flat'], $attrs['position'] );

					return ob_get_clean();
				}

				function group_meta_box( $post, $box ) {
					$defaults = [ 'taxonomy' => 'category' ];

					if ( ! isset( $box['args'] ) || ! is_array( $box['args'] ) ) {
						$args = [];
					} else {
						$args = $box['args'];
					}

					extract( wp_parse_args( $args, $defaults ), EXTR_SKIP );

					$selected = wp_get_object_terms( $post->ID, $taxonomy, [ 'fields' => 'ids' ] );

					echo '<div id="taxonomy-' . esc_attr( $taxonomy ) . '" class="selectdiv">';

					wp_dropdown_categories( [
						'taxonomy'         => $taxonomy,
						'class'            => 'widefat',
						'hide_empty'       => 0,
						'name'             => 'tax_input[' . $taxonomy . '][]',
						'selected'         => count( $selected ) >= 1 ? $selected[0] : '',
						'orderby'          => 'name',
						'hierarchical'     => 1,
						'show_option_none' => esc_html__( 'Choose a group', 'wpc-badge-management' ),
						'show_option_all'  => ''
					] );

					echo '</div>';
				}

				function group_show_fields( $term_or_tax ) {
					if ( is_object( $term_or_tax ) ) {
						$term_id    = $term_or_tax->term_id;
						$wrap_start = '<tr class="form-field"><th><label>';
						$wrap_mid   = '</label></th><td>';
						$wrap_end   = '</td></tr>';
					} else {
						$term_id    = 0;
						$wrap_start = '<div class="form-field"><label>';
						$wrap_mid   = '</label>';
						$wrap_end   = '</div>';
					}

					$position_archive   = get_term_meta( $term_id, 'position_archive', true ) ?: 'image';
					$position_single    = get_term_meta( $term_id, 'position_single', true ) ?: 'before_title';
					$position_quickview = get_term_meta( $term_id, 'position_quickview', true ) ?: 'image';

					// archive
					$positions_archive = [
						'image'              => esc_html__( 'On image', 'wpc-badge-management' ),
						'before_image'       => esc_html__( 'Above image', 'wpc-badge-management' ),
						'before_title'       => esc_html__( 'Above title', 'wpc-badge-management' ),
						'after_title'        => esc_html__( 'Under title', 'wpc-badge-management' ),
						'after_rating'       => esc_html__( 'Under rating', 'wpc-badge-management' ),
						'after_price'        => esc_html__( 'Under price', 'wpc-badge-management' ),
						'before_add_to_cart' => esc_html__( 'Above add to cart button', 'wpc-badge-management' ),
						'after_add_to_cart'  => esc_html__( 'Under add to cart button', 'wpc-badge-management' ),
						'none'               => esc_html__( 'None (hide it)', 'wpc-badge-management' ),
					];

					echo $wrap_start . esc_html__( 'Position on archive page', 'wpc-badge-management' ) . $wrap_mid . '<select name="position_archive">';

					foreach ( $positions_archive as $k => $p ) {
						echo '<option value="' . esc_attr( $k ) . '" ' . selected( $position_archive, $k, false ) . '>' . esc_html( $p ) . '</option>';
					}

					echo '</select>' . $wrap_end;

					// single
					$positions_single = [
						'before_title'       => esc_html__( 'Above title', 'wpc-badge-management' ),
						'after_title'        => esc_html__( 'Under title', 'wpc-badge-management' ),
						'after_rating'       => esc_html__( 'Under rating', 'wpc-badge-management' ),
						'after_excerpt'      => esc_html__( 'Under excerpt', 'wpc-badge-management' ),
						'before_add_to_cart' => esc_html__( 'Above add to cart button', 'wpc-badge-management' ),
						'after_add_to_cart'  => esc_html__( 'Under add to cart button', 'wpc-badge-management' ),
						'after_meta'         => esc_html__( 'Under meta', 'wpc-badge-management' ),
						'after_sharing'      => esc_html__( 'Under sharing', 'wpc-badge-management' ),
						'none'               => esc_html__( 'None (hide it)', 'wpc-badge-management' ),
					];

					echo $wrap_start . esc_html__( 'Position on product page', 'wpc-badge-management' ) . $wrap_mid . '<select name="position_single">';

					foreach ( $positions_single as $k => $p ) {
						echo '<option value="' . esc_attr( $k ) . '" ' . selected( $position_single, $k, false ) . '>' . esc_html( $p ) . '</option>';
					}

					echo '</select>' . $wrap_end;

					// quick view
					$positions_quickview = [
						'image'              => esc_html__( 'On image', 'wpc-badge-management' ),
						'before_title'       => esc_html__( 'Above title', 'wpc-badge-management' ),
						'after_title'        => esc_html__( 'Under title', 'wpc-badge-management' ),
						'before_rating'      => esc_html__( 'Above rating', 'wpc-badge-management' ),
						'after_rating'       => esc_html__( 'Under rating', 'wpc-badge-management' ),
						'before_price'       => esc_html__( 'Above price', 'wpc-badge-management' ),
						'after_price'        => esc_html__( 'Under price', 'wpc-badge-management' ),
						'before_excerpt'     => esc_html__( 'Above excerpt', 'wpc-badge-management' ),
						'after_excerpt'      => esc_html__( 'Under excerpt', 'wpc-badge-management' ),
						'before_meta'        => esc_html__( 'Above meta', 'wpc-badge-management' ),
						'after_meta'         => esc_html__( 'Under meta', 'wpc-badge-management' ),
						'before_add_to_cart' => esc_html__( 'Above add to cart button', 'wpc-badge-management' ),
						'after_add_to_cart'  => esc_html__( 'Under add to cart button', 'wpc-badge-management' ),
						'none'               => esc_html__( 'None (hide it)', 'wpc-badge-management' ),
					];

					echo $wrap_start . esc_html__( 'Position on quick view', 'wpc-badge-management' ) . $wrap_mid . '<select name="position_quickview">';

					foreach ( $positions_quickview as $k => $p ) {
						echo '<option value="' . esc_attr( $k ) . '" ' . selected( $position_quickview, $k, false ) . '>' . esc_html( $p ) . '</option>';
					}

					echo '</select>' . $wrap_end;
				}

				function group_save_fields( $term_id ) {
					if ( isset( $_POST['position_archive'] ) ) {
						update_term_meta( $term_id, 'position_archive', sanitize_text_field( $_POST['position_archive'] ) );
					}

					if ( isset( $_POST['position_single'] ) ) {
						update_term_meta( $term_id, 'position_single', sanitize_text_field( $_POST['position_single'] ) );
					}

					if ( isset( $_POST['position_quickview'] ) ) {
						update_term_meta( $term_id, 'position_quickview', sanitize_text_field( $_POST['position_quickview'] ) );
					}
				}

				private function badge_data( $id ) {
					$options = [
						'position',
						'style',
						'text',
						'link',
						'link_blank',
						'tooltip',
						'tooltip_position',
						'extra_class',
						'order',
						'background_color',
						'border_color',
						'text_color',
						'box_shadow',
						'apply',
						'categories',
						'conditionals',
						'tags',
						'terms',
						'roles',
						'timer',
						'image'
					];

					$position_archive   = self::get_setting( 'position_archive', 'image' );
					$position_single    = self::get_setting( 'position_single', '4' );
					$position_quickview = self::get_setting( 'position_quickview', 'image' );

					if ( $groups = get_the_terms( $id, 'wpc-badge-group' ) ) {
						$group_id           = $groups[0]->term_id;
						$position_archive   = get_term_meta( $group_id, 'position_archive', true ) ?: $position_archive;
						$position_single    = get_term_meta( $group_id, 'position_single', true ) ?: $position_single;
						$position_quickview = get_term_meta( $group_id, 'position_quickview', true ) ?: $position_quickview;
					} else {
						$group_id = 0;
					}

					$positions = [
						'archive_' . $position_archive,
						'single_' . $position_single,
						'quickview_' . $position_quickview
					];

					$data = [
						'id'        => $id,
						'title'     => get_the_title( $id ),
						'group'     => $group_id,
						'positions' => $positions
					];

					foreach ( $options as $option ) {
						$data[ $option ] = get_post_meta( $id, $option, true );
					}

					return $data;
				}

				function render_archive_image_start() {
					global $product;

					echo '<div class="wpcbm-wrapper">';

					if ( $product ) {
						$this->render_badges( $product, false, 'archive_image' );
					}
				}

				function render_archive_image_end() {
					echo '</div>';
				}

				function render_archive_before_image() {
					global $product;

					if ( $product ) {
						$this->render_badges( $product, true, 'archive_before_image' );
					}
				}

				function render_archive_before_title() {
					global $product;

					if ( $product ) {
						$this->render_badges( $product, true, 'archive_before_title' );
					}
				}

				function render_archive_after_title() {
					global $product;

					if ( $product ) {
						$this->render_badges( $product, true, 'archive_after_title' );
					}
				}

				function render_archive_after_rating() {
					global $product;

					if ( $product ) {
						$this->render_badges( $product, true, 'archive_after_rating' );
					}
				}

				function render_archive_after_price() {
					global $product;

					if ( $product ) {
						$this->render_badges( $product, true, 'archive_after_price' );
					}
				}

				function render_archive_before_add_to_cart() {
					global $product;

					if ( $product ) {
						$this->render_badges( $product, true, 'archive_before_add_to_cart' );
					}
				}

				function render_archive_after_add_to_cart() {
					global $product;

					if ( $product ) {
						$this->render_badges( $product, true, 'archive_after_add_to_cart' );
					}
				}

				function render_single_before_title() {
					global $product;

					if ( $product ) {
						$this->render_badges( $product, true, 'single_before_title' );
					}
				}

				function render_single_after_title() {
					global $product;

					if ( $product ) {
						$this->render_badges( $product, true, 'single_after_title' );
					}
				}

				function render_single_after_rating() {
					global $product;

					if ( $product ) {
						$this->render_badges( $product, true, 'single_after_rating' );
					}
				}

				function render_single_after_excerpt() {
					global $product;

					if ( $product ) {
						$this->render_badges( $product, true, 'single_after_excerpt' );
					}
				}

				function render_single_before_add_to_cart() {
					global $product;

					if ( $product ) {
						$this->render_badges( $product, true, 'single_before_add_to_cart' );
					}
				}

				function render_single_after_add_to_cart() {
					global $product;

					if ( $product ) {
						$this->render_badges( $product, true, 'single_after_add_to_cart' );
					}
				}

				function render_single_after_meta() {
					global $product;

					if ( $product ) {
						$this->render_badges( $product, true, 'single_after_meta' );
					}
				}

				function render_single_after_sharing() {
					global $product;

					if ( $product ) {
						$this->render_badges( $product, true, 'single_after_sharing' );
					}
				}

				function render_quickview_image_start() {
					global $product;

					echo '<div class="wpcbm-wrapper">';

					if ( $product ) {
						$this->render_badges( $product, false, 'quickview_image' );
					}
				}

				function render_quickview_image_end() {
					echo '</div>';
				}

				function render_quickview_before_title() {
					global $product;

					if ( $product ) {
						$this->render_badges( $product, true, 'quickview_before_title' );
					}
				}

				function render_quickview_after_title() {
					global $product;

					if ( $product ) {
						$this->render_badges( $product, true, 'quickview_after_title' );
					}
				}

				function render_quickview_before_rating() {
					global $product;

					if ( $product ) {
						$this->render_badges( $product, true, 'quickview_before_rating' );
					}
				}

				function render_quickview_after_rating() {
					global $product;

					if ( $product ) {
						$this->render_badges( $product, true, 'quickview_after_rating' );
					}
				}

				function render_quickview_before_price() {
					global $product;

					if ( $product ) {
						$this->render_badges( $product, true, 'quickview_before_price' );
					}
				}

				function render_quickview_after_price() {
					global $product;

					if ( $product ) {
						$this->render_badges( $product, true, 'quickview_after_price' );
					}
				}

				function render_quickview_before_excerpt() {
					global $product;

					if ( $product ) {
						$this->render_badges( $product, true, 'quickview_before_excerpt' );
					}
				}

				function render_quickview_after_excerpt() {
					global $product;

					if ( $product ) {
						$this->render_badges( $product, true, 'quickview_after_excerpt' );
					}
				}

				function render_quickview_before_meta() {
					global $product;

					if ( $product ) {
						$this->render_badges( $product, true, 'quickview_before_meta' );
					}
				}

				function render_quickview_after_meta() {
					global $product;

					if ( $product ) {
						$this->render_badges( $product, true, 'quickview_after_meta' );
					}
				}

				function render_quickview_before_add_to_cart() {
					global $product;

					if ( $product ) {
						$this->render_badges( $product, true, 'quickview_before_add_to_cart' );
					}
				}

				function render_quickview_after_add_to_cart() {
					global $product;

					if ( $product ) {
						$this->render_badges( $product, true, 'quickview_after_add_to_cart' );
					}
				}

				private function global_badges( $product ) {
					if ( ! is_a( $product, 'WC_Product' ) ) {
						$product = wc_get_product( absint( $product ) );
					}

					if ( ! $product ) {
						return false;
					}

					$product_id = $product->get_id();
					$badges     = [];

					foreach ( $this->badges as $key => $badge ) {
						if ( ! empty( $badge['apply'] ) ) {
							if ( ( $badge['apply'] === 'all' ) || ( $badge['apply'] === 'featured' && $product->is_featured() ) || ( $badge['apply'] === 'sale' && $product->is_on_sale() ) || ( $badge['apply'] === 'instock' && $product->is_in_stock() ) || ( $badge['apply'] === 'outofstock' && ! $product->is_in_stock() ) || ( $badge['apply'] === 'backorder' && $product->is_on_backorder() ) ) {
								$badges[ $key ] = $badge;
								continue;
							}

							if ( $badge['apply'] === 'bestselling' ) {
								$bestselling = self::best_selling_products();

								if ( ! empty( $bestselling ) && in_array( $product_id, $bestselling ) ) {
									$badges[ $key ] = $badge;
									continue;
								}
							}

							if ( ( $badge['apply'] === 'categories' ) && isset( $badge['categories'] ) && is_array( $badge['categories'] ) && count( $badge['categories'] ) > 0 ) {
								if ( has_term( $badge['categories'], 'product_cat', $product_id ) ) {
									$badges[ $key ] = $badge;
									continue;
								}
							}

							if ( ( $badge['apply'] === 'tags' ) && ! empty( $badge['tags'] ) ) {
								$tags = array_map( 'trim', explode( ',', $badge['tags'] ) );

								if ( has_term( $tags, 'product_tag', $product_id ) ) {
									$badges[ $key ] = $badge;
									continue;
								}
							}

							if ( ! in_array( $badge['apply'], [
									'all',
									'sale',
									'featured',
									'bestselling',
									'instock',
									'outofstock',
									'backorder',
									'categories',
									'tags',
									'combination'
								] ) && ! empty( $badge['terms'] ) ) {
								// taxonomy
								if ( ! is_array( $badge['terms'] ) ) {
									$terms = array_map( 'trim', explode( ',', $badge['terms'] ) );
								} else {
									$terms = $badge['terms'];
								}

								// for special characters
								$term_ids = [];
								$terms    = array_map( 'rawurldecode', $terms );

								foreach ( $terms as $term_slug ) {
									if ( $term_obj = get_term_by( 'slug', $term_slug, $badge['apply'] ) ) {
										$term_ids[] = $term_obj->term_id;
									}
								}

								if ( ! empty( $term_ids ) && ( has_term( $term_ids, $badge['apply'], $product_id ) || apply_filters( 'wpcbm_has_term', false, $term_ids, $badge['apply'], $product_id ) ) ) {
									$badges[ $key ] = $badge;
								}
							}
						}
					}

					return $badges;
				}

				public function render_badges( $product, $flat = false, $position = '' ) {
					$overwrite = false;
					$flat      = wc_string_to_bool( $flat );

					if ( ! is_a( $product, 'WC_Product' ) ) {
						$product = wc_get_product( absint( $product ) );
					}

					if ( ! $product ) {
						return;
					}

					$badges = [];
					$type   = $product->get_meta( 'wpcbm_type' );

					switch ( $type ) {
						case 'disable':

							return;
						case 'overwrite':
							$overwrite = true;

							if ( $ids = $product->get_meta( 'wpcbm_badges' ) ) {
								foreach ( $ids as $id ) {
									if ( isset( $this->badges[ 'b' . $id ] ) ) {
										$badges[ 'b' . $id ] = $this->badges[ 'b' . $id ];
									}
								}
							}

							break;
						case 'prepend':
							$prepend_badges = [];

							if ( $ids = $product->get_meta( 'wpcbm_badges' ) ) {
								foreach ( $ids as $id ) {
									if ( isset( $this->badges[ 'b' . $id ] ) ) {
										$prepend_badges[ 'b' . $id ] = $this->badges[ 'b' . $id ];
									}
								}
							}

							$badges = array_merge( $prepend_badges, $this->global_badges( $product ) );

							break;
						case 'append':
							$badges = $this->global_badges( $product );

							if ( $ids = $product->get_meta( 'wpcbm_badges' ) ) {
								foreach ( $ids as $id ) {
									if ( isset( $this->badges[ 'b' . $id ] ) ) {
										$badges[ 'b' . $id ] = $this->badges[ 'b' . $id ];
									}
								}
							}

							break;
						default:
							$badges = $this->global_badges( $product );
					}

					if ( empty( $badges ) || ! is_array( $badges ) ) {
						return;
					}

					if ( ! empty( $position ) ) {
						foreach ( $badges as $key => $badge ) {
							if ( ! in_array( $position, $badge['positions'] ) ) {
								unset( $badges[ $key ] );
							}
						}
					}

					if ( empty( $badges ) ) {
						return;
					}

					// sort badges
					if ( ! $overwrite ) {
						array_multisort( array_column( $badges, 'order' ), SORT_ASC, $badges );
					}

					if ( ! $flat ) {
						$badges_pos = [];

						if ( is_array( $badges ) && count( $badges ) > 0 ) {
							foreach ( $badges as $badge ) {
								$pos                  = $badge['position'];
								$badges_pos[ $pos ][] = $badge;
							}
						}

						if ( is_array( $badges_pos ) && count( $badges_pos ) > 0 ) {
							foreach ( $badges_pos as $pos => $badges ) {
								$badges_class = 'wpcbm-badges wpcbm-badges-' . esc_attr( $pos ) . ' ' . ( ! empty( $position ) ? 'wpcbm-badges-' . esc_attr( $position ) : '' );
								echo '<div class="' . esc_attr( apply_filters( 'wpcbm_badges_class', $badges_class, $pos, $badges ) ) . '">';

								if ( is_array( $badges ) && count( $badges ) > 0 ) {
									foreach ( $badges as $badge ) {
										$this->render_badge( $badge, false, $flat, $position, $product->get_id() );
									}
								}

								echo '</div>';
							}
						}
					} else {
						$badges_class = 'wpcbm-badges wpcbm-badges-flat ' . ( ! empty( $position ) ? 'wpcbm-badges-' . esc_attr( $position ) : '' );
						echo '<div class="' . esc_attr( apply_filters( 'wpcbm_badges_class', $badges_class, 'flat', $badges ) ) . '">';

						if ( is_array( $badges ) && count( $badges ) > 0 ) {
							foreach ( $badges as $badge ) {
								$this->render_badge( $badge, false, $flat, $position, $product->get_id() );
							}
						}

						echo '</div>';
					}
				}

				private function render_badge( $badge, $preview = false, $flat = false, $position = '', $product_id = 0 ) {
					$preview = wc_string_to_bool( $preview );
					$flat    = wc_string_to_bool( $flat );

					if ( ! $preview && ( ! self::check_roles( $badge ) || ! self::check_timer( $badge ) ) ) {
						return;
					}

					$badge_content = $badge_output = '';
					$badge_class   = 'wpcbm-badge wpcbm-badge-' . $badge['id'] . ' wpcbm-pid-' . $product_id . ' wpcbm-badge-style-' . $badge['style'] . ' wpcbm-badge-group-' . $badge['group'] . ' hint--' . ( ! empty( $badge['tooltip_position'] ) ? $badge['tooltip_position'] : 'top' );

					if ( ! empty( $badge['extra_class'] ) ) {
						$badge_class .= ' ' . $badge['extra_class'];
					}

					$badge_class = apply_filters( 'wpcbm_badge_class', $badge_class, $badge );

					if ( $badge['style'] == 'image' ) {
						if ( ! empty( $badge['image'] ) ) {
							$badge_content = wp_get_attachment_image( $badge['image'], 'full' );
						}
					} else {
						$badge_content = do_shortcode( html_entity_decode( $badge['text'] ) );
					}

					$badge_content = wp_kses_post( apply_filters( 'wpcbm_badge_content', $badge_content, $badge ) );

					if ( empty( $badge_content ) && $preview ) {
						$badge_content = 'ABC';
					}

					if ( empty( $badge_content ) ) {
						return;
					}

					if ( ! empty( $badge['link'] ) && ( $position !== 'archive_image' ) ) {
						$badge_output .= '<a class="' . esc_attr( $badge_class ) . '" aria-label="' . esc_attr( $badge['tooltip'] ) . '" href="' . esc_url( $badge['link'] ) . '" ' . ( ! empty( $badge['link_blank'] ) ? 'target="_blank"' : '' ) . '>';
						$badge_output .= '<div class="wpcbm-badge-inner">' . $badge_content . '</div>';
						$badge_output .= '</a>';
					} else {
						$badge_output .= '<div class="' . esc_attr( $badge_class ) . '" aria-label="' . esc_attr( $badge['tooltip'] ) . '">';
						$badge_output .= '<div class="wpcbm-badge-inner">' . $badge_content . '</div>';
						$badge_output .= '</div>';
					}

					echo apply_filters( 'wpcbm_render_badge', $badge_output, $badge, $preview, $flat, $position );
				}

				function badge_meta_box() {
					add_meta_box( 'wpcbm_configuration', esc_html__( 'Configuration', 'wpc-badge-management' ), [
						$this,
						'badge_configuration'
					], 'wpc_product_badge', 'advanced', 'low' );
					add_meta_box( 'wpcbm_preview', esc_html__( 'Preview', 'wpc-badge-management' ), [
						$this,
						'badge_preview'
					], 'wpc_product_badge', 'side', 'default' );
				}

				private function get_styles() {
					$arr = [
						'01' => [
							'name'  => '01',
							'image' => WPCBM_URI . 'assets/images/style/01.png',
							'allow' => [
								'text'             => 'New',
								'text_color'       => '#ffffff',
								'background_color' => '#00A1BC',
								'border_color'     => '#ffffff',
								'box_shadow'       => 'rgba(0, 0, 0, 0.1)',
							]
						],
						'02' => [
							'name'  => '02',
							'image' => WPCBM_URI . 'assets/images/style/02.png',
							'allow' => [
								'text'             => 'New',
								'text_color'       => '#ffffff',
								'background_color' => '#78BB24',
								'border_color'     => '#ffffff',
								'box_shadow'       => 'rgba(0, 0, 0, 0.1)',
							]
						],
						'03' => [
							'name'  => '03',
							'image' => WPCBM_URI . 'assets/images/style/03.png',
							'allow' => [
								'text'             => 'New',
								'text_color'       => '#ffffff',
								'background_color' => '#CA8C2E',
								'border_color'     => '#ffffff',
								'box_shadow'       => 'rgba(0, 0, 0, 0.1)',
							]
						],
						'04' => [
							'name'  => '04',
							'image' => WPCBM_URI . 'assets/images/style/04.png',
							'allow' => [
								'text'             => 'New',
								'text_color'       => '#ffffff',
								'background_color' => '#D83636',
								'border_color'     => '#ffffff',
								'box_shadow'       => 'rgba(0, 0, 0, 0.1)',
							]
						],
						'05' => [
							'name'  => '05',
							'image' => WPCBM_URI . 'assets/images/style/05.png',
							'allow' => [
								'text'             => 'New',
								'text_color'       => '#ffffff',
								'background_color' => '#00A1BC',
								'box_shadow'       => 'rgba(0, 0, 0, 0.1)',
							]
						],
						'06' => [
							'name'  => '06',
							'image' => WPCBM_URI . 'assets/images/style/06.png',
							'allow' => [
								'text'             => 'New',
								'text_color'       => '#ffffff',
								'background_color' => '#FFAD00',
								'box_shadow'       => 'rgba(0, 0, 0, 0.1)',
							]
						],
						'07' => [
							'name'  => '07',
							'image' => WPCBM_URI . 'assets/images/style/07.png',
							'allow' => [
								'text'             => 'New',
								'text_color'       => '#2E2E2E',
								'background_color' => '#FDE8C5',
								'border_color'     => '#272727',
								'box_shadow'       => 'rgba(0, 0, 0, 0.2)',
							]
						],
						'08' => [
							'name'  => '08',
							'image' => WPCBM_URI . 'assets/images/style/08.png',
							'allow' => [
								'text'             => 'New',
								'text_color'       => '#ffffff',
								'background_color' => '#D8367C',
								'box_shadow'       => 'rgba(0, 0, 0, 0.1)',
							]
						],
						'09' => [
							'name'  => '09',
							'image' => WPCBM_URI . 'assets/images/style/09.png',
							'allow' => [
								'text'             => 'New',
								'text_color'       => '#ffffff',
								'background_color' => '#91BB24',
								'box_shadow'       => 'rgba(0, 0, 0, 0.1)',
							]
						],
						'10' => [
							'name'  => '10',
							'image' => WPCBM_URI . 'assets/images/style/10.png',
							'allow' => [
								'text'             => 'New',
								'text_color'       => '#ffffff',
								'background_color' => '#ED9808',
							]
						],
						'11' => [
							'name'  => '11',
							'image' => WPCBM_URI . 'assets/images/style/11.png',
							'allow' => [
								'text'             => 'New',
								'text_color'       => '#ffffff',
								'background_color' => '#00AADB',
								'box_shadow'       => 'rgba(0, 0, 0, 0.1)',
							]
						],
						'12' => [
							'name'  => '12',
							'image' => WPCBM_URI . 'assets/images/style/12.png',
							'allow' => [
								'text'             => 'New',
								'text_color'       => '#ffffff',
								'background_color' => '#D8365E',
								'box_shadow'       => 'rgba(0, 0, 0, 0.1)',
							]
						],
						'13' => [
							'name'  => '13',
							'image' => WPCBM_URI . 'assets/images/style/13.png',
							'allow' => [
								'text'             => 'New',
								'text_color'       => '#ffffff',
								'background_color' => '#955FD5',
								'box_shadow'       => 'rgba(0, 0, 0, 0.1)',
							]
						],
						'14' => [
							'name'  => '14',
							'image' => WPCBM_URI . 'assets/images/style/14.png',
							'allow' => [
								'text'             => 'New',
								'text_color'       => '#ffffff',
								'background_color' => '#78BB24',
								'border_color'     => '#4A8800',
								'box_shadow'       => 'rgba(0, 0, 0, 0.1)',
							]
						],
						'15' => [
							'name'  => '15',
							'image' => WPCBM_URI . 'assets/images/style/15.png',
							'allow' => [
								'text'       => 'New',
								'text_color' => '#222222',
							]
						],
						'16' => [
							'name'  => '16',
							'image' => WPCBM_URI . 'assets/images/style/16.png',
							'allow' => [
								'text'       => 'New',
								'text_color' => '#222222',
							]
						],
						'17' => [
							'name'  => '17',
							'image' => WPCBM_URI . 'assets/images/style/17.png',
							'allow' => [
								'text'       => 'New',
								'text_color' => '#222222',
							]
						],
						'18' => [
							'name'  => '18',
							'image' => WPCBM_URI . 'assets/images/style/18.png',
							'allow' => [
								'text'       => 'New',
								'text_color' => '#222222',
							]
						],
						'19' => [
							'name'  => '19',
							'image' => WPCBM_URI . 'assets/images/style/19.png',
							'allow' => [
								'text'       => 'New',
								'text_color' => '#222222',
							]
						],
						'20' => [
							'name'  => '20',
							'image' => WPCBM_URI . 'assets/images/style/20.png',
							'allow' => [
								'text'       => 'New',
								'text_color' => '#222222',
							]
						],
						'21' => [
							'name'  => '21',
							'image' => WPCBM_URI . 'assets/images/style/21.png',
							'allow' => [
								'text'       => 'New',
								'text_color' => '#222222',
							]
						],
						'22' => [
							'name'  => '22',
							'image' => WPCBM_URI . 'assets/images/style/22.png',
							'allow' => [
								'text'       => 'New',
								'text_color' => '#222222',
							]
						],
						'23' => [
							'name'  => '23',
							'image' => WPCBM_URI . 'assets/images/style/23.png',
							'allow' => [
								'text'       => 'New',
								'text_color' => '#222222',
							]
						],
						'24' => [
							'name'  => '24',
							'image' => WPCBM_URI . 'assets/images/style/24.png',
							'allow' => [
								'text'             => 'New',
								'text_color'       => '#ffffff',
								'background_color' => '#b9466c',
								'border_color'     => '#6f2a41',
								'box_shadow'       => 'rgba(0, 0, 0, 0.1)',
							]
						],
						'25' => [
							'name'  => '25',
							'image' => WPCBM_URI . 'assets/images/style/25.png',
							'allow' => [
								'text'             => 'New',
								'text_color'       => '#ffffff',
								'background_color' => '#00a0bc',
								'border_color'     => '#327993',
								'box_shadow'       => 'rgba(0, 0, 0, 0.1)',
							]
						],
						'26' => [
							'name'  => '26',
							'image' => WPCBM_URI . 'assets/images/style/26.png',
							'allow' => [
								'text'             => 'New',
								'text_color'       => '#ffffff',
								'background_color' => '#ffad00',
								'border_color'     => '#cf7704',
								'box_shadow'       => 'rgba(0, 0, 0, 0.1)',
							]
						],
						'27' => [
							'name'  => '27',
							'image' => WPCBM_URI . 'assets/images/style/27.png',
							'allow' => [
								'text'             => 'New',
								'text_color'       => '#ffffff',
								'background_color' => '#77bb24',
								'border_color'     => '#4a8800',
								'box_shadow'       => 'rgba(0, 0, 0, 0.1)',
							]
						],
						'28' => [
							'name'  => '28',
							'image' => WPCBM_URI . 'assets/images/style/28.png',
							'allow' => [
								'text'             => 'New',
								'text_color'       => '#ffffff',
								'background_color' => '#b9466c',
								'border_color'     => '#6f2a41',
								'box_shadow'       => 'rgba(0, 0, 0, 0.1)',
							]
						],
						'29' => [
							'name'  => '29',
							'image' => WPCBM_URI . 'assets/images/style/29.png',
							'allow' => [
								'text'             => 'New',
								'text_color'       => '#ffffff',
								'background_color' => '#00a0bc',
								'border_color'     => '#327993',
								'box_shadow'       => 'rgba(0, 0, 0, 0.1)',
							]
						],
						'30' => [
							'name'  => '30',
							'image' => WPCBM_URI . 'assets/images/style/30.png',
							'allow' => [
								'text'             => 'New',
								'text_color'       => '#ffffff',
								'background_color' => '#77bb24',
								'border_color'     => '#4a8800',
								'box_shadow'       => 'rgba(0, 0, 0, 0.1)',
							]
						],
					];

					$extra_styles = apply_filters( 'wpcbm_extra_styles', [] );

					if ( ! empty( $extra_styles ) ) {
						foreach ( $extra_styles as $k => $s ) {
							if ( is_numeric( $k ) ) {
								$arr[ 'wpcbm-' . $k ] = $s;
							} else {
								$arr[ $k ] = $s;
							}
						}
					}

					return $arr;
				}

				function badge_configuration( $post ) {
					$post_id          = $post->ID;
					$position         = ! empty( get_post_meta( $post_id, 'position', true ) ) ? get_post_meta( $post_id, 'position', true ) : 'top-left';
					$style            = ! empty( get_post_meta( $post_id, 'style', true ) ) ? get_post_meta( $post_id, 'style', true ) : 'image';
					$text             = ! empty( get_post_meta( $post_id, 'text', true ) ) ? get_post_meta( $post_id, 'text', true ) : 'Hot';
					$link             = ! empty( get_post_meta( $post_id, 'link', true ) ) ? get_post_meta( $post_id, 'link', true ) : '';
					$link_blank       = ! empty( get_post_meta( $post_id, 'link_blank', true ) );
					$tooltip          = ! empty( get_post_meta( $post_id, 'tooltip', true ) ) ? get_post_meta( $post_id, 'tooltip', true ) : '';
					$tooltip_position = ! empty( get_post_meta( $post_id, 'tooltip_position', true ) ) ? get_post_meta( $post_id, 'tooltip_position', true ) : 'top';
					$extra_class      = ! empty( get_post_meta( $post_id, 'extra_class', true ) ) ? get_post_meta( $post_id, 'extra_class', true ) : '';
					$order            = ! empty( get_post_meta( $post_id, 'order', true ) ) ? get_post_meta( $post_id, 'order', true ) : '1';
					$background_color = ! empty( get_post_meta( $post_id, 'background_color', true ) ) ? get_post_meta( $post_id, 'background_color', true ) : '#39a0ba';
					$border_color     = ! empty( get_post_meta( $post_id, 'border_color', true ) ) ? get_post_meta( $post_id, 'border_color', true ) : '#0a6379';
					$box_shadow       = ! empty( get_post_meta( $post_id, 'box_shadow', true ) ) ? get_post_meta( $post_id, 'box_shadow', true ) : 'rgba(0, 0, 0, 0.1)';
					$text_color       = ! empty( get_post_meta( $post_id, 'text_color', true ) ) ? get_post_meta( $post_id, 'text_color', true ) : '#ffffff';
					$apply            = ! empty( get_post_meta( $post_id, 'apply', true ) ) ? get_post_meta( $post_id, 'apply', true ) : '';
					$categories       = ! empty( get_post_meta( $post_id, 'categories', true ) ) ? get_post_meta( $post_id, 'categories', true ) : '';
					$tags             = ! empty( get_post_meta( $post_id, 'tags', true ) ) ? get_post_meta( $post_id, 'tags', true ) : '';
					$terms            = ! empty( get_post_meta( $post_id, 'terms', true ) ) ? get_post_meta( $post_id, 'terms', true ) : [];
					$roles            = ! empty( get_post_meta( $post_id, 'roles', true ) ) ? (array) get_post_meta( $post_id, 'roles', true ) : [ 'wpcbm_all' ];
					$timer            = ! empty( get_post_meta( $post_id, 'timer', true ) ) ? (array) get_post_meta( $post_id, 'timer', true ) : [];
					$image            = ! empty( get_post_meta( $post_id, 'image', true ) ) ? get_post_meta( $post_id, 'image', true ) : '';
					$conditionals     = ! empty( get_post_meta( $post_id, 'conditionals', true ) ) ? (array) get_post_meta( $post_id, 'conditionals', true ) : [];
					$image_src        = '';

					if ( ! empty( $image ) ) {
						$image_obj = wp_get_attachment_image_src( $image, 'full' );
						$image_src = $image_obj[0];
					}
					?>
                    <table class="wpcbm_configuration_table">
                        <tr class="wpcbm_configuration_tr">
                            <td class="wpcbm_configuration_th">
								<?php esc_html_e( 'Apply', 'wpc-badge-management' ); ?>
                            </td>
                            <td class="wpcbm_configuration_td">
                                <label for="wpcbm_apply"></label><select name="wpcbm_apply" id="wpcbm_apply">
                                    <option value="" <?php selected( $apply, '' ); ?>><?php esc_html_e( 'None', 'wpc-badge-management' ); ?></option>
                                    <option value="combination" <?php selected( $apply, 'combination' ); ?>><?php esc_html_e( 'Combined', 'wpc-badge-management' ); ?></option>
                                    <option value="all" <?php selected( $apply, 'all' ); ?>><?php esc_html_e( 'All products', 'wpc-badge-management' ); ?></option>
                                    <option value="sale" <?php selected( $apply, 'sale' ); ?>><?php esc_html_e( 'On sale', 'wpc-badge-management' ); ?></option>
                                    <option value="featured" <?php selected( $apply, 'featured' ); ?>><?php esc_html_e( 'Featured', 'wpc-badge-management' ); ?></option>
                                    <option value="bestselling" <?php selected( $apply, 'bestselling' ); ?>><?php esc_html_e( 'Best selling', 'wpc-badge-management' ); ?></option>
                                    <option value="instock" <?php selected( $apply, 'instock' ); ?>><?php esc_html_e( 'In stock', 'wpc-badge-management' ); ?></option>
                                    <option value="outofstock" <?php selected( $apply, 'outofstock' ); ?>><?php esc_html_e( 'Out of stock', 'wpc-badge-management' ); ?></option>
                                    <option value="backorder" <?php selected( $apply, 'backorder' ); ?>><?php esc_html_e( 'On backorder', 'wpc-badge-management' ); ?></option>
									<?php
									$taxonomies = get_object_taxonomies( 'product', 'objects' ); //$taxonomies = get_taxonomies( [ 'object_type' => [ 'product' ] ], 'objects' );

									foreach ( $taxonomies as $taxonomy ) {
										echo '<option value="' . esc_attr( $taxonomy->name ) . '" ' . ( $apply === $taxonomy->name ? 'selected' : '' ) . '>' . esc_html( $taxonomy->label ) . '</option>';
									}
									?>
                                </select>
                                <p class="description"><?php esc_html_e( 'Select which products you want to add this badge automatically. If "None" is set, you can still manually choose to add this in the "Badges" tab of each individual product page.', 'wpc-badge-management' ); ?></p>
                            </td>
                        </tr>
                        <tr class="wpcbm_configuration_tr" id="wpcbm_configuration_combination" style="<?php echo esc_attr( $apply === 'combination' ? '' : 'display:none;' ); ?>">
                            <td class="wpcbm_configuration_th">
								<?php esc_html_e( 'Combined', 'wpc-badge-management' ); ?>
                            </td>
                            <td class="wpcbm_configuration_td">
                                <p class="description" style="color: #c9356e;">
                                    Using a combination of conditionals only available on the Premium Version.
                                    <a href="https://wpclever.net/downloads/wpc-badge-management?utm_source=pro&utm_medium=wpcbm&utm_campaign=wporg" target="_blank">Click here</a> to buy, just $29!
                                </p>
                                <div class="wpcbm_conditionals">
									<?php
									if ( ! empty( $conditionals ) ) {
										foreach ( $conditionals as $key => $conditional ) {
											self::conditional( $key, $conditional );
										}
									}
									?>
                                </div>
                                <input type="button" class="wpcbm_add_conditional button button-large" value="<?php esc_attr_e( '+ Add conditional', 'wpc-badge-management' ); ?>"/>
                            </td>
                        </tr>
                        <tr class="wpcbm_configuration_tr" id="wpcbm_configuration_categories" style="<?php echo esc_attr( $apply === 'categories' ? '' : 'display:none;' ); ?>">
                            <td class="wpcbm_configuration_th">
								<?php esc_html_e( 'Categories', 'wpc-badge-management' ); ?>
                            </td>
                            <td class="wpcbm_configuration_td">
								<?php
								wc_product_dropdown_categories(
									[
										'name'             => 'wpcbm_categories',
										'class'            => 'wpcbm_categories_dropdown',
										'hide_empty'       => 0,
										'value_field'      => 'id',
										'multiple'         => true,
										'show_option_all'  => '',
										'show_option_none' => '',
										'selected'         => ! empty( $categories ) ? implode( ',', $categories ) : ''
									] );
								?>
                            </td>
                        </tr>
                        <tr class="wpcbm_configuration_tr" id="wpcbm_configuration_tags" style="<?php echo esc_attr( $apply === 'tags' ? '' : 'display:none;' ); ?>">
                            <td class="wpcbm_configuration_th">
								<?php esc_html_e( 'Tags', 'wpc-badge-management' ); ?>
                            </td>
                            <td class="wpcbm_configuration_td">
                                <label for="wpcbm_tags"></label><input type="text" value="<?php echo esc_attr( $tags ); ?>" name="wpcbm_tags" id="wpcbm_tags" class="regular-text"/>
                                <p class="description"><?php esc_attr_e( 'Add some tags, split by a comma...', 'wpc-badge-management' ); ?></p>
                            </td>
                        </tr>
                        <tr class="wpcbm_configuration_tr" id="wpcbm_configuration_terms" style="<?php echo esc_attr( ! empty( $apply ) && ! in_array( $apply, [
							'all',
							'sale',
							'featured',
							'bestselling',
							'instock',
							'outofstock',
							'backorder',
							'categories',
							'tags',
							'combination'
						] ) ? '' : 'display:none;' ); ?>">
                            <td class="wpcbm_configuration_th">
                                <span id="wpcbm_configuration_terms_label"><?php esc_html_e( 'Terms', 'wpc-badge-management' ); ?></span>
                            </td>
                            <td class="wpcbm_configuration_td">
								<?php
								if ( ! is_array( $terms ) ) {
									$terms = array_map( 'trim', explode( ',', $terms ) );
								}

								// for special characters
								$terms = array_map( 'rawurldecode', $terms );
								?>
                                <label for="wpcbm_terms"></label><select class="wpcbm_terms" id="wpcbm_terms" name="wpcbm_terms[]" multiple="multiple" data-<?php echo esc_attr( $apply ); ?>="<?php echo esc_attr( implode( ',', $terms ) ); ?>">
									<?php
									if ( ! empty( $terms ) ) {
										foreach ( $terms as $t ) {
											if ( $term = get_term_by( 'slug', $t, $apply ) ) {
												echo '<option value="' . esc_attr( $t ) . '" selected>' . esc_html( $term->name ) . '</option>';
											}
										}
									}
									?>
                                </select>
                            </td>
                        </tr>
                        <tr class="wpcbm_configuration_tr">
                            <td class="wpcbm_configuration_th">
								<?php esc_html_e( 'User roles', 'wpc-badge-management' ); ?>
                            </td>
                            <td class="wpcbm_configuration_td">
                                <p class="description"><?php esc_html_e( 'Specify the user roles that are allowed to view this badge.', 'wpc-badge-management' ); ?></p>
                                <label> <select name="wpcbm_roles[]" multiple class="wpcbm_roles">
										<?php
										global $wp_roles;
										echo '<option value="wpcbm_all" ' . ( in_array( 'wpcbm_all', $roles ) ? 'selected' : '' ) . '>' . esc_html__( 'All', 'wpc-badge-management' ) . '</option>';
										echo '<option value="wpcbm_user" ' . ( in_array( 'wpcbm_user', $roles ) ? 'selected' : '' ) . '>' . esc_html__( 'User (logged in)', 'wpc-badge-management' ) . '</option>';
										echo '<option value="wpcbm_guest" ' . ( in_array( 'wpcbm_guest', $roles ) ? 'selected' : '' ) . '>' . esc_html__( 'Guest (not logged in)', 'wpc-badge-management' ) . '</option>';

										foreach ( $wp_roles->roles as $role => $details ) {
											echo '<option value="' . esc_attr( $role ) . '" ' . ( in_array( $role, $roles ) ? 'selected' : '' ) . '>' . esc_html( $details['name'] ) . '</option>';
										}
										?>
                                    </select> </label>
                            </td>
                        </tr>
                        <tr class="wpcbm_configuration_tr">
                            <td class="wpcbm_configuration_th">
								<?php esc_html_e( 'Time', 'wpc-badge-management' ); ?>
                            </td>
                            <td class="wpcbm_configuration_td">
                                <p class="description"><?php esc_html_e( 'Configure date and time that must match all listed conditions.', 'wpc-badge-management' ); ?></p>
                                <div class="wpcbm_timer">
									<?php
									if ( ! empty( $timer ) ) {
										foreach ( $timer as $tm_k => $tm_v ) {
											self::time( $tm_k, $tm_v );
										}
									} else {
										self::time();
									}
									?>
                                </div>
                                <div class="wpcbm_add_time">
                                    <a href="#" class="button wpcbm_new_time"><?php esc_html_e( '+ Add time', 'wpc-badge-management' ); ?></a>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpcbm_configuration_tr">
                            <td class="wpcbm_configuration_th">
								<?php esc_html_e( 'Position on image', 'wpc-badge-management' ); ?>
                            </td>
                            <td class="wpcbm_configuration_td">
                                <label for="wpcbm_position"></label><select name="wpcbm_position" id="wpcbm_position">
									<?php
									$pos = apply_filters( 'wpcbm_positions', [
										'top-left'      => esc_html__( 'Top Left', 'wpc-badge-management' ),
										'top-center'    => esc_html__( 'Top Center', 'wpc-badge-management' ),
										'top-right'     => esc_html__( 'Top Right', 'wpc-badge-management' ),
										'middle-left'   => esc_html__( 'Middle Left', 'wpc-badge-management' ),
										'middle-center' => esc_html__( 'Middle Center', 'wpc-badge-management' ),
										'middle-right'  => esc_html__( 'Middle Right', 'wpc-badge-management' ),
										'bottom-left'   => esc_html__( 'Bottom Left', 'wpc-badge-management' ),
										'bottom-center' => esc_html__( 'Bottom Center', 'wpc-badge-management' ),
										'bottom-right'  => esc_html__( 'Bottom Right', 'wpc-badge-management' )
									] );

									foreach ( $pos as $k => $p ) {
										echo '<option value="' . esc_attr( $k ) . '" ' . ( $k == $position ? 'selected' : '' ) . '>' . esc_html( $p ) . '</option>';
									}
									?>
                                </select>
                            </td>
                        </tr>
                        <tr class="wpcbm_configuration_tr">
                            <td class="wpcbm_configuration_th">
								<?php esc_html_e( 'Style', 'wpc-badge-management' ); ?>
                            </td>
                            <td class="wpcbm_configuration_td">
                                <div class="wpcbm_styles" id="wpcbm_styles">
                                    <div class="wpcbm_style_item hint--top" aria-label="<?php esc_attr_e( 'Add Image', 'wpc-badge-management' ); ?>">
                                        <div class="inner">
                                            <input type="radio" id="wpcbm_style_image" name="wpcbm_style" value="image" <?php checked( $style, 'image' ); ?>
                                                    data-allow="<?php echo esc_attr( htmlspecialchars( json_encode( [ 'allow' => [ 'image' => '' ] ] ), ENT_QUOTES, 'UTF-8' ) ); ?>" data-image="<?php echo esc_attr( $image ); ?>">
                                            <label class="wpcbm_style_label" for="wpcbm_style_image">
                                                <i class="dashicons dashicons-format-image"></i> </label>
                                        </div>
                                    </div>
									<?php
									$styles = $this->get_styles();

									foreach ( $styles as $k => $s ) { ?>
                                        <div class="wpcbm_style_item hint--top" aria-label="<?php echo esc_attr( $s['name'] ?? $k ); ?>">
                                            <div class="inner">
                                                <input type="radio" id="wpcbm_style_<?php echo esc_attr( $k ); ?>" name="wpcbm_style" value="<?php echo esc_attr( $k ); ?>" <?php checked( $style, $k ); ?>
                                                        data-allow="<?php echo esc_attr( htmlspecialchars( json_encode( $s ), ENT_QUOTES, 'UTF-8' ) ); ?>" <?php if ( $style == $k ) {
													echo 'data-text="' . esc_attr( $text ) . '" data-text_color="' . esc_attr( $text_color ) . '" data-background_color="' . esc_attr( $background_color ) . '" data-border_color="' . esc_attr( $border_color ) . '"';
												} ?>>
                                                <label class="wpcbm_style_label" for="wpcbm_style_<?php echo esc_attr( $k ); ?>">
                                                    <img src="<?php echo esc_url( $s['image'] ); ?>" alt="<?php echo esc_attr( $s['name'] ?? $k ); ?>">
                                                </label>
                                            </div>
                                        </div>
										<?php
									}
									?>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpcbm_configuration_tr wpcbm_configuration_tr_allow wpcbm_configuration_tr_image" id="wpcbm_configuration_badge_image" style="<?php echo ( $style == 'image' ) ? '' : 'display: none;' ?>">
                            <td class="wpcbm_configuration_th">
								<?php esc_html_e( 'Image', 'wpc-badge-management' ); ?>
                            </td>
                            <td class="wpcbm_configuration_td">
								<?php wp_enqueue_media(); ?>
                                <input type="hidden" value="<?php echo esc_attr( $image ); ?>" name="wpcbm_image" id="wpcbm_image"/>
                                <img src="<?php echo esc_url( $image_src ); ?>" alt="Badge Image" class="<?php echo empty( $image_src ) ? 'hidden' : 'has-image'; ?>" id="wpcbm_image_img">
                                <a href="#" class="button button-primary button-large" id="wpcbm_add_image"><?php esc_html_e( 'Add Image', 'wpc-badge-management' ); ?></a>
                                <a href="#" class="delete" id="wpcbm_remove_image"><?php esc_html_e( 'Remove Image', 'wpc-badge-management' ); ?></a>
                            </td>
                        </tr>
                        <tr class="wpcbm_configuration_tr wpcbm_configuration_tr_allow wpcbm_configuration_tr_text" style="<?php echo ( $style !== 'image' ) ? '' : 'display: none;' ?>">
                            <td class="wpcbm_configuration_th">
								<?php esc_html_e( 'Text', 'wpc-badge-management' ); ?>
                            </td>
                            <td class="wpcbm_configuration_td">
                                <label for="wpcbm_text"></label><input type="text" value="<?php echo esc_attr( html_entity_decode( $text ) ); ?>" name="wpcbm_text" id="wpcbm_text" class="regular-text"/>
                                <span class="description">You can use <a href="#" id="wpcbm_icons_btn">icons</a> or <a href="#" id="wpcbm_shortcodes_btn">shortcodes</a>.</span>
                                <div class="wpcbm-dialog" id="wpcbm_dialog_icons" style="display: none" title="<?php esc_html_e( 'Icons', 'wpc-badge-management' ); ?>">
                                    <p>
                                        After enabling Icon Libraries on the
                                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-wpcbm&tab=settings' ) ); ?>" target="_blank">settings page</a>, you can use the icon within the text, e.g:
                                        <code>ABC &lt;i class=&quot;fas fa-check&quot;&gt;&lt;/i&gt; XYZ</code>
                                    </p>
                                </div>
                                <div class="wpcbm-dialog" id="wpcbm_dialog_shortcodes" style="display: none" title="<?php esc_html_e( 'Shortcodes', 'wpc-badge-management' ); ?>">
                                    <p>
                                        You can use shortcodes within the text, e.g:<br/><code>ABC [your_shortcode] XYZ</code>
                                    </p>

                                    Try below build-in shortcodes:

                                    <ul>
                                        <li>
                                            <code>[wpcbm_product_data get="stock"]</code> - Display product data, e.g: stock, price, etc.
                                        </li>
                                        <li>
                                            <code>[wpcbm_best_seller top="10" in="product_cat" text="#%s in %s"]</code> - Display the bestseller position in a category, tag, brand, or collection. For example, [wpcbm_best_seller top="10" in="product_cat" text="#%s in %s"]. Allow "in" param: product_cat, product_tag, wpc-brand, wpc-collection.
                                        </li>
                                        <li><code>[wpcbm_price]</code> - Display product price.</li>
                                        <li>
                                            <code>[wpcbm_saved_percentage]</code> - Display saved percentage for on-sale product.
                                        </li>
                                        <li>
                                            <code>[wpcbm_saved_amount]</code> - Display saved amount for on-sale product.
                                        </li>
                                        <li><code>[wpcbm_tags]</code> - Display product tags.</li>
                                        <li><code>[wpcbm_categories]</code> - Display product categories.</li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpcbm_configuration_tr wpcbm_configuration_tr_allow wpcbm_configuration_tr_text_color" style="<?php echo ( $style !== 'image' ) ? '' : 'display: none;' ?>">
                            <td class="wpcbm_configuration_th">
								<?php esc_html_e( 'Text color', 'wpc-badge-management' ); ?>
                            </td>
                            <td class="wpcbm_configuration_td">
                                <label for="wpcbm_text_color"></label><input type="text" value="<?php echo esc_attr( $text_color ); ?>" name="wpcbm_text_color" id="wpcbm_text_color" data-alpha-enabled="true" data-alpha-color-type="rgba"/>
                            </td>
                        </tr>
                        <tr class="wpcbm_configuration_tr wpcbm_configuration_tr_allow wpcbm_configuration_tr_background_color" style="<?php echo ( $style !== 'image' ) ? '' : 'display: none;' ?>">
                            <td class="wpcbm_configuration_th">
								<?php esc_html_e( 'Background color', 'wpc-badge-management' ); ?>
                            </td>
                            <td class="wpcbm_configuration_td">
                                <label for="wpcbm_background_color"></label><input type="text" value="<?php echo esc_attr( $background_color ); ?>" name="wpcbm_background_color" id="wpcbm_background_color" data-alpha-enabled="true" data-alpha-color-type="rgba"/>
                            </td>
                        </tr>
                        <tr class="wpcbm_configuration_tr wpcbm_configuration_tr_allow wpcbm_configuration_tr_border_color" style="<?php echo ( $style !== 'image' ) ? '' : 'display: none;' ?>">
                            <td class="wpcbm_configuration_th">
								<?php esc_html_e( 'Border color', 'wpc-badge-management' ); ?>
                            </td>
                            <td class="wpcbm_configuration_td">
                                <label for="wpcbm_border_color"></label><input type="text" value="<?php echo esc_attr( $border_color ); ?>" name="wpcbm_border_color" id="wpcbm_border_color" data-alpha-enabled="true" data-alpha-color-type="rgba"/>
                            </td>
                        </tr>
                        <tr class="wpcbm_configuration_tr wpcbm_configuration_tr_allow wpcbm_configuration_tr_box_shadow" style="<?php echo ( $style !== 'image' ) ? '' : 'display: none;' ?>">
                            <td class="wpcbm_configuration_th">
								<?php esc_html_e( 'Box shadow', 'wpc-badge-management' ); ?>
                            </td>
                            <td class="wpcbm_configuration_td">
                                <label for="wpcbm_box_shadow"></label><input type="text" value="<?php echo esc_attr( $box_shadow ); ?>" name="wpcbm_box_shadow" id="wpcbm_box_shadow" data-alpha-enabled="true" data-alpha-color-type="rgba"/>
                            </td>
                        </tr>
                        <tr class="wpcbm_configuration_tr">
                            <td class="wpcbm_configuration_th">
								<?php esc_html_e( 'Link', 'wpc-badge-management' ); ?>
                            </td>
                            <td class="wpcbm_configuration_td">
                                <label for="wpcbm_link"></label><input type="url" value="<?php echo esc_url( $link ); ?>" name="wpcbm_link" id="wpcbm_link" class="regular-text"/>
                                <label><input type="checkbox" name="wpcbm_link_blank" <?php echo esc_attr( $link_blank ? 'checked' : '' ); ?>/> <?php esc_html_e( 'Open in a new tab.', 'wpc-badge-management' ); ?>
                                </label>
                            </td>
                        </tr>
                        <tr class="wpcbm_configuration_tr">
                            <td class="wpcbm_configuration_th">
								<?php esc_html_e( 'Tooltip', 'wpc-badge-management' ); ?>
                            </td>
                            <td class="wpcbm_configuration_td">
                                <label for="wpcbm_tooltip"></label><input type="text" value="<?php echo esc_attr( $tooltip ); ?>" name="wpcbm_tooltip" id="wpcbm_tooltip" class="regular-text"/>
                            </td>
                        </tr>
                        <tr class="wpcbm_configuration_tr">
                            <td class="wpcbm_configuration_th">
								<?php esc_html_e( 'Tooltip position', 'wpc-badge-management' ); ?>
                            </td>
                            <td class="wpcbm_configuration_td">
                                <label for="wpcbm_tooltip_position"></label><select name="wpcbm_tooltip_position" id="wpcbm_tooltip_position">
                                    <option value="top" <?php selected( $tooltip_position, 'top' ); ?>><?php esc_html_e( 'Top', 'wpc-badge-management' ); ?></option>
                                    <option value="right" <?php selected( $tooltip_position, 'right' ); ?>><?php esc_html_e( 'Right', 'wpc-badge-management' ); ?></option>
                                    <option value="bottom" <?php selected( $tooltip_position, 'bottom' ); ?>><?php esc_html_e( 'Bottom', 'wpc-badge-management' ); ?></option>
                                    <option value="left" <?php selected( $tooltip_position, 'left' ); ?>><?php esc_html_e( 'Left', 'wpc-badge-management' ); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr class="wpcbm_configuration_tr">
                            <td class="wpcbm_configuration_th">
								<?php esc_html_e( 'Order', 'wpc-badge-management' ); ?>
                            </td>
                            <td class="wpcbm_configuration_td">
                                <label for="wpcbm_order"></label><input type="number" min="1" max="500" value="<?php echo esc_attr( $order ); ?>" name="wpcbm_order" id="wpcbm_order"/>
                            </td>
                        </tr>
                        <tr class="wpcbm_configuration_tr">
                            <td class="wpcbm_configuration_th">
								<?php esc_html_e( 'Extra CSS classes', 'wpc-badge-management' ); ?>
                            </td>
                            <td class="wpcbm_configuration_td">
                                <label for="wpcbm_extra_class"></label><input type="text" value="<?php echo esc_attr( $extra_class ); ?>" name="wpcbm_extra_class" id="wpcbm_extra_class" class="regular-text"/>
                            </td>
                        </tr>
                    </table>
					<?php
				}

				function badge_save_fields( $post_id ) {
					if ( isset( $_POST['wpcbm_position'] ) ) {
						update_post_meta( $post_id, 'position', sanitize_text_field( $_POST['wpcbm_position'] ) );
					}

					if ( isset( $_POST['wpcbm_style'] ) ) {
						update_post_meta( $post_id, 'style', sanitize_text_field( $_POST['wpcbm_style'] ) );
					}

					if ( isset( $_POST['wpcbm_text'] ) ) {
						update_post_meta( $post_id, 'text', sanitize_text_field( htmlentities( wp_kses_post( $_POST['wpcbm_text'] ) ) ) );
					}

					if ( isset( $_POST['wpcbm_link'] ) ) {
						update_post_meta( $post_id, 'link', sanitize_url( $_POST['wpcbm_link'] ) );
					}

					if ( isset( $_POST['wpcbm_link_blank'] ) ) {
						update_post_meta( $post_id, 'link_blank', true );
					} else {
						update_post_meta( $post_id, 'link_blank', false );
					}

					if ( isset( $_POST['wpcbm_tooltip'] ) ) {
						update_post_meta( $post_id, 'tooltip', sanitize_text_field( $_POST['wpcbm_tooltip'] ) );
					}

					if ( isset( $_POST['wpcbm_tooltip_position'] ) ) {
						update_post_meta( $post_id, 'tooltip_position', sanitize_text_field( $_POST['wpcbm_tooltip_position'] ) );
					}

					if ( isset( $_POST['wpcbm_extra_class'] ) ) {
						update_post_meta( $post_id, 'extra_class', sanitize_text_field( $_POST['wpcbm_extra_class'] ) );
					}

					if ( isset( $_POST['wpcbm_order'] ) ) {
						update_post_meta( $post_id, 'order', sanitize_text_field( $_POST['wpcbm_order'] ) );
					}

					if ( isset( $_POST['wpcbm_background_color'] ) ) {
						update_post_meta( $post_id, 'background_color', sanitize_text_field( $_POST['wpcbm_background_color'] ) );
					}

					if ( isset( $_POST['wpcbm_border_color'] ) ) {
						update_post_meta( $post_id, 'border_color', sanitize_text_field( $_POST['wpcbm_border_color'] ) );
					}

					if ( isset( $_POST['wpcbm_text_color'] ) ) {
						update_post_meta( $post_id, 'text_color', sanitize_text_field( $_POST['wpcbm_text_color'] ) );
					}

					if ( isset( $_POST['wpcbm_box_shadow'] ) ) {
						update_post_meta( $post_id, 'box_shadow', sanitize_text_field( $_POST['wpcbm_box_shadow'] ) );
					}

					if ( isset( $_POST['wpcbm_apply'] ) ) {
						update_post_meta( $post_id, 'apply', sanitize_text_field( $_POST['wpcbm_apply'] ) );
					}

					if ( isset( $_POST['wpcbm_categories'] ) ) {
						update_post_meta( $post_id, 'categories', array_map( 'sanitize_text_field', $_POST['wpcbm_categories'] ) );
					}

					if ( isset( $_POST['wpcbm_tags'] ) ) {
						update_post_meta( $post_id, 'tags', sanitize_text_field( $_POST['wpcbm_tags'] ) );
					}

					if ( isset( $_POST['wpcbm_image'] ) ) {
						update_post_meta( $post_id, 'image', sanitize_text_field( $_POST['wpcbm_image'] ) );
					}

					if ( isset( $_POST['wpcbm_terms'] ) ) {
						update_post_meta( $post_id, 'terms', self::sanitize_array( $_POST['wpcbm_terms'] ) );
					}

					if ( isset( $_POST['wpcbm_roles'] ) ) {
						update_post_meta( $post_id, 'roles', self::sanitize_array( $_POST['wpcbm_roles'] ) );
					}

					if ( isset( $_POST['wpcbm_timer'] ) ) {
						update_post_meta( $post_id, 'timer', self::sanitize_array( $_POST['wpcbm_timer'] ) );
					} else {
						delete_post_meta( $post_id, 'timer' );
					}

					if ( isset( $_POST['wpcbm_conditionals'] ) ) {
						update_post_meta( $post_id, 'conditionals', self::sanitize_array( $_POST['wpcbm_conditionals'] ) );
					}
				}

				function time( $time_key = 0, $time_data = [] ) {
					if ( empty( $time_key ) || is_numeric( $time_key ) ) {
						$time_key = self::generate_key();
					}

					$type = ! empty( $time_data['type'] ) ? $time_data['type'] : 'every_day';
					$val  = ! empty( $time_data['val'] ) ? $time_data['val'] : '';
					$date = $date_time = $date_multi = $date_range = $from = $to = $time = $weekday = $monthday = $weekno = $monthno = $number = '';

					switch ( $type ) {
						case 'date_on':
						case 'date_before':
						case 'date_after':
							$date = $val;
							break;
						case 'date_time_before':
						case 'date_time_after':
							$date_time = $val;
							break;
						case 'date_multi':
							$date_multi = $val;
							break;
						case 'date_range':
							$date_range = $val;
							break;
						case 'time_range':
							$time_range = array_map( 'trim', explode( '-', (string) $val ) );
							$from       = ! empty( $time_range[0] ) ? $time_range[0] : '';
							$to         = ! empty( $time_range[1] ) ? $time_range[1] : '';
							break;
						case 'time_before':
						case 'time_after':
							$time = $val;
							break;
						case 'weekly_every':
							$weekday = $val;
							break;
						case 'week_no':
							$weekno = $val;
							break;
						case 'monthly_every':
							$monthday = $val;
							break;
						case 'month_no':
							$monthno = $val;
							break;
						default:
							$val = '';
					}
					?>
                    <div class="wpcbm_time">
                        <input type="hidden" class="wpcbm_time_val" name="wpcbm_timer[<?php echo esc_attr( $time_key ); ?>][val]" value="<?php echo esc_attr( $val ); ?>"/>
                        <span class="wpcbm_time_remove">&times;</span> <span>
							<label>
<select class="wpcbm_time_type" name="wpcbm_timer[<?php echo esc_attr( $time_key ); ?>][type]">
    <option value=""><?php esc_html_e( 'Choose the time', 'wpc-badge-management' ); ?></option>
    <option value="date_on" data-show="date" <?php selected( $type, 'date_on' ); ?>><?php esc_html_e( 'On the date', 'wpc-badge-management' ); ?></option>
<option value="date_time_before" data-show="date_time" <?php selected( $type, 'date_time_before' ); ?>><?php esc_html_e( 'Before date & time', 'wpc-badge-management' ); ?></option>
    <option value="date_time_after" data-show="date_time" <?php selected( $type, 'date_time_after' ); ?>><?php esc_html_e( 'After date & time', 'wpc-badge-management' ); ?></option>
    <option value="date_before" data-show="date" <?php selected( $type, 'date_before' ); ?>><?php esc_html_e( 'Before date', 'wpc-badge-management' ); ?></option>
    <option value="date_after" data-show="date" <?php selected( $type, 'date_after' ); ?>><?php esc_html_e( 'After date', 'wpc-badge-management' ); ?></option>
    <option value="date_multi" data-show="date_multi" <?php selected( $type, 'date_multi' ); ?>><?php esc_html_e( 'Multiple dates', 'wpc-badge-management' ); ?></option>
    <option value="date_range" data-show="date_range" <?php selected( $type, 'date_range' ); ?>><?php esc_html_e( 'Date range', 'wpc-badge-management' ); ?></option>
    <option value="date_even" data-show="none" <?php selected( $type, 'date_even' ); ?>><?php esc_html_e( 'All even dates', 'wpc-badge-management' ); ?></option>
    <option value="date_odd" data-show="none" <?php selected( $type, 'date_odd' ); ?>><?php esc_html_e( 'All odd dates', 'wpc-badge-management' ); ?></option>
    <option value="time_range" data-show="time_range" <?php selected( $type, 'time_range' ); ?>><?php esc_html_e( 'Daily time range', 'wpc-badge-management' ); ?></option>
<option value="time_before" data-show="time" <?php selected( $type, 'time_before' ); ?>><?php esc_html_e( 'Daily before time', 'wpc-badge-management' ); ?></option>
    <option value="time_after" data-show="time" <?php selected( $type, 'time_after' ); ?>><?php esc_html_e( 'Daily after time', 'wpc-badge-management' ); ?></option>
<option value="weekly_every" data-show="weekday" <?php selected( $type, 'weekly_every' ); ?>><?php esc_html_e( 'Weekly on every', 'wpc-badge-management' ); ?></option>
<option value="week_even" data-show="none" <?php selected( $type, 'week_even' ); ?>><?php esc_html_e( 'All even weeks', 'wpc-badge-management' ); ?></option>
    <option value="week_odd" data-show="none" <?php selected( $type, 'week_odd' ); ?>><?php esc_html_e( 'All odd weeks', 'wpc-badge-management' ); ?></option>
<option value="week_no" data-show="weekno" <?php selected( $type, 'week_no' ); ?>><?php esc_html_e( 'On week No.', 'wpc-badge-management' ); ?></option>
<option value="monthly_every" data-show="monthday" <?php selected( $type, 'monthly_every' ); ?>><?php esc_html_e( 'Monthly on the', 'wpc-badge-management' ); ?></option>
<option value="month_no" data-show="monthno" <?php selected( $type, 'month_no' ); ?>><?php esc_html_e( 'On month No.', 'wpc-badge-management' ); ?></option>
<option value="every_day" data-show="none" <?php selected( $type, 'every_day' ); ?>><?php esc_html_e( 'Everyday', 'wpc-badge-management' ); ?></option>
</select>
</label>
						</span> <span class="wpcbm_hide wpcbm_show_if_date_time">
							<label>
<input value="<?php echo esc_attr( $date_time ); ?>" class="wpcbm_dpk_date_time wpcbm_date_time_input" type="text" readonly="readonly" style="width: 300px"/>
</label>
						</span> <span class="wpcbm_hide wpcbm_show_if_date">
							<label>
<input value="<?php echo esc_attr( $date ); ?>" class="wpcbm_dpk_date wpcbm_date_input" type="text" readonly="readonly" style="width: 300px"/>
</label>
						</span> <span class="wpcbm_hide wpcbm_show_if_date_range">
							<label>
<input value="<?php echo esc_attr( $date_range ); ?>" class="wpcbm_dpk_date_range wpcbm_date_input" type="text" readonly="readonly" style="width: 300px"/>
</label>
						</span> <span class="wpcbm_hide wpcbm_show_if_date_multi">
							<label>
<input value="<?php echo esc_attr( $date_multi ); ?>" class="wpcbm_dpk_date_multi wpcbm_date_input" type="text" readonly="readonly" style="width: 300px"/>
</label>
						</span> <span class="wpcbm_hide wpcbm_show_if_time_range">
							<label>
<input value="<?php echo esc_attr( $from ); ?>" class="wpcbm_dpk_time wpcbm_time_from wpcbm_time_input" type="text" readonly="readonly" style="width: 300px" placeholder="from"/>
</label>
							<label>
<input value="<?php echo esc_attr( $to ); ?>" class="wpcbm_dpk_time wpcbm_time_to wpcbm_time_input" type="text" readonly="readonly" style="width: 300px" placeholder="to"/>
</label>
						</span> <span class="wpcbm_hide wpcbm_show_if_time">
							<label>
<input value="<?php echo esc_attr( $time ); ?>" class="wpcbm_dpk_time wpcbm_time_on wpcbm_time_input" type="text" readonly="readonly" style="width: 300px"/>
</label>
						</span> <span class="wpcbm_hide wpcbm_show_if_weekday">
							<label>
<select class="wpcbm_weekday">
<option value="mon" <?php selected( $weekday, 'mon' ); ?>><?php esc_html_e( 'Monday', 'wpc-badge-management' ); ?></option>
<option value="tue" <?php selected( $weekday, 'tue' ); ?>><?php esc_html_e( 'Tuesday', 'wpc-badge-management' ); ?></option>
<option value="wed" <?php selected( $weekday, 'wed' ); ?>><?php esc_html_e( 'Wednesday', 'wpc-badge-management' ); ?></option>
<option value="thu" <?php selected( $weekday, 'thu' ); ?>><?php esc_html_e( 'Thursday', 'wpc-badge-management' ); ?></option>
<option value="fri" <?php selected( $weekday, 'fri' ); ?>><?php esc_html_e( 'Friday', 'wpc-badge-management' ); ?></option>
<option value="sat" <?php selected( $weekday, 'sat' ); ?>><?php esc_html_e( 'Saturday', 'wpc-badge-management' ); ?></option>
<option value="sun" <?php selected( $weekday, 'sun' ); ?>><?php esc_html_e( 'Sunday', 'wpc-badge-management' ); ?></option>
</select>
</label>
						</span> <span class="wpcbm_hide wpcbm_show_if_monthday">
							<label>
<select class="wpcbm_monthday">
<?php for ( $i = 1; $i < 32; $i ++ ) {
	echo '<option value="' . esc_attr( $i ) . '" ' . ( (int) $monthday === $i ? 'selected' : '' ) . '>' . esc_html( $i ) . '</option>';
} ?>
</select>
</label>
						</span> <span class="wpcbm_hide wpcbm_show_if_weekno">
							<label>
<select class="wpcbm_weekno">
<?php
for ( $i = 1; $i < 54; $i ++ ) {
	echo '<option value="' . esc_attr( $i ) . '" ' . ( (int) $weekno === $i ? 'selected' : '' ) . '>' . esc_html( $i ) . '</option>';
}
?>
</select>
</label>
						</span> <span class="wpcbm_hide wpcbm_show_if_monthno">
							<label>
<select class="wpcbm_monthno">
<?php
for ( $i = 1; $i < 13; $i ++ ) {
	echo '<option value="' . esc_attr( $i ) . '" ' . ( (int) $monthno === $i ? 'selected' : '' ) . '>' . esc_html( $i ) . '</option>';
}
?>
</select>
</label>
						</span> <span class="wpcbm_hide wpcbm_show_if_number">
							<label>
<input type="number" step="1" min="0" class="wpcbm_number" value="<?php echo esc_attr( (int) $number ); ?>"/>
</label>
						</span>
                    </div>
					<?php
					return;
				}

				function ajax_add_time() {
					if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'wpcbm-security' ) ) {
						die( 'Permissions check failed!' );
					}

					self::time();
					wp_die();
				}

				function sanitize_array( $arr ) {
					foreach ( (array) $arr as $k => $v ) {
						if ( is_array( $v ) ) {
							$arr[ $k ] = self::sanitize_array( $v );
						} else {
							$arr[ $k ] = sanitize_post_field( 'post_content', $v, 0, 'db' );
						}
					}

					return $arr;
				}

				function badge_preview( $post ) {
					$post_id          = $post->ID;
					$position         = ! empty( get_post_meta( $post_id, 'position', true ) ) ? get_post_meta( $post_id, 'position', true ) : 'top-left';
					$style            = ! empty( get_post_meta( $post_id, 'style', true ) ) ? get_post_meta( $post_id, 'style', true ) : 'image';
					$text             = ! empty( get_post_meta( $post_id, 'text', true ) ) ? get_post_meta( $post_id, 'text', true ) : 'Hot';
					$tooltip          = ! empty( get_post_meta( $post_id, 'tooltip', true ) ) ? get_post_meta( $post_id, 'tooltip', true ) : '';
					$tooltip_position = ! empty( get_post_meta( $post_id, 'tooltip_position', true ) ) ? get_post_meta( $post_id, 'tooltip_position', true ) : 'top';
					$background_color = ! empty( get_post_meta( $post_id, 'background_color', true ) ) ? get_post_meta( $post_id, 'background_color', true ) : '';
					$border_color     = ! empty( get_post_meta( $post_id, 'border_color', true ) ) ? get_post_meta( $post_id, 'border_color', true ) : '';
					$box_shadow       = ! empty( get_post_meta( $post_id, 'box_shadow', true ) ) ? get_post_meta( $post_id, 'box_shadow', true ) : '';
					$text_color       = ! empty( get_post_meta( $post_id, 'text_color', true ) ) ? get_post_meta( $post_id, 'text_color', true ) : '';
					$image            = ! empty( get_post_meta( $post_id, 'image', true ) ) ? get_post_meta( $post_id, 'image', true ) : 0;
					$css              = '';

					if ( $style !== 'image' ) {
						if ( ! empty( $background_color ) ) {
							$css .= 'background-color: ' . sanitize_text_field( $background_color ) . ';';
						}

						if ( ! empty( $border_color ) ) {
							$css .= 'border-color: ' . sanitize_text_field( $border_color ) . ';';
						}

						if ( ! empty( $text_color ) ) {
							$css .= 'color: ' . sanitize_text_field( $text_color ) . ';';
						}

						if ( ! empty( $box_shadow ) ) {
							$css .= 'box-shadow: 4px 4px ' . sanitize_text_field( $box_shadow ) . ';';
						}
					}
					?>
                    <div id="wpcbm-preview">
                        <div class="wpcbm-wrapper">
                            <div class="wpcbm-badges wpcbm-badges-<?php echo esc_attr( $position ); ?>">
                                <div style="<?php echo esc_attr( $style !== 'image' ? esc_attr( $css ) : '' ); ?>" class="wpcbm-badge wpcbm-badge-style-<?php echo esc_attr( $style ); ?> hint--<?php echo esc_attr( $tooltip_position ); ?>" aria-label="<?php echo esc_attr( $tooltip ); ?>">
                                    <div class="wpcbm-badge-inner">
										<?php
										if ( $style !== 'image' ) {
											echo html_entity_decode( preg_replace( '/\[(.+?)\]/i', '[#]', $text ) );
										} else {
											echo wp_get_attachment_image( $image, 'full' );
										}
										?>
                                    </div>
                                </div>
                            </div>
                            <img src="<?php echo esc_url( WPCBM_URI . 'assets/images/product.jpg' ); ?>" alt=""/>
                        </div>
                    </div>
					<?php
				}

				private function inline_css() {
					$css = '';

					foreach ( $this->badges as $badge ) {
						if ( $badge['style'] === 'image' ) {
							continue;
						}

						$color            = apply_filters( 'wpcbm_badge_color', ! empty( $badge['text_color'] ) ? 'color: ' . $badge['text_color'] . ';' : '', $badge );
						$background_color = apply_filters( 'wpcbm_badge_background_color', ! empty( $badge['background_color'] ) ? 'background-color: ' . $badge['background_color'] . ';' : '', $badge );
						$border_color     = apply_filters( 'wpcbm_badge_border_color', ! empty( $badge['border_color'] ) ? 'border-color: ' . $badge['border_color'] . ';' : '', $badge );
						$box_shadow       = apply_filters( 'wpcbm_badge_box_shadow', ! empty( $badge['box_shadow'] ) ? 'box-shadow: 4px 4px ' . $badge['box_shadow'] . ';' : '', $badge );
						$css              .= apply_filters( 'wpcbm_badge_css', '.wpcbm-badge-' . $badge['id'] . '{' . $color . ' ' . $background_color . ' ' . $border_color . ' ' . $box_shadow . '}', $badge );
					}

					return apply_filters( 'wpcbm_inline_css', $css );
				}

				function admin_enqueue_scripts( $hook ) {
					if ( apply_filters( 'wpcbm_ignore_backend_scripts', false, $hook ) ) {
						return null;
					}

					// wpcdpk
					wp_enqueue_style( 'wpcdpk', WPCBM_URI . 'assets/libs/wpcdpk/css/datepicker.css' );
					wp_enqueue_script( 'wpcdpk', WPCBM_URI . 'assets/libs/wpcdpk/js/datepicker.js', [ 'jquery' ], WPCBM_VERSION, true );

					// icon
					$icon_libs = (array) self::get_setting( 'icon_libs', [] );

					if ( in_array( 'fontawesome', $icon_libs ) ) {
						wp_enqueue_style( 'fontawesome', WPCBM_URI . 'assets/libs/fontawesome/css/all.css' );
					}

					if ( in_array( 'feathericon', $icon_libs ) ) {
						wp_enqueue_style( 'feathericon', WPCBM_URI . 'assets/libs/feathericon/css/feathericon.css' );
					}

					if ( in_array( 'ionicons', $icon_libs ) ) {
						wp_enqueue_style( 'ionicons', WPCBM_URI . 'assets/libs/ionicons/css/ionicons.css' );
					}

					wp_enqueue_style( 'hint', WPCBM_URI . 'assets/css/hint.css' );

					wp_enqueue_style( 'wp-color-picker' );
					wp_register_script( 'wp-color-picker-alpha', WPCBM_URI . 'assets/js/wp-color-picker-alpha.min.js', [ 'wp-color-picker' ], WPCBM_VERSION );

					wp_enqueue_style( 'wpcbm-style', WPCBM_URI . 'assets/css/style.css', [], WPCBM_VERSION );
					wp_add_inline_style( 'wpcbm-style', $this->inline_css() );

					wp_enqueue_style( 'wpcbm-backend', WPCBM_URI . 'assets/css/backend.css', [ 'woocommerce_admin_styles' ], WPCBM_VERSION );
					wp_enqueue_script( 'wpcbm-backend', WPCBM_URI . 'assets/js/backend.js', [
						'jquery',
						'jquery-ui-dialog',
						'wp-color-picker',
						'wp-color-picker-alpha',
						'wc-enhanced-select',
						'selectWoo',
					], WPCBM_VERSION, true );
					wp_localize_script( 'wpcbm-backend', 'wpcbm_vars', [
							'nonce' => wp_create_nonce( 'wpcbm-security' )
						]
					);
				}

				function enqueue_scripts() {
					// icon
					$icon_libs = (array) self::get_setting( 'icon_libs', [] );

					if ( in_array( 'fontawesome', $icon_libs ) ) {
						wp_enqueue_style( 'fontawesome', WPCBM_URI . 'assets/libs/fontawesome/css/all.css' );
					}

					if ( in_array( 'feathericon', $icon_libs ) ) {
						wp_enqueue_style( 'feathericon', WPCBM_URI . 'assets/libs/feathericon/css/feathericon.css' );
					}

					if ( in_array( 'ionicons', $icon_libs ) ) {
						wp_enqueue_style( 'ionicons', WPCBM_URI . 'assets/libs/ionicons/css/ionicons.css' );
					}

					wp_enqueue_style( 'hint', WPCBM_URI . 'assets/css/hint.css' );
					wp_enqueue_style( 'wpcbm-frontend', WPCBM_URI . 'assets/css/frontend.css', [], WPCBM_VERSION );
					wp_enqueue_style( 'wpcbm-style', WPCBM_URI . 'assets/css/style.css', [], WPCBM_VERSION );
					wp_add_inline_style( 'wpcbm-style', $this->inline_css() );
				}

				function action_links( $links, $file ) {
					static $plugin;

					if ( ! isset( $plugin ) ) {
						$plugin = plugin_basename( __FILE__ );
					}

					if ( $plugin === $file ) {
						$settings             = '<a href="' . esc_url( admin_url( 'admin.php?page=wpclever-wpcbm&tab=settings' ) ) . '">' . esc_html__( 'Settings', 'wpc-badge-management' ) . '</a>';
						$links['wpc-premium'] = '<a href="' . esc_url( admin_url( 'admin.php?page=wpclever-wpcbm&tab=premium' ) ) . '" style="color: #c9356e">' . esc_html__( 'Premium Version', 'wpc-badge-management' ) . '</a>';
						array_unshift( $links, $settings );
					}

					return (array) $links;
				}

				function row_meta( $links, $file ) {
					static $plugin;

					if ( ! isset( $plugin ) ) {
						$plugin = plugin_basename( __FILE__ );
					}

					if ( $plugin === $file ) {
						$row_meta = [
							'support' => '<a href="' . esc_url( WPCBM_DISCUSSION ) . '" target="_blank">' . esc_html__( 'Community support', 'wpc-badge-management' ) . '</a>',
						];

						return array_merge( $links, $row_meta );
					}

					return (array) $links;
				}

				function register_settings() {
					// settings
					register_setting( 'wpcbm_settings', 'wpcbm_settings' );
				}

				function admin_menu() {
					add_submenu_page( 'wpclever', esc_html__( 'WPC Badge Management', 'wpc-badge-management' ), esc_html__( 'Badge Management', 'wpc-badge-management' ), 'manage_options', 'wpclever-wpcbm', [
						$this,
						'admin_menu_content'
					] );
				}

				function admin_menu_content() {
					add_thickbox();
					$active_tab = sanitize_key( $_GET['tab'] ?? 'settings' );
					?>
                    <div class="wpclever_settings_page wrap">
                        <h1 class="wpclever_settings_page_title"><?php echo esc_html__( 'WPC Badge Management', 'wpc-badge-management' ) . ' ' . esc_html( WPCBM_VERSION ) . ' ' . ( defined( 'WPCBM_PREMIUM' ) ? '<span class="premium" style="display: none">' . esc_html__( 'Premium', 'wpc-badge-management' ) . '</span>' : '' ); ?></h1>
                        <div class="wpclever_settings_page_desc about-text">
                            <p>
								<?php printf( /* translators: stars */ esc_html__( 'Thank you for using our plugin! If you are satisfied, please reward it a full five-star %s rating.', 'wpc-badge-management' ), '<span style="color:#ffb900">&#9733;&#9733;&#9733;&#9733;&#9733;</span>' ); ?>
                                <br/>
                                <a href="<?php echo esc_url( WPCBM_REVIEWS ); ?>" target="_blank"><?php esc_html_e( 'Reviews', 'wpc-badge-management' ); ?></a> |
                                <a href="<?php echo esc_url( WPCBM_CHANGELOG ); ?>" target="_blank"><?php esc_html_e( 'Changelog', 'wpc-badge-management' ); ?></a> |
                                <a href="<?php echo esc_url( WPCBM_DISCUSSION ); ?>" target="_blank"><?php esc_html_e( 'Discussion', 'wpc-badge-management' ); ?></a>
                            </p>
                        </div>
						<?php if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] ) { ?>
                            <div class="notice notice-success is-dismissible">
                                <p><?php esc_html_e( 'Settings updated.', 'wpc-badge-management' ); ?></p>
                            </div>
						<?php } ?>
                        <div class="wpclever_settings_page_nav">
                            <h2 class="nav-tab-wrapper">
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-wpcbm&tab=how' ) ); ?>" class="<?php echo esc_attr( $active_tab === 'how' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>">
									<?php esc_html_e( 'How to use?', 'wpc-badge-management' ); ?>
                                </a>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-wpcbm&tab=settings' ) ); ?>" class="<?php echo $active_tab === 'settings' ? 'nav-tab nav-tab-active' : 'nav-tab'; ?>">
									<?php esc_html_e( 'Settings', 'wpc-badge-management' ); ?>
                                </a>
                                <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wpc_product_badge' ) ); ?>" class="nav-tab">
									<?php esc_html_e( 'Global Badges', 'wpc-badge-management' ); ?>
                                </a>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-wpcbm&tab=premium' ) ); ?>" class="<?php echo $active_tab === 'premium' ? 'nav-tab nav-tab-active' : 'nav-tab'; ?>" style="color: #c9356e">
									<?php esc_html_e( 'Premium Version', 'wpc-badge-management' ); ?>
                                </a>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-kit' ) ); ?>" class="nav-tab">
									<?php esc_html_e( 'Essential Kit', 'wpc-badge-management' ); ?>
                                </a>
                            </h2>
                        </div>
                        <div class="wpclever_settings_page_content">
							<?php if ( $active_tab === 'how' ) { ?>
                                <div class="wpclever_settings_page_content_text">
                                    <p>
										<?php esc_html_e( '1. Global badges: Switch to the Global Badges tab to add the badge and also choose which products that you want to apply this badge.', 'wpc-badge-management' ); ?>
                                    </p>
                                    <p>
										<?php esc_html_e( '2. Badges at a product basis: When adding/editing the product you can choose the Badges tab then add some badges as you want.', 'wpc-badge-management' ); ?>
                                    </p>
                                </div>
							<?php } elseif ( $active_tab === 'settings' ) {
								$icon_libs          = (array) self::get_setting( 'icon_libs', [] );
								$position_archive   = self::get_setting( 'position_archive', 'image' );
								$position_single    = self::get_setting( 'position_single', '4' );
								$position_quickview = self::get_setting( 'position_quickview', 'image' );
								?>
                                <form method="post" action="options.php">
                                    <table class="form-table">
                                        <tr>
                                            <th>
												<?php esc_html_e( 'Icon Libraries', 'wpc-badge-management' ); ?>
                                            </th>
                                            <td>
                                                <ul>
                                                    <li>
                                                        <label>
                                                            <input type="checkbox" name="wpcbm_settings[icon_libs][]" value="fontawesome" <?php echo esc_attr( in_array( 'fontawesome', $icon_libs ) ? 'checked' : '' ); ?>/>
                                                        </label>
                                                        <a href="https://fontawesome.com/v5/search" target="_blank"><?php esc_html_e( 'FontAwesome', 'wpc-badge-management' ); ?></a> Example:
                                                        <code>&lt;i class=&quot;fas fa-check&quot;&gt;&lt;/i&gt;</code>
                                                    </li>
                                                    <li>
                                                        <label>
                                                            <input type="checkbox" name="wpcbm_settings[icon_libs][]" value="feathericon" <?php echo esc_attr( in_array( 'feathericon', $icon_libs ) ? 'checked' : '' ); ?>/>
                                                        </label>
                                                        <a href="https://feathericons.com/" target="_blank"><?php esc_html_e( 'Feather', 'wpc-badge-management' ); ?></a> Example:
                                                        <code>&lt;i class=&quot;fe fe-activity&quot;&gt;&lt;/i&gt;</code>
                                                    </li>
                                                    <li>
                                                        <label>
                                                            <input type="checkbox" name="wpcbm_settings[icon_libs][]" value="ionicons" <?php echo esc_attr( in_array( 'ionicons', $icon_libs ) ? 'checked' : '' ); ?>/>
                                                        </label>
                                                        <a href="https://ionic.io/ionicons/v2" target="_blank"><?php esc_html_e( 'Ionicons', 'wpc-badge-management' ); ?></a> Example:
                                                        <code>&lt;i class=&quot;ion-gear-b&quot;&gt;&lt;/i&gt;</code>
                                                    </li>
                                                </ul>
                                                <p class="description"><?php esc_html_e( 'Only enable which icon library that you\'re using for better performance.', 'wpc-badge-management' ); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Position on archive page', 'wpc-badge-management' ); ?></th>
                                            <td>
												<?php
												$positions_archive = [
													'image'              => esc_html__( 'On image', 'wpc-badge-management' ),
													'before_image'       => esc_html__( 'Above image', 'wpc-badge-management' ),
													'before_title'       => esc_html__( 'Above title', 'wpc-badge-management' ),
													'after_title'        => esc_html__( 'Under title', 'wpc-badge-management' ),
													'after_rating'       => esc_html__( 'Under rating', 'wpc-badge-management' ),
													'after_price'        => esc_html__( 'Under price', 'wpc-badge-management' ),
													'before_add_to_cart' => esc_html__( 'Above add to cart button', 'wpc-badge-management' ),
													'after_add_to_cart'  => esc_html__( 'Under add to cart button', 'wpc-badge-management' ),
													'none'               => esc_html__( 'None (hide it)', 'wpc-badge-management' ),
												];
												?>
                                                <label> <select name="wpcbm_settings[position_archive]">
														<?php
														foreach ( $positions_archive as $k => $p ) {
															echo '<option value="' . esc_attr( $k ) . '" ' . selected( $position_archive, $k, false ) . '>' . esc_html( $p ) . '</option>';
														}
														?>
                                                    </select> </label>
                                                <span class="description"><?php esc_html_e( 'Choose the position to show the badges on the archive page.', 'wpc-badge-management' ); ?></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Position on product page', 'wpc-badge-management' ); ?></th>
                                            <td>
												<?php
												$positions_single = [
													'before_title'       => esc_html__( 'Above title', 'wpc-badge-management' ),
													'after_title'        => esc_html__( 'Under title', 'wpc-badge-management' ),
													'after_rating'       => esc_html__( 'Under rating', 'wpc-badge-management' ),
													'after_excerpt'      => esc_html__( 'Under excerpt', 'wpc-badge-management' ),
													'before_add_to_cart' => esc_html__( 'Above add to cart button', 'wpc-badge-management' ),
													'after_add_to_cart'  => esc_html__( 'Under add to cart button', 'wpc-badge-management' ),
													'after_meta'         => esc_html__( 'Under meta', 'wpc-badge-management' ),
													'after_sharing'      => esc_html__( 'Under sharing', 'wpc-badge-management' ),
													'none'               => esc_html__( 'None (hide it)', 'wpc-badge-management' ),
												];
												?>
                                                <label> <select name="wpcbm_settings[position_single]">
														<?php
														foreach ( $positions_single as $k => $p ) {
															echo '<option value="' . esc_attr( $k ) . '" ' . selected( $position_single, $k, false ) . '>' . esc_html( $p ) . '</option>';
														}
														?>
                                                    </select> </label>
                                                <span class="description"><?php esc_html_e( 'Choose the position to show the badges on the product page.', 'wpc-badge-management' ); ?></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Position on quick view', 'wpc-badge-management' ); ?></th>
                                            <td>
												<?php
												$positions_quickview = [
													'image'              => esc_html__( 'On image', 'wpc-badge-management' ),
													'before_title'       => esc_html__( 'Above title', 'wpc-badge-management' ),
													'after_title'        => esc_html__( 'Under title', 'wpc-badge-management' ),
													'before_rating'      => esc_html__( 'Above rating', 'wpc-badge-management' ),
													'after_rating'       => esc_html__( 'Under rating', 'wpc-badge-management' ),
													'before_price'       => esc_html__( 'Above price', 'wpc-badge-management' ),
													'after_price'        => esc_html__( 'Under price', 'wpc-badge-management' ),
													'before_excerpt'     => esc_html__( 'Above excerpt', 'wpc-badge-management' ),
													'after_excerpt'      => esc_html__( 'Under excerpt', 'wpc-badge-management' ),
													'before_meta'        => esc_html__( 'Above meta', 'wpc-badge-management' ),
													'after_meta'         => esc_html__( 'Under meta', 'wpc-badge-management' ),
													'before_add_to_cart' => esc_html__( 'Above add to cart button', 'wpc-badge-management' ),
													'after_add_to_cart'  => esc_html__( 'Under add to cart button', 'wpc-badge-management' ),
													'none'               => esc_html__( 'None (hide it)', 'wpc-badge-management' ),
												];
												?>
                                                <label> <select name="wpcbm_settings[position_quickview]">
														<?php
														foreach ( $positions_quickview as $k => $p ) {
															echo '<option value="' . esc_attr( $k ) . '" ' . selected( $position_quickview, $k, false ) . '>' . esc_html( $p ) . '</option>';
														}
														?>
                                                    </select> </label>
                                                <span class="description"><?php esc_html_e( 'Choose the position to show the badges on the quick view popup.', 'wpc-badge-management' ); ?> It works for <a href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=woo-smart-quick-view&TB_iframe=true&width=800&height=550' ) ); ?>" class="thickbox" title="WPC Smart Quick View">WPC Smart Quick View</a> only.</span>
                                            </td>
                                        </tr>
                                        <tr class="submit">
                                            <th colspan="2">
												<?php settings_fields( 'wpcbm_settings' ); ?><?php submit_button(); ?>
                                            </th>
                                        </tr>
                                    </table>
                                </form>
							<?php } elseif ( $active_tab === 'premium' ) { ?>
                                <div class="wpclever_settings_page_content_text">
                                    <p>Get the Premium Version just $29!
                                        <a href="https://wpclever.net/downloads/wpc-badge-management?utm_source=pro&utm_medium=wpcbm&utm_campaign=wporg" target="_blank">https://wpclever.net/downloads/wpc-badge-management</a>
                                    </p>
                                    <p><strong>Extra features for Premium Version:</strong></p>
                                    <ul style="margin-bottom: 0">
                                        <li>- Using a combination of conditionals.</li>
                                        <li>- Manage badges at a product basis.</li>
                                        <li>- Get lifetime update & premium support.</li>
                                    </ul>
                                </div>
							<?php } ?>
                        </div><!-- /.wpclever_settings_page_content -->
                        <div class="wpclever_settings_page_suggestion">
                            <div class="wpclever_settings_page_suggestion_label">
                                <span class="dashicons dashicons-yes-alt"></span> Suggestion
                            </div>
                            <div class="wpclever_settings_page_suggestion_content">
                                <div>
                                    To display custom engaging real-time messages on any wished positions, please install
                                    <a href="https://wordpress.org/plugins/wpc-smart-messages/" target="_blank">WPC Smart Messages</a> plugin. It's free!
                                </div>
                                <div>
                                    Wanna save your precious time working on variations? Try our brand-new free plugin
                                    <a href="https://wordpress.org/plugins/wpc-variation-bulk-editor/" target="_blank">WPC Variation Bulk Editor</a> and
                                    <a href="https://wordpress.org/plugins/wpc-variation-duplicator/" target="_blank">WPC Variation Duplicator</a>.
                                </div>
                            </div>
                        </div>
                    </div>
					<?php
				}

				function product_data_tabs( $tabs ) {
					$tabs['wpcbm'] = [
						'label'  => esc_html__( 'Badges', 'wpc-badge-management' ),
						'target' => 'wpcbm_settings'
					];

					return $tabs;
				}

				function product_data_panels() {
					global $post, $thepostid, $product_object;

					if ( $product_object instanceof WC_Product ) {
						$product_id = $product_object->get_id();
					} elseif ( is_numeric( $thepostid ) ) {
						$product_id = $thepostid;
					} elseif ( $post instanceof WP_Post ) {
						$product_id = $post->ID;
					} else {
						$product_id = 0;
					}

					if ( ! $product_id ) {
						?>
                        <div id='wpcbm_settings' class='panel woocommerce_options_panel wpcbm_settings wpcbm_table'>
                            <p style="padding: 0 12px; color: #c9356e"><?php esc_html_e( 'Product wasn\'t returned.', 'wpc-badge-management' ); ?></p>
                        </div>
						<?php
						return;
					}

					$type   = get_post_meta( $product_id, 'wpcbm_type', true ) ?: 'default';
					$badges = get_post_meta( $product_id, 'wpcbm_badges', true );
					?>
                    <div id='wpcbm_settings' class='panel woocommerce_options_panel wpcbm_settings wpcbm_table'>
                        <div class="wpcbm_tr">
                            <div class="wpcbm_td"><?php esc_html_e( 'Type', 'wpc-badge-management' ); ?></div>
                            <div class="wpcbm_td">
                                <div class="wpcbm_active">
                                    <label>
                                        <input name="wpcbm_type" type="radio" value="default" <?php echo esc_attr( $type === 'default' ? 'checked' : '' ); ?>/>
										<?php esc_html_e( 'Default', 'wpc-badge-management' ); ?>
                                    </label> <label>
                                        <input name="wpcbm_type" type="radio" value="disable" <?php echo esc_attr( $type === 'disable' ? 'checked' : '' ); ?>/>
										<?php esc_html_e( 'Disable', 'wpc-badge-management' ); ?>
                                    </label> <label>
                                        <input name="wpcbm_type" type="radio" value="overwrite" <?php echo esc_attr( $type === 'overwrite' ? 'checked' : '' ); ?>/>
										<?php esc_html_e( 'Overwrite', 'wpc-badge-management' ); ?>
                                    </label> <label>
                                        <input name="wpcbm_type" type="radio" value="prepend" <?php echo esc_attr( $type === 'prepend' ? 'checked' : '' ); ?>/>
										<?php esc_html_e( 'Prepend', 'wpc-badge-management' ); ?>
                                    </label> <label>
                                        <input name="wpcbm_type" type="radio" value="append" <?php echo esc_attr( $type === 'append' ? 'checked' : '' ); ?>/>
										<?php esc_html_e( 'Append', 'wpc-badge-management' ); ?>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="wpcbm_show_if_overwrite">
                            <div class="wpcbm_tr">
                                <div class="wpcbm_td"><?php esc_html_e( 'Badges', 'wpc-badge-management' ); ?></div>
                                <div class="wpcbm_td">
                                    <div style="color: #c9356e;">
                                        Manage badges at a product basis only available on the Premium Version.
                                        <a href="https://wpclever.net/downloads/wpc-badge-management?utm_source=pro&utm_medium=wpcbm&utm_campaign=wporg" target="_blank">Click here</a> to buy, just $29!
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
					<?php
				}

				function product_save_fields( $post_id ) {
					if ( isset( $_POST['wpcbm_type'] ) ) {
						update_post_meta( $post_id, 'wpcbm_type', sanitize_text_field( $_POST['wpcbm_type'] ) );
					}

					if ( isset( $_POST['wpcbm_badges'] ) ) {
						update_post_meta( $post_id, 'wpcbm_badges', array_map( 'sanitize_text_field', $_POST['wpcbm_badges'] ) );
					}
				}

				function ajax_activate() {
					if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'wpcbm-security' ) || ! current_user_can( 'manage_options' ) ) {
						die( 'Permissions check failed!' );
					}

					if ( isset( $_POST['id'], $_POST['act'] ) ) {
						$id  = sanitize_text_field( $_POST['id'] );
						$act = sanitize_text_field( $_POST['act'] );

						update_post_meta( $id, 'wpcbm_activate', ( $act === 'activate' ? 'on' : 'off' ) );
						echo $act;
					}

					wp_die();
				}

				function ajax_add_conditional() {
					if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'wpcbm-security' ) ) {
						die( 'Permissions check failed!' );
					}
					self::conditional();
					wp_die();
				}

				function ajax_search_badges() {
					if ( ! isset( $_REQUEST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['nonce'] ), 'wpcbm-security' ) ) {
						die( 'Permissions check failed!' );
					}

					$return         = [];
					$search_results = new WP_Query( [
						'post_type'           => 'wpc_product_badge',
						's'                   => sanitize_text_field( $_REQUEST['q'] ?? '' ),
						'post_status'         => 'publish',
						'ignore_sticky_posts' => 1,
						'posts_per_page'      => 500
					] );

					if ( $search_results->have_posts() ) {
						while ( $search_results->have_posts() ) {
							$search_results->the_post();
							$return[] = [ $search_results->post->ID, $search_results->post->post_title ];
						}
					}

					wp_send_json( $return );
				}

				function ajax_search_term() {
					if ( ! isset( $_REQUEST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['nonce'] ), 'wpcbm-security' ) ) {
						die( 'Permissions check failed!' );
					}

					$return = [];
					$args   = [
						'taxonomy'   => sanitize_text_field( $_REQUEST['taxonomy'] ?? '' ),
						'orderby'    => 'id',
						'order'      => 'ASC',
						'hide_empty' => false,
						'fields'     => 'all',
						'name__like' => sanitize_text_field( $_REQUEST['q'] ?? '' ),
					];

					$terms = get_terms( $args );

					if ( count( $terms ) ) {
						foreach ( $terms as $term ) {
							$return[] = [ $term->slug, $term->name ];
						}
					}

					wp_send_json( $return );
				}

				function conditional( $key = '', $conditional = [] ) {
					if ( empty( $key ) || is_numeric( $key ) || ( strlen( $key ) > 4 ) ) {
						$key = self::generate_key();
					}

					$apply   = $conditional['apply'] ?? 'sale';
					$compare = $conditional['compare'] ?? 'is';
					$value   = $conditional['value'] ?? '';
					$select  = isset( $conditional['select'] ) ? (array) $conditional['select'] : [];
					?>
                    <div class="wpcbm_conditional">
                        <span class="wpcbm_conditional_remove"> &times; </span> <label>
                            <select class="wpcbm_conditional_apply" name="wpcbm_conditionals[<?php echo esc_attr( $key ); ?>][apply]">
                                <option value="sale" <?php selected( $apply, 'sale' ); ?>><?php esc_html_e( 'On sale', 'wpc-badge-management' ); ?></option>
                                <option value="featured" <?php selected( $apply, 'featured' ); ?>><?php esc_html_e( 'Featured', 'wpc-badge-management' ); ?></option>
                                <option value="bestselling" <?php selected( $apply, 'bestselling' ); ?>><?php esc_html_e( 'Best selling', 'wpc-badge-management' ); ?></option>
                                <option value="instock" <?php selected( $apply, 'instock' ); ?>><?php esc_html_e( 'In stock', 'wpc-badge-management' ); ?></option>
                                <option value="outofstock" <?php selected( $apply, 'outofstock' ); ?>><?php esc_html_e( 'Out of stock', 'wpc-badge-management' ); ?></option>
                                <option value="backorder" <?php selected( $apply, 'backorder' ); ?>><?php esc_html_e( 'On backorder', 'wpc-badge-management' ); ?></option>
                                <option value="price" <?php selected( $apply, 'price' ); ?>><?php esc_html_e( 'Price', 'wpc-badge-management' ); ?></option>
                                <option value="rating" <?php selected( $apply, 'rating' ); ?>><?php esc_html_e( 'Star rating', 'wpc-badge-management' ); ?></option>
                                <option value="stock" <?php selected( $apply, 'stock' ); ?>><?php esc_html_e( 'Stock quantity', 'wpc-badge-management' ); ?></option>
                                <option value="release" <?php selected( $apply, 'release' ); ?>><?php esc_html_e( 'New release (days)', 'wpc-badge-management' ); ?></option>
								<?php
								$taxonomies = get_object_taxonomies( 'product', 'objects' ); //$taxonomies = get_taxonomies( [ 'object_type' => [ 'product' ] ], 'objects' );

								foreach ( $taxonomies as $taxonomy ) {
									echo '<option value="' . esc_attr( $taxonomy->name ) . '" ' . ( $apply === $taxonomy->name ? 'selected' : '' ) . '>' . esc_html( $taxonomy->label ) . '</option>';
								}
								?>
                            </select> </label> <label>
                            <select class="wpcbm_conditional_compare" name="wpcbm_conditionals[<?php echo esc_attr( $key ); ?>][compare]">
                                <optgroup label="<?php esc_attr_e( 'Text', 'wpc-badge-management' ); ?>" class="wpcbm_conditional_compare_terms">
                                    <option value="is" <?php selected( $compare, 'is' ); ?>><?php esc_html_e( 'including', 'wpc-badge-management' ); ?></option>
                                    <option value="is_not" <?php selected( $compare, 'is_not' ); ?>><?php esc_html_e( 'excluding', 'wpc-badge-management' ); ?></option>
                                </optgroup>
                                <optgroup label="<?php esc_attr_e( 'Number', 'wpc-badge-management' ); ?>" class="wpcbm_conditional_compare_price">
                                    <option value="equal" <?php selected( $compare, 'equal' ); ?>><?php esc_html_e( 'equal to', 'wpc-badge-management' ); ?></option>
                                    <option value="not_equal" <?php selected( $compare, 'not_equal' ); ?>><?php esc_html_e( 'not equal to', 'wpc-badge-management' ); ?></option>
                                    <option value="greater" <?php selected( $compare, 'greater' ); ?>><?php esc_html_e( 'greater than', 'wpc-badge-management' ); ?></option>
                                    <option value="less" <?php selected( $compare, 'less' ); ?>><?php esc_html_e( 'less than', 'wpc-badge-management' ); ?></option>
                                    <option value="greater_equal" <?php selected( $compare, 'greater_equal' ); ?>><?php esc_html_e( 'greater or equal to', 'wpc-badge-management' ); ?></option>
                                    <option value="less_equal" <?php selected( $compare, 'less_equal' ); ?>><?php esc_html_e( 'less or equal to', 'wpc-badge-management' ); ?></option>
                                </optgroup>
                            </select> </label> <label>
                            <input type="number" class="wpcbm_conditional_value" min="0" step="0.0001" data-<?php echo esc_attr( $apply ); ?>="<?php echo esc_attr( $value ); ?>" name="wpcbm_conditionals[<?php echo esc_attr( $key ); ?>][value]" value="<?php echo esc_attr( $value ); ?>"/>
                        </label> <span class="wpcbm_conditional_select_wrap">
                            <label>
<select class="wpcbm_conditional_select" data-<?php echo esc_attr( $apply ); ?>="<?php echo esc_attr( implode( ',', $select ) ); ?>" name="wpcbm_conditionals[<?php echo esc_attr( $key ); ?>][select][]" multiple="multiple">
    <?php
    if ( count( $select ) ) {
	    foreach ( $select as $t ) {
		    if ( $term = get_term_by( 'slug', $t, $apply ) ) {
			    echo '<option value="' . esc_attr( $t ) . '" selected>' . esc_html( $term->name ) . '</option>';
		    }
	    }
    }
    ?>
</select>
</label>
                        </span>
                    </div>
					<?php
				}

				function columns( $columns ) {
					return [
						'cb'            => $columns['cb'],
						'activate'      => esc_html__( 'Activate', 'wpc-badge-management' ),
						'title'         => esc_html__( 'Title', 'wpc-badge-management' ),
						'wpcbm_preview' => esc_html__( 'Preview', 'wpc-badge-management' ),
						'wpcbm_desc'    => esc_html__( 'Description', 'wpc-badge-management' ),
						'wpcbm_group'   => esc_html__( 'Group', 'wpc-badge-management' ),
						'wpcbm_order'   => esc_html__( 'Order', 'wpc-badge-management' ),
						'date'          => esc_html__( 'Date', 'wpc-badge-management' ),
					];
				}

				function columns_content( $column, $post_id ) {
					if ( $column === 'activate' ) {
						if ( get_post_meta( $post_id, 'wpcbm_activate', true ) === 'off' ) {
							echo '<a href="#" class="wpcbm-activate-btn activate button" data-id="' . esc_attr( $post_id ) . '"></a>';
						} else {
							echo '<a href="#" class="wpcbm-activate-btn deactivate button button-primary" data-id="' . esc_attr( $post_id ) . '"></a>';
						}
					}

					if ( $column === 'wpcbm_preview' ) {
						$badge = $this->badge_data( $post_id );
						$this->render_badge( $badge, true );
					}

					if ( $column === 'wpcbm_desc' ) {
						echo esc_html( get_the_excerpt( $post_id ) );
					}

					if ( $column === 'wpcbm_group' ) {
						if ( $groups = get_the_terms( $post_id, 'wpc-badge-group' ) ) {
							$edit_link = get_edit_term_link( $groups[0]->term_id, 'wpc-badge-group' );
							echo '<a href="' . esc_url( $edit_link ) . '">' . esc_html( $groups[0]->name ) . '</a>';
						}
					}

					if ( $column === 'wpcbm_order' ) {
						echo esc_attr( get_post_meta( $post_id, 'order', true ) ?: 1 );
					}
				}

				function sortable_columns( $columns ) {
					$columns['wpcbm_order'] = 'wpcbm_order';

					return $columns;
				}

				function request( $vars ) {
					if ( isset( $vars['orderby'] ) && 'wpcbm_order' == $vars['orderby'] ) {
						$vars = array_merge( $vars, [
							'meta_key' => 'order',
							'orderby'  => 'meta_value_num'
						] );
					}

					return $vars;
				}

				function dropdown_cats_multiple( $output, $r ) {
					if ( isset( $r['multiple'] ) && $r['multiple'] ) {
						$output = preg_replace( '/^<select/i', '<select multiple', $output );
						$output = str_replace( "name='{$r['name']}'", "name='{$r['name']}[]'", $output );

						foreach ( array_map( 'trim', explode( ',', $r['selected'] ) ) as $value ) {
							$output = str_replace( "value=\"{$value}\"", "value=\"{$value}\" selected", $output );
						}
					}

					return $output;
				}

				function check_roles( $badge ) {
					$roles = $badge['roles'] ?? [];

					if ( empty( $roles ) || ! is_array( $roles ) || in_array( 'wpcbm_all', $roles ) ) {
						return true;
					}

					if ( is_user_logged_in() ) {
						if ( in_array( 'wpcbm_user', $roles ) ) {
							return true;
						}

						$current_user = wp_get_current_user();

						foreach ( $current_user->roles as $role ) {
							if ( in_array( $role, $roles ) ) {
								return true;
							}
						}
					} else {
						if ( in_array( 'wpcbm_guest', $roles ) ) {
							return true;
						}
					}

					return false;
				}

				function check_timer( $badge ) {
					$timer = $badge['timer'] ?? [];
					$check = true;

					if ( is_array( $timer ) && ! empty( $timer ) ) {
						foreach ( $timer as $time ) {
							$check_item = false;
							$time_type  = isset( $time['type'] ) ? trim( $time['type'] ) : '';
							$time_value = isset( $time['val'] ) ? trim( $time['val'] ) : '';

							switch ( $time_type ) {
								case 'date_range':
									$date_range = array_map( 'trim', explode( '-', $time_value ) );

									if ( count( $date_range ) === 2 ) {
										$date_range_start = trim( $date_range[0] );
										$date_range_end   = trim( $date_range[1] );
										$current_date     = strtotime( current_time( 'm/d/Y' ) );

										if ( $current_date >= strtotime( $date_range_start ) && $current_date <= strtotime( $date_range_end ) ) {
											$check_item = true;
										}
									} elseif ( count( $date_range ) === 1 ) {
										$date_range_start = trim( $date_range[0] );

										if ( strtotime( current_time( 'm/d/Y' ) ) === strtotime( $date_range_start ) ) {
											$check_item = true;
										}
									}

									break;
								case 'date_multi':
									$multiple_dates_arr = array_map( 'trim', explode( ', ', $time_value ) );

									if ( in_array( current_time( 'm/d/Y' ), $multiple_dates_arr ) ) {
										$check_item = true;
									}

									break;
								case 'date_even':
									if ( (int) current_time( 'd' ) % 2 === 0 ) {
										$check_item = true;
									}

									break;
								case 'date_odd':
									if ( (int) current_time( 'd' ) % 2 !== 0 ) {
										$check_item = true;
									}

									break;
								case 'date_on':
									if ( strtotime( current_time( 'm/d/Y' ) ) === strtotime( $time_value ) ) {
										$check_item = true;
									}

									break;
								case 'date_before':
									if ( strtotime( current_time( 'm/d/Y' ) ) < strtotime( $time_value ) ) {
										$check_item = true;
									}

									break;
								case 'date_after':
									if ( strtotime( current_time( 'm/d/Y' ) ) > strtotime( $time_value ) ) {
										$check_item = true;
									}

									break;
								case 'date_time_before':
									$current_time = current_time( 'm/d/Y h:i a' );

									if ( strtotime( $current_time ) < strtotime( $time_value ) ) {
										$check_item = true;
									}

									break;
								case 'date_time_after':
									$current_time = current_time( 'm/d/Y h:i a' );

									if ( strtotime( $current_time ) > strtotime( $time_value ) ) {
										$check_item = true;
									}

									break;
								case 'time_range':
									$time_range = array_map( 'trim', explode( '-', $time_value ) );

									if ( count( $time_range ) === 2 ) {
										$current_time     = strtotime( current_time( 'm/d/Y h:i a' ) );
										$current_date     = current_time( 'm/d/Y' );
										$time_range_start = $current_date . ' ' . $time_range[0];
										$time_range_end   = $current_date . ' ' . $time_range[1];

										if ( $current_time >= strtotime( $time_range_start ) && $current_time <= strtotime( $time_range_end ) ) {
											$check_item = true;
										}
									}

									break;
								case 'time_before':
									$current_time = current_time( 'm/d/Y h:i a' );
									$current_date = current_time( 'm/d/Y' );

									if ( strtotime( $current_time ) < strtotime( $current_date . ' ' . $time_value ) ) {
										$check_item = true;
									}

									break;
								case 'time_after':
									$current_time = current_time( 'm/d/Y h:i a' );
									$current_date = current_time( 'm/d/Y' );

									if ( strtotime( $current_time ) > strtotime( $current_date . ' ' . $time_value ) ) {
										$check_item = true;
									}

									break;
								case 'weekly_every':
									if ( strtolower( current_time( 'D' ) ) === $time_value ) {
										$check_item = true;
									}

									break;
								case 'week_even':
									if ( (int) current_time( 'W' ) % 2 === 0 ) {
										$check_item = true;
									}

									break;
								case 'week_odd':
									if ( (int) current_time( 'W' ) % 2 !== 0 ) {
										$check_item = true;
									}

									break;
								case 'week_no':
									if ( (int) current_time( 'W' ) === (int) $time_value ) {
										$check_item = true;
									}

									break;
								case 'monthly_every':
									if ( strtolower( current_time( 'j' ) ) === $time_value ) {
										$check_item = true;
									}

									break;
								case 'month_no':
									if ( (int) current_time( 'm' ) === (int) $time_value ) {
										$check_item = true;
									}

									break;
								case 'every_day':
									$check_item = true;

									break;
							}

							$check &= $check_item;
						}
					}

					return $check;
				}

				public static function get_settings() {
					return apply_filters( 'wpcbm_get_settings', self::$settings );
				}

				public static function get_setting( $name, $default = false ) {
					if ( ! empty( self::$settings ) && isset( self::$settings[ $name ] ) ) {
						$setting = self::$settings[ $name ];
					} else {
						$setting = get_option( 'wpcbm_' . $name, $default );
					}

					return apply_filters( 'wpcbm_get_setting', $setting, $name, $default );
				}

				public static function best_selling_products() {
					if ( ! ( $products = get_site_transient( 'wpcbm_best_selling_products' ) ) ) {
						$args = [
							'limit'   => '10',
							'status'  => 'publish',
							'orderby' => 'total_sales',
							'order'   => 'DESC',
							'return'  => 'ids',
						];

						$products = wc_get_products( apply_filters( 'wpcbm_best_selling_products_args', $args ) );
						set_site_transient( 'wpcbm_best_selling_products', $products, 24 * HOUR_IN_SECONDS );
					}

					return apply_filters( 'wpcbm_best_selling_products', $products );
				}

				public static function generate_key() {
					$key         = '';
					$key_str     = apply_filters( 'wpcbm_key_characters', 'abcdefghijklmnopqrstuvwxyz0123456789' );
					$key_str_len = strlen( $key_str );

					for ( $i = 0; $i < apply_filters( 'wpcbm_key_length', 4 ); $i ++ ) {
						$key .= $key_str[ random_int( 0, $key_str_len - 1 ) ];
					}

					if ( is_numeric( $key ) ) {
						$key = self::generate_key();
					}

					return apply_filters( 'wpcbm_generate_key', $key );
				}
			}

			return WPCleverWpcbm::instance();
		}

		return null;
	}
}

if ( ! function_exists( 'wpcbm_notice_wc' ) ) {
	function wpcbm_notice_wc() {
		?>
        <div class="error">
            <p><strong>WPC Badge Management</strong> requires WooCommerce version 3.0 or greater.</p>
        </div>
		<?php
	}
}
