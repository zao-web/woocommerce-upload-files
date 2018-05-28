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
        add_action( 'zao_wc_attach_files_data_success', [ $this, 'maybe_add_pdf_thumbnail' ], 20, 3 );
    }

    public function maybe_add_pdf_thumbnail( $data, $order, $file_name ) {
        $extension =  pathinfo( $file_name, PATHINFO_EXTENSION );

        if ( 'ai' !== $extension && 'pdf' !== $extension ) {
            return $data;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-includes/class-wp-image-editor.php';

        $file_name = str_replace( '.ai', '.pdf', $file_name );

        // Array based on $_FILE as seen in PHP file uploads
        $file = [
            'name'     => $file_name, // ex: wp-header-logo.png
            'type'     => 'application/pdf',
            'tmp_name' => $_FILES['file']['tmp_name'] ,
            'error'    => 0,
            'size'     => $_FILES['file']['size'] ,
        ];

        $overrides = [
            'test_form' => false,
            'test_size' => true,
        ];

        // Move the temporary file into the uploads directory
        $results = wp_handle_sideload( $file, $overrides );

        if ( ! empty( $results['error'] ) ) {
            $data['thumb_results'] = $results;
            return $data;
        } else {
            $filename  = $results['file']; // Full path to the file
            $local_url = $results['url'];  // URL to the file in the uploads dir
            $type      = $results['type']; // MIME type of the file

            $editor = wp_get_image_editor( $filename );

            if ( ! is_wp_error( $editor ) ) { // No support for this type of file

                $preview_file = $filename . '-pdf.jpg';

                $uploaded = $editor->save( $preview_file, 'image/jpeg' );

				unset( $editor );

                // Resize based on the full size image, rather than the source.
				if ( ! is_wp_error( $uploaded ) ) {
					$editor = wp_get_image_editor( $uploaded['path'] );
                    $sizes  = $this->get_pdf_sizes();
                    if ( ! is_wp_error( $editor ) ) {
                        $uploaded['path'] = str_replace( ABSPATH, home_url( '/' ), $uploaded['path'] );
                        $data['link']          = $uploaded['path'];
                        $data['sizes']         = $editor->multi_resize( $sizes );
						$data['sizes']['full'] = $uploaded;
					}
				}
			}

            $data['editor']        = $editor;
            $data['thumb_results'] = $results;

            return $data;
            // Perform any actions here based in the above results
        }
    }

    public function get_pdf_sizes() {

        $fallback_sizes = array(
			'thumbnail',
			'medium',
			'large',
        );

		/**
		 * Filters the image sizes generated for non-image mime types.
		 *
		 * @since 4.7.0
		 *
		 * @param array $fallback_sizes An array of image size names.
		 * @param array $metadata       Current attachment metadata.
		 */
		$fallback_sizes             = apply_filters( 'fallback_intermediate_image_sizes', $fallback_sizes, $metadata );
		$sizes                      = array();
        $_wp_additional_image_sizes = wp_get_additional_image_sizes();

		foreach ( $fallback_sizes as $s ) {
			if ( isset( $_wp_additional_image_sizes[ $s ]['width'] ) ) {
				$sizes[ $s ]['width'] = intval( $_wp_additional_image_sizes[ $s ]['width'] );
			} else {
				$sizes[ $s ]['width'] = get_option( "{$s}_size_w" );
			}
			if ( isset( $_wp_additional_image_sizes[ $s ]['height'] ) ) {
				$sizes[ $s ]['height'] = intval( $_wp_additional_image_sizes[ $s ]['height'] );
			} else {
				$sizes[ $s ]['height'] = get_option( "{$s}_size_h" );
			}
			if ( isset( $_wp_additional_image_sizes[ $s ]['crop'] ) ) {
				$sizes[ $s ]['crop'] = $_wp_additional_image_sizes[ $s ]['crop'];
			} else {
				// Force thumbnails to be soft crops.
				if ( 'thumbnail' !== $s ) {
					$sizes[ $s ]['crop'] = get_option( "{$s}_crop" );
				}
			}
        }

        return $sizes;
    }

    public function remove_file() {
        $file     = $_REQUEST['filename'];
        $order    = wc_get_order( $_REQUEST['order_id'] );
        $item_key = $_REQUEST['item_key'];

        if ( ! $order ) {
            wp_send_json_error( array( 'error' => __( 'Could not find order' ) ) );
        }

        if ( ! current_user_can( 'manage_woocommerce' ) && get_current_user_id() !== $order->get_customer_id() ) {
            wp_send_json_error( array( 'error' => __( 'Current user cannot delete files' ) ) );
        }

        $results = $this->dropbox->search( '/' . $order_number, $file, [ 'max_results' => 1 ] );

        $links = [];

        foreach ( $results->getItems() as $item ) {
            $meta  = $item->getMetadata();
            $name  = $meta->getName();
            break;
        }

        $existing_meta = (array) $order->get_meta( 'dropbox_links' );

        if ( isset( $existing_meta[ $item_key ][ $name ] ) ) {
            unset( $existing_meta[ $item_key ][ $name ] );
        }

        $order->update_meta_data( 'dropbox_links', $existing_meta );
        $order->save_meta_data();

        wp_send_json_success( $this->dropbox->delete( $meta->getPathDisplay() ) );
    }

    public function update_order_meta( $uploaded_file, $order, $item_key, $data ) {

        $existing_meta = (array) $order->get_meta( 'dropbox_links' );
        $new_name      = $uploaded_file->getName();

        if ( ! isset( $existing_meta[ $item_key ] ) ) {
            $existing_meta[ $item_key ] = [];
        }

        $results = $this->dropbox->search( '/' . $order_number , $item_key . '_' );

        foreach ( $results->getItems() as $item ) {

            $meta          = $item->getMetadata();
            $name          = $meta->getName();

            if ( ! empty( $existing_meta[ $item_key ][ $name ] ) ) {
                continue;
            }

            //Get Link
            $temporaryLink = $this->dropbox->getTemporaryLink( $meta->getPathDisplay() );
            $link = $temporaryLink->getLink();

            $extension = pathinfo( $name, PATHINFO_EXTENSION );

            $args = [];

            if ( $name === $new_name && ( 'pdf' === $extension || 'ai' === $extension ) ) {
                $link = $data['link'];
            }

            $size = $meta->getSize();

            $existing_meta[ $item_key ][ $name ] = [
                'link' => $link,
                'size' => $size
            ];
        }

        $order->update_meta_data( 'dropbox_links', $existing_meta );
        $order->save_meta_data();

        return $order->get_meta( 'dropbox_links' );

    }

    public function populate_dropzone( $args ) {

        if ( ! isset( $args['order_id'] ) ) {
            return;
        }

        wp_localize_script( 'woocommerce-upload-files', 'wcUploadedFiles', array_filter( (array) wc_get_order( $args['order_id'] )->get_meta( 'dropbox_links' ) ) );
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

            $path = '/' . $order->get_order_number() . '/' . date( 'Ymd_Hi_s' ) ;

            $folder = $this->dropbox->createFolder( $path );

            // Upload the file to Dropbox
            $uploaded_file = $this->dropbox->upload( $dropbox_file, $path . '/' . $file_name, [
                    'autorename'      => false,
                ] );

            $new_path = $uploaded_file->getPathDisplay();

            $meta     = $this->dropbox->search( $path . '/', $file_name );
            $data     = array( 'meta' => var_export( $meta, 1 ), 'link' => $this->dropbox->getTemporaryLink( $new_path )->getLink(), 'path' => $path, 'file' => var_export( $uploaded_file, 1 ) );
            $success  = true;

        } catch ( DropboxClientException $e ) {
            $data = array( 'error' => $e->getMessage(), 'folder' => $folder,  );
        }

        if ( $success ) {
            $data = apply_filters( 'zao_wc_attach_files_data_success', $data, $order, $file_name, $this );
            $meta = $this->update_order_meta( $uploaded_file, $order, $_REQUEST['item_key'], $data );
            $email = new Admin_Email();
            $email->trigger( $order, $this->dropbox, $data );
            // Add file to order item meta.
            wp_send_json_success( array_merge( $data, $meta ) );
        } else {
            wp_send_json_error( $data );
        }

    }

}