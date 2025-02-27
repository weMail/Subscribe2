<?php

/**
 * Shortcode handler class.
 */
trait Shortcode {
	public $please_log_in;
	public $confirmation_sent;
	public $already_subscribed;
	public $not_subscribed;
	public $not_an_email;
	public $barred_domain;
	public $error;
	public $no_such_email;
	public $added;
	public $deleted;
	public $subscribe;
	public $unsubscribe;
	public $input_form_action;
	public $form;
	public $s2form;
	public $email;
	public $ip;
	public $action;

	/**
	 * @var ?string
	 */
	public $profile = '';

    /**
     * Load all our strings
     *
     * @return void
     */
    public function load_strings() {
	    /* translators: Placeholders: %s - link to login page */
	    $this->please_log_in = '<p class="s2_message">' . sprintf( __( 'To manage your subscription options please <a href="%1$s">login</a>.', 'subscribe2' ), get_option( 'siteurl' ) . '/wp-login.php' ) . '</p>';
	    $profile             = apply_filters( 's2_profile_link', get_option( 'siteurl' ) . '/wp-admin/admin.php?page=s2' );

	    /* translators: Placeholders: %s - link to Profile page */
	    $this->profile = '<p class="s2_message">' . sprintf( __( 'You may manage your subscription options from your <a href="%1$s">profile</a>.', 'subscribe2' ), $profile ) . '</p>';

        if ( $this->s2_mu ) {
            global $blog_id;

            $user_ID = get_current_user_id();
            if ( ! is_user_member_of_blog( $user_ID, $blog_id ) ) {
	            // If we are on multisite and the user is not a member of this blog change the link.
	            $mu_profile = apply_filters( 's2_mu_profile_link', get_option( 'siteurl' ) . '/wp-admin/?s2mu_subscribe=' . $blog_id );
	            /* translators: Placeholders: %s - link to profile page */
	            $this->profile = '<p class="s2_message">' . sprintf( __( '<a href="%1$s">Subscribe</a> to email notifications when this blog posts new content.', 'subscribe2' ), $mu_profile ) . '</p>';
            }
        }

        $this->confirmation_sent = '<p class="s2_message">' . __( 'A confirmation message is on its way!', 'subscribe2' ) . '</p>';

        $this->already_subscribed = '<p class="s2_error">' . __( 'That email address is already subscribed.', 'subscribe2' ) . '</p>';

        $this->not_subscribed = '<p class="s2_error">' . __( 'That email address is not subscribed.', 'subscribe2' ) . '</p>';

        $this->not_an_email = '<p class="s2_error">' . __( 'Sorry, but that does not look like an email address to me.', 'subscribe2' ) . '</p>';

        $this->barred_domain = '<p class="s2_error">' . __( 'Sorry, email addresses at that domain are currently barred due to spam, please use an alternative email address.', 'subscribe2' ) . '</p>';

        $this->error = '<p class="s2_error">' . __( 'Sorry, there seems to be an error on the server. Please try again later.', 'subscribe2' ) . '</p>';

        // confirmation messages
        $this->no_such_email = '<p class="s2_error">' . __( 'No such email address is registered.', 'subscribe2' ) . '</p>';

        $this->added = '<p class="s2_message">' . __( 'You have successfully subscribed!', 'subscribe2' ) . '</p>';

        $this->deleted = '<p class="s2_message">' . __( 'You have successfully unsubscribed.', 'subscribe2' ) . '</p>';

        $this->subscribe = __( 'subscribe', 'subscribe2' ); //ACTION replacement in subscribing confirmation email

        $this->unsubscribe = __( 'unsubscribe', 'subscribe2' ); //ACTION replacement in unsubscribing in confirmation email

        if ( ! empty( $_GET['s2_unsub'] ) ) {
	        $this->unsubscribe( sanitize_email( base64_decode( $_GET['s2_unsub'] ) ) );
        }
    }

