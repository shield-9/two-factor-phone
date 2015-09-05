<?php
/**
 * Class for creating a Phone Call provider.
 *
 * @since 0.1-dev
 *
 * @package Two_Factor
 */
class Two_Factor_Phone extends Two_Factor_Provider {

	/**
	 * Twilio Library
	 * @var Services_Twilio
	 */
	public static $twilio;

	/**
	 * The user meta Twilio AccoutSID
	 * @type string
	 */
	const ACCOUNT_SID_META_KEY = '_two_factor_phone_twilio_account_sid';

	/**
	 * The user meta Twilio AuthToken
	 * @type string
	 */
	const AUTH_TOKEN_META_KEY = '_two_factor_phone_auth_token';

	/**
	 * The user meta Twilio Sender Phone Number
	 * @type string
	 */
	const SENDER_NUMBER_META_KEY = '_two_factor_phone_sender_number';

	/**
	 * The user meta Twilio Receiver Phone Number
	 * @type string
	 */
	const RECEIVER_NUMBER_META_KEY = '_two_factor_phone_receiver_number';

	/**
	 * The user meta token key.
	 * @type string
	 */
	const TOKEN_META_KEY = '_two_factor_phone_token';

	/**
	 * Number words.
	 * @var string[]
	 */
	private $number_words;

	/**
	 * Ensures only one instance of this class exists in memory at any one time.
	 *
	 * @since 0.1-dev
	 */
	static function get_instance() {
		static $instance;
		$class = __CLASS__;
		if ( ! is_a( $instance, $class ) ) {
			if ( did_action( 'plugins_loaded' ) ) {
				self::load_plugin_textdomain();
			} else {
				add_action( 'plugins_loaded', array( __CLASS__, 'load_plugin_textdomain' ) );
			}

			$instance = new $class;
		}
		return $instance;
	}

