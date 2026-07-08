<?php
/**
 * Plugin Name: MainWP for Google Security for WordPress
 * Plugin URI: https://onedog.solutions/
 * Description: Configure the Google Security for WordPress plugin on each connected child site directly from the MainWP Dashboard. Since reCAPTCHA keys are issued per domain, configuration lives as a tab on each individual child site rather than a global screen.
 * Version: 1.1.0
 * Author: One Dog Solutions
 * Author URI: https://onedog.solutions/
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Text Domain: mainwp-for-google-security-for-wordpress
 * Domain Path: /languages
 * Documentation URI: https://github.com/onedogsolutions/mainwp-for-google-security-for-wordpress
 *
 * @package MainWP/Extensions/GSWP
 */

namespace MainWP\Extensions\GSWP;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! defined( 'MWPGSWP_PLUGIN_FILE' ) ) {
	define( 'MWPGSWP_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'MWPGSWP_PLUGIN_DIR' ) ) {
	define( 'MWPGSWP_PLUGIN_DIR', plugin_dir_path( MWPGSWP_PLUGIN_FILE ) );
}

if ( ! defined( 'MWPGSWP_PLUGIN_URL' ) ) {
	define( 'MWPGSWP_PLUGIN_URL', plugin_dir_url( MWPGSWP_PLUGIN_FILE ) );
}

if ( ! defined( 'MWPGSWP_VERSION' ) ) {
	define( 'MWPGSWP_VERSION', '1.1.0' );
}

/**
 * Class MWPGSWP_Activator
 *
 * Bootstraps the extension: registers it with the MainWP Dashboard, waits for
 * MainWP to confirm activation, then wires the per-site "Google Security" tab
 * and the AJAX endpoints that back it. Mirrors the structure of the official
 * MainWP Development Extension starter.
 *
 * @package MainWP/Extensions/GSWP
 */
class MWPGSWP_Activator {

	/**
	 * Whether the MainWP Dashboard plugin is active, or its extension-enabled
	 * payload (array with a 'key' member) once resolved.
	 *
	 * @var bool|array
	 */
	protected $mainwpMainActivated = false;

	/**
	 * The 'mainwp_extension_enabled_check' payload for this plugin.
	 *
	 * @var bool|array
	 */
	protected $childEnabled = false;

	/**
	 * This extension's signed child key, used to authorize every
	 * 'mainwp_fetchurlauthed' call this extension makes.
	 *
	 * @var bool|string
	 */
	protected $childKey = false;

	/**
	 * This plugin's main file path, passed as the 'plugin' identity to every
	 * MainWP extension filter.
	 *
	 * @var string
	 */
	protected $childFile;

	/**
	 * Extension handle used to register with MainWP (mainwp_current_user_can
	 * capability checks, activation/deactivation hooks).
	 *
	 * @var string
	 */
	protected $plugin_handle = 'mainwp-for-google-security-for-wordpress';

	/**
	 * Human-readable product name, reported to MainWP on activation.
	 *
	 * @var string
	 */
	protected $product_id = 'MainWP for Google Security for WordPress';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->childFile = __FILE__;

		spl_autoload_register( array( $this, 'autoload' ) );

		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		/**
		 * Registers this extension with the MainWP Dashboard's Extensions
		 * page. MainWP calls the returned 'callback' to render our entry
		 * there; the real per-site configuration screen is registered
		 * separately, once MainWP confirms activation, via
		 * 'mainwp_getsubpages_sites'.
		 */
		add_filter( 'mainwp_getextensions', array( $this, 'get_this_extension' ) );

		$this->mainwpMainActivated = apply_filters( 'mainwp_activated_check', false );
		if ( false !== $this->mainwpMainActivated ) {
			$this->activate_this_plugin();
		} else {
			add_action( 'mainwp_activated', array( $this, 'activate_this_plugin' ) );
		}

		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
	}

	/**
	 * Autoloader for this extension's classes.
	 *
	 * Class MWPGSWP_Foo_Bar autoloads from class/class-mwpgswp-foo-bar.php.
	 *
	 * @param string $class_name Fully qualified class name being instantiated.
	 */
	public function autoload( $class_name ) {
		if ( 0 === strpos( $class_name, __NAMESPACE__ . '\\' ) ) {
			$class_name = str_replace( __NAMESPACE__ . '\\', '', $class_name );
		} else {
			return;
		}

		if ( 0 !== strpos( $class_name, 'MWPGSWP_' ) ) {
			return;
		}

		$class_file = MWPGSWP_PLUGIN_DIR . 'class' . DIRECTORY_SEPARATOR . 'class-' . str_replace( '_', '-', strtolower( $class_name ) ) . '.php';
		if ( file_exists( $class_file ) ) {
			require_once $class_file;
		}
	}

