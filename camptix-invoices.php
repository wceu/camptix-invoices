<?php

/**
 * Plugin name: Camptix Invoices
 * Description: Allow Camptix user to send invoices when a attendee buy a ticket
 * Version: 1.0.0
 * Author: Willy Bahuaud, Simon Janin
 * Author URI: https://2018.wptech.io
 * Text Domain: camptix-invoices
 */

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

define( 'CTX_INV_VER', '1.0.0' );

/**
 * Load invoice addon
 */
add_action( 'camptix_load_addons', 'load_camptix_invoices' );
function load_camptix_invoices() {
	class CampTix_Addon_Invoices extends \CampTix_Addon {
		/**
		 * Init invoice addon
		 */
		function camptix_init() {
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
		 * Add a new tab in camptix settings
		 */
		static function invoice_settings_tab( $sections ) {
			$sections['invoice'] = __( 'Facturation', 'camptix-invoices' );
			return $sections;
		}

		/**
		 * Tab content
		 */
		static function invoice_settings( $section ) {
			if ( 'invoice' !== $section ) {
				return false;
			}
			$opt = get_option( 'camptix_options' );
			add_settings_section( 'invoice', __( 'Réglages des factures', 'camptix-invoices' ), '__return_false', 'camptix_options' );
			global $camptix;
			$camptix->add_settings_field_helper( 'invoice-new-year-reset', 'Réinitialisation annuelle', 'field_yesno' ,'invoice', 
				sprintf( __( 'Les numéros de facture sont préfixés par l’année, et seront réinitialisés le premier janvier. (ex: %1$s-125)', 'camptix-invoices' ), date( 'Y' ) )
			);
			add_settings_field( 'invoice-current-number', 'Prochaine facture', array( __CLASS__, 'current_number_callback' ), 'camptix_options', 'invoice', array(
				'id'    => 'invoice-current-number',
				'value' => isset( $opt['invoice-current-number'] ) ? $opt['invoice-current-number'] : 1,
				'yearly' => isset( $opt['invoice-new-year-reset'] ) ? $opt['invoice-new-year-reset'] : false
			) );
			add_settings_field( 'invoice-logo', __( 'Logo', 'camptix-invoices' ), array( __CLASS__, 'type_file_callback' ), 'camptix_options', 'invoice', array(
				'id'    => 'invoice-logo',
				'value' => ! empty( $opt['invoice-logo'] ) ? $opt['invoice-logo'] : '',
			) );
			$camptix->add_settings_field_helper( 'invoice-company', __( 'Adresse de l’organisme', 'camptix-invoices' ), 'field_textarea' ,'invoice');
			$camptix->add_settings_field_helper( 'invoice-cgv', __( 'CGV', 'camptix-invoices' ), 'field_textarea' ,'invoice');
			$camptix->add_settings_field_helper( 'invoice-thankyou', __( 'Mot en dessous du total', 'camptix-invoices' ), 'field_textarea' ,'invoice');
		}

		/**
		 * Next invoice number setting
		 */
		static function current_number_callback( $args ) {
			vprintf( '<p>' . __( 'La prochaine facture portera le numéro', 'camptix-invoices' ) . ' %3$s<input type="number" min="1" value="%2$d" name="camptix_options[%1$s]" class="small-text">%4$s</p>', array(
				esc_attr( $args['id'] ),
				esc_attr( $args['value'] ),
				$args['yearly'] ? '<code>' . date( 'Y-' ) : '',
				$args['yearly'] ? '</code>' : '',
			) );
		}

		/**
		 * Input type file
		 */
		static function type_file_callback( $args ) {
			wp_enqueue_media();
			wp_enqueue_script( 'admin-camptix-invoices' );
			wp_localize_script( 'admin-camptix-invoices', 'camptixInvoiceBackVars', array(
				'selectText'  => __( 'Sélectionner un logo a télécharger', 'camptix-invoices' ),
				'selectImage' => __( 'Choisir ce logo', 'camptix-invoices' ),
			) );

			vprintf( '<div class="camptix-media"><div class="camptix-invoice-logo-preview-wrapper" data-imagewrapper>
				%4$s
			</div>
			<input data-set type="button" class="button button-secondary" value="%3$s" />
			<input data-unset type="button" class="button button-secondary" value="%5$s"%6$s/>
			<input type="hidden" name=camptix_options[%1$s] data-field="image_attachment" value="%2$s"></div>', array(
				esc_attr( $args['id'] ),
				esc_attr( $args['value'] ),
				esc_attr__( 'Choisir un logo', 'camptix-invoices' ),
				! empty( $args['value'] ) ? wp_get_attachment_image( $args['value'], 'thumbnail', '', array() ) : '',
				esc_attr__( 'Retirer le logo', 'camptix-invoices' ),
				empty( $args['value'] ) ? ' style="display:none;"' : '',
			) );
		}

		/**
		 * Validate our custom options
		 */
		static function validate_options( $output, $input ) {
			if ( isset( $input['invoice-new-year-reset'] ) ) {
				$output['invoice-new-year-reset'] = (int) $input['invoice-new-year-reset'];
			}
			if ( ! empty( $input['invoice-current-number'] ) ) {
				$output['invoice-current-number'] = (int) $input['invoice-current-number'];
			}
			if ( isset( $input['invoice-logo'] ) ) {
				$output['invoice-logo'] = (int) $input['invoice-logo'];
			}
			if ( isset( $input['invoice-company'] ) ) {
				$output['invoice-company'] = sanitize_textarea_field( $input['invoice-company'] );
			}
			if ( isset( $input['invoice-cgv'] ) ) {
				$output['invoice-cgv'] = sanitize_textarea_field( $input['invoice-cgv'] );
			}
			if ( isset( $input['invoice-thankyou'] ) ) {
				$output['invoice-thankyou'] = sanitize_textarea_field( $input['invoice-thankyou'] );
			}
			return $output;
		}

		/**
		 * Listen payment result to create invoice
		 */
		static function maybe_create_invoice( $payment_token, $result, $data ) {
			if ( 2 !== $result ) {
				return;
			}

			$attendees = get_posts( array(
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
			) );
			if ( ! $attendees ) {
				return;
			}
			if ( $metas = get_post_meta( $attendees[0]->ID, 'invoice_metas', true ) ) {
				$order = get_post_meta( $attendees[0]->ID, 'tix_order', true );
				$invoice_id = CampTix_Addon_Invoices::create_invoice( $attendees[0], $order, $metas );
				if ( ! is_wp_error( $invoice_id ) && ! empty( $invoice_id ) ) {
					CampTix_Addon_Invoices::send_invoice( $invoice_id );
				}
			}
		}

		/**
		 * Get, increment and return invoice number
		 * @todo can be refactorized
		 */
		static function create_invoice_number() {
			$opt = get_option( 'camptix_options' );
			$current = ! empty( $opt['invoice-current-number'] ) ? intval( $opt['invoice-current-number'] ) : 1;
			$year = date( 'Y' );

			if ( ! empty( $opt['invoice-new-year-reset'] ) ) {
				if ( ! empty( $opt['invoice-current-year'] ) && $opt['invoice-current-year'] != $year ) {
					$opt['invoice-current-number'] = 1;
					$current = 1;
				}
				$current = sprintf( '%s-%s', $year, $current );
			}
			
			$opt['invoice-current-year'] = $year;
			$opt['invoice-current-number']++;
			update_option( 'camptix_options', $opt );
			return $current;
		}

		/**
		 * Create invoice
		 * @todo Faire une liaison entre la facture et les participants
		 */
		static function create_invoice( $attendee, $order, $metas ) {
			$number = CampTix_Addon_Invoices::create_invoice_number();

			// Prevent assign invoice_number twice
			remove_action( 'publish_tix_invoice', 'ctx_assign_invoice_number', 10 );

			$arr = array(
				'post_type'   => 'tix_invoice',
				'post_status' => 'publish',
				'post_title'  => sprintf( __( 'Facture n°%1$s de la commande %2$s du %3$s', 'camptix-invoices' ), $number, get_post_meta( $attendee->ID, 'tix_transaction_id', true ), get_the_time( 'd/m/Y', $attendee ) ),
				'post_name'   => sprintf( 'invoice-%s', $number ),
			);
			$invoice = wp_insert_post( $arr );
			if ( ! $invoice || is_wp_error( $invoice ) ) {
				return;
			}
			update_post_meta( $invoice, 'invoice_number', $number );
			update_post_meta( $invoice, 'invoice_metas', $metas );
			update_post_meta( $invoice, 'original_order', $order );
			update_post_meta( $invoice, 'auth', uniqid() );

			return $invoice;
		}

		/**
		 * Send invoice by mail
		 */
		static function send_invoice( $invoice_id ) {
			$i_m = get_post_meta( $invoice_id, 'invoice_metas', true );
			if ( empty( $i_m['email'] ) && is_email( $i_m['email'] ) ) {
				return false;
			}
			$invoice_pdf = ctx_get_invoice( $invoice_id, 'F' );
			$attachments = array( $invoice_pdf );
			$opt         = get_option( 'camptix_options' );
			$subject     = apply_filters( 'camptix-invoices-mailsubjet', sprintf( __( 'Votre facture – %s', 'camptix-invoices' ), $opt['event_name'] ), $opt['event_name'] );
			$from        = apply_filters( 'camptix-invoices-mailfrom', get_option( 'admin_email' ) );		
			$headers     = apply_filters( 'camptix-invoices-mailheaders', array(
				"From: {$opt['event_name']} <{$from}>",
				'Content-type: text/html; charset=UTF-8',
			) );
			$message     = array(
				__( 'Bonjour,', 'camptix-invoices' ),
				sprintf( __( 'Comme demandé lors de l’achat, vous trouverez en pièce jointe de cette email la facture de vos billets pour l‘événement « %s ».', 'camptix-invoices' ), sanitize_text_field( $opt['event_name'] ) ),
				sprintf( __( 'En cas de réclamation, vous pouvez contacter notre équipe à l’adresse %s', 'camptix-invoices' ), $from ),
				__( 'Nous vous souhaitons une excellente journée !', 'camptix-invoices' ),
				'',
				sprintf( __( 'L’équipe du %s', 'camptix-invoices' ), sanitize_text_field( $opt['event_name'] ) ),
			);
			$message = implode( PHP_EOL, $message );
			$message = '<p>' . nl2br( $message ) . '</p>';
			wp_mail( $i_m['email'], $subject, $message, $headers, $attachments );
		}

		/**
		 * Enqueue assets
		 * @todo enqueue only on [camptix] shortcode
		 */
		static function enqueue_assets() {
			wp_register_script( 'camptix-invoices', plugins_url( 'camptix-invoices.js', __FILE__ ), array( 'jquery' ), CTX_INV_VER, true );
			wp_enqueue_script( 'camptix-invoices' );
			wp_localize_script( 'camptix-invoices', 'camptixInvoicesVars', array(
				'invoiceDetailsForm' => home_url( '/wp-json/camptix-invoices/v1/invoice-form' ),
			) );
			wp_register_style( 'camptix-invoices-css', plugins_url( 'camptix-invoices.css', __FILE__ ), array(), CTX_INV_VER );
			wp_enqueue_style( 'camptix-invoices-css' );
		}

		/**
		 * Register assets on admin side
		 */
		static function admin_enqueue_assets() {
			wp_register_script( 'admin-camptix-invoices', plugins_url( 'camptix-invoices-back.js', __FILE__ ), array( 'jquery' ), CTX_INV_VER, true );
		}

		/**
		 * Attendee invoice information
		 * (also check for missing invoice infos)
		 */
		static function attendee_info( $attendee_info ) {
			global $camptix;
			if ( ! empty( $_POST['camptix-need-invoice'] ) ) {
				if ( empty( $_POST['invoice-email'] )
				  || empty( $_POST['invoice-name'] )
				  || empty( $_POST['invoice-address'] )
				  || ! is_email( $_POST['invoice-email'] )
				) {
					$camptix->error_flag( 'fuck' );
				} else {
					$attendee_info['invoice-email'] = sanitize_email( $_POST['invoice-email'] );
					$attendee_info['invoice-name'] = sanitize_text_field( $_POST['invoice-name'] );
					$attendee_info['invoice-address'] = sanitize_textarea_field( $_POST['invoice-address'] );
				}
			}
			return $attendee_info;
		}

		/**
		 * Define custom attributes for an attendee object
		 */
		static function attendee_object( $attendee, $attendee_info ) {
			if ( ! empty( $attendee_info['invoice-email'] ) ) {
				$attendee->invoice = array(
					'email'   => $attendee_info['invoice-email'],
					'name'    => $attendee_info['invoice-name'],
					'address' => $attendee_info['invoice-address'],
				);
			}
			return $attendee;
		}

		/**
		 * Add Invoice meta on an attendee post
		 */
		static function add_meta_invoice_on_attendee( $post_id, $attendee ) {
			if ( ! empty( $attendee->invoice ) ) {
				update_post_meta( $post_id, 'invoice_metas', $attendee->invoice );
				global $camptix;
				$camptix->log( __( 'Le participant a demandé une facture.', 'camptix-invoices' ), $post_id, $attendee->invoice );
			}
		}

		/**
		 * My custom errors flags
		 */
		static function error_flag() {
			
			global $camptix;
			/**
			 * Hack
			 */
			$rp = new ReflectionProperty( 'CampTix_Plugin', 'error_flags' );
			$rp->setAccessible( true );
			$error_flags = $rp->getValue( $camptix );
			if ( ! empty( $error_flags['fuck'] ) ) {
				$camptix->error( __( 'Vous avez demandé une facture, il faut donc complèter les champs requis.', 'camptix-invoices' ) );
			}
		}

		/**
		 * Display invoice meta on attendee admin page
		 */
		static function add_invoice_meta_on_attendee_metabox( $rows, $post ) {
			$invoice_meta = get_post_meta( $post->ID, 'invoice_metas', true );
			if ( ! empty( $invoice_meta ) ) {
				$rows[] = array( __( 'A demandé une facture', 'camptix-invoices' ), __( 'Oui' ) );
				$rows[] = array( __( 'Destinataire de la facture', 'camptix-invoices' ), $invoice_meta['name'] );
				$rows[] = array( __( 'Facture à envoyer à', 'camptix-invoices' ), $invoice_meta['email'] );
				$rows[] = array( __( 'Adresse du client', 'camptix-invoices' ), $invoice_meta['address'] );
			} else {
				$rows[] = array( __( 'A demandé une facture', 'camptix-invoices' ), __( 'Non' ) );				
			}
			return $rows;
		}
	}
	camptix_register_addon( 'CampTix_Addon_Invoices' );

	add_action( 'init', 'register_tix_invoice' );
}

