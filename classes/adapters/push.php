<?php

class WP_Stream_Notification_Adapter_Push extends WP_Stream_Notification_Adapter {

	const PUSHOVER_OPTION_NAME = 'ckpn_pushover_notifications_settings';

	public static function register( $title = '' ) {
		parent::register( __( 'Push', 'stream-notifications' ) );
		add_filter( 'wp_stream_serialized_labels', array( __CLASS__, 'pushover_key_labels' ) );
	}

	public static function get_application_key() {
		$options = get_option( self::PUSHOVER_OPTION_NAME, array() );
		$result  = ( isset( $options['application_key'] ) && ! empty( $options['application_key'] ) ) ? $options['application_key'] : false;

		return $result;
	}

	public static function fields() {
		$plugin_path  = 'pushover-notifications/pushover-notifications.php';
		$is_installed = ( $plugin_path && defined( 'WP_PLUGIN_DIR' ) && file_exists( trailingslashit( WP_PLUGIN_DIR )  . $plugin_path ) );

		if ( ! $is_installed ) {
			$fields = array(
				'error' => array(
					'title'   => __( 'Missing Required Plugin', 'stream-notifications' ),
					'type'    => 'error',
					'message' => sprintf(
						__( 'Please install and activate the %1$s plugin to enable push alerts.', 'stream-notifications' ),
						sprintf(
							'<a href="%1$s" target="_blank">%2$s</a>',
							esc_url( 'http://wordpress.org/plugins/pushover-notifications/' ),
							__( 'Pushover Notifications', 'stream-notifications' )
						)
					),
				),
			);
		} elseif ( ! is_plugin_active( $plugin_path ) ) {
			$fields = array(
				'error' => array(
					'title'   => __( 'Required Plugin Not Activated', 'stream-notifications' ),
					'type'    => 'error',
					'message' => sprintf(
						__( 'Please activate the %1$s plugin to enable push alerts.', 'stream-notifications' ),
						sprintf(
							'<a href="%1$s">%2$s</a>',
							admin_url( 'plugins.php' ),
							__( 'Pushover Notifications', 'stream-notifications' )
						)
					),
				),
			);
		} elseif ( false !== self::get_application_key() ) {
			$fields = array(
				'users' => array(
					'title'    => __( 'Send to Users', 'stream-notifications' ),
					'type'     => 'hidden',
					'multiple' => true,
					'ajax'     => true,
					'key'      => 'author',
					'args'     => array(
						'push' => true,
					),
					'hint'     => __( 'Alert specific users via push.', 'stream-notifications' ),
				),
				'subject' => array(
					'title' => __( 'Subject', 'stream-notifications' ),
					'type'  => 'text',
					'hint'  => __( 'Data tags are allowed.', 'stream-notifications' ),
				),
				'message' => array(
					'title' => __( 'Message', 'stream-notifications' ),
					'type'  => 'textarea',
					'hint'  => __( 'Data tags are allowed.', 'stream-notifications' ),
				),
			);
		} else {
			$fields = array(
				'error' => array(
					'title'   => __( 'Application key is missing', 'stream-notifications' ),
					'type'    => 'error',
					'message' => sprintf(
						__( 'Please provide your Application key on %1$s.', 'stream-notifications' ),
						sprintf(
							'<a href="%1$s">%2$s</a>',
							admin_url( 'options-general.php?page=pushover-notifications' ),
							__( 'Pushover Notifications settings page', 'stream-notifications' )
						)
					),
				),
			);
		}

		return $fields;
	}

	public function send( $log ) {
		$application_key = self::get_application_key();

		if ( false === $application_key ) {
			return false;
		}

		if ( ! empty( $this->params['users'] ) ) {
			$users_ids = explode( ',', $this->params['users'] );
			$users = get_users( array(
				'include'  => $users_ids,
				'fields'   => 'ID',
				'meta_key' => 'ckpn_user_key',
			) );
			$users_pushover_keys = array_map(
				function( $user_id ) {
					return get_user_meta( $user_id, 'ckpn_user_key', true );
				},
				$users
			);
		}

		$subject = $this->replace( $this->params['subject'], $log );
		$message = $this->replace( $this->params['message'], $log );

		$post_fields = array(
			'token'   => $application_key,
			'message' => $message,
			'title'   => $subject,
		);

		$connection = curl_init();

		foreach ( $users_pushover_keys as $key ) {
			$post_fields['user'] = $key;
			curl_setopt_array(
				$connection,
				array(
					CURLOPT_URL            => 'https://api.pushover.net/1/messages.json',
					CURLOPT_POST           => true,
					CURLOPT_RETURNTRANSFER => 1,
					CURLOPT_POSTFIELDS     => http_build_query( $post_fields ),
				)
			);
			$response = curl_exec( $connection );
		}
		curl_close( $connection );
	}

	/**
	 * @filter wp_stream_serialized_labels
	 */
	function pushover_key_labels( $labels ) {
		$labels[self::PUSHOVER_OPTION_NAME] = array(
			'application_key' => __( 'Application API Token/Key', 'stream-notifications' ),
			'api_key'         => __( 'Your User Key', 'stream-notifications' ),
			'new_user'        => __( 'New Users', 'stream-notifications' ),
			'new_post'        => __( 'New Posts are Published', 'stream-notifications' ),
			'new_post_roles'  => __( 'Roles to Notify', 'stream-notifications' ),
			'new_comment'     => __( 'New Comments', 'stream-notifications' ),
			'notify_authors'  => __( 'Notify the Post Author (for multi-author blogs)', 'stream-notifications' ),
			'password_reset'  => __( 'Notify users when password resets are requested for their accounts', 'stream-notifications' ),
			'core_update'     => __( 'WordPress Core Update is Available', 'stream-notifications' ),
			'plugin_updates'  => __( 'Plugin & Theme Updates are Available', 'stream-notifications' ),
			'multiple_keys'   => __( 'Use Multiple Application Keys', 'stream-notifications' ),
			'sslverify'       => __( 'Verify SSL from api.pushover.net', 'stream-notifications' ),
			'logging'         => __( 'Enable Logging', 'stream-notifications' ),
		);

		return $labels;
	}

}

WP_Stream_Notification_Adapter_Push::register();
