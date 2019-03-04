<?php
/**
 * Plugin name: Camptix Invoices
 * Description: Allow Camptix user to send invoices when an attendee buys a ticket
 * Version: 1.0.1
 * Author: Willy Bahuaud, Simon Janin, Antonio Villegas, Mathieu Sarrasin
 * Author URI: https://central.wordcamp.org/
 * Text Domain: invoices-camptix
 *
 * @package Camptix_Invoices
 */

defined( 'ABSPATH' ) || exit;

define( 'CTX_INV_VER', '1.0.1' );
define( 'CTX_INV_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );
define( 'CTX_INV_DIR', untrailingslashit( dirname( __FILE__ ) ) );
define( 'CTX_INV_ADMIN_URL', CTX_INV_URL . '/admin' );

/**
 * Load textdomain
 */
function ctx_invoice_load_textdomain() {
	load_plugin_textdomain( 'invoices-camptix', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'ctx_invoice_load_textdomain' );

/**
 * Load invoice addon.
 */
function load_camptix_invoices() {
	require plugin_dir_path( __FILE__ ) . 'includes/class-camptix-addon-invoices.php';
	camptix_register_addon( 'CampTix_Addon_Invoices' );
	add_action( 'init', 'register_tix_invoice' );
}
add_action( 'camptix_load_addons', 'load_camptix_invoices' );

/**
 * Register invoice CPT.
 */
function register_tix_invoice() {
	register_post_type(
		'tix_invoice',
		array(
			'label'        => __( 'Invoices', 'invoices-camptix' ),
			'labels'       => array(
				'name'           => __( 'Invoices', 'invoices-camptix' ),
				'singular_name'  => _x( 'Invoice', 'Post Type Singular Name', 'invoices-camptix' ),
				'menu_name'      => __( 'Invoices', 'invoices-camptix' ),
				'name_admin_bar' => __( 'Invoice', 'invoices-camptix' ),
				'archives'       => __( 'Invoice Archives', 'invoices-camptix' ),
				'attributes'     => __( 'Invoice Attributes', 'invoices-camptix' ),
				'add_new_item'   => __( 'Add New Invoice', 'invoices-camptix' ),
				'add_new'        => __( 'Add New', 'invoices-camptix' ),
				'new_item'       => __( 'New Invoice', 'invoices-camptix' ),
				'edit_item'      => __( 'Edit Invoice', 'invoices-camptix' ),
				'update_item'    => __( 'Update Invoice', 'invoices-camptix' ),
				'view_item'      => __( 'View Invoice', 'invoices-camptix' ),
				'view_items'     => __( 'View Invoices', 'invoices-camptix' ),
				'search_items'   => __( 'Search Invoices', 'invoices-camptix' ),
			),
			'supports'     => array( 'title' ),
			'public'       => false,
			'show_ui'      => true,
			'show_in_menu' => 'edit.php?post_type=tix_ticket',
		)
	);
}

/**
 * Display an invoice button.
 *
 * @param object $post The post.
 */
function ctx_invoice_link( $post ) {

	if ( 'tix_invoice' !== $post->post_type || 'publish' !== $post->post_status ) {
		return false;
	}//end if

	$invoice_number = get_post_meta( $post->ID, 'invoice_number', true );
	$auth           = get_post_meta( $post->ID, 'auth', true );

	include CTX_INV_DIR . '/includes/views/invoice-download-button.php';
}
add_action( 'post_submitbox_misc_actions', 'ctx_invoice_link' );

/**
 * Register metabox on invoices.
 *
 * @param object $post The post.
 */
function ctx_register_invoice_metabox( $post ) {
	if ( 'publish' === $post->post_status ) {
		add_meta_box(
			'ctx_invoice_metabox',
			esc_html( 'Info', 'invoices-camptix' ),
			'ctx_invoice_metabox_sent',
			'tix_invoice',
			'normal',
			'high'
		);
	} else {
		add_meta_box(
			'ctx_invoice_metabox',
			esc_html( 'Info', 'invoices-camptix' ),
			'ctx_invoice_metabox_editable',
			'tix_invoice',
			'normal',
			'high'
		);
	}//end if
}
add_action( 'add_meta_boxes_tix_invoice', 'ctx_register_invoice_metabox' );

/**
 * Metabox for edible invoice (not published).
 *
 * @param object $args The args.
 */
function ctx_invoice_metabox_editable( $args ) {

	$order              = get_post_meta( $args->ID, 'original_order', true );
	$metas              = get_post_meta( $args->ID, 'invoice_metas', true );
	$opt                = get_option( 'camptix_options' );
	$invoice_vat_number = $opt['invoice-vat-number'];

	if ( ! is_array( $order ) ) {
		$order = array();
	}//end if
	if ( ! is_array( $metas ) ) {
		$metas = array();
	}//end if

	if ( empty( $order['items'] ) || ! is_array( $order['items'] ) ) {
		$order['items'] = array();
	}//end if

	wp_nonce_field( 'edit-invoice-' . get_current_user_id() . '-' . $args->ID, 'edit-invoice' );

	include CTX_INV_DIR . '/includes/views/editable-invoice-metabox.php';
}

/**
 * Metabox for published invoices.
 *
 * @param object $args The args.
 */
function ctx_invoice_metabox_sent( $args ) {

	$order              = get_post_meta( $args->ID, 'original_order', true );
	$metas              = get_post_meta( $args->ID, 'invoice_metas', true );
	$opt                = get_option( 'camptix_options' );
	$invoice_vat_number = $opt['invoice-vat-number'];
	$txn_id             = isset( $metas['transaction_id'] ) ? $metas['transaction_id'] : '';

	include CTX_INV_DIR . '/includes/views/sent-invoice-metabox.php';
}

/**
 * Save invoice metabox.
 *
 * @param int $post_id The post ID.
 */
function ctx_save_invoice_details( $post_id ) {
	if ( ! isset( $_POST['edit-invoice'] ) ) {
		return;
	}//end if

	check_admin_referer( 'edit-invoice-' . $_POST['user_ID'] . '-' . $_POST['post_ID'], 'edit-invoice' );

	// Filter items to save.
	$order = $_POST['order'];
	$items = array();
	foreach ( $order['items'] as $item ) {
		if ( ! empty( $item['name'] ) && ! empty( $item['quantity'] ) ) {
			$items[] = $item;
		}//end if
	}//end foreach
	$order['items'] = $items;
	update_post_meta( $post_id, 'original_order', $order );
	update_post_meta( $post_id, 'invoice_metas', $_POST['invoice_metas'] );
}
add_action( 'save_post_tix_invoice', 'ctx_save_invoice_details', 10, 2 );

/**
 * Assign invoice number on status transitions to PUBLISH
 *
 * @param int $id The id.
 */
function ctx_assign_invoice_number( $id ) {
	if ( ! get_post_meta( $id, 'invoice_number', true ) ) {
		$number = CampTix_Addon_Invoices::create_invoice_number();
		update_post_meta( $id, 'invoice_number', $number );
	}//end if
}
add_action( 'publish_tix_invoice', 'ctx_assign_invoice_number', 10, 2 );

/**
 * Assign invoice auth on status transitions to PUBLISH
 *
 * @param int $id The id.
 */
function ctx_assign_invoice_auth( $id ) {
	if ( ! get_post_meta( $id, 'auth', true ) ) {
		update_post_meta( $id, 'auth', uniqid() );
	}//end if
}
add_action( 'publish_tix_invoice', 'ctx_assign_invoice_auth', 10, 2 );

/**
 * Disallow an invoice to be edit after publish.
 *
 * @param int $post_id The post id.
 */
function ctx_dissallow_invoice_edit( $post_id ) {
	if ( 'tix_invoice' !== get_post_type( $post_id ) ) {
		return;
	}//end if

	$status = get_post_status( $post_id );
	if ( 'publish' === $status ) {
		wp_die( esc_html__( 'Published invoices cannot be edited.', 'invoices-camptix' ) );
	}//end if
}
add_action( 'pre_post_update', 'ctx_dissallow_invoice_edit', 10, 2 );

/**
 * Register REST API endpoint to serve invoice details form
 */
function ctx_register_form_route() {
	$opt = get_option( 'camptix_options' );
		if ( ! empty( $opt['invoice-active'] ) ) {
			register_rest_route(
				'camptix-invoices/v1',
				'/invoice-form',
				array(
					'methods'  => 'GET',
					'callback' => 'ctx_invoice_form',
				)
			);
		}//end if
}
add_action( 'rest_api_init', 'ctx_register_form_route' );

/**
 * Invoice form generator.
 */
function ctx_invoice_form() {

	$opt                = get_option( 'camptix_options' );
	$invoice_vat_number = $opt['invoice-vat-number'];

	ob_start();
	include CTX_INV_DIR . '/includes/views/invoice-form.php';
	$form = ob_get_clean();

	wp_send_json( array( 'form' => $form ) );
}

/**
 * Add an admin_post endpoint to get an invoice.
 *
 * @todo generate the invoice
 */
function ctx_download_invoice() {
	$invoice = ctx_can_get_invoice();
	if ( ! $invoice ) {
		wp_die( esc_html__( 'You do not have access to this invoice', 'invoices-camptix' ) );
	}//end if
	ctx_get_invoice( $invoice );
}
add_action( 'admin_post_nopriv_camptix-invoice.get', 'ctx_download_invoice' );
add_action( 'admin_post_camptix-invoice.get', 'ctx_download_invoice' );

/**
 * Generate a PDF invoice.
 *
 * @param int    $invoice The invoice id.
 * @param string $target  The target.
 */
function ctx_get_invoice( $invoice, $target = 'D' ) {
	$obj            = get_post( $invoice );
	$order          = get_post_meta( $invoice, 'original_order', true );
	$metas          = get_post_meta( $invoice, 'invoice_metas', true );
	$invoice_number = sanitize_title( get_post_meta( $invoice, 'invoice_number', true ) );
	$opt            = get_option( 'camptix_options' );
	$currency       = esc_html( $opt['currency'] );

	require 'includes/lib/fpdf/invoicePDF.php';

	// #1 Initialize the basic information.
	//
	// address of the company issuing the invoice.
	$address   = __( 'From:', 'invoices-camptix' ) . PHP_EOL . $opt['invoice-company'];
	$thank_you = $opt['invoice-thankyou'];

	// customer address.
	$args = array( $metas['name'], $metas['address'], $metas['email'] );
	$opt  = get_option( 'camptix_options' );
	if ( ! empty( $opt['invoice-vat-number'] ) ) {
		array_push( $args, __( 'VAT no:', 'invoices-camptix' ) . ' ' . $metas['vat-number'] );
	}//end if

	$customer_address = __( 'To:', 'invoices-camptix' ) . PHP_EOL . implode( PHP_EOL, $args );

	// CGV.
	$cgv = $opt['invoice-tac'];

	// initialize the object invoicePDF.
	$pdf = new invoicePDF( $address, $customer_address, get_bloginfo( 'name' ) );

	// set the logo.
	$logo_url      = wp_get_attachment_url( $opt['invoice-logo'] );
	$logo_metadata = wp_get_attachment_metadata( $opt['invoice-logo'] );
	$pdf->setLogo( $logo_url, $logo_metadata->width );

	// product header.
	$pdf->productHeaderAddRow( __( 'Title', 'invoices-camptix' ), 75, 'L' );
	$pdf->productHeaderAddRow( __( 'Quantity', 'invoices-camptix' ), 25, 'R' );
	$pdf->productHeaderAddRow( __( 'Unit Price', 'invoices-camptix' ), 30, 'R' );
	$pdf->productHeaderAddRow( __( 'Total Price', 'invoices-camptix' ), 30, 'R' );

	// header of the totals.
	$pdf->totalHeaderAddRow( 30, 'L' );
	$pdf->vatTotalHeaderAddRow( 30, 'L' );

	// custom element.
	$pdf->elementAdd( '', 'traitEnteteProduit', 'content' );
	$pdf->elementAdd( '', 'traitBas', 'footer' );

	// #2 Create an invoice
	//
	// invoice title, date, text before the page number.
	// translators: invoice number
	$invoice_title = sprintf( __( 'Invoice #%s', 'invoices-camptix' ), $invoice_number );

	$date_format = ! empty( $opt['invoice-date-format'] ) ? $opt['invoice-date-format'] : 'd F Y';
	$pdf->initFacture( $invoice_title, date_i18n( $date_format, strtotime( $obj->post_date ) ), '' );

	// product.
	$items = $order['items'];
	if ( ! is_array( $items ) ) {
		$items = array();
	}//end if
	foreach ( $items as $item ) {
		$item_title   = $item['name'];
		$item_price   = number_format_i18n( $item['price'], 2 );
		$item_quatity = $item['quantity'];
		$item_total   = number_format_i18n( $item_price * $item_quatity, 2 );
		$pdf->productAdd( array( $item_title, $item_quatity, $item_price, $item_total ) );
	}//end foreach

	// total line.
	$total     = number_format_i18n( $order['total'], 2 ) . ' ' . $currency;
	$vat_total = number_format_i18n( 0, 2 ) . ' ' . $currency;
	$pdf->vatTotalAdd( array( __( 'VAT amount:', 'invoices-camptix' ), $vat_total ) );
	$pdf->totalAdd( array( __( 'Total amount:', 'invoices-camptix' ), $total ) );
	$pdf->afterContentAdd( explode( PHP_EOL, $thank_you . PHP_EOL . $cgv ) );

	// #3 Imports the template
	//
	$template = locate_template( 'template-invoice.php' ) ? locate_template( 'template-invoice.php' ) : 'includes/lib/fpdf/template.php';
	require $template;

	// #4 Finalization
	// build the PDF
	$pdf->buildPDF();

	// download the file.
	$invoice_title = sanitize_title( __( 'invoice-', 'invoices-camptix' ) . $invoice_number ) . '.pdf';
	if ( in_array( $target, array( 'D', 'I' ), true ) ) {
		$pdf->Output( $invoice_title, $target );
		die();
	} else {
		$upload     = wp_upload_dir();
		$upload_dir = $upload['basedir'];
		$upload_dir = $upload_dir . '/camptix-invoices';
		if ( ! is_dir( $upload_dir ) ) {
			mkdir( $upload_dir, 0700 );
			foreach ( array(
				'.htaccess'  => 'Deny from all',
				'index.html' => '',
			) as $file => $content ) {
				$file_handle = @fopen( trailingslashit( $upload_dir ) . $file, 'w' );
				if ( $file_handle ) {
					fwrite( $file_handle, $content );
					fclose( $file_handle );
				}//end if
			}//end foreach
		}//end if
		$path = $upload_dir . '/' . $invoice_title;
		$pdf->Output( $path, 'F' );
		return $path;
	}//end if
}

/**
 * Can a request print an invoice ?
 */
function ctx_can_get_invoice() {
	if ( empty( $_REQUEST['invoice_id'] ) || empty( $_REQUEST['invoice_auth'] ) ) {
		return false;
	}//end if
	if ( 'tix_invoice' !== get_post_type( $_REQUEST['invoice_id'] ) ) {
		return false;
	}//end if
	$auth = get_post_meta( (int) $_REQUEST['invoice_id'], 'auth', true );
	if ( $auth !== $_REQUEST['invoice_auth'] ) {
		return false;
	}//end if
	return (int) $_REQUEST['invoice_id'];
}
