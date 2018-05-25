<?php

namespace Zao\WooCommerce\AttachFile;

/**
 * Generate an “Upload Files” page
 *
 * - Customers should be able to access this page via a special URL from their receipt email, from their account page, or from the receipt page after purchase.
 * - This page should be pre-populated with the specific order and order item that the uploaded file should be associated with.
 * - Administrators should be notified as soon as an upload occurs here via email, with a link to the specific order that has an associated upload.
 */

class Customer {

    /**
	 * Base plugin class.
	 *
	 * @var    WooCommerce_Attach_File_To_Order
	 * @since  0.0.0
	 */
    protected $base = null;

    /**
	 * Custom endpoint name.
	 *
	 * @var string
	 */
	public static $endpoint = 'upload-files';

	public $order = null;

    public function __construct( $base ) {
		$this->base     = $base;
    }

    public function setup() {
		$this->add_upload_links();
		add_action( 'wp', [ $this, 'set_up_order' ] );
	}

	public function set_up_order() {
		$this->order = wc_get_order( $GLOBALS['wp_query']->query_vars[ self::$endpoint ] );
	}

    public function add_upload_links() {
        if ( ! apply_filters( 'zao_wc_attach_file_show_upload_links', true ) ) {
            return;
        }

        $this->create_account_upload_endpoint();

        if ( apply_filters( 'zao_wc_attach_file_add_upload_link_to_order_actions', true ) ) {
            $this->add_upload_link_to_order_actions();
        }

        if ( apply_filters( 'zao_wc_attach_file_add_upload_link_to_receipt_page', true ) ) {
            $this->add_upload_link_to_receipt_page();
        }

        if ( apply_filters( 'zao_wc_attach_file_add_upload_links_to_email', true ) ) {
            $this->add_upload_links_to_email();
        }
    }

    public function create_account_upload_endpoint() {

        // Actions used to insert a new endpoint in the WordPress.
		add_action( 'init', array( $this, 'add_endpoints' ) );
        add_filter( 'query_vars', array( $this, 'add_query_vars' ), 0 );

		// Change the My Account page title.
		add_filter( 'the_title'      , array( $this, 'endpoint_title' ) );

		// Insering your new tab/page into the My Account page.
		add_action( 'woocommerce_account_' . self::$endpoint .  '_endpoint', array( $this, 'endpoint_content' ) );

		add_action( 'wc_add_dropzone_shortcode'       , [ $this, 'show_product_name' ], 20 );

		do_action( 'wc_upload_files_create_upload_endpoint', $this );

	}

	public function show_product_name( $args ) {
		if ( isset( $args['name'] ) ) {
			?>
			<h2><?php echo esc_html( $args['name'] ); ?></h2>
			<?php
		}
	}

    public function add_upload_link_to_order_actions() {

		add_filter( 'woocommerce_my_account_my_orders_actions', function( $actions, $order ) {
			$order_id = $order->get_id();
			$endpoint = self::$endpoint;

			$url      = wc_get_account_endpoint_url( "{$endpoint}/{$order_id}" );

			$actions['upload_file'] = [
				'name' => __( 'Upload File' ),
				'url'  => $url
			];

			return $actions;
		}, 10, 2 );
    }

    public function add_upload_link_to_receipt_page() {
		add_action( 'woocommerce_thankyou', function( $order_id ) {
			$endpoint = self::$endpoint;
			$url      = wc_get_account_endpoint_url( "{$endpoint}/{$order_id}" );

			?>
			<a class="button" href="<?php echo esc_url( $url ); ?>"><?php _e( 'Upload Your Files' ); ?></a>
			<?php
		} );
    }

    public function add_upload_links_to_email() {

		add_action( 'woocommerce_email_order_details', function( $order, $sent_to_admin, $plain_text, $email ) {

			if ( ! $email || ! in_array( $email->template_html, array( 'emails/customer-on-hold-order.php', 'emails/customer-processing-order.php' ) ) ) {
				return;
			}

			$endpoint = self::$endpoint;
			$order_id = $order->get_id();
			$url      = wc_get_account_endpoint_url( "{$endpoint}/{$order_id}" );
			?>
			<a href="<?php echo esc_url( $url ); ?>" class="button"><?php _e( 'Upload Your Files' ); ?></a>
			<?php
		}, 10, 4 );
    }

	/**
	 * Register new endpoint to use inside My Account page.
	 *
	 * @see https://developer.wordpress.org/reference/functions/add_rewrite_endpoint/
	 */
	public function add_endpoints() {
		add_rewrite_endpoint( self::$endpoint, EP_ROOT | EP_PAGES );
	}
	/**
	 * Add new query var.
	 *
	 * @param array $vars
	 * @return array
	 */
	public function add_query_vars( $vars ) {
		$vars[] = self::$endpoint;
		return $vars;
	}
	/**
	 * Set endpoint title.
	 *
	 * @param string $title
	 * @return string
	 */
	public function endpoint_title( $title ) {
        if ( $this->order && ! is_admin() && is_main_query() &&  is_account_page() ) {
			// New page title.
			$title = __( 'Upload Your File', 'woocommerce' );
			remove_filter( 'the_title', array( $this, 'endpoint_title' ) );
		}
		return $title;
	}


	/**
	 * Endpoint HTML content.
	 */
	public function endpoint_content() {

		if ( $this->order ) {
			$order_customer_id = $this->order->get_customer_id();
		}

		if ( ! $this->order ) {
			?>
			<p><?php _e( 'Whoops! You ended up on our Uploader Page with no valid order ID. Try re-following the link in the email you received, or the link on your Orders page. ' ); ?></p>
			<?php
		} else if ( get_current_user_id() !== $order_customer_id ) {
			?>
				<p><?php _e( 'Whoops! You appear to be attempting to upload a file to an order that does not belong to you. Try re-following the link in the email you received, or the link on your Orders page. ' ); ?></p>
		<?php
		} else {
			?>
			<p><?php echo sprintf( __( 'Upload your file for your order, order #%s below. You can click to upload, or drag and drop it.' ), $this->order->get_id() ); ?></p>
			<?php

			$order_id = $this->order->get_id();

			foreach ( $this->order->get_items() as $key => $item ) {
				$is_custom = $this->base->can_attach_file_to_item( $item );

				if ( $is_custom ) {
					$this->base->add_dropzone_shortcode( array( 'order_id' => $order_id, 'item_key' => $key, 'name' => $item->get_name(), 'sku' => $item->get_product()->get_sku() ) );
				}
			}

		}
	}
}