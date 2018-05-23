<?php

namespace Zao\WooCommerce\AttachFile;
use Kunnu\Dropbox\Dropbox;
use Kunnu\Dropbox\DropboxFile;
use Kunnu\Dropbox\Exceptions\DropboxClientException;
use Kunnu\Dropbox\DropboxApp;

/**
 * WC Vendors
 *  - Allow vendors to upload file to orders, sent through as attachment
 *  - Future additional dropbox-like integrations
 */
class Integrations {

    /**
	 * Base plugin class.
	 *
	 * @var    WooCommerce_Attach_File_To_Order
	 * @since  0.0.0
	 */
    protected $base = null;

    /**
	 * Base plugin class.
	 *
	 * @var    Dropbox
	 * @since  0.0.0
	 */
    protected $dropbox_api = null;

    public function __construct( $base ) {
        $this->base = $base;
    }

    public function setup() {

        add_filter( 'woocommerce_integrations', [ $this, 'add_integration' ] );

        if ( $this->integrate_with( 'dropbox' ) ) {
            $this->setup_dropbox();
            $this->dropbox_init();
        }
    }

    public function add_integration( $integrations ) {
		$integrations[] = Integration::class;
		return $integrations;
	}

    public function integrate_with( $service ) {
        return apply_filters( "zao_wc_attach_file_integrate_with_{$service}", true );
    }

    public function setup_dropbox() {

        $integration = Integration::get_instance();

        //Configure Dropbox Application
        $app = new DropboxApp( $integration->get_option( 'app_key' ), $integration->get_option( 'app_secret' ), $integration->get_option( 'access_token' ) );

        //Configure Dropbox service
        $this->dropbox = new Dropbox( $app );

        return $this->dropbox;

    }

    public function dropbox_init() {
        // Add handlers for file upload
        add_action( 'zao_wc_attach_file_uploaded_file', [ $this, 'upload_file_to_dropbox' ] );
        add_action( 'wc_add_dropzone_shortcode'       , [ $this, 'populate_dropzone' ] );
        add_action( 'wp_ajax_wc_dropzone_remove_file' , [ $this, 'remove_file' ] );
    }

    public function remove_file() {
        $file = $_REQUEST['filename'];

        $results = $this->dropbox->search( '/', $file, [ 'max_results' => 1 ] );

        $links = [];

        foreach ( $results->getItems() as $item ) {
            $meta          = $item->getMetadata();
            break;
        }

        wp_send_json_error( $this->dropbox->delete( $meta->getPathDisplay() ) );
    }

    public function populate_dropzone( $args ) {

        if ( ! isset( $args['order_id'] ) ) {
            return;
        }

        $results = $this->dropbox->search( '/', 'Artwork for Order #' . $args['order_id'] );

        $links = [];

        foreach ( $results->getItems() as $item ) {
            $meta          = $item->getMetadata();
            $name          = $meta->getName();
            $temporaryLink = $this->dropbox->getTemporaryLink( $meta->getPathDisplay() );

            //Get Link
            $link = $temporaryLink->getLink();
            $size = $meta->getSize();

            $links[ $name ] = [ 'link' => $link, 'size' => $size ];
        }

        wp_localize_script( 'woocommerce-upload-files', 'wcUploadedFiles', $links );

    }

    public function upload_file_to_dropbox() {

        // Check if file was uploaded
        if ( ! isset( $_FILES['file'] ) ) {
             return;
        }

        // File to Upload
        $file = $_FILES['file'];

        if ( ! isset( $_REQUEST['order_id'] ) ) {
            wp_send_json_error();
        }

        $order = wc_get_order( $_REQUEST['order_id'] );

        if ( ! $order || ( get_current_user_id() !== $order->get_customer_id() ) ) {
            wp_send_json_error();
        }

        // File Path
        $file_name =  apply_filters( 'wc_upload_files_dropbox_filename', $file['name'], $file, $order, $_REQUEST );
        $file_path = $file['tmp_name'];

        $success  = false;

        try {
            // Create Dropbox File from Path
            $dropbox_file = new DropboxFile( $file_path );

            $path = '/' . $order->get_id() . '/' . date( 'Ymd_Hi_s' ) ;

            $folder = $this->dropbox->createFolder( $path );

            // Upload the file to Dropbox
            $uploaded_file = $this->dropbox->upload( $dropbox_file, $path . '/' . $file_name, [
                    'autorename'      => false,
                ] );

            $new_path = $uploaded_file->getPathDisplay();

            $meta     = $this->dropbox->search( $path . '/', $file_name );
            $data     = array( 'meta' => var_export( $meta, 1 ), 'link' => var_export( $this->dropbox->getTemporaryLink( $path . '/' . $file_name ), 1 ), 'path' => $new_path, 'file' => var_export( $uploaded_file, 1 ) );
            $success  = true;

        } catch ( DropboxClientException $e ) {
            $data = array( 'error' => $e->getMessage(), 'folder' => $folder,  );
        }

        if ( $success ) {
            $email = new Admin_Email();
            $email->trigger( $order, $this->dropbox, $data );
            // Add file to order item meta.
            wp_send_json_success( $data );
        } else {
            wp_send_json_error( $data );
        }

    }

}