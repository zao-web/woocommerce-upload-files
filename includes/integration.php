<?php

namespace Zao\WooCommerce\AttachFile;
use WC_Integration;

class Integration extends WC_Integration {

    /**
	 * Singleton instance of plugin.
	 *
	 * @var    Integration
	 * @since  0.0.0
	 */
    protected static $single_instance = null;

    public static function get_instance() {
		if ( null === self::$single_instance ) {
			self::$single_instance = new self();
		}

		return self::$single_instance;
    }

	/**
	 * Init and hook in the integration.
	 */
	public function __construct() {

        $this->id                 = 'attach-file-to-order';
		$this->method_title       = __( 'Attach File to Order', 'woocommerce-attach-file-to-order' );
		$this->method_description = __( 'Set your Attach to File integration values here.', 'woocommerce-attach-file-to-order' );

		// Actions.
        add_action( 'woocommerce_update_options_integration_' .  $this->id, array( $this, 'process_admin_options' ) );

		// Filters.
        add_filter( 'woocommerce_settings_api_sanitized_fields_' . $this->id, array( $this, 'sanitize_settings' ) );

        // Load the settings.
		$this->init_form_fields();
		$this->init_settings();

    }

	/**
	 * Initialize integration settings form fields.
	 *
	 * @return void
	 */
	public function init_form_fields() {
        $this->form_fields = [
            'debug' => [
                'title'             => __( 'Debug Log', 'woocommerce-attach-file-to-order' ),
                'type'              => 'checkbox',
                'label'             => __( 'Enable logging', 'woocommerce-attach-file-to-order' ),
                'default'           => 'no',
                'description'       => __( 'Log events such as API requests', 'woocommerce-attach-file-to-order' ),
            ],
        ];

        if ( ( new Integrations( woocommerce_attach_file_to_order() ) )->integrate_with( 'dropbox' ) ) {

            $dropbox = [
                'app_key' => [
                    'title'             => __( 'App Key', 'woocommerce-attach-file-to-order' ),
                    'type'              => 'text',
                    'description'           => __( 'Need help on creating a Dropbox app? <a href="https://docs.gravityforms.com/creating-a-custom-dropbox-app/">Review this excellent documentation</a>.' )
                 ],
                 'app_secret' => [
                    'title'             => __( 'App Secret', 'woocommerce-attach-file-to-order' ),
                    'type'              => 'text',
                    'description'       => __( 'You can find the app secret right under App key, clicking the "Show" link', 'woocommerce-attach-file-to-order' ),
                    'desc_tip'          => true,
                    'default'           => ''
                 ],
                 'access_token' => [
                    'title'             => __( 'Access Token', 'woocommerce-attach-file-to-order' ),
                    'type'              => 'text',
                    'description'       => __( 'Under the OAuth2 section, click Generate access token', 'woocommerce-attach-file-to-order' ),
                    'desc_tip'          => true,
                    'default'           => ''
                 ]
            ];

            $this->form_fields = $dropbox + $this->form_fields;
        }
	}

	/**
	 * Santize our settings
	 * @see process_admin_options()
	 */
	public function sanitize_settings( $settings ) {

        foreach ( $this->form_fields as $key => $field ) {
            if ( isset( $settings ) && isset( $settings[ $key ] ) ) {
                $settings[ $key ] = sanitize_text_field( $settings[ $key ] );
            }
        }

		return $settings;
	}

}