	/**
	 * Class constructor.
	 *
	 * @since 0.1-dev
	 */
	protected function __construct() {
		add_action( 'wp_ajax_nopriv_two-factor-phone-twiml', array( $this, 'show_twiml_page' ) );

		add_action( 'two-factor-user-options-' . __CLASS__, array( $this, 'user_options' ) );

		add_action( 'show_user_profile',        array( __CLASS__, 'show_user_profile' ) );
		add_action( 'edit_user_profile',        array( __CLASS__, 'show_user_profile' ) );
		add_action( 'user_profile_twilio',      array( __CLASS__, 'show_twilio_item' ) );
		add_action( 'personal_options_update',  array( __CLASS__, 'catch_submission' ), 0 );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'catch_submission' ), 0 );

		return parent::__construct();
	}

	/**
	 * Returns the name of the provider.
	 *
	 * @since 0.1-dev
	 */
	public function get_label() {
		return _x( 'Phone Call (Twilio)', 'Provider Label', 'two-factor-phone' );
	}

	/**
	 * Generate the user token.
	 *
	 * @since 0.1-dev
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	public function generate_token( $user_id ) {
		$token = $this->get_code();
		update_user_meta( $user_id, self::TOKEN_META_KEY, wp_hash( $token ) );
		return $token;
	}

	/**
	 * Validate the user token.
	 *
	 * @since 0.1-dev
	 *
	 * @param int    $user_id User ID.
	 * @param string $token User token.
	 * @return boolean
	 */
	public function validate_token( $user_id, $token ) {
		$hashed_token = get_user_meta( $user_id, self::TOKEN_META_KEY, true );
		if ( wp_hash( $token ) !== $hashed_token ) {
			$this->delete_token( $user_id );
			return false;
		}
		return true;
	}

	/**
	 * Delete the user token.
	 *
	 * @since 0.1-dev
	 *
	 * @param int $user_id User ID.
	 */
	public function delete_token( $user_id ) {
		delete_user_meta( $user_id, self::TOKEN_META_KEY );
	}

	/**
	 * Generate and call the user token.
	 *
	 * @since 0.1-dev
	 *
	 * @param WP_User $user WP_User object of the logged-in user.
	 * @return boolean
	 */
	public function generate_and_call_token( $user ) {
		require_once( TWO_FACTOR_PHONE_DIR . 'includes/Twilio/Services/Twilio.php' );

		$sid      = get_user_meta( $user->ID, self::ACCOUNT_SID_META_KEY,     true );
		$token    = get_user_meta( $user->ID, self::AUTH_TOKEN_META_KEY,      true );
		$sender   = get_user_meta( $user->ID, self::SENDER_NUMBER_META_KEY,   true );
		$receiver = get_user_meta( $user->ID, self::RECEIVER_NUMBER_META_KEY, true );

		self::$twilio = new Services_Twilio( $sid, $token );

		$twiml_url = admin_url( 'admin-ajax.php?action=two-factor-phone-twiml&user=' . $user->ID );
		$twiml_url = add_query_arg( 'nonce', wp_create_nonce( 'two-factor-phone-twiml' ), $twiml_url );

		try {
			$call = self::$twilio->account->calls->create(
				$sender,
				$receiver,
				$twiml_url,
				array()
			);
		} catch ( Services_Twilio_RestException $e ) {
			return false;
		}

		return true;
	}

	/**
	 * Prints the form that prompts the user to authenticate.
	 *
	 * @since 0.1-dev
	 *
	 * @param WP_User $user WP_User object of the logged-in user.
	 */
	public function authentication_page( $user ) {
		if ( $this->generate_and_call_token( $user ) ) {
			require_once( ABSPATH . '/wp-admin/includes/template.php' );
			?>
			<p><?php esc_html_e( 'A verification code has been sent to the phone number associated with your account.', 'two-factor-phone' ); ?></p>
			<p>
				<label for="authcode"><?php esc_html_e( 'Verification Code:', 'two-factor-phone' ); ?></label>
				<input type="tel" name="two-factor-phone-code" id="authcode" class="input" value="" size="20" pattern="[0-9]*" />
			</p>
			<script type="text/javascript">
				setTimeout( function(){
					var d;
					try{
						d = document.getElementById('authcode');
						d.value = '';
						d.focus();
					} catch(e){}
				}, 200);
			</script>
			<?php
			submit_button( __( 'Log In', 'two-factor-phone' ) );
		} else {
			?>
			<p><?php esc_html_e( 'An error occured while calling.', 'two-factor-phone' ); ?></p>
			<?php
		}
	}

	/**
	 * Validates the users input token.
	 *
	 * @since 0.1-dev
	 *
	 * @param WP_User $user WP_User object of the logged-in user.
	 * @return boolean
	 */
	public function validate_authentication( $user ) {
		return $this->validate_token( $user->ID, $_REQUEST['two-factor-phone-code'] );
	}

	/**
	 * Whether this Two Factor provider is configured and available for the user specified.
	 *
	 * @since 0.1-dev
	 *
	 * @param WP_User $user WP_User object of the logged-in user.
	 * @return boolean
	 */
	public function is_available_for_user( $user ) {
		return (
			get_user_meta( $user->ID, self::ACCOUNT_SID_META_KEY, true ) !== false
			 && get_user_meta( $user->ID, self::AUTH_TOKEN_META_KEY, true ) !== false
			 && get_user_meta( $user->ID, self::SENDER_NUMBER_META_KEY, true ) !== false
			 && get_user_meta( $user->ID, self::RECEIVER_NUMBER_META_KEY, true ) !== false
		);
	}

	/**
	 * Inserts markup at the end of the user profile field for this provider.
	 *
	 * @since 0.1-dev
	 *
	 * @param WP_User $user WP_User object of the logged-in user.
	 */
	public function user_options( $user ) {
		?>
		<div>
			<?php echo esc_html( __( 'You need Twilio account.', 'two-factor-phone' ) ); ?>
		</div>
		<?php
	}

	/**
	 * Display the Twilio section in a users profile.
	 *
	 * This executes during the `show_user_profile` & `edit_user_profile` actions.
	 *
	 * @since 0.1-dev
	 *
	 * @access public
	 * @static
	 *
	 * @param WP_User $user WP_User object of the logged-in user.
	 */
	public static function show_user_profile( $user ) {
		if ( did_action( 'user_profile_twilio' ) ) {
			return;
		}
		?>
		<div class="twilio" id="twilio-section">
			<h3><?php esc_html_e( 'Twilio', 'two-factor-phone' ); ?></h3>
			<table class="form-table">
				<?php do_action( 'user_profile_twilio', $user ); ?>
			</table>
		</div>
		<?php
	}

	/**
	 * Display the form in Twilio section.
	 *
	 * @since 0.1-dev
	 *
	 * @access public
	 * @static
	 *
	 * @param WP_User $user WP_User object of the logged-in user.
	 */
	public static function show_twilio_item( $user ) {
		$sid      = get_user_meta( $user->ID, self::ACCOUNT_SID_META_KEY, true );
		$token    = get_user_meta( $user->ID, self::AUTH_TOKEN_META_KEY, true );
		$sender   = get_user_meta( $user->ID, self::SENDER_NUMBER_META_KEY, true );
		$receiver = get_user_meta( $user->ID, self::RECEIVER_NUMBER_META_KEY, true );
		?>
		<tr class="user-twilio-sid-wrap">
			<th><label for="twilio-sid"><?php esc_html_e( 'AccountSID' , 'two-factor-phone' ); ?></label></th>
			<td><input type="text" name="twilio-phone-sid" id="twilio-phone-sid" value="<?php echo esc_attr( $sid ) ?>" class="regular-text code"></td>
		</tr>
		<tr class="user-twilio-token-wrap">
			<th><label for="twilio-token"><?php esc_html_e( 'AuthToken', 'two-factor-phone' ); ?></label></th>
			<td><input type="password" name="twilio-phone-token" id="twilio-phone-token" value="<?php echo esc_attr( $token ) ?>" class="regular-text code"></td>
		</tr>
		<tr class="user-twilio-sender-wrap">
			<th><label for="twilio-sender"><?php esc_html_e( 'Sender Phone Number', 'two-factor-phone' ); ?></label></th>
			<td><input type="tel" name="twilio-phone-sender" id="twilio-phone-sender" value="<?php echo esc_attr( $sender ) ?>" class="regular-text code"></td>
		</tr>
		<tr class="user-twilio-receiver-wrap">
			<th><label for="twilio-receiver"><?php esc_html_e( 'Receiver Phone Number', 'two-factor-phone' ); ?></label></th>
			<td><input type="tel" name="twilio-phone-receiver" id="twilio-phone-receiver" value="<?php echo esc_attr( $receiver ) ?>" class="regular-text code"></td>
		</tr>
		<?php
	}

	/**
	 * Catch the non-ajax submission from the new form.
	 *
	 * This executes during the `personal_options_update` & `edit_user_profile_update` actions.
	 *
	 * @since 0.1-dev
	 *
	 * @access public
	 * @static
	 *
	 * @param int $user_id User ID.
	 */
	public static function catch_submission( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		update_user_meta( $user_id, self::ACCOUNT_SID_META_KEY,     $_POST['twilio-phone-sid'] );
		update_user_meta( $user_id, self::AUTH_TOKEN_META_KEY,      $_POST['twilio-phone-token'] );
		update_user_meta( $user_id, self::SENDER_NUMBER_META_KEY,   $_POST['twilio-phone-sender'] );
		update_user_meta( $user_id, self::RECEIVER_NUMBER_META_KEY, $_POST['twilio-phone-receiver'] );
	}

	/**
	 * Display TwiML.
	 *
	 * @since 0.1-dev
	 *
	 * @access public
	 */
	public function show_twiml_page() {
		check_ajax_referer( 'two-factor-phone-twiml', 'nonce' );
		if ( empty( $_REQUEST['user'] ) || ! is_numeric( $_REQUEST['user'] ) ) {
			return false;
		}

		$this->number_words = array(
			0 => __( 'zero',  'two-factor-phone' ),
			1 => __( 'one',   'two-factor-phone' ),
			2 => __( 'two',   'two-factor-phone' ),
			3 => __( 'three', 'two-factor-phone' ),
			4 => __( 'four',  'two-factor-phone' ),
			5 => __( 'five',  'two-factor-phone' ),
			6 => __( 'six',   'two-factor-phone' ),
			7 => __( 'seven', 'two-factor-phone' ),
			8 => __( 'eight', 'two-factor-phone' ),
			9 => __( 'nine',  'two-factor-phone' ),
		);

		require_once( TWO_FACTOR_PHONE_DIR . 'includes/Twilio/Services/Twilio.php' );

		$code = $this->generate_token( absint( $_REQUEST['user'] ) );
		$say_options = array(
			'voice' => 'alice',
			'language' => 'en-US',
		);

		$response = new Services_Twilio_Twiml();

		$response->say(
			wp_strip_all_tags(
				sprintf(
					__( 'Your login confirmation code for %s is:', 'two-factor-phone' ),
					get_bloginfo( 'name' )
				)
			),
			$say_options
		);
		foreach ( str_split( $code ) as $number ) {
			$response->say( $this->number_words[ $number ], $say_options );
		}

		echo $response;
		exit;
	}

	/**
	 * Load Translations.
	 *
	 * @since 0.1-dev
	 *
	 * @access public
	 * @static
	 */
	public static function load_plugin_textdomain() {
		load_plugin_textdomain( 'two-factor-phone', FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );
	}
}
