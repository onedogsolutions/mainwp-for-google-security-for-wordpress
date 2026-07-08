<?php
/**
 * Extensions-page landing screen.
 *
 * A thin index, not a second configurator: lists connected child sites with a
 * link to each one's own "Google Security" tab (the actual configuration
 * screen), an optional per-row status check, a one-click "Install GSWP" using
 * MainWP's standard plugin-install mechanism, and the package URL setting
 * that button installs.
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
	 * Render the overview screen: the package-URL setting, then the site table.
	 *
	 * Markup uses MainWP's own Fomantic UI classes (ui segment/table/form)
	 * rather than wp-admin's, so the screen inherits MainWP's light/dark theme
	 * and fills its content column instead of rendering as a mismatched
	 * wp-admin block inside it.
	 */
	public function render_page() {
		MWPGSWP_Admin::enqueue_assets();
		wp_enqueue_media();

		$sites   = $this->get_sites();
		$package = $this->get_package();
		?>
		<div class="ui segment">
			<h2 class="ui header"><?php esc_html_e( 'Google Security for WordPress', 'mainwp-for-google-security-for-wordpress' ); ?></h2>
			<div class="ui info message">
				<?php esc_html_e( 'reCAPTCHA keys are issued per domain, so configuration lives on each individual site rather than here. Use "Configure" to open a site\'s settings tab.', 'mainwp-for-google-security-for-wordpress' ); ?>
			</div>

			<div class="ui segment">
				<h3 class="ui header"><?php esc_html_e( 'GSWP Plugin Package', 'mainwp-for-google-security-for-wordpress' ); ?></h3>
				<p>
					<?php esc_html_e( 'Used by the "Install GSWP" button below to install or update Google Security for WordPress on a child site. GSWP is not distributed on wordpress.org, so point this at a ZIP file — upload one here or link to one hosted on your own update server.', 'mainwp-for-google-security-for-wordpress' ); ?>
				</p>
				<form id="mwpgswp-package-form" class="ui form">
					<input type="hidden" name="action" value="mwpgswp_save_package" />
					<?php wp_nonce_field( 'mwpgswp_save_package', 'nonce' ); ?>
					<div class="field">
						<label for="mwpgswp-package-url"><?php esc_html_e( 'Package ZIP URL', 'mainwp-for-google-security-for-wordpress' ); ?></label>
						<div class="ui action input">
							<input
								type="url"
								id="mwpgswp-package-url"
								name="url"
								value="<?php echo esc_attr( $package['url'] ); ?>"
								placeholder="https://example.com/google-security-for-wordpress.zip"
							/>
							<button type="button" class="ui button" id="mwpgswp-package-upload">
								<?php esc_html_e( 'Upload ZIP', 'mainwp-for-google-security-for-wordpress' ); ?>
							</button>
						</div>
					</div>
					<button type="submit" class="ui primary button">
						<?php esc_html_e( 'Save Package', 'mainwp-for-google-security-for-wordpress' ); ?>
					</button>
					<span id="mwpgswp-package-status" class="mwpgswp-save-status"></span>
				</form>
			</div>

			<?php if ( empty( $sites ) ) : ?>
				<div class="ui message"><?php esc_html_e( 'No connected child sites were found.', 'mainwp-for-google-security-for-wordpress' ); ?></div>
			<?php else : ?>
				<table class="ui unstackable table mwpgswp-overview-table">
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
							if ( ! is_array( $site ) || empty( $site['id'] ) ) {
								continue;
							}
							$site_id       = (int) $site['id'];
							$configure_url = add_query_arg(
								array(
									'page' => 'ManageSitesGSWPConfig',
									'id'   => $site_id,
								),
								admin_url( 'admin.php' )
							);
							?>
							<tr data-site-id="<?php echo esc_attr( $site_id ); ?>">
								<td>
									<strong><?php echo esc_html( isset( $site['name'] ) ? $site['name'] : '' ); ?></strong><br />
									<span class="mwpgswp-site-url"><?php echo esc_html( isset( $site['url'] ) ? $site['url'] : '' ); ?></span>
								</td>
								<td class="mwpgswp-status-cell">&#8212;</td>
								<td>
									<a class="ui mini button" href="<?php echo esc_url( $configure_url ); ?>">
										<?php esc_html_e( 'Configure', 'mainwp-for-google-security-for-wordpress' ); ?>
									</a>
									<button type="button" class="ui mini button mwpgswp-check-btn" data-site-id="<?php echo esc_attr( $site_id ); ?>">
										<?php esc_html_e( 'Check', 'mainwp-for-google-security-for-wordpress' ); ?>
									</button>
									<button type="button" class="ui mini green button mwpgswp-install-btn" data-site-id="<?php echo esc_attr( $site_id ); ?>">
										<?php esc_html_e( 'Install GSWP', 'mainwp-for-google-security-for-wordpress' ); ?>
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
	 * Returns a list of associative arrays (id, url, name, ...) — MainWP_DB::
	 * get_sites() builds plain arrays, not objects, for every row.
	 *
	 * @return array<int,array<string,mixed>> Site rows, empty on failure.
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
	 * The configured GSWP package (currently just a ZIP URL).
	 *
	 * @return array{url:string}
	 */
	private function get_package() {
		$package = get_option( 'mwpgswp_package', array() );
		return array(
			'url' => is_array( $package ) && ! empty( $package['url'] ) ? $package['url'] : '',
		);
	}

	/**
	 * AJAX handler: save the GSWP package URL used by the Install button.
	 */
	public function ajax_save_package() {
		check_ajax_referer( 'mwpgswp_save_package', 'nonce' );

		if ( ! current_user_can( 'install_plugins' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to manage plugin packages.', 'mainwp-for-google-security-for-wordpress' ) ) );
		}

		$url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
		update_option( 'mwpgswp_package', array( 'url' => $url ) );

		wp_send_json_success( array( 'url' => $url ) );
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

	/**
	 * AJAX handler backing the "Install GSWP" button (overview row, or the
	 * per-site tab's bridge-missing notice): installs/upgrades GSWP on the
	 * child site from the configured package URL via MainWP's standard
	 * plugin-install mechanism.
	 */
	public function ajax_install() {
		check_ajax_referer( 'mwpgswp_install_gswp', 'nonce' );

		if ( ! function_exists( 'mainwp_current_user_can' ) || ! mainwp_current_user_can( 'dashboard', 'edit_sites' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to edit this site.', 'mainwp-for-google-security-for-wordpress' ) ) );
		}

		if ( ! current_user_can( 'install_plugins' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to install plugins.', 'mainwp-for-google-security-for-wordpress' ) ) );
		}

		$site_id = isset( $_POST['site_id'] ) ? intval( $_POST['site_id'] ) : 0;
		if ( ! $site_id ) {
			wp_send_json_error( array( 'message' => __( 'No site selected.', 'mainwp-for-google-security-for-wordpress' ) ) );
		}

		$result = MWPGSWP_Connector::install_package( $site_id );

		if ( ! $result['ok'] ) {
			wp_send_json_error(
				array(
					'message'    => $result['message'],
					'error_type' => $result['error_type'],
				)
			);
		}

		wp_send_json_success(
			array(
				'message' => __( 'Google Security for WordPress was installed and activated.', 'mainwp-for-google-security-for-wordpress' ),
			)
		);
	}
}