	/**
	 * Add this extension to MainWP's Extensions page.
	 *
	 * @param array $extensions Extensions MainWP already knows about.
	 * @return array Extensions including this one.
	 */
	public function get_this_extension( $extensions ) {
		$extensions[] = array(
			'plugin'     => __FILE__,
			'api'        => $this->plugin_handle,
			'mainwp'     => true,
			// Explicit display name. Without this, MainWP falls back to the
			// plugin header and then runs polish_string_name() on it, which
			// strips the literal token 'MainWP' (among others) from the
			// name — turning "MainWP for Google Security for WordPress"
			// into "for Google Security for WordPress" on the Add-ons card,
			// left menu, and page title.
			'name'       => 'Google Security for WordPress',
			'callback'   => array( $this, 'render_extensions_page' ),
			'apiManager' => false,
		);

		return $extensions;
	}

	/**
	 * Render the Extensions-page landing screen: a list of connected sites
	 * with a link to each one's "Google Security" configuration tab.
	 */
	public function render_extensions_page() {
		do_action( 'mainwp_pageheader_extensions', __FILE__ );
		MWPGSWP_Overview::get_instance()->render_page();
		do_action( 'mainwp_pagefooter_extensions', __FILE__ );
	}

	/**
	 * Runs once MainWP confirms it is active. Resolves this extension's
	 * signed child key, registers the per-site tab, and boots the admin
	 * (assets + AJAX) class.
	 */
	public function activate_this_plugin() {
		$this->mainwpMainActivated = apply_filters( 'mainwp_activated_check', $this->mainwpMainActivated );
		$this->childEnabled        = apply_filters( 'mainwp_extension_enabled_check', __FILE__ );
		$this->childKey            = is_array( $this->childEnabled ) && isset( $this->childEnabled['key'] ) ? $this->childEnabled['key'] : false;

		if ( function_exists( 'mainwp_current_user_can' ) && ! mainwp_current_user_can( 'extension', $this->plugin_handle ) ) {
			return;
		}

		add_filter( 'mainwp_getsubpages_sites', array( $this, 'hook_managesites_subpage' ) );

		MWPGSWP_Admin::get_instance();
	}

	/**
	 * Register the per-site "Google Security" tab via 'mainwp_getsubpages_sites'.
	 *
	 * `sitetab => true` places it in the individual child site's own tab bar
	 * (Manage Sites -> site -> Google Security) rather than the global Sites
	 * left-hand menu; `menu_hidden => true` keeps it out of that global menu
	 * entirely, since reCAPTCHA keys are per domain and there is nothing
	 * useful to show without a selected site.
	 *
	 * @param array $sub_pages Sub pages MainWP already knows about.
	 * @return array Sub pages including ours.
	 */
	public function hook_managesites_subpage( $sub_pages ) {
		$sub_pages[] = array(
			'title'       => __( 'Google Security', 'mainwp-for-google-security-for-wordpress' ),
			'slug'        => 'GSWPConfig',
			'sitetab'     => true,
			'menu_hidden' => true,
			'callback'    => array( MWPGSWP_Individual::get_instance(), 'render_page' ),
		);

		return $sub_pages;
	}

	/**
	 * Get this extension's signed child key.
	 *
	 * @return bool|string
	 */
	public function get_child_key() {
		return $this->childKey;
	}

	/**
	 * Get this plugin's main file path.
	 *
	 * @return string
	 */
	public function get_child_file() {
		return $this->childFile;
	}

	/**
	 * Show an admin notice on the Plugins screen when MainWP Dashboard is
	 * missing.
	 */
	public function admin_notices() {
		global $current_screen;
		if ( isset( $current_screen->parent_base ) && 'plugins' === $current_screen->parent_base && false === $this->mainwpMainActivated ) {
			echo '<div class="error"><p>' . sprintf(
				/* translators: 1: opening link tag, 2: closing link tag. */
				esc_html__( 'MainWP for Google Security for WordPress requires the %1$sMainWP Dashboard plugin%2$s to be installed and activated.', 'mainwp-for-google-security-for-wordpress' ),
				'<a href="https://mainwp.com/" target="_blank" rel="noopener noreferrer">',
				'</a>'
			) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup with escaped substitutions above.
		}
	}

	/**
	 * Report activation to MainWP.
	 */
	public function activate() {
		do_action(
			'mainwp_activate_extention',
			$this->plugin_handle,
			array(
				'product_id'       => $this->product_id,
				'software_version' => MWPGSWP_VERSION,
			)
		);
	}

	/**
	 * Report deactivation to MainWP.
	 */
	public function deactivate() {
		do_action( 'mainwp_deactivate_extention', $this->plugin_handle );
	}
}

global $mwpgswp_activator;
$mwpgswp_activator = new MWPGSWP_Activator();
