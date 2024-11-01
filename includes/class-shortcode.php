<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WPCleverWpcbm_Shortcode' ) ) {
	class WPCleverWpcbm_Shortcode {
		function __construct() {
			add_action( 'init', [ $this, 'wpcbm_shortcodes' ] );
		}

		function wpcbm_shortcodes() {
			add_shortcode( 'wpcbm_product_data', [ $this, 'product_data' ] );
			add_shortcode( 'wpcbm_best_seller', [ $this, 'best_seller' ] );
			add_shortcode( 'wpcbm_price', [ $this, 'price' ] );
			add_shortcode( 'wpcbm_save_percentage', [ $this, 'saved_percentage' ] );
			add_shortcode( 'wpcbm_saved_percentage', [ $this, 'saved_percentage' ] );
			add_shortcode( 'wpcbm_save_amount', [ $this, 'saved_amount' ] );
			add_shortcode( 'wpcbm_saved_amount', [ $this, 'saved_amount' ] );
			add_shortcode( 'wpcbm_tags', [ $this, 'tags' ] );
			add_shortcode( 'wpcbm_categories', [ $this, 'categories' ] );
		}

		function product_data( $attrs ) {
			$output = '';

			$attrs = shortcode_atts( [
				'get'  => 'price',
				'type' => 'html',
				'id'   => null
			], $attrs );

			if ( ! $attrs['id'] ) {
				global $product;
			} else {
				$product = wc_get_product( $attrs['id'] );
			}

			if ( $product && is_a( $product, 'WC_Product' ) ) {
				switch ( $attrs['get'] ) {
					case 'price':
						switch ( $attrs['type'] ) {
							case 'html':
								$output = $product->get_price_html();

								break;
							case 'regular':
								$output = wc_price( $product->get_regular_price() );

								break;
							case 'sale':
								$output = wc_price( $product->get_sale_price() );

								break;
						}

						break;
					case 'stock':
						if ( $product->managing_stock() ) {
							$output = $product->get_stock_quantity();
						}

						break;
					case 'category':
						$output = wc_get_product_category_list( $product->get_id() );

						break;
					case 'tag':
						$output = wc_get_product_tag_list( $product->get_id() );

						break;
					default:
						$func = 'get_' . $attrs['get'];

						if ( method_exists( $product, $func ) ) {
							$output = $product->$func();
						}
				}
			}

			return apply_filters( 'wpcbm_shortcode_product_data', $output, $attrs );
		}

		function best_seller( $attrs ) {
			$output = '';

			$attrs = shortcode_atts( [
				'id'   => null,
				'top'  => 10,
				'in'   => 'product_cat',
				'text' => /* translators: category */ esc_html__( '#%1$s in %2$s', 'wpc-badge-management' )
			], $attrs );

			if ( ! $attrs['id'] ) {
				global $product;
			} else {
				$product = wc_get_product( $attrs['id'] );
			}

			$text       = apply_filters( 'wpcbm_shortcode_best_seller_text', $attrs['text'] );
			$taxonomies = apply_filters( 'wpcbm_shortcode_best_seller_taxonomies', [
				'product_cat',
				'product_tag',
				'wpc-brand',
				'wpc-collection'
			] );

			if ( ! in_array( $attrs['in'], $taxonomies ) ) {
				$attrs['in'] = 'product_cat';
			}

			if ( $product ) {
				$product_id = $product->get_id();
				$terms      = get_the_terms( $product_id, $attrs['in'] );

				if ( is_array( $terms ) && ! empty( $terms ) ) {
					foreach ( $terms as $term ) {
						$args  = [
							'post_type'      => 'product',
							'meta_key'       => 'total_sales',
							'orderby'        => 'meta_value_num',
							'order'          => 'DESC',
							'post_status'    => 'publish',
							'posts_per_page' => (int) $attrs['top'],
							'tax_query'      => [
								[
									'taxonomy' => $attrs['in'],
									'field'    => 'term_id',
									'terms'    => [ $term->term_id ],
									'operator' => 'IN'
								]
							],
						];
						$query = new WP_Query( $args );

						if ( $query->have_posts() ) {
							$top = 1;

							while ( $query->have_posts() ) {
								$query->the_post();

								if ( get_the_ID() === $product_id ) {
									$output = sprintf( $text, $top, '<a href="' . get_term_link( $term->term_id, $attrs['in'] ) . '">' . $term->name . '</a>' );
									break;
								}

								$top ++;
							}

							wp_reset_postdata();
						}
					}
				}
			}

			return apply_filters( 'wpcbm_shortcode_best_seller', $output, $attrs );
		}

		function price( $attrs ) {
			$output = '';

			$attrs = shortcode_atts( [
				'id' => null
			], $attrs );

			if ( ! $attrs['id'] ) {
				global $product;

				if ( $product ) {
					$output = $product->get_price_html();
				}
			} else {
				if ( $_product = wc_get_product( $attrs['id'] ) ) {
					$output = $_product->get_price_html();
				}
			}

			return apply_filters( 'wpcbm_shortcode_price', $output, $attrs );
		}

		function saved_amount( $attrs ) {
			$output = '';

			$attrs = shortcode_atts( [
				'id' => null
			], $attrs );

			if ( ! $attrs['id'] ) {
				global $product;

				if ( $product && $product->is_on_sale() ) {
					$output = $this->wpcbm_get_saved_amount( $product );
				}
			} else {
				if ( ( $_product = wc_get_product( $attrs['id'] ) ) && $_product->is_on_sale() ) {
					$output = $this->wpcbm_get_saved_amount( $_product );
				}
			}

			return apply_filters( 'wpcbm_shortcode_saved_amount', $output, $attrs );
		}

		function saved_percentage( $attrs ) {
			$output = '';

			$attrs = shortcode_atts( [
				'id' => null
			], $attrs );

			if ( ! $attrs['id'] ) {
				global $product;

				if ( $product && $product->is_on_sale() ) {
					$output = $this->wpcbm_get_saved_percentage( $product );
				}
			} else {
				if ( ( $_product = wc_get_product( $attrs['id'] ) ) && $_product->is_on_sale() ) {
					$output = $this->wpcbm_get_saved_percentage( $_product );
				}
			}

			return apply_filters( 'wpcbm_shortcode_saved_percentage', $output, $attrs );
		}

		function tags( $attrs ) {
			$output = '';

			$attrs = shortcode_atts( [
				'id' => null
			], $attrs );

			if ( ! $attrs['id'] ) {
				global $product;

				if ( $product ) {
					$attrs['id'] = $product->get_id();
				}
			}

			if ( $attrs['id'] ) {
				$output = wc_get_product_tag_list( $attrs['id'] );
			}

			return apply_filters( 'wpcbm_shortcode_tags', $output, $attrs );
		}

		function categories( $attrs ) {
			$output = '';

			$attrs = shortcode_atts( [
				'id' => null
			], $attrs );

			if ( ! $attrs['id'] ) {
				global $product;

				if ( $product ) {
					$attrs['id'] = $product->get_id();
				}
			}

			if ( $attrs['id'] ) {
				$output = wc_get_product_category_list( $attrs['id'] );
			}

			return apply_filters( 'wpcbm_shortcode_categories', $output, $attrs );
		}

		function wpcbm_get_saved_percentage( $product ) {
			$output = '';

			if ( $product->get_type() == 'variable' ) {
				$available_variations = $product->get_variation_prices();
				$max_percentage       = 0;

				foreach ( $available_variations['regular_price'] as $key => $regular_price ) {
					$sale_price = $available_variations['sale_price'][ $key ];

					if ( $regular_price && $sale_price < $regular_price ) {
						$percentage = round( ( $regular_price - $sale_price ) * 100 / $regular_price );

						if ( $percentage > $max_percentage ) {
							$max_percentage = $percentage;
						}
					}
				}

				if ( $max_percentage ) {
					$output = $max_percentage . '%';
				}
			} else {
				$regular_price = $product->get_regular_price();
				$sale_price    = $product->get_sale_price();

				if ( $regular_price && $sale_price < $regular_price ) {
					$output = round( ( $regular_price - $sale_price ) * 100 / $regular_price ) . '%';
				}
			}

			return $output;
		}

		function wpcbm_get_saved_amount( $product ) {
			$output = '';

			if ( $product->get_type() == 'variable' ) {
				$available_variations = $product->get_variation_prices();
				$max_amount           = 0;

				foreach ( $available_variations['regular_price'] as $key => $regular_price ) {
					$sale_price = $available_variations['sale_price'][ $key ];

					if ( $regular_price && $sale_price < $regular_price ) {
						$amount = $regular_price - $sale_price;

						if ( $amount > $max_amount ) {
							$max_amount = $amount;
						}
					}
				}

				if ( $max_amount ) {
					$output = wc_price( $max_amount );
				}
			} else {
				$regular_price = $product->get_regular_price();
				$sale_price    = $product->get_sale_price();

				if ( $regular_price && $sale_price < $regular_price ) {
					$output = wc_price( $regular_price - $sale_price );
				}
			}

			return $output;
		}
	}

	new WPCleverWpcbm_Shortcode();
}