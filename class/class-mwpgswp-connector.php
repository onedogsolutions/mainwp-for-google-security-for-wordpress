<?php
/**
 * Dashboard -> child connector.
 *
 * Wraps the 'mainwp_fetchurlauthed' filter (MainWP's signed dashboard-to-child
 * channel) to talk to the GSWP bridge that ships inside the Google Security
 * for WordPress plugin itself (>= 2.9.0, `includes/class-gswp-mainwp-child.php`,
 * hooked to MainWP Child's `mainwp_child_extra_execution` filter). This class
 * owns every dashboard->child call this extension makes.
 *
 * @package MainWP/Extensions/GSWP
 */

namespace MainWP\Extensions\GSWP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MWPGSWP_Connector
 *
 * @package MainWP/Extensions/GSWP
 */
class MWPGSWP_Connector {

	/**
	 * Minimum GSWP plugin version known to carry the MainWP bridge.
	 *
	 * @var string
	 */
	const MIN_GSWP_VERSION = '2.9.0';

	/**
	 * Fetch the current GSWP settings from a child site.
	 *
	 * @param int $site_id MainWP internal website ID.
	 * @return array{ok:bool,error_type:?string,message:string,data:?array} Normalized result.
	 */
	public static function get_settings( $site_id ) {
		return self::call( $site_id, 'get_settings' );
	}

	/**
	 * Push updated GSWP settings to a child site.
	 *
	 * @param int                  $site_id  MainWP internal website ID.
	 * @param array<string,mixed>  $settings Settings keyed exactly as the GSWP
	 *                                       REST route expects (see
	 *                                       MWPGSWP_Settings_Schema).
	 * @return array{ok:bool,error_type:?string,message:string,data:?array} Normalized
	 *         result; on success `data['settings']` holds what the child
	 *         actually persisted after its own validation/clamping.
	 */
	public static function update_settings( $site_id, array $settings ) {
		return self::call( $site_id, 'update_settings', array( 'settings' => wp_json_encode( $settings ) ) );
	}

	/**
	 * Make one 'extra_execution' round trip and normalize the response into
	 * one of three typed outcomes: a transport/site-level failure (site
	 * unreachable, suspended, not editable by this dashboard user), a missing
	 * bridge (GSWP absent, deactivated, or older than MIN_GSWP_VERSION), or a
	 * bridge-reported error (e.g. an internal REST callback failure).
	 *
	 * @param int                  $site_id MainWP internal website ID.
	 * @param string               $action  'get_settings' or 'update_settings'.
	 * @param array<string,mixed>  $extra   Extra POST fields for the action.
	 * @return array{ok:bool,error_type:?string,message:string,data:?array}
	 */
	private static function call( $site_id, $action, array $extra = array() ) {
		global $mwpgswp_activator;

		$post_data                    = $extra;
		$post_data['mwpgswp_action']  = $action;

		$response = apply_filters(
			'mainwp_fetchurlauthed',
			$mwpgswp_activator->get_child_file(),
			$mwpgswp_activator->get_child_key(),
			(int) $site_id,
			'extra_execution',
			$post_data
		);

		// Transport/site-level failure: unreachable site, suspended site, or a
		// dashboard user without edit rights on this site.
		if ( ! is_array( $response ) || isset( $response['error'] ) ) {
			$message = ( is_array( $response ) && ! empty( $response['error'] ) )
				? $response['error']
				: __( 'The site did not respond.', 'mainwp-for-google-security-for-wordpress' );

			return array(
				'ok'         => false,
				'error_type' => 'transport',
				'message'    => $message,
				'data'       => null,
			);
		}

		// Bridge missing: MainWP Child answered, but nothing on the child
		// hooked our action, so the envelope key never appears. This is the
		// expected state until the child runs GSWP >= 2.9.0.
		if ( empty( $response['mwpgswp'] ) || ! is_array( $response['mwpgswp'] ) ) {
			return array(
				'ok'         => false,
				'error_type' => 'bridge_missing',
				'message'    => sprintf(
					/* translators: %s: minimum required GSWP version. */
					__( 'Google Security for WordPress is not active on this child site, or is older than version %s.', 'mainwp-for-google-security-for-wordpress' ),
					self::MIN_GSWP_VERSION
				),
				'data'       => null,
			);
		}

		$envelope = $response['mwpgswp'];

		// Bridge-reported error: the action reached GSWP but it refused it.
		if ( empty( $envelope['success'] ) ) {
			return array(
				'ok'         => false,
				'error_type' => 'bridge_error',
				'message'    => ! empty( $envelope['error'] )
					? $envelope['error']
					: __( 'Google Security for WordPress refused the request.', 'mainwp-for-google-security-for-wordpress' ),
				'data'       => null,
			);
		}

		return array(
			'ok'         => true,
			'error_type' => null,
			'message'    => '',
			'data'       => $envelope,
		);
	}
}
