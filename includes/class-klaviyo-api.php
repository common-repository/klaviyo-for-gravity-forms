<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GF_Klaviyo_API {
    protected $api_key = null;

    public function __construct( $api_key = null ) {
		$this->api_key = $api_key;
	}

    protected function make_request( $route = '', $body = array(), $method = 'GET', $response_code = 200 ) {

        $request_url = 'https://a.klaviyo.com/api/' . $route;

        // Construct headers.
		$headers = array(
            'content-Type'  => 'application/json',
			'accept'        => 'application/json',
			'Authorization' => 'Klaviyo-API-Key ' . $this->api_key, 
            'revision'      => '2024-05-15',
		);

        $args = [
            'method'    => $method,
            'headers'   => $headers
        ];

        if( 'GET' !== $method ) {
            $args['body'] = wp_json_encode( $body );
        }
        
        $result = wp_remote_request( $request_url, $args );

        if( is_wp_error( $result ) ) {
            return $result;
        }

        $response = wp_remote_retrieve_body( $result );
        $response = GFCommon::maybe_decode_json($response);
        $api_response_code = (int) wp_remote_retrieve_response_code( $result );

        if( $api_response_code !== $response_code && is_array( $response ) ) {

            $wp_error = new WP_Error( $api_response_code, $response['errors'][0]['detail'] );

            return $wp_error;
        }

        return $response;
    }

    public function get_accoount() {
		return $this->make_request( "accounts/");
	}

    public function get_lists() {
        return $this->make_request("lists/");
    }

    public function create_profile( $attributes ) {

        $data = '{
                "data": {
                    "type": "profile",
                    "attributes": {
                        "email": "'. $attributes['email'].'",
                        "first_name": "'. $attributes['name'] .'"
                    }
                }
            }';

        $body = json_decode( $data);    

        return $this->make_request( 'profiles/', $body, 'POST', 201 );
    }

    public function add_profile_with_list( $attributes ) {

        $profile = $this->create_profile( $attributes );
        $profile_id = $profile['data']['id'];

        gform_update_meta( $attributes['entry_id'], 'klaviyo_free_profile_id', $profile_id );

        $data = '{
            "data": [
                {
                    "type": "profile",
                    "id": "'. $profile_id .'"
                }
            ]
        }';

        $body = json_decode($data);

        return $this->make_request( 'lists/'. $attributes['list']['klaviyoId'].'/relationships/profiles/', $body, 'POST', 204);
    }

}