/**
 * Register invoice CPT
 */
function register_tix_invoice() {
	register_post_type( 'tix_invoice', array(
		'label'        => __( 'Factures', 'camptix-invoices' ),
		'labels' => array(
			'name' => __( 'Factures', 'camptix-invoices' ),
		),
		'supports'     => array( 'title' ),
		'public'       => false,
		'show_ui'      => true,
		'show_in_menu' => 'edit.php?post_type=tix_ticket',
	) );
}

/**
 * Display an invoice button
 */
add_action( 'post_submitbox_misc_actions', 'ctx_invoice_link' );
function ctx_invoice_link( $post ) {
	if ( 'tix_invoice' !== $post->post_type || $post->post_status !== 'publish' ) {
		return false;
	}
	$invoice_number = get_post_meta( $post->ID, 'invoice_number', true );
	$auth = get_post_meta( $post->ID, 'auth', true );
	vprintf( '<div class="misc-pub-section"><p>%3$s <strong>%4$s</strong></p><a href="%s" class="button button-secondary" target="_blank">%2$s</a></div>',
		array(
			admin_url( 'admin-post.php?action=camptix-invoice.get&invoice_id=' . $post->ID . '&invoice_auth=' . $auth ),
			esc_html__( 'Imprimer la facture', 'camptix-invoices' ),
			esc_html__( 'Numero de facture :', 'camptix-invoices' ),
			esc_attr( $invoice_number ),
		) );
}

