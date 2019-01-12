<?php
/**
 * Addon class that extends Camptix with invoicing functionalities.
 *
 * @package    Camptix_Invoices
 * @subpackage Camptix_invoices/includes
 */

/**
 * This class defines all code necessary to include invoices into Camptix.
 *
 * @package    Camptix_Invoices
 * @subpackage Camptix_invoices/includes
 */
class CampTix_Addon_Invoices extends \CampTix_Addon {

	/**
	 * Init invoice addon
	 */
	public function camptix_init() {
		global $camptix;
		global $camptix_invoice_custom_error;
		$camptix_invoice_custom_error = false;
		add_filter( 'camptix_setup_sections', array( __CLASS__, 'invoice_settings_tab' ) );
		add_action( 'camptix_menu_setup_controls', array( __CLASS__, 'invoice_settings' ) );
		add_filter( 'camptix_validate_options', array( __CLASS__, 'validate_options' ), 10, 2 );
		add_action( 'camptix_payment_result', array( __CLASS__, 'maybe_create_invoice' ), 10, 3 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_enqueue_assets' ) );
		add_filter( 'camptix_checkout_attendee_info', array( __CLASS__, 'attendee_info' ) );
		add_action( 'camptix_notices', array( __CLASS__, 'error_flag' ), 0 );
		add_filter( 'camptix_form_register_complete_attendee_object', array( __CLASS__, 'attendee_object' ), 10, 2 );
		add_action( 'camptix_checkout_update_post_meta', array( __CLASS__, 'add_meta_invoice_on_attendee' ), 10, 2 );
		add_filter( 'camptix_metabox_attendee_info_additional_rows', array( __CLASS__, 'add_invoice_meta_on_attendee_metabox' ), 10, 2 );
	}

	/**
	 * Add a new tab in camptix settings.
	 *
	 * @param array $sections Sections of the Camptix settings.
	 */
	public static function invoice_settings_tab( $sections ) {
		$sections['invoice'] = __( 'Invoicing', 'invoices-camptix' );
		return $sections;
	}

	/**
	 * Tab content.
	 *
	 * @param string $section Section.
	 */
	public static function invoice_settings( $section ) {
		if ( 'invoice' !== $section ) {
			return false;
		}//end if

		$opt = get_option( 'camptix_options' );
		add_settings_section( 'invoice', __( 'Invoices settings', 'invoices-camptix' ), '__return_false', 'camptix_options' );
		global $camptix;

		$camptix->add_settings_field_helper(
			'invoice-active', __( 'Activate invoice requests', 'invoices-camptix' ), 'field_yesno', 'invoice',
			// translators: %1$s is a date.
			sprintf( __( 'Allow ticket buyers to ask for an invoice when purchasing their tickets.', 'invoices-camptix' ), date( 'Y' ) )
		);

		$camptix->add_settings_field_helper(
			'invoice-new-year-reset', __( 'Yearly reset', 'invoices-camptix' ), 'field_yesno', 'invoice',
			// translators: %1$s is a date.
			sprintf( __( 'Invoice numbers are prefixed with the year, and will be reset on the 1st of January (e.g. %1$s-125)', 'invoices-camptix' ), date( 'Y' ) )
		);

		add_settings_field(
			'invoice-date-format', __( 'Date format', 'invoices-camptix' ), array( __CLASS__, 'date_format_callback' ), 'camptix_options', 'invoice', array(
				'id'    => 'invoice-date-format',
				'value' => ! empty( $opt['invoice-date-format'] ) ? $opt['invoice-date-format'] : 'd F Y',
			)
		);

		$camptix->add_settings_field_helper(
			'invoice-vat-number', __( 'VAT number', 'invoices-camptix' ), 'field_yesno', 'invoice',
			// translators: %1$s is a date.
			sprintf( __( 'Add a "VAT Number" field to the invoice request form', 'invoices-camptix' ), date( 'Y' ) )
		);

		add_settings_field(
			'invoice-current-number', __( 'Next invoice', 'invoices-camptix' ), array( __CLASS__, 'current_number_callback' ), 'camptix_options', 'invoice', array(
				'id'     => 'invoice-current-number',
				'value'  => isset( $opt['invoice-current-number'] ) ? $opt['invoice-current-number'] : 1,
				'yearly' => isset( $opt['invoice-new-year-reset'] ) ? $opt['invoice-new-year-reset'] : false,
			)
		);

		add_settings_field(
			'invoice-logo', __( 'Logo', 'invoices-camptix' ), array( __CLASS__, 'type_file_callback' ), 'camptix_options', 'invoice', array(
				'id'    => 'invoice-logo',
				'value' => ! empty( $opt['invoice-logo'] ) ? $opt['invoice-logo'] : '',
			)
		);

		$camptix->add_settings_field_helper( 'invoice-company', __( 'Company address', 'invoices-camptix' ), 'field_textarea', 'invoice' );
		$camptix->add_settings_field_helper( 'invoice-tac', __( 'Terms and Conditions', 'invoices-camptix' ), 'field_textarea', 'invoice' );
		$camptix->add_settings_field_helper( 'invoice-thankyou', __( 'Note below invoice total', 'invoices-camptix' ), 'field_textarea', 'invoice' );
	}

	/**
	 * Date format setting callback.
	 *
	 * @param array $args Arguments.
	 */
	public static function date_format_callback( $args ) {
		vprintf(
			'<input type="text" value="%2$s" name="camptix_options[%1$s]">
		<p class="description">Date format to use on the invoice, as a PHP Date formatting string (default \'d F Y\' formats dates as %3$s).<br><a href="%4$s">%5$s</a></p>',
			array(
				esc_attr( $args['id'] ),
				esc_attr( $args['value'] ),
				esc_html( date( 'd F Y' ) ),
				esc_attr__( 'https://codex.wordpress.org/Formatting_Date_and_Time', 'invoices-camptix' ),
				esc_html__( 'Documentation on date and time formatting', 'invoices-camptix' ),
			)
		);
	}

	/**
	 * Next invoice number setting.
	 *
	 * @param array $args Arguments.
	 */
	public static function current_number_callback( $args ) {
		vprintf(
			'<p>' . __( "The next invoice's number will be", 'invoices-camptix' ) . ' %3$s<input type="number" min="1" value="%2$d" name="camptix_options[%1$s]" class="small-text">%4$s</p>', array(
				esc_attr( $args['id'] ),
				esc_attr( $args['value'] ),
				$args['yearly'] ? '<code>' . esc_html( date( 'Y-' ) ) : '',
			$args['yearly'] ? '</code>' : '', // @codingStandardsIgnoreLine
			)
		);
	}

	/**
	 * Input type file.
	 *
	 * @param object $args Arguments.
	 */
	public static function type_file_callback( $args ) {
		wp_enqueue_media();
		wp_enqueue_script( 'admin-camptix-invoices' );
		wp_localize_script(
			'admin-camptix-invoices', 'camptixInvoiceBackVars', array(
				'selectText'  => __( 'Pick a logo to upload', 'invoices-camptix' ),
				'selectImage' => __( 'Pick this logo', 'invoices-camptix' ),
			)
		);

		vprintf(
			'<div class="camptix-media"><div class="camptix-invoice-logo-preview-wrapper" data-imagewrapper>
			%4$s
		</div>
		<input data-set type="button" class="button button-secondary" value="%3$s" />
		<input data-unset type="button" class="button button-secondary" value="%5$s"%6$s/>
		<input type="hidden" name=camptix_options[%1$s] data-field="image_attachment" value="%2$s"></div>',
			array(
				esc_attr( $args['id'] ),
				esc_attr( $args['value'] ),
				esc_attr__( 'Pick a logo', 'invoices-camptix' ),
				! empty( $args['value'] ) ? wp_get_attachment_image( $args['value'], 'thumbnail', '', array() ) : '', // @codingStandardsIgnoreLine
				esc_attr__( 'Remove logo', 'invoices-camptix' ),
				empty( $args['value'] ) ? ' style="display:none;"' : '', // @codingStandardsIgnoreLine
			)
		);
	}

	/**
	 * Validate our custom options.
	 *
	 * @param object $output Output options.
	 * @param object $input  Input options.
	 */
	public static function validate_options( $output, $input ) {
		if ( isset( $input['invoice-active'] ) ) {
			$output['invoice-active'] = (int) $input['invoice-active'];
		}//end if
		if ( isset( $input['invoice-new-year-reset'] ) ) {
			$output['invoice-new-year-reset'] = (int) $input['invoice-new-year-reset'];
		}//end if
		if ( isset( $input['invoice-date-format'] ) ) {
			$output['invoice-date-format'] = $input['invoice-date-format'];
		}//end if
		if ( isset( $input['invoice-vat-number'] ) ) {
			$output['invoice-vat-number'] = (int) $input['invoice-vat-number'];
		}//end if
		if ( ! empty( $input['invoice-current-number'] ) ) {
			$output['invoice-current-number'] = (int) $input['invoice-current-number'];
		}//end if
		if ( isset( $input['invoice-logo'] ) ) {
			$output['invoice-logo'] = (int) $input['invoice-logo'];
		}//end if
		if ( isset( $input['invoice-company'] ) ) {
			$output['invoice-company'] = sanitize_textarea_field( $input['invoice-company'] );
		}//end if
		if ( isset( $input['invoice-tac'] ) ) {
			$output['invoice-tac'] = sanitize_textarea_field( $input['invoice-tac'] );
		}//end if
		if ( isset( $input['invoice-thankyou'] ) ) {
			$output['invoice-thankyou'] = sanitize_textarea_field( $input['invoice-thankyou'] );
		}//end if
		return $output;
	}

	/**
	 * Listen payment result to create invoice.
	 *
	 * @param string $payment_token The payment token.
	 * @param int    $result        The result.
	 */
	public static function maybe_create_invoice( $payment_token, $result ) {
		if ( 2 !== $result ) {
			return;
		}//end if

		$attendees = get_posts(
			array(
				'posts_per_page' => -1,
				'post_type'      => 'tix_attendee',
				'post_status'    => 'any',
				'meta_query'     => array(
					array(
						'key'     => 'tix_payment_token',
						'compare' => ' = ',
						'value'   => $payment_token,
						'type'    => 'CHAR',
					),
				),
			)
		);
		if ( ! $attendees ) {
			return;
		}//end if

		$metas = get_post_meta( $attendees[0]->ID, 'invoice_metas', true );
		if ( $metas ) {
			$order      = get_post_meta( $attendees[0]->ID, 'tix_order', true );
			$invoice_id = self::create_invoice( $attendees[0], $order, $metas );
			if ( ! is_wp_error( $invoice_id ) && ! empty( $invoice_id ) ) {
				self::send_invoice( $invoice_id );
			}//end if
		}//end if
	}

	/**
	 * Get, increment and return invoice number.
	 *
	 * @todo can be refactorized
	 */
	public static function create_invoice_number() {
		$opt     = get_option( 'camptix_options' );
		$current = ! empty( $opt['invoice-current-number'] ) ? intval( $opt['invoice-current-number'] ) : 1;
		$year    = date( 'Y' );

		if ( ! empty( $opt['invoice-new-year-reset'] ) ) {
			if ( ! empty( $opt['invoice-current-year'] ) && $opt['invoice-current-year'] !== $year ) {
				$opt['invoice-current-number'] = 1;
				$current                       = 1;
			}//end if
			$current = sprintf( '%s-%s', $year, $current );
		}//end if

		$opt['invoice-current-year'] = $year;
		$opt['invoice-current-number']++;
		update_option( 'camptix_options', $opt );
		return $current;
	}

	/**
	 * Create invoice.
	 *
	 * @param object $attendee The attendee.
	 * @param object $order    The order.
	 * @param object $metas    The metas.
	 *
	 * @todo Link invoice and corresponding attendees
	 */
	public static function create_invoice( $attendee, $order, $metas ) {
		$number         = self::create_invoice_number();
		$attendee_email = get_post_meta( $attendee->ID, 'tix_email', true );
		$txn_id         = get_post_meta( $attendee->ID, 'tix_transaction_id', true );

		// Prevent invoice_number from being assigned twice.
		remove_action( 'publish_tix_invoice', 'ctx_assign_invoice_number', 10 );

		// $txn_id may be null if no transaction was created (100% coupon used).
		if ( $txn_id ) {
			$invoice_title = sprintf(
				// translators: 1: invoice number, 2: email, 3: transaction id, 4. date.
				__( 'Invoice #%1$s for %2$s (order #%3$s) on %4$s', 'invoices-camptix' ),
				$number,
				$attendee_email,
				$txn_id,
				get_the_time( 'd/m/Y', $attendee )
			);
		} else {
			$invoice_title = sprintf(
				// translators: 1: invoice number, 2: email, 3. date.
				__( 'Invoice #%1$s for %2$s on %3$s', 'invoices-camptix' ),
				$number,
				$attendee_email,
				get_the_time( 'd/m/Y', $attendee )
			);
		}//end if

		$arr = array(
			'post_type'   => 'tix_invoice',
			'post_status' => 'publish',
			'post_title'  => $invoice_title,
			'post_name'   => sprintf( 'invoice-%s', $number ),
		);

		$invoice = wp_insert_post( $arr );
		if ( ! $invoice || is_wp_error( $invoice ) ) {
			return;
		}//end if
		update_post_meta( $invoice, 'invoice_number', $number );
		update_post_meta( $invoice, 'invoice_metas', $metas );
		update_post_meta( $invoice, 'original_order', $order );
		update_post_meta( $invoice, 'transaction_id', $txn_id );
		update_post_meta( $invoice, 'auth', uniqid() );

		return $invoice;
	}

	/**
	 * Send invoice by mail.
	 *
	 * @param int $invoice_id The invoice ID.
	 *
	 * @todo Add a template for $message in the settings.
	 */
	public static function send_invoice( $invoice_id ) {
		$i_m = get_post_meta( $invoice_id, 'invoice_metas', true );
		if ( empty( $i_m['email'] ) && is_email( $i_m['email'] ) ) {
			return false;
		}//end if
		$invoice_pdf = ctx_get_invoice( $invoice_id, 'F' );
		$attachments = array( $invoice_pdf );
		$opt         = get_option( 'camptix_options' );

		/* translators: The name of the event */
		$subject = apply_filters( 'camptix_invoices_mailsubjet', sprintf( __( 'Your invoice - %s', 'invoices-camptix' ), $opt['event_name'] ), $opt['event_name'] );
		$from    = apply_filters( 'camptix_invoices_mailfrom', get_option( 'admin_email' ) );
		$headers = apply_filters(
			'camptix_invoices_mailheaders', array(
				"From: {$opt['event_name']} <{$from}>",
				'Content-type: text/html; charset=UTF-8',
			)
		);
		$message = array(
			__( 'Hello,', 'invoices-camptix' ),
			// translators: event name.
			sprintf( __( 'As requested during your purchase, please find attached an invoice for your tickets to "%s".', 'invoices-camptix' ), sanitize_text_field( $opt['event_name'] ) ),
			// translators: email.
			sprintf( __( 'Please let us know if we can be of any further assistance at %s.', 'invoices-camptix' ), $from ),
			__( 'Kind regards', 'invoices-camptix' ),
			'',
			// translators: event name.
			sprintf( __( 'The %s team', 'invoices-camptix' ), sanitize_text_field( $opt['event_name'] ) ),
		);
		$message = implode( PHP_EOL, $message );
		$message = '<p>' . nl2br( $message ) . '</p>';
		wp_mail( $i_m['email'], $subject, $message, $headers, $attachments );
	}

	/**
	 * Enqueue assets
	 *
	 * @todo enqueue only on [camptix] shortcode
	 */
	public static function enqueue_assets() {

		$opt = get_option( 'camptix_options' );
		if ( ! empty( $opt['invoice-active'] ) ) {

			wp_register_script( 'invoices-camptix', CTX_INV_ADMIN_URL . '/js/camptix-invoices.js', array( 'jquery' ), CTX_INV_VER, true );
			wp_enqueue_script( 'invoices-camptix' );
			wp_localize_script(
				'invoices-camptix', 'camptixInvoicesVars', array(
					'invoiceDetailsForm' => get_rest_url( null, 'camptix-invoices/v1/invoice-form' ),
				)
			);

		}//end if

		wp_register_style( 'camptix-invoices-css', CTX_INV_ADMIN_URL . '/css/camptix-invoices.css', array(), CTX_INV_VER );
		wp_enqueue_style( 'camptix-invoices-css' );
	}

	/**
	 * Register assets on admin side
	 */
	public static function admin_enqueue_assets() {
		wp_register_script( 'admin-camptix-invoices', CTX_INV_ADMIN_URL . '/js/camptix-invoices-back.js', array( 'jquery' ), CTX_INV_VER, true );
	}

	/**
	 * Attendee invoice information
	 * (also check for missing invoice infos).
	 *
	 * @param array $attendee_info The attendee info.
	 */
	public static function attendee_info( $attendee_info ) {
		global $camptix;
		if ( ! empty( $_POST['camptix-need-invoice'] ) ) { // @codingStandardsIgnoreLine

			if ( empty( $_POST['invoice-email'] )        // @codingStandardsIgnoreLine
			|| empty( $_POST['invoice-name'] )           // @codingStandardsIgnoreLine
			|| empty( $_POST['invoice-address'] )        // @codingStandardsIgnoreLine
			|| ! is_email( $_POST['invoice-email'] ) ) { // @codingStandardsIgnoreLine
				$camptix->error_flag( 'nope' );
			} else {
				$attendee_info['invoice-email']   = sanitize_email( $_POST['invoice-email'] );
				$attendee_info['invoice-name']    = sanitize_text_field( $_POST['invoice-name'] );
				$attendee_info['invoice-address'] = sanitize_textarea_field( $_POST['invoice-address'] );

				$opt = get_option( 'camptix_options' );
				if ( ! empty( $opt['invoice-vat-number'] ) ) {
					$attendee_info['invoice-vat-number'] = sanitize_text_field( $_POST['invoice-vat-number'] );
				}//end if
			}//end if
		}//end if
		return $attendee_info;
	}

	/**
	 * Define custom attributes for an attendee object.
	 *
	 * @param object $attendee      The attendee.
	 * @param array  $attendee_info The attendee info.
	 */
	public static function attendee_object( $attendee, $attendee_info ) {
		if ( ! empty( $attendee_info['invoice-email'] ) ) {
			$attendee->invoice = array(
				'email'   => $attendee_info['invoice-email'],
				'name'    => $attendee_info['invoice-name'],
				'address' => $attendee_info['invoice-address'],
			);

			$opt = get_option( 'camptix_options' );
			if ( ! empty( $opt['invoice-vat-number'] ) ) {
				$attendee->invoice['vat-number'] = $attendee_info['invoice-vat-number'];
			}//end if
		}//end if
		return $attendee;
	}

	/**
	 * Add Invoice meta on an attendee post.
	 *
	 * @param int    $post_id  The post ID.
	 * @param object $attendee The attendee.
	 */
	public static function add_meta_invoice_on_attendee( $post_id, $attendee ) {
		if ( ! empty( $attendee->invoice ) ) {
			update_post_meta( $post_id, 'invoice_metas', $attendee->invoice );
			global $camptix;
			$camptix->log( __( 'This attendee requested an invoice.', 'invoices-camptix' ), $post_id, $attendee->invoice );
		}//end if
	}

	/**
	 * My custom errors flags.
	 */
	public static function error_flag() {

		global $camptix;
		/**
		 * Hack
		 */
		$rp = new ReflectionProperty( 'CampTix_Plugin', 'error_flags' );
		$rp->setAccessible( true );
		$error_flags = $rp->getValue( $camptix );
		if ( ! empty( $error_flags['nope'] ) ) {
			$camptix->error( __( 'As you have requested an invoice, please fill in the required fields.', 'invoices-camptix' ) );
		}//end if
	}

	/**
	 * Display invoice meta on attendee admin page.
	 *
	 * @param array  $rows The rows.
	 * @param object $post The post.
	 */
	public static function add_invoice_meta_on_attendee_metabox( $rows, $post ) {
		$invoice_meta = get_post_meta( $post->ID, 'invoice_metas', true );
		if ( ! empty( $invoice_meta ) ) {
			$rows[] = array( __( 'Requested an invoice', 'invoices-camptix' ), __( 'Yes', 'invoices-camptix' ) );
			$rows[] = array( __( 'Invoice recipient', 'invoices-camptix' ), $invoice_meta['name'] );
			$rows[] = array( __( 'Invoice to be sent to', 'invoices-camptix' ), $invoice_meta['email'] );
			$rows[] = array( __( 'Customer address', 'invoices-camptix' ), $invoice_meta['address'] );

			$opt = get_option( 'camptix_options' );
			if ( ! empty( $opt['invoice-vat-number'] ) ) {
				$rows[] = array( __( 'VAT number', 'invoices-camptix' ), $invoice_meta['vat-number'] );
			}//end if
		} else {
			$rows[] = array( __( 'Requested an invoice', 'invoices-camptix' ), __( 'No', 'invoices-camptix' ) );
		}//end if
		return $rows;
	}
}
