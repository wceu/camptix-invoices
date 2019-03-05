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

	$invoice_url    = ctx_get_invoice_url( $post->ID );
	$invoice_number = get_post_meta( $post->ID, 'invoice_number', true );

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
 * Metabox for editable invoice (not published).
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
 * Generate invoice document on status transitions to PUBLISH
 *
 * @param int $id The id.
 */
function ctx_assign_invoice_number( $id ) {

	if ( ! get_post_meta( $id, 'invoice_number', true ) ) {

		$number = CampTix_Addon_Invoices::create_invoice_number();
		update_post_meta( $id, 'invoice_number', $number );

		CampTix_Addon_Invoices::create_invoice_document( $id );

	}//end if
}
add_action( 'publish_tix_invoice', 'ctx_assign_invoice_number', 10, 2 );

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
 * Recovers a path for a PDF invoice.
 *
 * @param int $invoice_id The invoice id.
 */
function ctx_get_invoice( $invoice_id ) {
	$invoice_document = get_post_meta( $invoice_id, 'invoice_document', true );
	$upload_dir       = wp_upload_dir();

	if ( empty( $upload_dir['basedir'] ) ) {
		wp_die( esc_html__( 'Base upload directory is empty.', 'invoices-camptix' ) );
	}

	$invoices_dirname = $upload_dir['basedir'] . '/camptix-invoices';
	$path             = $invoices_dirname . '/' . $invoice_document;

	if ( ! file_exists( $path ) ) {
		wp_die( esc_html__( 'Invoice document does not exist.', 'invoices-camptix' ) );
	}

	return $path;
}

/**
 * Recovers the URL for a PDF invoice.
 *
 * @param int $invoice_id The invoice id.
 */
function ctx_get_invoice_url( $invoice_id ) {
	$invoice_document = get_post_meta( $invoice_id, 'invoice_document', true );
	$upload_dir       = wp_upload_dir();

	if ( empty( $upload_dir['basedir'] ) ) {
		wp_die( esc_html__( 'Base upload directory is empty.', 'invoices-camptix' ) );
	}

	$invoices_dirurl = $upload_dir['baseurl'] . '/camptix-invoices';
	$url             = $invoices_dirurl . '/' . $invoice_document;

	return $url;
}