/**
 * Register metabox on invoices
 */
add_action( 'add_meta_boxes_tix_invoice', 'ctx_register_invoice_metabox' );
function ctx_register_invoice_metabox( $post ) {
	if ( 'publish' === $post->post_status ) {
		add_meta_box( 'ctx_invoice_metabox', 'Informations', 'ctx_invoice_metabox_sent', 'tix_invoice', 'normal', 'high' );
	} else {
		add_meta_box( 'ctx_invoice_metabox', 'Informations', 'ctx_invoice_metabox_editable', 'tix_invoice', 'normal', 'high' );
	}
}

/**
 * Metabox for edible invoice (not published)
 */
function ctx_invoice_metabox_editable( $args ) {
	$order = get_post_meta( $args->ID, 'original_order', true );
	$metas = get_post_meta( $args->ID, 'invoice_metas', true );
	wp_nonce_field( 'edit-invoice-' . get_current_user_id() . '-' . $args->ID, 'edit-invoice' );
	echo '<h3>' . esc_html__( 'Détails de la commande', 'camptix-invoices' ) . '</h3>';
	$item_line = '<tr>
		<td><input type="text" value="%2$s" name="order[items][%1$d][name]" class="widefat"></td><!-- name -->
		<td><input type="number" min="0" value="%3$.2f" name="order[items][%1$d][price]" class="widefat"></td><!-- price -->
		<td><input type="number" min="0" value="%4$s" name="order[items][%1$d][quantity]" class="widefat"></td><!-- qty -->
		</tr>';
	vprintf( '<table class="widefat"><thead><tr>
		<th>%1$s</th>
		<th>%2$s</th>
		<th>%3$s</th>
		</tr></thead><tbody>', array(
			__( 'Titre', 'camptix-invoices' ),
			__( 'Prix unitaire', 'camptix-invoices' ),
			__( 'Quantité', 'camptix-invoices' ),
	) );
	foreach ( $order['items'] as $k => $item ) {
		vprintf( $item_line, array(
			$k,
			$item['name'],
			$item['price'],
			$item['quantity'],
			) );
	}
	vprintf( $item_line, array(
		count( $order['items'] ) + 1,
		'',
		'',
		'',
		) );
	echo '</tbody></table>';
	vprintf( '<table class="form-table">
		<tr><th scope="row"><label for="order[total]">%1$s</label></th>
		<td><input
		type="number"
		min="0"
		value="%2$.2f"
		name="order[total]"
		id="order[total]"/></td></tr>
		<tr><th scope="row"><label for="invoice_metas[name]">%3$s</label></th>
		<td><input name="invoice_metas[name]" id="invoice_metas[name]" value="%4$s" type="texte" class="widefat"/><td></tr>
		<tr><th scope="row"><label for="invoice_metas[email]">%5$s</label></th>
		<td><input name="invoice_metas[email]" id="invoice_metas[email]" value="%6$s" type="email" class="widefat"/><td></tr>
		<tr><th scope="row"><label for="invoice_metas[address]">%7$s</label></th>
		<td><textarea name="invoice_metas[address]" id="invoice_metas[address]" class="widefat">%8$s</textarea><td></tr>
		</table>', array( 
			esc_html__( 'Montant total', 'camptix-invoices' ),
			esc_attr( $order['total'] ),
			esc_html__( 'Client', 'camptix-invoices' ),
			esc_attr( $metas['name'] ),
			esc_html__( 'Email de contact', 'camptix-invoices' ),
			esc_attr( $metas['email'] ),
			esc_html__( 'Adresse du client', 'camptix-invoices' ),
			esc_textarea( $metas['address'] ),
		) );
}

