<?php
/**
 * Admin wiring.
 *
 * Registers the AJAX endpoints backing the per-site configuration tab and the
 * Extensions-page overview, and provides the shared asset-enqueue helper both
 * screens call from their own render methods.
 *
 * @package MainWP/Extensions/GSWP
 */

namespace MainWP\Extensions\GSWP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MWPGSWP_Admin
 *
 * @package MainWP/Extensions/GSWP
 */
class MWPGSWP_Admin {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Whether enqueue_assets() has already run this request.
	 *
	 * @var bool
	 */
	private static $assets_enqueued = false;

	/**
	 * Get the singleton instance.
	 *
	 * @return self
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor. Wires AJAX endpoints to the screens that own their logic.
	 */
	public function __construct() {
		add_action( 'wp_ajax_mwpgswp_save_settings', array( MWPGSWP_Individual::get_instance(), 'ajax_save' ) );
		add_action( 'wp_ajax_mwpgswp_check_site', array( MWPGSWP_Overview::get_instance(), 'ajax_check' ) );
		add_action( 'wp_ajax_mwpgswp_save_package', array( MWPGSWP_Overview::get_instance(), 'ajax_save_package' ) );
		add_action( 'wp_ajax_mwpgswp_upload_package', array( MWPGSWP_Overview::get_instance(), 'ajax_upload_package' ) );
		add_action( 'wp_ajax_mwpgswp_install_gswp', array( MWPGSWP_Overview::get_instance(), 'ajax_install' ) );
	}

	/**
	 * Enqueue this extension's admin script/style. Safe to call from every
	 * screen we render — WordPress only enqueues once per request, so a
	 * second call from the same page load is a no-op.
	 */
	public static function enqueue_assets() {
		if ( self::$assets_enqueued ) {
			return;
		}
		self::$assets_enqueued = true;

		wp_enqueue_style(
			'mwpgswp-admin',
			MWPGSWP_PLUGIN_URL . 'assets/css/mwpgswp-admin.css',
			array(),
			MWPGSWP_VERSION
		);

		wp_enqueue_script(
			'mwpgswp-admin',
			MWPGSWP_PLUGIN_URL . 'assets/js/mwpgswp-admin.js',
			array(),
			MWPGSWP_VERSION,
			true
		);

		wp_localize_script(
			'mwpgswp-admin',
			'mwpgswpAdmin',
			array(
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'saveNonce'        => wp_create_nonce( 'mwpgswp_save_settings' ),
				'checkNonce'       => wp_create_nonce( 'mwpgswp_check_site' ),
				'packageNonce'     => wp_create_nonce( 'mwpgswp_save_package' ),
				'uploadNonce'      => wp_create_nonce( 'mwpgswp_upload_package' ),
				'installNonce'     => wp_create_nonce( 'mwpgswp_install_gswp' ),
				'confirmUnsaved'   => __( 'You have unsaved changes. Leave this page anyway?', 'mainwp-for-google-security-for-wordpress' ),
				'confirmInstall'   => __( 'Install (or reinstall/upgrade) Google Security for WordPress on this site now?', 'mainwp-for-google-security-for-wordpress' ),
				'saving'           => __( 'Saving…', 'mainwp-for-google-security-for-wordpress' ),
				'saved'            => __( 'Saved.', 'mainwp-for-google-security-for-wordpress' ),
				'saveError'        => __( 'Something went wrong.', 'mainwp-for-google-security-for-wordpress' ),
				'checking'         => __( 'Checking…', 'mainwp-for-google-security-for-wordpress' ),
				'installing'       => __( 'Installing…', 'mainwp-for-google-security-for-wordpress' ),
				'installed'        => __( 'Installed.', 'mainwp-for-google-security-for-wordpress' ),
				'show'             => __( 'Show', 'mainwp-for-google-security-for-wordpress' ),
				'hide'             => __( 'Hide', 'mainwp-for-google-security-for-wordpress' ),
				'uploading'        => __( 'Uploading…', 'mainwp-for-google-security-for-wordpress' ),
				'uploadError'      => __( 'Upload failed.', 'mainwp-for-google-security-for-wordpress' ),
				'invalidType'      => __( 'Only ZIP files are accepted.', 'mainwp-for-google-security-for-wordpress' ),
				'invalidSize'      => __( 'File exceeds the maximum size of 10 MB.', 'mainwp-for-google-security-for-wordpress' ),
				'bulkConfirm'      => __( 'Install (or reinstall/upgrade) Google Security for WordPress on the selected sites now?', 'mainwp-for-google-security-for-wordpress' ),
				'bulkInstalling'   => __( 'Installing %1$d of %2$d…', 'mainwp-for-google-security-for-wordpress' ),
				'bulkDone'         => __( 'Bulk install complete: %1$d succeeded, %2$d failed.', 'mainwp-for-google-security-for-wordpress' ),
				'bulkNone'         => __( 'No sites selected.', 'mainwp-for-google-security-for-wordpress' ),
				'bulkSelected'     => __( '%d selected', 'mainwp-for-google-security-for-wordpress' ),
			)
		);
	}
}
