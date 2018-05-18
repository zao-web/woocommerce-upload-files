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
 *
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

        //TODO: Add WC Integration tab/settings
        //Configure Dropbox Application
        $app = new DropboxApp( "gw8waeb8cxcq9lf", "ou4kc7o9asi41kj", "EcC11EMt1sAAAAAAAAACpVqCdi9n7xsJZnQ6FWVI4cozhZpY2SGnYdH_K_enM-Eb" );

        //Configure Dropbox service
        $this->dropbox = new Dropbox( $app );

        // Add handlers for file upload
        add_action( 'zao_wc_attach_file_uploaded_file', [ $this, 'upload_file_to_dropbox' ] );

    }

    public function upload_file_to_dropbox() {

        // Check if file was uploaded
        if ( ! isset( $_FILES['file'] ) ) {
             return;
        }

        // File to Upload
        $file = $_FILES['file'];

        // File Path
        $file_name = $file['name'];
        $file_path = $file['tmp_name'];

        try {
            // Create Dropbox File from Path
            $dropbox_file = new DropboxFile( $file_path );

            // Upload the file to Dropbox
            $uploaded_file = $this->dropbox->upload( $dropbox_file, '/' . $file_name, [ 'autorename' => true ] );

            // File Uploaded
            echo $uploaded_file->getPathDisplay();
        } catch ( DropboxClientException $e ) {
            echo $e->getMessage();
        }

    }

}