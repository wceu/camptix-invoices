<?php

namespace CAMPTIX\INVOICES;

/**
 * Plugin name: Camptix Invoices
 */

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

define( 'CTX_INV_VER', time() );

/**
 * All settings functions are in this file
 */
require 'options-page.php';

/**
 * Load invoice addon
 */
add_action( 'camptix_load_addons', __NAMESPACE__ . '\load' );
function load() {
	class CampTix_Addon_Invoices extends \CampTix_Addon {
		function camptix_init() {
			global $camptix;
			add_filter( 'camptix_setup_sections', __NAMESPACE__ . '\invoice_settings_tab' );
			add_action( 'camptix_menu_setup_controls', __NAMESPACE__ . '\invoice_settings' );
			add_filter( 'camptix_validate_options', __NAMESPACE__ . '\validate_options', 10, 2 );
		}
	}
	camptix_register_addon( __NAMESPACE__ . '\CampTix_Addon_Invoices' );
}
