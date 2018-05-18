<?php
/**
 * WooCommerce_Attach_File_To_Order.
 *
 * @since   0.0.0
 * @package WooCommerce_Attach_File_To_Order
 */
class WooCommerce_Attach_File_To_Order_Test extends WP_UnitTestCase {

	/**
	 * Test if our class exists.
	 *
	 * @since  0.0.0
	 */
	function test_class_exists() {
		$this->assertTrue( class_exists( 'WooCommerce_Attach_File_To_Order') );
	}

	/**
	 * Test that our main helper function is an instance of our class.
	 *
	 * @since  0.0.0
	 */
	function test_get_instance() {
		$this->assertInstanceOf(  'WooCommerce_Attach_File_To_Order', woocommerce_attach_file_to_order() );
	}

	/**
	 * Replace this with some actual testing code.
	 *
	 * @since  0.0.0
	 */
	function test_sample() {
		$this->assertTrue( true );
	}
}