/**
 * Metabox for published invoices
 */
function ctx_invoice_metabox_sent( $args ) {
	$order = get_post_meta( $args->ID, 'original_order', true );
	$metas = get_post_meta( $args->ID, 'invoice_metas', true );
	echo '<h3>' . esc_html__( 'Détails de la commande', 'camptix-invoices' ) . '</h3>';
	$item_line = '<tr>
		<td>%1$s</td><!-- name -->
		<td>%2$.2f</td><!-- price -->
		<td>%3$s</td><!-- qty -->
		</tr>';
	vprintf( '<table class="widefat"><thead><tr>
		<th>%1$s</th>
		<th>%2$s</th>
		<th>%3$s</th>
		</tr></thead><tbody>', array(
			__( 'Titre', 'camptix-invoices' ),
			__( 'Prix unitaire', 'camptix-invoices' ),
			__( 'Quantité', 'camptix-invoices' ),
	) );
	foreach ( $order['items'] as $k => $item ) {
		vprintf( $item_line, array(
			$item['name'],
			$item['price'],
			$item['quantity'],
			) );
	}
	echo '</tbody></table>';
	vprintf( '<table class="form-table"><tr><th scope="row">%1$s</th>
		<td>%2$.2f</td></tr>
		<tr><th scope="row">%3$s</th>
		<td>%4$s<td></tr>
		<tr><th scope="row">%5$s</th>
		<td>%6$s<td></tr>
		<tr><th scope="row">%7$s</th>
		<td>%8$s<td></tr>
		</table>', array(
			esc_html__( 'Montant total', 'camptix-invoices' ),
			esc_html( $order['total'] ),
			esc_html__( 'Client', 'camptix-invoices' ),
			esc_html( $metas['name'] ),
			esc_html__( 'Email de contact', 'camptix-invoices' ),
			esc_html( $metas['email'] ),
			esc_html__( 'Adresse du client', 'camptix-invoices' ),
			wp_kses( nl2br( $metas['address'] ), array( 'br' => true ) ),
		) );
}

