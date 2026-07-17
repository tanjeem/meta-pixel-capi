<?php
namespace Mpc\Marketing;

class CatalogFeed {
	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', [ $this, 'add_feed_endpoint' ] );
		add_action( 'template_redirect', [ $this, 'render_feed' ] );
	}

	public function add_feed_endpoint() {
		add_feed( 'fb-catalog', [ $this, 'render_feed' ] );
	}

	public function render_feed() {
		if ( ! is_feed( 'fb-catalog' ) ) {
			return;
		}

		header( 'Content-Type: application/xml; charset=' . get_option( 'blog_charset' ), true );
		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo '<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">' . "\n";
		echo '<channel>' . "\n";
		echo '<title>' . get_bloginfo_rss('name') . ' Facebook Catalog</title>' . "\n";
		echo '<link>' . get_bloginfo_rss('url') . '</link>' . "\n";

		$page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
		$limit = 500;
		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'paged'          => $page,
		);

		$products = get_posts( $args );

		foreach ( $products as $post ) {
			$product = wc_get_product( $post->ID );
			if ( ! $product ) continue;
			
			$id = $product->get_sku() ? $product->get_sku() : $product->get_id();
			
			echo '<item>' . "\n";
			echo '<g:id>' . esc_xml( $id ) . '</g:id>' . "\n";
			echo '<g:title>' . esc_xml( $product->get_name() ) . '</g:title>' . "\n";
			echo '<g:description>' . esc_xml( wp_strip_all_tags( $product->get_short_description() ?: $product->get_description() ) ) . '</g:description>' . "\n";
			echo '<g:link>' . esc_url( $product->get_permalink() ) . '</g:link>' . "\n";
			
			$image_id = $product->get_image_id();
			if ( $image_id ) {
				echo '<g:image_link>' . esc_url( wp_get_attachment_url( $image_id ) ) . '</g:image_link>' . "\n";
			}
			
			echo '<g:brand>' . esc_xml( get_bloginfo('name') ) . '</g:brand>' . "\n";
			echo '<g:condition>new</g:condition>' . "\n";
			echo '<g:availability>' . ( $product->is_in_stock() ? 'in stock' : 'out of stock' ) . '</g:availability>' . "\n";
			echo '<g:price>' . esc_xml( $product->get_price() . ' ' . get_woocommerce_currency() ) . '</g:price>' . "\n";
			
			echo '</item>' . "\n";
		}

		echo '</channel>' . "\n";
		echo '</rss>';
		exit;
	}
}
