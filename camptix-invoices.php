<?php

/**
 * Plugin name: Camptix Invoices
 */

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

define( 'CTX_INV_VER', time() );

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
			add_filter( 'camptix_setup_sections', array( __CLASS__, 'invoice_settings_tab' ) );
			add_action( 'camptix_menu_setup_controls', array( __CLASS__, 'invoice_settings' ) );
			add_filter( 'camptix_validate_options', array( __CLASS__, 'validate_options' ), 10, 2 );
			add_action( 'camptix_payment_result', array( __CLASS__, 'maybe_create_invoice' ), 10, 3 );
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		}

		/**
		 * Add a new tab in camptix settings
		 */
		static function invoice_settings_tab( $sections ) {
			$sections['invoice'] = __( 'Facturation' );
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
			add_settings_section( 'invoice', __( 'Réglages des factures' ), '__return_false', 'camptix_options' );
			global $camptix;
			$camptix->add_settings_field_helper( 'invoice-new-year-reset', 'Réinitialisation annuelle', 'field_yesno' ,'invoice', 
				sprintf( __( 'Les numéros de facture sont préfixés par l’année, et seront réinitialisés le premier janvier. (ex: %1$s-125)' ), date( 'Y' ) )
			);
			add_settings_field( 'invoice-current-number', 'Prochaine facture', array( __CLASS__, 'current_number' ), 'camptix_options', 'invoice', array(
				'id'    => 'invoice-current-number',
				'value' => isset( $opt['invoice-current-number'] ) ? $opt['invoice-current-number'] : 1,
				'yearly' => isset( $opt['invoice-new-year-reset'] ) ? $opt['invoice-new-year-reset'] : false
			) );
		}

		/**
		 * Next invoice number setting
		 */
		static function current_number_callback( $args ) {
			vprintf( '<p>' . __( 'La prochaine facture portera le numéro' ) . ' %3$s<input type="number" min="1" value="%2$d" name="camptix_options[%1$s]" class="small-text">%4$s</p>', array(
				esc_attr( $args['id'] ),
				esc_attr( $args['value'] ),
				$args['yearly'] ? '<code>' . date( 'Y-' ) : '',
				$args['yearly'] ? '</code>' : '',
			) );
		}

		/**
		 * Validate our custom options
		 */
		static function validate_options( $output, $input ) {
			if ( isset( $input['invoice-new-year-reset'] ) ) {
				$output['invoice-new-year-reset'] = intval( $input['invoice-new-year-reset'] );
			}
			if ( ! empty( $input['invoice-current-number'] ) ) {
				$output['invoice-current-number'] = (int) $input['invoice-current-number'];
			}
			return $output;
		}

		/**
		 * Attach invoice to email
		 * @todo find another way, don't work
		 */
		function maybe_attach_invoice( $type, $attendee ) {
			if ( 'email_template_pending_succeeded' !== $type ) {
				return;
			}
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
				'post_type' => 'tix_attendee',
				'post_status' => 'any',
				'meta_query' => array(
					array(
						'key' => 'tix_payment_token',
						'compare' => '=',
						'value' => $payment_token,
						'type' => 'CHAR',
					),
				),
			) );
			if ( ! $attendees ) {
				return;
			}
			
			$receipt_email = get_post_meta( $attendees[0]->ID, 'tix_receipt_email', true );
			$order = get_post_meta( $attendees[0]->ID, 'tix_order', true );
			CampTix_Addon_Invoices::create_invoice( $attendees[0], $order, $receipt_email );
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
				if ( $opt['invoice-current-year'] != $year ) {
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
		 * @todo Save invoice
		 */
		static function create_invoice( $attendee, $order, $receipt_email ) {
			$number = CampTix_Addon_Invoices::create_invoice_number();
		}

		/**
		 * Enqueue assets
		 * @todo enqueue only on [camptix] shortcode
		 */
		static function enqueue_assets() {
			wp_register_script( 'camptix-invoices', plugins_url( 'camptix-invoices.js', __FILE__ ), array( 'jquery' ), true );
			wp_enqueue_script( 'camptix-invoices' );
		}
	}
	camptix_register_addon( 'CampTix_Addon_Invoices' );

	add_action( 'init', 'register_tix_invoices' );
}

/**
 * Register invoice CPT
 */
function register_tix_invoices() {
	register_post_type( 'tix_invoices', array(
		'label'        => __( 'Factures' ),
		'labels' => array(
			'name' => __( 'Factures' ),
		),
		'public'       => true,
		'show_ui'      => true,
		'show_in_menu' => 'edit.php?post_type=tix_ticket',
	) );
}
