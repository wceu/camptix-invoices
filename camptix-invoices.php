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
 * Wait for camptix to load plugin
 */
add_action( 'plugins_loaded', __NAMESPACE__ . '\load' );
function load() {
	if ( function_exists( 'camptix_register_addon' ) ) {
		load_camptix_invoices();
	}
}

/**
 * Load invoice addon
 */
function load_camptix_invoices() {
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
