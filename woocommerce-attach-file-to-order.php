<?php
namespace Zao\WooCommerce\AttachFile;

/**
 * Plugin Name: WooCommerce Attach File to Order
 * Plugin URI:  https://zao.is
 * Description: Adds ability for seller or buyer to attach a file to an order, notifying the other party of the attachment.
 * Version:     0.1.0
 * Author:      Zao
 * Author URI:  https://zao.is
 * Donate link: https://zao.is
 * License:     MIT
 * Text Domain: woocommerce-attach-file-to-order
 * Domain Path: /languages
 *
 * @link    https://zao.is
 *
 * @package WooCommerce_Attach_File_To_Order
 * @version 0.0.0
 *
 * Built using generator-plugin-wp (https://github.com/WebDevStudios/generator-plugin-wp)
 */

/**
 * Copyright (c) 2018 Zao (email : justin@zao.is)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2 or, at
 * your discretion, any later version, as published by the Free
 * Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

function autoload( $class_name ) {

	// project-specific namespace prefix
	$prefix = __NAMESPACE__ . '\\';

	// does the class use the namespace prefix?
	$len = strlen( $prefix );

	if ( 0 !== strncmp( $prefix, $class_name, $len ) ) {
		// no, move to the next registered autoloader
		return;
	}

	// get the relative class name
	$relative_class = substr( $class_name, $len );

	/*
	 * replace the namespace prefix with the base directory, replace namespace
	 * separators with directory separators in the relative class name, replace
	 * underscores with dashes, and append with .php
	 */
	$path = str_replace( array( '\\' ), array( '/' ), $relative_class );
	$file = woocommerce_attach_file_to_order()->path . '/includes/' .  $path . '.php';

	// if the file exists, require it
	if ( file_exists( $file ) ) {
		require $file;
	}
}


/**
 * Main initiation class.
 *
 * @since  0.0.0
 */
final class WooCommerce_Attach_File_To_Order {

	/**
	 * Current version.
	 *
	 * @var    string
	 * @since  0.0.0
	 */
	const VERSION = '0.1.0';

	/**
	 * URL of plugin directory.
	 *
	 * @var    string
	 * @since  0.0.0
	 */
	protected $url = '';

	/**
	 * Path of plugin directory.
	 *
	 * @var    string
	 * @since  0.0.0
	 */
	protected $path = '';

	/**
	 * Plugin basename.
	 *
	 * @var    string
	 * @since  0.0.0
	 */
	protected $basename = '';

	/**
	 * Detailed activation error messages.
	 *
	 * @var    array
	 * @since  0.0.0
	 */
	protected $activation_errors = array();

	/**
	 * Singleton instance of plugin.
	 *
	 * @var    WooCommerce_Attach_File_To_Order
	 * @since  0.0.0
	 */
	protected static $single_instance = null;

	/**
	 * Singleton instance of plugin.
	 *
	 * @var    WCAFTO_Customer
	 * @since  0.0.0
	 */
	public $customer_controller = null;

	/**
	 * Singleton instance of plugin.
	 *
	 * @var    WCAFTO_Admin
	 * @since  0.0.0
	 */
	public $admin_controller = null;

	/**
	 * Singleton instance of plugin.
	 *
	 * @var    WCAFTO_Integrations
	 * @since  0.0.0
	 */
	public $integrations = null;

	/**
	 * Creates or returns an instance of this class.
	 *
	 * @since   0.0.0
	 * @return  WooCommerce_Attach_File_To_Order A single instance of this class.
	 */
	public static function get_instance() {
		if ( null === self::$single_instance ) {
			self::$single_instance = new self();
		}

		return self::$single_instance;
	}

	/**
	 * Sets up our plugin.
	 *
	 * @since  0.0.0
	 */
	protected function __construct() {
		$this->basename = plugin_basename( __FILE__ );
		$this->url      = plugin_dir_url( __FILE__ );
		$this->path     = plugin_dir_path( __FILE__ );

		$n = function( $function ) {
			return __NAMESPACE__ . "\\$function";
		};

		spl_autoload_register( $n( 'autoload' ), false );
	}

	/**
	 * Attach other plugin classes to the base plugin class.
	 *
	 * @since  0.0.0
	 */
	public function plugin_classes() {
		$this->admin_controller    = new Admin( $this );
		$this->customer_controller = new Customer( $this );
		$this->integrations        = new Integrations( $this );

	} // END OF PLUGIN CLASSES FUNCTION

