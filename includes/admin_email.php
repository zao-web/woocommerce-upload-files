<?php
namespace Zao\WooCommerce\AttachFile;

include_once( \WC()->plugin_path(). '/includes/emails/class-wc-email.php' );

if ( ! class_exists( 'Emogrifier' ) && class_exists( 'DOMDocument' ) ) {
	include_once( \WC()->plugin_path().  '/includes/libraries/class-emogrifier.php' );
}

class Admin_Email extends \WC_Email {

    public $dropbox_url = '';

	/**
	 * Set email defaults
	 *
	 * @since 0.1
	 */
	public function __construct() {

		$this->id    = 'wc_customer_uploaded_artwork';
		$this->title = __( 'Customer Uploaded Artwork' );

        $this->description = __( 'Emails administrator whenever customer uploads artwork.' );

		$this->heading = __( 'Customer Uploaded Artwork' );
        $this->subject = __( 'Customer Uploaded Artwork' );

		$this->template_html  = 'emails/admin-customer-uploaded-artwork.php';
        $this->template_plain = 'emails/plain/admin-customer-uploaded-artwork.php';

		// Call parent constructor to load any other defaults not explicity defined here
        parent::__construct();

		// this sets the recipient to the settings defined below in init_form_fields()
        $this->recipient = $this->get_option( 'recipient' );

		if ( ! $this->recipient ) {
			$this->recipient = get_option( 'admin_email' );
        }
    }

	/**
	 * Determine if the email should actually be sent and setup email merge variables
	 *
	 * @since 0.1
	 * @param int $order_id
	 */
	public function trigger( $order, $dropbox, $data ) {

        if ( ! $order ) {
			return;
        }

        $response = $dropbox->postToAPI( '/sharing/create_shared_link_with_settings', array( 'path' => $data['path'], 'settings' => array( 'requested_visibility' => 'public' ) ) );
        $url      = $response->getDecodedBody()['url'];

        $this->dropbox_url = '<a href="' . $url . '">Go to file in Dropbox</a>';

		// setup order object
        $this->object = $order;;

        // replace variables in the subject/headings
        $this->placeholders   = array(
			'{order_date}'   => date_i18n( woocommerce_date_format(), strtotime( $this->object->order_date ) ),
            '{order_number}' => $this->object->get_order_number(),
		);

		if ( ! $this->is_enabled() || ! $this->get_recipient() ) {
			return;
        }

		// woohoo, send the email!
		$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
	}
	/**
	 * get_content_html function.
	 *
	 * @since 0.1
	 * @return string
	 */
	public function get_content_html() {
		ob_start();
		woocommerce_get_template( $this->template_html, array(
			'order'         => $this->object,
			'email_heading' => $this->get_heading(),
            'dropbox_url'   => $this->dropbox_url
        ) );


		return ob_get_clean();
	}
	/**
	 * get_content_plain function.
	 *
	 * @since 0.1
	 * @return string
	 */
	public function get_content_plain() {
		ob_start();
		woocommerce_get_template( $this->template_plain, array(
			'order'         => $this->object,
            'email_heading' => $this->get_heading(),
            'dropbox_url'   => $this->dropbox_url
		) );
		return ob_get_clean();
	}
	/**
	 * Initialize Settings Form Fields
	 *
	 * @since 2.0
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'    => array(
				'title'   => 'Enable/Disable',
				'type'    => 'checkbox',
				'label'   => 'Enable this email notification',
				'default' => 'yes'
			),
			'recipient'  => array(
				'title'       => 'Recipient(s)',
				'type'        => 'text',
				'description' => sprintf( 'Enter recipients (comma separated) for this email. Defaults to <code>%s</code>.', esc_attr( get_option( 'admin_email' ) ) ),
				'placeholder' => '',
				'default'     => ''
			),
			'subject'    => array(
				'title'       => 'Subject',
				'type'        => 'text',
				'description' => sprintf( 'This controls the email subject line. Leave blank to use the default subject: <code>%s</code>.', $this->subject ),
				'placeholder' => '',
				'default'     => ''
			),
			'heading'    => array(
				'title'       => 'Email Heading',
				'type'        => 'text',
				'description' => sprintf( __( 'This controls the main heading contained within the email notification. Leave blank to use the default heading: <code>%s</code>.' ), $this->heading ),
				'placeholder' => '',
				'default'     => ''
			),
			'email_type' => array(
				'title'       => 'Email type',
				'type'        => 'select',
				'description' => 'Choose which format of email to send.',
				'default'     => 'html',
				'class'       => 'email_type',
				'options'     => array(
					'plain'	    => __( 'Plain text', 'woocommerce' ),
					'html' 	    => __( 'HTML', 'woocommerce' ),
					'multipart' => __( 'Multipart', 'woocommerce' ),
				)
			)
		);
	}

}