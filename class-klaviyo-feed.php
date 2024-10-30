<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

GFForms::include_feed_addon_framework();

class GF_Klaviyo_Free extends GFFeedAddOn {

    protected $_version                  	= GF_KLAVIYO_FREE;
	protected $_min_gravityforms_version	= '1.9.16';
	protected $_slug 						= 'klaviyo_addon_free';
    protected $_path 						= 'klaviyo-for-gravity-forms/gf-klaviyo.php';
	protected $_full_path                	= __FILE__;
	protected $_title                   	= 'Klaviyo For Gravity Forms';
	protected $_short_title             	= 'Klaviyo';
    protected $_multiple_feeds           	= false;
	private static $_instance = null;

    protected $api = null;

    protected $_async_feed_processing = true;

    /**
	 * Get an instance of this class.
	 *
	 * @return GF_Klaviyo_Free
	 */
	public static function get_instance() {
		if ( self::$_instance == null ) {
			self::$_instance = new GF_Klaviyo_Free();
		}

		return self::$_instance;
	}


    public function init() {
		parent::init();

		$this->add_delayed_payment_support(
			array(
				'option_label' => esc_html__( 'Add data to Klaviyo only when payment is received.', 'klaviyo-for-gravity-forms' ),
			)
		);
    }

    public function init_admin() {
		parent::init_admin();
        
		add_filter( 'gform_entry_detail_meta_boxes', array($this, 'klaviyo_created_profile_info'), 10, 3 );
    }

    public function get_menu_icon() {
        return '<svg xmlns="http://www.w3.org/2000/svg" id="a" viewBox="0 0 150 150"><path d="M148.76,124.01H3.24V26.63H148.76l-30.55,48.69,30.55,48.69Z"/></svg>';
    }


    public function plugin_settings_fields() {
        return array(
            array(
                'title'  => esc_html__( 'Klaviyo Add-On Settings', 'klaviyo-for-gravity-forms' ),
                'fields' => array(
                    array(
                        'name'      => 'klaviyo_api_key',
                        'label'     => esc_html__( 'Klaviyo API Key', 'klaviyo-for-gravity-forms' ),
                        'tooltip'   => esc_html__( 'Please enter your company name', 'klaviyo-for-gravity-forms' ),
                        'type'      => 'text',
                        'class'     => 'small',
                        'feedback_callback' => array( $this, 'initialize_klaviyo_api' ),
                    ),
                    array(
						'type'     => 'save',
						'messages' => array(
							'success' => esc_html__( 'Klaviyo settings have been updated.', 'klaviyo-for-gravity-forms' )
						),
					)
                )
            )
        );
    }

    public function initialize_klaviyo_api() {
        
        if ( ! is_null( $this->api ) ) {
			return true;
		}

        $settings = $this->get_plugin_settings();

        if ( ! rgar( $settings, 'klaviyo_api_key' ) ) {
			return null;
		}

        $klaviyo = new GF_Klaviyo_API( $settings['klaviyo_api_key'] );

        $account = $klaviyo->get_accoount();

        if( is_wp_error( $account ) ){
            return false;
        }

        $this->api = $klaviyo;
        
        return true;
    }

    public function feed_settings_fields() {
        return array(
            array(
                'title'  => esc_html__( 'Integration with Klaviyo', 'klaviyo-for-gravity-forms' ),
                'fields' => array(
                    array(
                        'label'     => esc_html__( 'Enable', 'klaviyo-for-gravity-forms' ),
                        'name'      => 'is_enable',
                        'type'      => 'toggle',
                        'default_value' => false,
                    ),
                    array(
						'name'      => 'api_field',
						'type'      => 'field_map',
						'field_map' => array(
							array(
								'name'       => 'first_name',
								'label'      => esc_html__( 'First Name', 'klaviyo-for-gravity-forms' ),
								'required'   => false,
								'tooltip'    => esc_html__( 'Please choose the first name', 'klaviyo-for-gravity-forms' ),
							),
							array(
								'name'       => 'email',
								'label'      => esc_html__( 'Email Address', 'klaviyo-for-gravity-forms' ),
								'required'   => true,
                                'field_type' => array( 'email' ),
								'tooltip'    => esc_html__( 'Please choose the email', 'klaviyo-for-gravity-forms' ),
							),
                        ),
                        'dependency' => array(
                            'live'     => true,
                            'fields' => array(
                                array(
                                    'field' => 'is_enable',
                                ),
                            ),
                        )
                    ),
                    array(
						'name'       => 'klaviyo_list',
						'label'      => esc_html__( 'Klaviyo List', 'klaviyo-for-gravity-forms' ),
						'type'       => 'select',
						'required'   => true,
						'choices'    => $this->get_lists_for_feed_settings(),
                        'dependency' => array(
                            'live'     => true,
                            'fields' => array(
                                array(
                                    'field' => 'is_enable',
                                ),
                            ),
                        )
					),
                )
            ),
            array(
				'title'  => esc_html__( 'Conditional logic', 'klaviyo-for-gravity-forms' ),
				'fields' => array(
					array(
						'type'           => 'feed_condition',
						'name'           => 'feed-condition',
						'label'          => esc_html__( 'Conditions', 'klaviyo-for-gravity-forms' ),
						'checkbox_label' => esc_html__( 'Enable conditional processing', 'klaviyo-for-gravity-forms' )
					),
				),
			)
        );
    }

