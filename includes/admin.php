<?php

namespace Zao\WooCommerce\AttachFile;


/**
 * Generate an “Upload Files” page
 *
 * - Administrators should be notified as soon as an upload occurs via email, with a link to the specific order that has an associated upload.
 * - The uploaded file should be easily accessible for an administrator from the order
 * - Upload directly to Dropbox, as an option.
 */
class Admin {

     /**
	 * Base plugin class.
	 *
	 * @var    WooCommerce_Attach_File_To_Order
	 * @since  0.0.0
	 */
    protected $base = null;

     /**
	 * Dropbox plugin class.
	 *
	 * @var    WooCommerce_Attach_File_To_Order
	 * @since  0.0.0
	 */
    protected $dropbox = null;

    public function __construct( $base ) {
        $this->base    = $base;
        $this->dropbox = ( new Integrations( $this->base ) )->setup_dropbox();
    }

    public function setup() {

        add_filter( 'woocommerce_email_classes', function( $email_classes ) {
            $email_classes['zao_wc_attach_file_admin_email'] = new Admin_Email();
            return $email_classes;
        } );

        add_action( 'add_meta_boxes', [ $this, 'add_dropbox_metabox' ] );
        add_action( 'init'          , [ $this, 'process_drobpox_temp_link_request' ] );

    }

    public function process_drobpox_temp_link_request() {
        if ( ! isset( $_GET['dropbox_get_item_link'] ) ) {
            return;
        }

        $file_path = $_GET['dropbox_get_item_link'];
        wp_redirect( $this->dropbox->getTemporaryLink( $file_path )->getLink() );
        exit;
    }


    public function add_dropbox_metabox()  {
        add_meta_box( 'dropbox_links', __( 'Dropbox Uploads', 'woocommerce' ), [ $this, 'render_dropbox_links_on_order_page' ], 'shop_order', 'side', 'core' );
    }

    public function render_dropbox_links_on_order_page() {

        $order_id     = get_post()->ID;
        $order        = wc_get_order( $order_id );
        $order_number = $order->get_order_number();

        try {
            $results = $this->dropbox->search( '/' . $order_number , date( 'Y' ) );
            $items   = method_exists( $results, 'getItems' ) ? count( $results->getItems() ) : false;
        } catch ( DropboxClientException $e ) {
            $items = false;
        }

        if ( ! $items ) {
            ?>
            <p><?php _e( 'The customer has not uploaded any files yet.' ); ?></p>
            <?php
            $this->maybe_render_legacy_data( $order );
            return;
        }

        try {
            $links    = $this->dropbox->postToAPI( '/sharing/list_shared_links', array( 'path' => '/' . $order_number ) );
            $body     = $links->getDecodedBody();

            if ( empty( $body['links'] ) ) {
                try {
                    $response = $this->dropbox->postToAPI( '/sharing/create_shared_link_with_settings', array( 'path' => '/' . $order_number,  'settings' => array( 'requested_visibility' => 'public' ) ) );
                    $body     = $response->getBody();
                    $_body    = $response->getDecodedBody();
                } catch ( DropboxClientException $e ) {
                    echo '<p>There was a problem accessing these files in Dropbox. Go to Dropbox directly to access.</p>';
                 }
            } else {
                $url = $body['links'][0]['url'];
            }

        } catch ( DropboxClientException $e ) {
            try {
                $response = $this->dropbox->postToAPI( '/sharing/create_shared_link_with_settings', array( 'path' => '/' . $order_number,  'settings' => array( 'requested_visibility' => 'public' ) ) );
                $url      = $response->getDecodedBody()['url'];
            } catch ( DropboxClientException $e ) {
                echo '<p>There was a problem accessing these files in Dropbox. Go to Dropbox directly to access.</p>';
             }

        }

        $file_count = sprintf( esc_html( _n( '%d file', '%d files', $items, 'woocommerce-attach-file-to-order'  ) ), $items );

        ?>
        <p><?php printf( __( '%s has uploaded %s to Dropbox - <a href="%s" target="_new">click here to review them</a>.' ), $order->get_formatted_billing_full_name(), $file_count, $url ); ?>
        <?php

    }

   public function maybe_render_legacy_data( $order ) {
       $legacy_data = $order->get_meta( '_wcuf_uploaded_files' );

       if ( empty( $legacy_data ) ) {
           return;
       }

       echo '<h4>' . __( 'Legacy Data (from WCUF)' ) . '</h4>';
       echo '<ul>';
       foreach ( $legacy_data as $data ) {
           ?>
            <li><a href="<?php echo esc_url( $data['url'][0] ); ?>"><?php echo esc_html( $data['title'] ); ?></a></li>
           <?php
       }

       echo '</ul>';
   }
}