/**
 * Save invoice metabox
 */
add_action( 'save_post_tix_invoice', 'ctx_save_invoice_details', 10, 2 );
function ctx_save_invoice_details( $post_id, $post ) {
	if ( ! isset( $_POST['edit-invoice'] ) ) {
		return;
	}
	check_admin_referer( 'edit-invoice-' . $_POST['user_ID'] . '-' . $_POST['post_ID'], 'edit-invoice' );
	// Filter items to save
	$order = $_POST['order'];
	$items = array();
	foreach ( $order['items'] as $item ) {
		if ( ! empty( $item['name'] ) && ! empty( $item['quantity'] ) ) {
			$items[] = $item;
		}
	}
	$order['items'] = $items;
	update_post_meta( $post_id, 'original_order', $order );
	update_post_meta( $post_id, 'invoice_metas', $_POST['invoice_metas'] );
}

/**
 * Assign invoice number on status transitions to PUBLISH
 */
add_action( 'publish_tix_invoice', 'ctx_assign_invoice_number', 10, 2 );
function ctx_assign_invoice_number( $id, $post ) {
	if ( ! get_post_meta( $id, 'invoice_number', true ) ) {
		$number = CampTix_Addon_Invoices::create_invoice_number();
		update_post_meta( $id, 'invoice_number', $number );
	}
}

