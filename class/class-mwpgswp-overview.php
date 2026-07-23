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

					<!-- Drag-and-drop file upload zone -->
					<div class="field">
						<label><?php esc_html_e( 'Upload Plugin ZIP', 'mainwp-for-google-security-for-wordpress' ); ?></label>
						<div class="mwpgswp-dropzone" id="mwpgswp-dropzone">
							<input
								type="file"
								id="mwpgswp-file-input"
								accept=".zip,application/zip"
								class="mwpgswp-dropzone-input"
								aria-label="<?php esc_attr_e( 'Upload ZIP file', 'mainwp-for-google-security-for-wordpress' ); ?>"
							/>
							<!-- Empty state: prompt -->
							<div class="mwpgswp-dropzone-prompt" id="mwpgswp-dropzone-prompt">
								<span class="mwpgswp-dropzone-icon" aria-hidden="true">
									<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/></svg>
								</span>
								<p class="mwpgswp-dropzone-title"><?php esc_html_e( 'Drop your ZIP here', 'mainwp-for-google-security-for-wordpress' ); ?></p>
								<p class="mwpgswp-dropzone-hint"><?php esc_html_e( 'ZIP only (max. 10 MB)', 'mainwp-for-google-security-for-wordpress' ); ?></p>
								<button type="button" class="ui button" id="mwpgswp-select-file-btn">
									<?php esc_html_e( 'Select file', 'mainwp-for-google-security-for-wordpress' ); ?>
								</button>
							</div>
							<!-- Filled state: file preview -->
							<div class="mwpgswp-dropzone-preview" id="mwpgswp-dropzone-preview" hidden>
								<span class="mwpgswp-dropzone-filename" id="mwpgswp-dropzone-filename"></span>
								<button type="button" class="mwpgswp-dropzone-remove" id="mwpgswp-dropzone-remove" aria-label="<?php esc_attr_e( 'Remove file', 'mainwp-for-google-security-for-wordpress' ); ?>" title="<?php esc_attr_e( 'Remove file', 'mainwp-for-google-security-for-wordpress' ); ?>">&times;</button>
							</div>
						</div>
						<div class="mwpgswp-dropzone-error" id="mwpgswp-dropzone-error" role="alert" hidden></div>
					</div>

					<div class="field">
						<label for="mwpgswp-package-url"><?php esc_html_e( 'Package ZIP URL', 'mainwp-for-google-security-for-wordpress' ); ?></label>
						<div class="ui input">
							<input
								type="url"
								id="mwpgswp-package-url"
								name="url"
								value="<?php echo esc_attr( $package['url'] ); ?>"
								placeholder="https://example.com/google-security-for-wordpress.zip"
							/>
						</div>
						<p class="mwpgswp-field-description"><?php esc_html_e( 'Populated automatically when you upload a file above, or enter a URL to a ZIP hosted on your own update server.', 'mainwp-for-google-security-for-wordpress' ); ?></p>
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
				<div class="mwpgswp-bulk-bar" id="mwpgswp-bulk-bar">
					<span id="mwpgswp-bulk-count"></span>
					<button type="button" class="ui green button" id="mwpgswp-bulk-install-btn" disabled>
						<?php esc_html_e( 'Install GSWP on Selected', 'mainwp-for-google-security-for-wordpress' ); ?>
					</button>
					<span id="mwpgswp-bulk-status" class="mwpgswp-save-status"></span>
				</div>
				<table class="ui unstackable table mwpgswp-overview-table">
					<thead>
						<tr>
							<th class="collapsing">
								<div class="ui fitted checkbox">
									<input type="checkbox" id="mwpgswp-select-all" aria-label="<?php esc_attr_e( 'Select all sites', 'mainwp-for-google-security-for-wordpress' ); ?>" />
									<label></label>
								</div>
							</th>
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
								<td class="collapsing">
									<div class="ui fitted checkbox">
										<input type="checkbox" class="mwpgswp-site-checkbox" value="<?php echo esc_attr( $site_id ); ?>" aria-label="<?php esc_attr_e( 'Select site', 'mainwp-for-google-security-for-wordpress' ); ?>" />
										<label></label>
									</div>
								</td>
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
	 * AJAX handler: receive a ZIP file upload, store it in the WordPress
	 * uploads directory, and return its URL. Used by the drag-and-drop zone
	 * on the Extensions page as a direct alternative to the wp.media dialog.
	 */
	public function ajax_upload_package() {
		check_ajax_referer( 'mwpgswp_upload_package', 'nonce' );

		if ( ! current_user_can( 'upload_files' ) || ! current_user_can( 'install_plugins' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to upload plugin packages.', 'mainwp-for-google-security-for-wordpress' ) ) );
		}

		if ( empty( $_FILES['file'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file was uploaded.', 'mainwp-for-google-security-for-wordpress' ) ) );
		}

		$file = $_FILES['file']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- validated below.

		// Validate extension.
		$filename = sanitize_file_name( $file['name'] );
		$ext      = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		if ( 'zip' !== $ext ) {
			wp_send_json_error( array( 'message' => __( 'Only ZIP files are accepted.', 'mainwp-for-google-security-for-wordpress' ) ) );
		}

		// Validate size (10 MB max).
		$max_size = 10 * 1024 * 1024;
		if ( (int) $file['size'] > $max_size ) {
			wp_send_json_error( array( 'message' => __( 'File exceeds the maximum size of 10 MB.', 'mainwp-for-google-security-for-wordpress' ) ) );
		}

		// Validate upload error.
		if ( UPLOAD_ERR_OK !== (int) $file['error'] ) {
			wp_send_json_error( array( 'message' => __( 'Upload failed. Please try again.', 'mainwp-for-google-security-for-wordpress' ) ) );
		}

		// Store in wp-content/uploads/mwpgswp-packages/.
		$upload_dir = wp_upload_dir();
		$target_dir = trailingslashit( $upload_dir['basedir'] ) . 'mwpgswp-packages';

		if ( ! file_exists( $target_dir ) ) {
			wp_mkdir_p( $target_dir );
		}

		// Prevent direct PHP execution inside the package directory.
		$htaccess = $target_dir . '/.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			file_put_contents( $htaccess, 'Deny from all' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
		$index = $target_dir . '/index.php';
		if ( ! file_exists( $index ) ) {
			file_put_contents( $index, '<?php // Silence is golden.' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}

		// Unique filename to avoid collisions.
		$unique_name = 'gswp-' . gmdate( 'Ymd-His' ) . '-' . wp_generate_password( 6, false ) . '.zip';
		$target_path = $target_dir . '/' . $unique_name;

		if ( ! move_uploaded_file( $file['tmp_name'], $target_path ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			wp_send_json_error( array( 'message' => __( 'Could not save the uploaded file.', 'mainwp-for-google-security-for-wordpress' ) ) );
		}

		$url = trailingslashit( $upload_dir['baseurl'] ) . 'mwpgswp-packages/' . $unique_name;

		wp_send_json_success(
			array(
				'url'      => $url,
				'filename' => $filename,
			)
		);
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
