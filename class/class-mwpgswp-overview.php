<?php
/**
 * Extensions-page landing screen.
 *
 * A thin index, not a second configurator: lists connected child sites with a
 * link to each one's own "Google Security" tab (the actual configuration
 * screen), plus an optional per-row status check.
 *
 * @package MainWP/Extensions/GSWP
 */

namespace MainWP\Extensions\GSWP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MWPGSWP_Overview
 *
 * @package MainWP/Extensions/GSWP
 */
class MWPGSWP_Overview {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

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
	 * Render the overview table.
	 */
	public function render_page() {
		MWPGSWP_Admin::enqueue_assets();

		$sites = $this->get_sites();
		?>
		<div class="mwpgswp-wrap">
			<h2><?php esc_html_e( 'Google Security for WordPress', 'mainwp-for-google-security-for-wordpress' ); ?></h2>
			<p>
				<?php esc_html_e( 'reCAPTCHA keys are issued per domain, so configuration lives on each individual site rather than here. Use "Configure" to open a site\'s settings tab.', 'mainwp-for-google-security-for-wordpress' ); ?>
			</p>

			<?php if ( empty( $sites ) ) : ?>
				<p><?php esc_html_e( 'No connected child sites were found.', 'mainwp-for-google-security-for-wordpress' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped mwpgswp-overview-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Site', 'mainwp-for-google-security-for-wordpress' ); ?></th>
							<th><?php esc_html_e( 'Status', 'mainwp-for-google-security-for-wordpress' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'mainwp-for-google-security-for-wordpress' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $sites as $site ) : ?>
							<?php
							$configure_url = add_query_arg(
								array(
									'page' => 'ManageSitesGSWPConfig',
									'id'   => (int) $site->id,
								),
								admin_url( 'admin.php' )
							);
							?>
							<tr data-site-id="<?php echo esc_attr( $site->id ); ?>">
								<td>
									<strong><?php echo esc_html( $site->name ); ?></strong><br />
									<span class="mwpgswp-site-url"><?php echo esc_html( $site->url ); ?></span>
								</td>
								<td class="mwpgswp-status-cell">&#8212;</td>
								<td>
									<a class="button" href="<?php echo esc_url( $configure_url ); ?>">
										<?php esc_html_e( 'Configure', 'mainwp-for-google-security-for-wordpress' ); ?>
									</a>
									<button type="button" class="button mwpgswp-check-btn" data-site-id="<?php echo esc_attr( $site->id ); ?>">
										<?php esc_html_e( 'Check', 'mainwp-for-google-security-for-wordpress' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Fetch every connected child site via MainWP's 'mainwp_getsites' filter.
	 *
	 * @return array<int,object> Site rows (id, name, url, ...), empty on failure.
	 */
	private function get_sites() {
		global $mwpgswp_activator;

		$sites = apply_filters(
			'mainwp_getsites',
			$mwpgswp_activator->get_child_file(),
			$mwpgswp_activator->get_child_key(),
			null,
			false
		);

		return is_array( $sites ) ? $sites : array();
	}

	/**
	 * AJAX handler backing the per-row "Check" button: fetches the child's
	 * current settings and returns a short status summary for that row.
	 */
	public function ajax_check() {
		check_ajax_referer( 'mwpgswp_check_site', 'nonce' );

		if ( ! function_exists( 'mainwp_current_user_can' ) || ! mainwp_current_user_can( 'dashboard', 'edit_sites' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to view this site.', 'mainwp-for-google-security-for-wordpress' ) ) );
		}

		$site_id = isset( $_POST['site_id'] ) ? intval( $_POST['site_id'] ) : 0;
		if ( ! $site_id ) {
			wp_send_json_error( array( 'message' => __( 'No site selected.', 'mainwp-for-google-security-for-wordpress' ) ) );
		}

		$result = MWPGSWP_Connector::get_settings( $site_id );

		if ( ! $result['ok'] ) {
			wp_send_json_error(
				array(
					'message'    => $result['message'],
					'error_type' => $result['error_type'],
				)
			);
		}

		$settings  = (array) $result['data']['settings'];
		$key_type  = isset( $settings['key_type'] ) ? $settings['key_type'] : 'classic';
		$protected = array_filter(
			array(
				! empty( $settings['enable_wp_login'] ) || ! empty( $settings['enable_login'] ),
				! empty( $settings['tfa_enabled'] ),
			)
		);

		wp_send_json_success(
			array(
				'version'   => isset( $result['data']['version'] ) ? $result['data']['version'] : '',
				'key_type'  => $key_type,
				'protected' => ! empty( $protected ),
			)
		);
	}
}
