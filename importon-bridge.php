<?php
/**
 * Plugin Name: Importon Bridge
 * Description: Import products into WooCommerce via browser companion + REST API.
 * Version: 0.2.0
 * Author: Nasratul Nayem
 * Author URI: https://codex.nayem.dev
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: importon-bridge
 *
 * Browser-companion-first importer (no scraping UI in admin).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( function_exists( 'ib_fs' ) ) {
	ib_fs()->set_basename( true, __FILE__ );
} else {
	if ( ! function_exists( 'ib_fs' ) ) {
		// Create a helper function for easy SDK access.
		function ib_fs() {
			global $ib_fs;
			if ( ! isset( $ib_fs ) ) {
				// Include Freemius SDK.
				require_once dirname( __FILE__ ) . '/vendor/freemius/start.php';
				$ib_fs = fs_dynamic_init( array(
					'id'                  => '28475',
					'slug'                => 'importon-bridge',
					'type'                => 'plugin',
					'public_key'          => 'pk_899cd9e07ac2b4825e4c96464c7e0',
					'is_premium'          => true,
					'premium_suffix'      => 'Pro',
					'has_premium_version' => true,
					'has_addons'          => false,
					'has_paid_plans'      => true,
					'is_org_compliant'    => true,
					'wp_org_gatekeeper'   => 'OA7#BoRiBNqdf52FvzEf!!074aRLPs8fspif$7K1#4u4Csys1fQlCecVcUTOs2mcpeVHi#C2j9d09fOTvbC0HloPT7fFee5WdS3G',
					'menu'                => array(
						'support'        => false,
					),
				) );
			}
			return $ib_fs;
		}
		// Init Freemius.
		ib_fs();
		// Signal that SDK was initiated.
		do_action( 'ib_fs_loaded' );
	}
	// Backward compat: atwi_fs was previous name, keep alias
	if ( ! function_exists( 'atwi_fs' ) ) {
		function atwi_fs() { return ib_fs(); }
	}

	define( 'IMPORTONBRIDGE_VERSION', '0.2.0' );
	define( 'IMPORTONBRIDGE_PLUGIN_FILE', __FILE__ );
	define( 'IMPORTONBRIDGE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

	require_once IMPORTONBRIDGE_PLUGIN_DIR . 'includes/class-importonbridge-admin.php';
	require_once IMPORTONBRIDGE_PLUGIN_DIR . 'includes/class-importonbridge-rest.php';
	require_once IMPORTONBRIDGE_PLUGIN_DIR . 'includes/class-importonbridge-frontend.php';
	require_once IMPORTONBRIDGE_PLUGIN_DIR . 'includes/class-importonbridge-url-import.php';

	final class ImportonBridge_Plugin {
		public static function init(): void {
			register_activation_hook( IMPORTONBRIDGE_PLUGIN_FILE, array( __CLASS__, 'activate' ) );
			add_action( 'plugins_loaded', array( __CLASS__, 'plugins_loaded' ) );
			add_action( 'init', array( __CLASS__, 'load_textdomain' ) );
		}

		public static function load_textdomain(): void {
			load_plugin_textdomain( 'importon-bridge', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
		}

		public static function activate(): void {
			ImportonBridge_Rest::create_usage_table();
		}

		public static function plugins_loaded(): void {
			if ( ! class_exists( 'WooCommerce' ) ) {
				add_action( 'admin_notices', function () {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'Importon Bridge requires WooCommerce to be installed and active.', 'importon-bridge' ) . '</p></div>';
				} );
				return;
			}

			if ( is_admin() ) {
				ImportonBridge_Admin::init();
				ImportonBridge_Url_Import::init();
			}

			ImportonBridge_Rest::init();
			ImportonBridge_Frontend::init();
		}
	}

	ImportonBridge_Plugin::init();

	add_action( 'before_woocommerce_init', function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', IMPORTONBRIDGE_PLUGIN_FILE, true );
		}
	} );
}
