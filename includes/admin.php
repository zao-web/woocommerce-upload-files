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

    public function __construct( $base ) {
        $this->base = $base;
    }

    public function setup() {

        add_filter( 'woocommerce_email_classes', function( $email_classes ) {
            $email_classes['zao_wc_attach_file_admin_email'] = new Admin_Email();
            return $email_classes;
        } );

    }
}