/**
 * Disallow an invoice to be edit after publish
 */
add_action( 'pre_post_update', 'ctx_dissallow_invoice_edit', 10, 2 );
function ctx_dissallow_invoice_edit( $post_id, $data ) {
	if ( 'tix_invoice' !== get_post_type( $post_id ) ) {
		return;
	}

	$status = get_post_status( $post_id );
    if ( $status === 'publish' ) {
		wp_die( __( 'Il n’est pas possible de modifier une facture déjà publiée.', 'camptix-invoices' ) );
	}
}

/**
 * Register REST API endpoint to serve invoice details form
 */
add_action( 'rest_api_init', function () {
	register_rest_route( 'camptix-invoices/v1', '/invoice-form', array(
		'methods'  => 'GET',
		'callback' => 'ctx_invoice_form',
	) );
} );

function ctx_invoice_form() {
	$fields = array();
	$fields['main' ]  = '<input type="checkbox" value="1" name="camptix-need-invoice" id="camptix-need-invoice"/> <label for="camptix-need-invoice">' . __( 'J’ai besoin d’une facture', 'camptix-invoices' ) . '</label>';
	$fields['hidden'][] = '<td class="tix-left"><label for="invoice-email">' . __( 'Email pour recevoir la facture', 'camptix-invoices' ) . ' <span class="tix-required-star">*</span></label></td>
		<td class="tix-right"><input type="text" name="invoice-email" id="invoice-email" pattern="^[a-zA-Z0-9_.+-]+@[a-zA-Z0-9-]+.[a-zA-Z0-9-.]+$"></td>';
	$fields['hidden'][] = '<td class="tix-left"><label for="invoice-name">' . __( 'Nom de facturation', 'camptix-invoices' ) . ' <span class="tix-required-star">*</span></label></td>
		<td class="tix-right"><input type="text" name="invoice-name" id="invoice-name"></td>';
	$fields['hidden'][] = '<td class="tix-left"><label for="invoice-address">' . __( 'Adresse de facturation', 'camptix-invoices' ) . ' <span class="tix-required-star">*</span></label></td>
		<td class="tix-right"><textarea name="invoice-address" id="invoice-address" rows="2"></textarea></td>';
	$fields = apply_filters( 'camptix-invoices/invoice-details-form-fields', $fields );
	$fields_formatted = $fields['main'] . '<table class="camptix-invoice-details tix_tickets_table tix_invoice_table"><tbody><tr>' . implode( '</tr><tr>', $fields[ 'hidden'] ) . '</tr></tbody></table>';
	$form = apply_filters( 'camptix-invoice/invoice-details-form', '<div class="camptix-invoice-toggle-wrapper">' . $fields_formatted . '</div>', $fields );
	wp_send_json( array( 'form' => $form ) );
}