	/**
	 * Set feed creation control.
	 *
	 * @since  1.0
	 *
	 * @return bool
	 */
	public function can_create_feed() {
		return $this->initialize_klaviyo_api();
	}

    public function get_lists_for_feed_settings() {
        if( ! $this->initialize_klaviyo_api() ) {
            return array();
        }

        $cache_name = $this->get_slug() . '_list_attr';
        $list_item = get_transient($cache_name);

        if( $list_item !== false ) {
            return $list_item;
        }

        $choices = array(
			array(
				'label' => esc_html__( 'Select a List', 'klaviyo-for-gravity-forms' ),
				'value' => '',
			),
		);

        $lists = $this->get_lists();
        
        foreach ($lists['data'] as  $list) {
            $choices[] = array(
                'label' => esc_html($list['attributes']['name']),
                'value' => esc_attr($list['id'])
            );
        }

        set_transient( $cache_name, $choices, 5 * MINUTE_IN_SECONDS);

       return $choices;
    }

    public function get_lists() {
        $lists = $this->api->get_lists();

        if( is_wp_error($lists) ) {
            return $lists;
        }

        return $lists;
    }

    public function process_feed( $feed, $entry, $form ) {

        if( ! $feed['meta']['is_enable'] ) {
            return $entry;
        }

        if( ! $this->initialize_klaviyo_api() ) {
            return $entry;
        }

        $contact = array(
			'name'      => $this->get_field_value( $form, $entry, $feed['meta']['api_field_first_name'] ),
			'email'     => $this->get_field_value( $form, $entry, $feed['meta']['api_field_email'] ),
			'list'      => array( 'klaviyoId' => $feed['meta']['klaviyo_list'] ),
            'entry_id'  => $entry['id']
		);

        if ( GFCommon::is_invalid_or_empty_email( $contact['email'] ) ) {
			$this->add_feed_error( esc_html__( 'Unable to subscribe user to list because an invalid or empty email address was provided.', 'klaviyo-for-gravity-forms' ), $feed, $entry, $form );
			return $entry;
		}

        $created_profile = $this->api->add_profile_with_list( $contact );

        if( is_wp_error( $created_profile ) ) {
            $error_message = $created_profile->get_error_message();

			// Log that contact could not be created.
			$this->add_feed_error(
				sprintf(
					// translators: Placeholder represents error message.
					esc_html__( 'Unable to create contact: %s', 'klaviyo-for-gravity-forms' ),
					$error_message
				),
				$feed,
				$entry,
				$form
			);
        } else {
			// Log that contact was created.
			$this->log_debug( __METHOD__ . '(): Contact was created.' );
		}

        return $entry;
    }

    public function klaviyo_created_profile_info( $meta_boxes, $entry, $form ) {

        $meta_boxes['klaviyo_profile_id'] = array(
            'title'         => esc_html__( 'Klaviyo', 'klaviyo-for-gravity-forms' ),
            'callback'      => array($this, 'get_klaviyo_profile_id'),
            'context'       => 'side'
        );

        return $meta_boxes;
    }

    public function get_klaviyo_profile_id( $args ) {

        $meta_value = gform_get_meta( $args['entry']['id'], 'klaviyo_free_profile_id' );

        if( $meta_value ) {
            printf(
                '<span class="success">%1$s</span>',
                /* translators: %s: Profile id */
                sprintf(esc_html__('New profile created ID - %s', 'klaviyo-for-gravity-forms'), esc_html($meta_value))
            );
        } else {
            printf(
                '<span class="no-data">%s</span>',
                esc_html__('No data.', 'klaviyo-for-gravity-forms')
            );
        }
    }

}