	/**
	 * Display our form, also handles (un)subscribe requests.
	 * Template and filter functions for shortcode widget.
	 *
	 * @param array $atts User defined tag attributes.
	 *
	 * @return string
	 */
	public function widget_shortcode( $atts ) {
		$args = shortcode_atts(
			array(
				'hide'       => '',
				'id'         => '',
				'nojs'       => 'false',
				'noantispam' => 'false',
				'link'       => '',
				'size'       => 20,
				'wrap'       => 'true',
				'widget'     => 'false',
			),
			$atts
		);

		// If link is true return a link to the page with the ajax class.
		if ( '1' === $this->subscribe2_options['ajax'] && '' !== $args['link'] && ! is_user_logged_in() ) {
			$id = '';
			foreach ( $args as $arg_name => $arg_value ) {
				if ( ! empty( $arg_value ) && 'link' !== $arg_name && 'id' !== $arg_name ) {
					if ( 'nojs' === $arg_name ) {
						$arg_value = 'true';
					}
					( '' === $id ) ? $id .= $arg_name . '-' . $arg_value : $id .= ':' . $arg_name . '-' . $arg_value;
				}
			}
			$this->s2form = '<a href="#" class="s2popup" id="' . esc_attr( $id ) . '">' . esc_html( $args['link'] ) . '</a>' . "\r\n";
			return $this->s2form;
		}

		// Apply filters to button text.
		$unsubscribe_button_value = apply_filters( 's2_unsubscribe_button', __( 'Unsubscribe', 'subscribe2' ) );
		$subscribe_button_value   = apply_filters( 's2_subscribe_button', __( 'Subscribe', 'subscribe2' ) );

		// If a button is hidden, show only other.
		$hide = strtolower( $args['hide'] );
		if ( 'subscribe' === $hide ) {
			$this->input_form_action = '<input type="submit" name="unsubscribe" value="' . esc_attr( $unsubscribe_button_value ) . '" />';
		} elseif ( 'unsubscribe' === $hide ) {
			$this->input_form_action = '<input type="submit" name="subscribe" value="' . esc_attr( $subscribe_button_value ) . '" />';
		} else {
			// Both form input actions.
			$this->input_form_action = '<input type="submit" name="subscribe" value="' . esc_attr( $subscribe_button_value ) . '" />&nbsp;<input type="submit" name="unsubscribe" value="' . esc_attr( $unsubscribe_button_value ) . '" />';
		}

		// If ID is provided, get permalink.
		$action = '';
		if ( is_numeric( $args['id'] ) ) {
			$action = ' action="' . get_permalink( $args['id'] ) . '"';
		} elseif ( 'home' === $args['id'] ) {
			$action = ' action="' . get_site_url() . '"';
		} elseif ( 'self' === $args['id'] ) {
			// Correct for Static front page redirect behaviour
			if ( 'page' === get_option( 'show_on_front' ) && is_front_page() ) {
				$post   = get_post( get_option( 'page_on_front' ) );
				$action = ' action="' . get_option( 'home' ) . '/' . $post->post_name . '/"';
			} else {
				$action = '';
			}
		} elseif ( $this->subscribe2_options['s2page'] > 0 ) {
			$action = ' action="' . get_permalink( $this->subscribe2_options['s2page'] ) . '"';
		}

		// Allow remote setting of email in form.
		$email = ! empty( $_REQUEST['email'] ) ? sanitize_email( $_REQUEST['email'] ) : '';
		if ( ! empty( $email ) && false !== $this->validate_email( $email ) ) {
			$value = $email;
		} elseif ( 'true' === strtolower( $args['nojs'] ) ) {
			$value = '';
		} else {
			$value = __( 'Enter email address...', 'subscribe2' );
		}

		// If wrap is true add paragraph html tags.
		$wrap_text = '';
		if ( 'true' === strtolower( $args['wrap'] ) ) {
			$wrap_text = '</p><p>';
		}

		// Deploy some anti-spam measures.
		$antispam_text = '';
		if ( 'true' !== strtolower( $args['noantispam'] ) ) {
			$antispam_text  = '<span style="display:none !important">';
			$antispam_text .= '<label for="firstname">' . __( 'Leave This Blank:', 'subscribe2' ) . '</label><input type="text" id="firstname" name="firstname" />';
			$antispam_text .= '<label for="lastname">' . __( 'Leave This Blank Too:', 'subscribe2' ) . '</label><input type="text" id="lastname" name="lastname" />';
			$antispam_text .= '<label for="uri">' . __( 'Do Not Change This:', 'subscribe2' ) . '</label><input type="text" id="uri" name="uri" value="http://" />';
			$antispam_text .= '</span>';
		}

		// Form name.
		if ( 'true' === $args['widget'] ) {
			$form_name = 's2formwidget';
		} else {
			$form_name = 's2form';
		}

		// Build default form.
		if ( 'true' === strtolower( $args['nojs'] ) ) {
			$this->form = '<form name="' . $form_name . '" method="post"' . $action . '><input type="hidden" name="ip" value="' . esc_attr( $_SERVER['REMOTE_ADDR'] ) . '" />' . $antispam_text . '<p><label for="s2email">' . __( 'Your email:', 'subscribe2' ) . '</label><br><input type="email" name="email" id="s2email" value="' . esc_attr( $value ) . '" size="' . esc_attr( $args['size'] ) . '" />' . $wrap_text . $this->input_form_action . '</p></form>';
		} else {
			$this->form = '<form name="' . $form_name . '" method="post"' . $action . '><input type="hidden" name="ip" value="' . esc_attr( $_SERVER['REMOTE_ADDR'] ) . '" />' . $antispam_text . '<p><label for="s2email">' . __( 'Your email:', 'subscribe2' ) . '</label><br><input type="email" name="email" id="s2email" value="' . esc_attr( $value ) . '" size="' . esc_attr( $args['size'] ) . '" onfocus="if (this.value === \'' . $value . '\') {this.value = \'\';}" onblur="if (this.value === \'\') {this.value = \'' . $value . '\';}" />' . $wrap_text . $this->input_form_action . '</p></form>' . "\r\n";
		}
		$this->s2form = apply_filters( 's2_form', $this->form, $args );

        if ( is_user_logged_in() ) {
            return $this->profile;
        }

		// Anti spam sign up measure.
		if ( isset( $_POST['subscribe'] ) || isset( $_POST['unsubscribe'] ) ) {
			if ( ! empty( $_POST['firstname'] ) || ! empty( $_POST['lastname'] ) || ( ! empty( $_POST['uri'] ) && 'http://' !== sanitize_url( $_POST['uri'] ) ) ) {
				// Looks like some invisible-to-user fields were changed; falsely report success.
				return $this->confirmation_sent;
			}

			$validation = apply_filters( 's2_form_submission', true );
			if ( ! $validation ) {
				return apply_filters( 's2_form_failed_validation', $this->s2form );
			}

			global $wpdb;
			$this->email = sanitize_email( $_POST['email'] );
			if ( false === $this->validate_email( $this->email ) ) {
				$this->s2form = $this->s2form . $this->not_an_email;
			} elseif ( $this->is_barred( $this->email ) ) {
				$this->s2form = $this->s2form . $this->barred_domain;
			} else {
				$this->ip = rest_is_ip_address( $_POST['ip'] ) ? $_POST['ip'] : $this->get_remote_ip();
				if ( is_int( $this->lockout ) && $this->lockout > 0 ) {
					$date = current_datetime( $this->lockout )->format( 'H:i:s.u' );
					$ips  = $wpdb->get_col(
						$wpdb->prepare(
							"SELECT ip FROM $wpdb->subscribe2 WHERE date = CURDATE() AND time > SUBTIME(CURTIME(), %s)",
							$date
						)
					);

					if ( in_array( $this->ip, $ips, true ) ) {
						return __( 'Slow down, you move too fast.', 'subscribe2' );
					}
				}

				// Does the supplied email belong to a registered user?
				$check = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT user_email FROM $wpdb->users WHERE user_email = %s",
						$this->email
					)
				);

				if ( null !== $check ) {
					// This is a registered email.
					$this->s2form = $this->please_log_in;
				} else {
					// This is not a registered email.
					// What should we do?
					if ( isset( $_POST['subscribe'] ) ) {
						// Someone is trying to subscribe.
						// Let's see if they've tried to subscribe previously.
						if ( '1' !== $this->is_public( $this->email ) ) {
							// The user is unknown or inactive.
							$this->add( $this->email );
							$status = $this->send_confirm( 'add' );
							// Set a variable to denote that we've already run, and shouldn't run again.
							$this->filtered = 1;

							if ( $status ) {
								$this->s2form = $this->confirmation_sent;
							} else {
								$this->s2form = $this->error;
							}
						} else {
							// They're already subscribed.
							$this->s2form = $this->already_subscribed;
						}

						$this->action = 'subscribe';
					} elseif ( isset( $_POST['unsubscribe'] ) ) {
						// Is this email a subscriber?
						if ( false === $this->is_public( $this->email ) ) {
							$this->s2form = $this->s2form . $this->not_subscribed;
						} else {
							$status = $this->send_confirm( 'del' );
							// Set a variable to denote that we've already run, and shouldn't run again.
							$this->filtered = 1;
							if ( $status ) {
								$this->s2form = $this->confirmation_sent;
							} else {
								$this->s2form = $this->error;
							}
						}

						$this->action = 'unsubscribe';
					}
				}
			}
		}

		return $this->s2form;
	}

	/**
	 * Collect and return the IP address of the remote client machine.
	 *
	 * @return bool
	 */
	public function get_remote_ip() {
		$remote_ip = false;

		// In order of preference, with the best ones for this purpose first.
		$address_headers = array(
			'REMOTE_ADDR',
			'HTTP_FORWARDED',
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED',
			'HTTP_FORWARDED_FOR',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_CLUSTER_CLIENT_IP',
		);

		foreach ( $address_headers as $header ) {
			if ( array_key_exists( $header, $_SERVER ) ) {
				// HTTP_X_FORWARDED_FOR can contain a chain of comma-separated
				// addresses. The first one is the original client. It can't be
				// trusted for authenticity, but we don't need to for this purpose.
				$address_chain = explode( ',', $_SERVER[ $header ] );
				$remote_ip     = trim( $address_chain[0] );
				break;
			}
		}

		return $remote_ip;
	}
}