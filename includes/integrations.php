<?php

namespace Zao\WooCommerce\AttachFile;

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

    public function __construct( $base ) {
        $this->base = $base;
    }
}