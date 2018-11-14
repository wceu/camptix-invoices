<?php
/**
 * Plugin name: Camptix Invoices
 * Description: Allow Camptix user to send invoices when an attendee buys a ticket
 * Version: 1.0.0
 * Author: Willy Bahuaud, Simon Janin
 * Author URI: https://2018.wptech.io
 * Text Domain: invoices-camptix
 *
 * @package Camptix_Invoices
 */

defined( 'ABSPATH' ) || exit;

define( 'CTX_INV_VER', '1.0.0' );
define( 'CTX_INV_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );
define( 'CTX_INV_ADMIN_URL', CTX_INV_URL . '/admin' );

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
	register_post_type( 'tix_invoice', array(
		'label'        => __( 'Invoices', 'invoices-camptix' ),
		'labels'       => array(
			'name' => __( 'Invoices', 'invoices-camptix' ),
		),
		'supports'     => array( 'title' ),
		'public'       => false,
		'show_ui'      => true,
		'show_in_menu' => 'edit.php?post_type=tix_ticket',
	) );
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
	vprintf( '<div class="misc-pub-section"><p>%3$s <strong>%4$s</strong></p><a href="%s" class="button button-secondary" target="_blank">%2$s</a></div>',
		array(
			esc_attr( admin_url( 'admin-post.php?action=camptix-invoice.get&invoice_id=' . $post->ID . '&invoice_auth=' . $auth ) ),
			esc_html__( 'Print invoice', 'invoices-camptix' ),
			esc_html__( 'Invoice number', 'invoices-camptix' ),
			esc_attr( $invoice_number ),
		)
	);
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
	$order = get_post_meta( $args->ID, 'original_order', true );
	$metas = get_post_meta( $args->ID, 'invoice_metas', true );

	if ( ! is_array( $order ) ) {
		$order = array();
	}//end if
	if ( ! is_array( $metas ) ) {
		$metas = array();
	}//end if

	wp_nonce_field( 'edit-invoice-' . get_current_user_id() . '-' . $args->ID, 'edit-invoice' );
	echo '<h3>' . esc_html__( 'Order details', 'invoices-camptix' ) . '</h3>';
	$item_line = '<tr>
		<td><input type="text" value="%2$s" name="order[items][%1$d][name]" class="widefat"></td><!-- name -->
		<td><input type="number" min="0" value="%3$.2f" name="order[items][%1$d][price]" class="widefat"></td><!-- price -->
		<td><input type="number" min="0" value="%4$s" name="order[items][%1$d][quantity]" class="widefat"></td><!-- qty -->
		</tr>';
	vprintf( '<table class="widefat"><thead><tr>
		<th>%1$s</th>
		<th>%2$s</th>
		<th>%3$s</th>
		</tr></thead><tbody>',
		array(
			esc_html__( 'Title', 'invoices-camptix' ),
			esc_html__( 'Unit price', 'invoices-camptix' ),
			esc_html__( 'Quantity', 'invoices-camptix' ),
		)
	);

	if ( ! is_array( $order['items'] ) ) {
		$order['items'] = array();
	}//end if
	foreach ( $order['items'] as $k => $item ) {
		vprintf( $item_line, // @codingStandardsIgnoreLine
			array(
				esc_attr( $k ),
				esc_attr( $item['name'] ),
				esc_attr( $item['price'] ),
				esc_attr( $item['quantity'] ),
			)
		);
	}//end foreach
	vprintf( $item_line, // @codingStandardsIgnoreLine
		array(
			count( $order['items'] ) + 1,
			'',
			'',
			'',
		)
	);
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
		</table>',
		array(
			esc_html__( 'Total amount', 'invoices-camptix' ),
			esc_attr( $order['total'] ),
			esc_html__( 'Customer', 'invoices-camptix' ),
			esc_attr( $metas['name'] ),
			esc_html__( 'Contact email', 'invoices-camptix' ),
			esc_attr( $metas['email'] ),
			esc_html__( 'Customer Address', 'invoices-camptix' ),
			esc_textarea( $metas['address'] ),
		)
	);
}

/**
 * Metabox for published invoices.
 *
 * @param object $args The args.
 */
