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

    public function __construct( $base ) {
        $this->base = $base;
    }

}