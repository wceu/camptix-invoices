<?php

namespace CAMPTIX\INVOICES;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

/**
 * Add a new tab in camptix settings
 */
function invoice_settings_tab( $sections ) {
    $sections['invoice'] = __( 'Facturation' );
    return $sections;
}

/**
 * Tab content
 */
function invoice_settings( $section ) {
    if ( 'invoice' !== $section ) {
        return false;
    }
    $opt = get_option( 'camptix_options' );
    add_settings_section( 'invoice', __( 'Réglages des factures' ), '__return_false', 'camptix_options' );
    add_settings_field( 'invoice-new-year-reset', 'Réinitialisation annuelle', __NAMESPACE__ . '\new_year_reset', 'camptix_options', 'invoice', array(
        'id'    => 'invoice-new-year-reset',
        'value' => isset( $opt['invoice-new-year-reset'] ) ? $opt['invoice-new-year-reset'] : false,
    ) );
    add_settings_field( 'invoice-current-number', 'Prochaine facture', __NAMESPACE__ . '\current_number', 'camptix_options', 'invoice', array(
        'id'    => 'invoice-current-number',
        'value' => isset( $opt['invoice-current-number'] ) ? $opt['invoice-current-number'] : 1,
        'yearly' => isset( $opt['invoice-new-year-reset'] ) ? $opt['invoice-new-year-reset'] : false
    ) );
}

/**
 * Callback for the invoice name pattern
 */
function new_year_reset( $args ) {
    vprintf( '<input type="hidden" value="no" name="camptix_options[%1$s]"><input type="checkbox" id="camptix_options[%1$s]" value="yes" name="camptix_options[%1$s]" %3$s><label for="camptix_options[%1$s]" class="widefat">'
        . __( 'Les numéros de facture sont préfixés par l’année, et réinitialisés au 1<sup>er</sup> janvier. (ex: %2$s-125)' )
        . '</label>', array(
            esc_attr( $args['id'] ),
            date( 'Y' ),
            checked( $args['value'], true, false ),
        ) );
}

/**
 * Overide next invoice number
 */
function current_number( $args ) {
    vprintf( '<p>La prochaine facture portera le numéro %3$s<input type="number" min="1" value="%2$d" name="camptix_options[%1$s]" class="small-text">%4$s</p>', array(
        esc_attr( $args['id'] ),
        esc_attr( $args['value'] ),
        $args['yearly'] ? '<code>' . date( 'Y-' ) : '',
        $args['yearly'] ? '</code>' : '',
    ) );
}

/**
 * Validate our custom options
 */
function validate_options( $output, $input ) {
    if ( ! empty( $input['invoice-new-year-reset'] ) ) {
        $output['invoice-new-year-reset'] = 'yes' === $input['invoice-new-year-reset'];
    }
    if ( ! empty( $input['invoice-current-number'] ) ) {
        $output['invoice-current-number'] = (int) $input['invoice-current-number'];
    }
    return $output;
}