function ctx_invoice_metabox_sent( $args ) {
	$order = get_post_meta( $args->ID, 'original_order', true );
	$metas = get_post_meta( $args->ID, 'invoice_metas', true );
	echo '<h3>' . esc_html__( 'Order details', 'invoices-camptix' ) . '</h3>';
	$item_line = '<tr>
		<td>%1$s</td><!-- name -->
		<td>%2$.2f</td><!-- price -->
		<td>%3$s</td><!-- qty -->
		</tr>';
	vprintf( '<table class="widefat"><thead><tr>
		<th>%1$s</th>
		<th>%2$s</th>
		<th>%3$s</th>
		</tr></thead><tbody>',
		array(
			esc_html__( 'Title', 'invoices-camptix' ),
			esc_html__( 'Unit price', 'invoices-camptix' ),
			esc_html__( 'Quantity', 'invoices-camptix' ),
		)
	);
	foreach ( $order['items'] as $k => $item ) {
		vprintf( $item_line, // @codingStandardsIgnoreLine
			array(
				esc_attr( $item['name'] ),
				esc_attr( $item['price'] ),
				esc_attr( $item['quantity'] ),
			)
		);
	}//end foreach
	echo '</tbody></table>';
	vprintf( '<table class="form-table"><tr><th scope="row">%1$s</th>
		<td>%2$.2f</td></tr>
		<tr><th scope="row">%3$s</th>
		<td>%4$s<td></tr>
		<tr><th scope="row">%5$s</th>
		<td>%6$s<td></tr>
		<tr><th scope="row">%7$s</th>
		<td>%8$s<td></tr>
		</table>',
		array(
			esc_html__( 'Total amount', 'invoices-camptix' ),
			esc_html( $order['total'] ),
			esc_html__( 'Customer', 'invoices-camptix' ),
			esc_html( $metas['name'] ),
			esc_html__( 'Contact email', 'invoices-camptix' ),
			esc_html( $metas['email'] ),
			esc_html__( 'Customer Address', 'invoices-camptix' ),
			wp_kses( nl2br( $metas['address'] ), array( 'br' => true ) ),
		)
	);
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
add_action( 'rest_api_init', function () {
	register_rest_route( 'camptix-invoices/v1', '/invoice-form', array(
		'methods'  => 'GET',
		'callback' => 'ctx_invoice_form',
	) );
} );

/**
 * Invoice form generator.
 */
function ctx_invoice_form() {
	$fields = array();

	$fields['main']     = '<input type="checkbox" value="1" name="camptix-need-invoice" id="camptix-need-invoice"/> <label for="camptix-need-invoice">' . __( 'I need an invoice', 'invoices-camptix' ) . '</label>';
	$fields['hidden'][] = '<td class="tix-left"><label for="invoice-email">' . __( 'Email for the invoice to be sent to', 'invoices-camptix' ) . ' <span class="tix-required-star">*</span></label></td>
		<td class="tix-right"><input type="text" name="invoice-email" id="invoice-email" pattern="^[a-zA-Z0-9_.+-]+@[a-zA-Z0-9-]+.[a-zA-Z0-9-.]+$"></td>';
	$fields['hidden'][] = '<td class="tix-left"><label for="invoice-name">' . __( 'Name or organisation that the invoice should be made out to', 'invoices-camptix' ) . ' <span class="tix-required-star">*</span></label></td>
		<td class="tix-right"><input type="text" name="invoice-name" id="invoice-name"></td>';
	$fields['hidden'][] = '<td class="tix-left"><label for="invoice-address">' . __( 'Street address', 'invoices-camptix' ) . ' <span class="tix-required-star">*</span></label></td>
		<td class="tix-right"><textarea name="invoice-address" id="invoice-address" rows="2"></textarea></td>';

	$fields = apply_filters( 'camptix_invoices_invoice_details_form_fields', $fields );

	$fields_formatted = $fields['main'] . '<table class="camptix-invoice-details tix_tickets_table tix_invoice_table"><tbody><tr>' . implode( '</tr><tr>', $fields['hidden'] ) . '</tr></tbody></table>';

	$form = apply_filters( 'camptix_invoice_invoice_details_form', '<div class="camptix-invoice-toggle-wrapper">' . $fields_formatted . '</div>', $fields );

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

	require 'admin/lib/fpdf/facturePDF.php';

	// #1 Initialize the basic information.
	//
	// address of the company issuing the invoice.
	$address   = __( 'Organizer:', 'invoices-camptix' ) . PHP_EOL . $opt['invoice-company'];
	$thank_you = $opt['invoice-thankyou'];

	// customer address.
	$customer_address = implode( PHP_EOL, array( $metas['name'], $metas['address'], $metas['email'] ) );

	// CGV.
	$cgv = $opt['invoice-tac'];

	// initialize the object invoicePDF.
	$pdf = new facturePDF( $address, $customer_address, $cgv . PHP_EOL . $thank_you );

	// set the logo.
	$logo_url = wp_get_attachment_url( $opt['invoice-logo'] );
	$pdf->setLogo( $logo_url );

	// product header.
	$pdf->productHeaderAddRow( __( 'Title', 'invoices-camptix' ), 45, 'L' );
	$pdf->productHeaderAddRow( __( 'Unit price', 'invoices-camptix' ), 45, 'C' );
	$pdf->productHeaderAddRow( __( 'Quantity', 'invoices-camptix' ), 45, 'C' );
	$pdf->productHeaderAddRow( __( 'Total', 'invoices-camptix' ), 45, 'C' );

	// header of the totals.
	$pdf->totalHeaderAddRow( 30, 'L' );
	$pdf->totalHeaderAddRow( 30, 'C' );

	// custom element.
	$pdf->elementAdd( '', 'traitEnteteProduit', 'content' );
	$pdf->elementAdd( '', 'traitBas', 'footer' );

	// #2 Create an invoice
	//
	// invoice title, date, text before the page number.
	// translators: invoice number
	$invoice_title = sprintf( __( 'Invoice #%s', 'invoices-camptix' ), $invoice_number );

	// TODO: Add a setting to allow the date format to be changed.
	$pdf->initFacture( $invoice_title, date_i18n( 'd F Y', strtotime( $obj->post_date ) ), '' );

	// product.
	$items = $order['items'];
	foreach ( $items as $item ) {
		$item_title   = $item['name'];
		$item_price   = number_format_i18n( $item['price'], 2 );
		$item_quatity = $item['quantity'];
		$item_total   = number_format_i18n( $item_price * $item_quatity, 2 );
		$pdf->productAdd( array( $item_title, $item_price, $item_quatity, $item_total ) );
	}//end foreach

	// total line.
	$total = number_format_i18n( $order['total'], 2 ) . ' ' . $currency;
	$pdf->totalAdd( array( __( 'Total amount:', 'invoices-camptix' ), $total ) );

	// #3 Imports the template
	//
	$template = locate_template( 'template-invoice.php' ) ? locate_template( 'template-invoice.php' ) : 'fpdf/template.php';
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