	/**
	 * Add hooks and filters.
	 * Priority needs to be
	 * < 10 for CPT_Core,
	 * < 5 for Taxonomy_Core,
	 * and 0 for Widgets because widgets_init runs at init priority 1.
	 *
	 * @since  0.0.0
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'init' ), 0 );
	}

	/**
	 * Activate the plugin.
	 *
	 * @since  0.0.0
	 */
	public function _activate() {
		// Bail early if requirements aren't met.
		if ( ! $this->check_requirements() ) {
			return;
		}

		// Make sure any rewrite functionality has been loaded.
		flush_rewrite_rules();
	}

	/**
	 * Deactivate the plugin.
	 * Uninstall routines should be in uninstall.php.
	 *
	 * @since  0.0.0
	 */
	public function _deactivate() {
		// Add deactivation cleanup functionality here.
	}

	/**
	 * Init hooks
	 *
	 * @since  0.0.0
	 */
	public function init() {

		// Bail early if requirements aren't met.
		if ( ! $this->check_requirements() ) {
			return;
		}

		// Load translated strings for plugin.
		load_plugin_textdomain( 'woocommerce-attach-file-to-order', false, dirname( $this->basename ) . '/languages/' );

		// Initialize plugin classes.
		$this->plugin_classes();
	}

	/**
	 * Check if the plugin meets requirements and
	 * disable it if they are not present.
	 *
	 * @since  0.0.0
	 *
	 * @return boolean True if requirements met, false if not.
	 */
	public function check_requirements() {

		// Bail early if plugin meets requirements.
		if ( $this->meets_requirements() ) {
			return true;
		}

		// Add a dashboard notice.
		add_action( 'all_admin_notices', array( $this, 'requirements_not_met_notice' ) );

		// Deactivate our plugin.
		add_action( 'admin_init', array( $this, 'deactivate_me' ) );

		// Didn't meet the requirements.
		return false;
	}

	/**
	 * Deactivates this plugin, hook this function on admin_init.
	 *
	 * @since  0.0.0
	 */
	public function deactivate_me() {

		// We do a check for deactivate_plugins before calling it, to protect
		// any developers from accidentally calling it too early and breaking things.
		if ( function_exists( 'deactivate_plugins' ) ) {
			deactivate_plugins( $this->basename );
		}
	}

	/**
	 * Check that all plugin requirements are met.
	 *
	 * @since  0.0.0
	 *
	 * @return boolean True if requirements are met.
	 */
	public function meets_requirements() {

		// Do checks for required classes / functions or similar.
		// Add detailed messages to $this->activation_errors array.
		return true;
	}

	/**
	 * Adds a notice to the dashboard if the plugin requirements are not met.
	 *
	 * @since  0.0.0
	 */
	public function requirements_not_met_notice() {

		// Compile default message.
		$default_message = sprintf( __( 'WooCommerce Attach File to Order is missing requirements and has been <a href="%s">deactivated</a>. Please make sure all requirements are available.', 'woocommerce-attach-file-to-order' ), admin_url( 'plugins.php' ) );

		// Default details to null.
		$details = null;

		// Add details if any exist.
		if ( $this->activation_errors && is_array( $this->activation_errors ) ) {
			$details = '<small>' . implode( '</small><br /><small>', $this->activation_errors ) . '</small>';
		}

		// Output errors.
		?>
		<div id="message" class="error">
			<p><?php echo wp_kses_post( $default_message ); ?></p>
			<?php echo wp_kses_post( $details ); ?>
		</div>
		<?php
	}

	/**
	 * Magic getter for our object.
	 *
	 * @since  0.0.0
	 *
	 * @param  string $field Field to get.
	 * @throws Exception     Throws an exception if the field is invalid.
	 * @return mixed         Value of the field.
	 */
	public function __get( $field ) {
		switch ( $field ) {
			case 'version':
				return self::VERSION;
			case 'basename':
			case 'url':
			case 'path':
				return $this->$field;
			default:
				throw new Exception( 'Invalid ' . __CLASS__ . ' property: ' . $field );
		}
	}
}

/**
 * Grab the WooCommerce_Attach_File_To_Order object and return it.
 * Wrapper for WooCommerce_Attach_File_To_Order::get_instance().
 *
 * @since  0.0.0
 * @return WooCommerce_Attach_File_To_Order  Singleton instance of plugin class.
 */
function woocommerce_attach_file_to_order() {
	return WooCommerce_Attach_File_To_Order::get_instance();
}

// Kick it off.
add_action( 'plugins_loaded', array( woocommerce_attach_file_to_order(), 'hooks' ) );

// Activation and deactivation.
register_activation_hook( __FILE__, array( woocommerce_attach_file_to_order(), '_activate' ) );
register_deactivation_hook( __FILE__, array( woocommerce_attach_file_to_order(), '_deactivate' ) );
