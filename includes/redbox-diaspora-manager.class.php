<?php

/**
 * Transmits a message from Wordpress to Diaspora.
 * WORKING IN PROGRESS ... THIS OPTION IS NOT AVAILABLE NOW
 */
class RedBoxDiaspora {

	const HTTP  = 'http';
	const HTTPS = 'https';

	const WP_MESSAGE_PUBLISHED_UPDATE = 1;
	const WP_MESSAGE_PUBLISHED        = 6;

	/**
	 * Fully qualified id in the form of username@server_domain
	 * @var string
	 */
	private $id;

	/**
	 * Password to the username
	 * @var string
	 */
	private $password;

	/**
	 * Message to send to the server instance
	 * @var string
	 */
	private $message;

	/**
	 * Domain name of the server
	 * @var string
	 */
	private $server_domain;

	/**
	 * Username to sign into the server
	 * @var string
	 */
	private $username;

	private $protocol;

	/**
	 * Prefix identifier to hold transient (cached) information.
	 *
	 * WordPress does not use sessions.  I prefer not use GET parameters or
	 * cookies to send success/error messages between page requests.
	 * @var string
	 */
	private $transient_name_prefix = 'wp_post_to_diaspora';

	function __construct(&$redbox){
		$this->redbox = $redbox;
		$this->protocol = self::HTTPS;
		add_filter( 'post_updated_messages', array( &$this, 'diasporaPostUpdatedMessages' ), 10, 1 );
	}

	public function setId( $id ) {
		$this->id = $id;
		$id_array = explode( '@', $id, 2 );
		if ( count( $id_array ) == 2 ) {
			$this->username = $id_array[0];
			$this->server_domain = $id_array[1];
		}
	}

	public function setMessage( $message ) {
		$this->message = $message;
	}

	public function setPassword( $password ) {
		$this->password = $password;
	}

	public function setProtocol( $protocol ) {
		$this->protocol = $protocol;
	}

	/**
	 * Sends a WordPress post to a Diaspora server.
	 */
	function postToDiaspora() {
		$diaspora_status   = '';
		$id                = get_the_ID();
		$processed_message = $this->message;

		if ( ( empty( $this->username ) ) || ( empty( $this->server_domain ) ) ) {
			$diaspora_status = 'Error posting to Diaspora.  Please use your full Diaspora Handle in the form of username@server_name.com';
		}
		else {
			echo "Go...";
			$host = $this->protocol . '://' . $this->server_domain . '/users/sign_in';
			$auth_html = file_get_contents($host);
			$auth_doc = new DomDocument();
			$auth_doc->loadHTML($auth_html);
			$auth_form = $auth_doc->getElementById('new_user');
			$auth_doc->getElementById('user_username')->value = $this->id;
			$auth_doc->getElementById('user_password')->value = $this->password;
			echo "sending...";
			
			$postdata = http_build_query(
				array(
					'user[password]' => $this->password,
					'user[username]' => $this->username,
					'authenticity_token' =>'NhZ45OC3OoKth7ouIqBisFdGJxGqSM83zsJ4d/HJ2rI',
					'utf8' => '✓',
					'user[remember_me]' => 0,
					'commit' => 'Sign in'
				)
			);
			$opts = array('http' =>
				array(
					'method'  => 'POST',
					'header'  => 'Content-type: application/x-www-form-urlencoded',
					'content' => $postdata
				)
			);
			$context  = stream_context_create($opts);
			echo $diaspora_status = file_get_contents($host, false, $context);
	
			//create array of data to be posted
			$post_data = array(
								'user[password]' => $this->password,
								'user[username]' => $this->username,
								'authenticity_token' =>'NhZ45OC3OoKth7ouIqBisFdGJxGqSM83zsJ4d/HJ2rI',
								'utf8' => '✓',
								'user[remember_me]' => 0,
								'commit' => 'Sign in'
							);
			 
			//traverse array and prepare data for posting (key1=value1)
			foreach ( $post_data as $key => $value) {
				$post_items[] = $key . '=' . $value;
			}
			 
			//create the final string to be posted using implode()
			$post_string = implode ('&', $post_items);
			 
			//create cURL connection
			$curl_connection = curl_init($host);
			 
			//set options
			curl_setopt($post, CURLOPT_URL, $host);
			curl_setopt($post, CURLOPT_POST, count($post_items));
			curl_setopt($curl_connection, CURLOPT_CONNECTTIMEOUT, 30);
			curl_setopt($curl_connection, CURLOPT_USERAGENT,
			  "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)");
			curl_setopt($curl_connection, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curl_connection, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($curl_connection, CURLOPT_FOLLOWLOCATION, 1);
			 
			//set data to be posted
			curl_setopt($curl_connection, CURLOPT_POSTFIELDS, $post_string);
			 
			//perform our request
			echo $result = curl_exec($curl_connection);
			 
			//show information regarding the request
			print_r(curl_getinfo($curl_connection));
			//echo curl_errno($curl_connection) . '-' . curl_error($curl_connection);
			 
			//close the connection
			curl_close($curl_connection);
	
			echo $diaspora_status = $result;
		}

		set_transient( $this->transient_name_prefix . '_diaspora_status_' . $id, $diaspora_status, 60 );

		return $diaspora_status;
	}

	/**
	 * Append the return status from Diaspora to the published or updated message.
	 *
	 * @param $message Status messages for post and page actions.  Refer to the
	 *                 message array declared in wp-admin/edit-form-advanced.php 
	 * @return array   A message array with the Diaspora return status appended to
	 *                 the text of WordPress return codes of 4 (updated) and 
	 *                 6 (an update to a published post).
	 */
	public function diasporaPostUpdatedMessages( $messages ) {
		$wp_message = '';

		if ( isset( $_GET['message'] ) ) {
			$wp_message = $_GET['message'];
		}

		if ( ( $wp_message == self::WP_MESSAGE_PUBLISHED_UPDATE ) ||
		     ( $wp_message == self::WP_MESSAGE_PUBLISHED) ) {

			$id = get_the_ID();
			$diaspora_status = get_transient( $this->transient_name_prefix . '_diaspora_status_' . $id );

			if ( !empty( $diaspora_status) ) {
				delete_transient( $this->transient_name_prefix . '_diaspora_status_'  . $id);

				$messages['post'][self::WP_MESSAGE_PUBLISHED_UPDATE] .= '. ' . $diaspora_status;
				$messages['post'][self::WP_MESSAGE_PUBLISHED] .= '. ' . $diaspora_status;
			}

		}

		return $messages;
	}

}

?>