/**
 * Add an admin_post endpoint to get an invoice
 * @todo générer la facture
 */
add_action( 'admin_post_nopriv_camptix-invoice.get', 'ctx_download_invoice' );
add_action( 'admin_post_camptix-invoice.get', 'ctx_download_invoice' );
function ctx_download_invoice() {
	if ( ! $invoice = ctx_can_get_invoice() ) {
		wp_die( __( 'Vous ne pouvez pas accéder à cette facture' ) );
	}
	ctx_get_invoice( $invoice );
}

/**
 * Generate a PDF invoice
 */
function ctx_get_invoice( $invoice, $target = 'D' ) {
	$obj = get_post( $invoice );
	$order = get_post_meta( $invoice, 'original_order', true );
	$metas = get_post_meta( $invoice, 'invoice_metas', true );
	$invoice_number = sanitize_title( get_post_meta( $invoice, 'invoice_number', true ) );
	$opt = get_option( 'camptix_options' );
	$currency = esc_html( $opt['currency'] );
	require( 'fpdf/facturePDF.php' );
	// #1 Initialize the basic information
	//
	// address of the company issuing the invoice
	$address = __( 'Organisateur :', 'camptix-invoices' ) . PHP_EOL . $opt['invoice-company'];
	$thank_you = $opt['invoice-thankyou'];
	// customer address
	$customerAddress = implode( PHP_EOL, array( $metas['name'], $metas['address'], $metas['email'] ) );
	// CGV
	$cgv = $opt['invoice-cgv'];
	// initialize the object invoicePDF
	$pdf = new facturePDF( $address, $customerAddress, $cgv . PHP_EOL . $thank_you );
	// set the logo
	$logo_url = wp_get_attachment_url( $opt['invoice-logo'] );
	$pdf->setLogo( $logo_url );
	// product header
	$pdf->productHeaderAddRow( __( 'Titre', 'camptix-invoices' ), 45, 'L' );
	$pdf->productHeaderAddRow( __( 'Prix unitaire', 'camptix-invoices' ), 45, 'C' );
	$pdf->productHeaderAddRow( __( 'Quantité', 'camptix-invoices' ), 45, 'C' );
	$pdf->productHeaderAddRow( __( 'Total', 'camptix-invoices' ), 45, 'C' );
	// header of the totals
	$pdf->totalHeaderAddRow( 30, 'L' );
	$pdf->totalHeaderAddRow( 30, 'C' );
	// custom element
	$pdf->elementAdd( '', 'traitEnteteProduit', 'content' );
	$pdf->elementAdd( '', 'traitBas', 'footer' );
	
	// #2 Create an invoice
	//
	// invoice title, date, text before the page number
	$invoice_title = sprintf( __( 'Facture n° %s', 'camptix-invoices'), $invoice_number );
	$pdf->initFacture( $invoice_title, date_i18n( '\L\e d F Y', strtotime( $obj->post_date ) ), '' );
	// product
	$items = $order['items'];
	foreach ( $items as $item ) {
		$item_title   = $item['name'];
		$item_price   = number_format_i18n( $item['price'], 2 );
		$item_quatity = $item['quantity'];
		$item_total   = number_format_i18n( $item_price * $item_quatity, 2 );
		$pdf->productAdd( array( $item_title, $item_price, $item_quatity, $item_total ) );
	}
	
	// total line
	$total = number_format_i18n( $order['total'], 2 ) . ' ' . $currency;
	$pdf->totalAdd( array( __( 'Montant total :', 'camptix-invoices' ), $total ) );
	
	// #3 Imports the template
	//
	$template = locate_template( 'gabarit-invoice.php' ) ? locate_template( 'gabarit-invoice.php' ) : 'fpdf/gabarit.php';
	require( $template );
	
	// #4 Finalization
	// build the PDF
	$pdf->buildPDF();
	// download the file
	$invoice_title = 'facture-' . sanitize_title( $invoice_number ) . '.pdf';
	if ( in_array( $target, array( 'D', 'I' ) ) ) {
		$pdf->Output( $invoice_title, $target );
		die();
	} else {
		$upload = wp_upload_dir();
		$upload_dir = $upload['basedir'];
		$upload_dir = $upload_dir . '/camptix-invoices';
		if ( ! is_dir( $upload_dir ) ) {
			mkdir( $upload_dir, 0700 );
		}
		$path = $upload_dir . '/' . $invoice_title;
		$pdf->Output( $path, 'F' );
		return $path;
	}
}

/**
 * Can a request print an invoice ?
 */
function ctx_can_get_invoice() {
	if ( empty( $_REQUEST['invoice_id'] ) || empty( $_REQUEST['invoice_auth'] ) ) {
		return false;
	}
	if ( 'tix_invoice' !== get_post_type( $_REQUEST['invoice_id'] ) ) {
		return false;
	}
	$auth = get_post_meta( (int) $_REQUEST['invoice_id'], 'auth', true );
	if ( $auth !== $_REQUEST['invoice_auth'] ) {
		return false;
	}
	return (int) $_REQUEST['invoice_id'];
}
