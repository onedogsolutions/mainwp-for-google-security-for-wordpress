<?php
/**
 * Per-site "Google Security" configuration tab.
 *
 * Registered on the individual child site's own screen (Manage Sites -> site
 * -> Google Security) via 'mainwp_getsubpages_sites' in the main activator,
 * since reCAPTCHA keys are issued per domain and there is nothing meaningful
 * to configure without a specific site selected.
 *
 * @package MainWP/Extensions/GSWP
 */

namespace MainWP\Extensions\GSWP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MWPGSWP_Individual
 *
 * @package MainWP/Extensions/GSWP
 */
class MWPGSWP_Individual {

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
	 * Render the per-site configuration tab.
	 *
	 * Fetches the child's current settings synchronously; on any failure the
	 * typed error is shown and no form is rendered, so a site we can't
	 * reliably read from is never blindly overwritten.
	 */
	public function render_page() {
		do_action( 'mainwp_pageheader_sites', 'GSWPConfig' );

		MWPGSWP_Admin::enqueue_assets();

		$site_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only site selector, matches MainWP's own convention.

		if ( ! $site_id ) {
			echo '<div class="ui segment"><div class="ui negative message">' . esc_html__( 'No site selected.', 'mainwp-for-google-security-for-wordpress' ) . '</div></div>';
			do_action( 'mainwp_pagefooter_sites', 'GSWPConfig' );
			return;
		}

		$result = MWPGSWP_Connector::get_settings( $site_id );

		if ( ! $result['ok'] ) {
			$this->render_error_notice( $result, $site_id );
			do_action( 'mainwp_pagefooter_sites', 'GSWPConfig' );
			return;
		}

		$this->render_form( $site_id, $result['data'] );

		do_action( 'mainwp_pagefooter_sites', 'GSWPConfig' );
	}

	/**
	 * Render one of the three typed connector failure states.
	 *
	 * @param array{error_type:?string,message:string} $result  Connector result.
	 * @param int                                       $site_id MainWP internal website ID.
	 */
	private function render_error_notice( $result, $site_id ) {
		?>
		<div class="ui segment">
			<div class="ui negative message mwpgswp-fetch-error">
				<div class="header"><?php echo esc_html( $result['message'] ); ?></div>
				<?php if ( 'bridge_missing' === $result['error_type'] ) : ?>
					<p><?php esc_html_e( 'Install or update the Google Security for WordPress plugin on this child site, then reload this page.', 'mainwp-for-google-security-for-wordpress' ); ?></p>
					<div class="mwpgswp-bridge-missing">
						<button type="button" class="ui green button mwpgswp-install-btn" data-site-id="<?php echo esc_attr( $site_id ); ?>">
							<?php esc_html_e( 'Install GSWP', 'mainwp-for-google-security-for-wordpress' ); ?>
						</button>
					</div>
				<?php elseif ( 'transport' === $result['error_type'] ) : ?>
					<p><?php esc_html_e( 'Confirm the site is online and its MainWP connection is healthy from Manage Sites, then reload this page.', 'mainwp-for-google-security-for-wordpress' ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the five-tab settings form.
	 *
	 * @param int   $site_id MainWP internal website ID.
	 * @param array $data    The connector's successful 'get_settings' payload:
	 *                       'settings', 'woocommerce_active', 'roles', 'version'.
	 */
	private function render_form( $site_id, array $data ) {
		$settings           = array_merge( MWPGSWP_Settings_Schema::get_defaults(), (array) $data['settings'] );
		$woocommerce_active = ! empty( $data['woocommerce_active'] );
		$roles              = ! empty( $data['roles'] ) && is_array( $data['roles'] ) ? $data['roles'] : array();
		$key_type           = isset( $settings['key_type'] ) ? $settings['key_type'] : 'classic';
		$tabs               = MWPGSWP_Settings_Schema::get_tabs();
		$first              = true;
		?>
		<div class="ui segment">
			<p class="mwpgswp-child-version">
				<?php
				printf(
					/* translators: %s: GSWP plugin version reported by the child site. */
					esc_html__( 'Google Security for WordPress %s on this site.', 'mainwp-for-google-security-for-wordpress' ),
					esc_html( isset( $data['version'] ) ? $data['version'] : '?' )
				);
				?>
			</p>

			<div id="mwpgswp-notice" class="ui negative message" hidden></div>

			<form id="mwpgswp-settings-form" class="ui form" data-site-id="<?php echo esc_attr( $site_id ); ?>">
				<input type="hidden" name="action" value="mwpgswp_save_settings" />
				<?php wp_nonce_field( 'mwpgswp_save_settings', 'nonce' ); ?>
				<input type="hidden" name="site_id" value="<?php echo esc_attr( $site_id ); ?>" />

				<div class="ui top attached tabular menu mwpgswp-tabs" role="tablist">
					<?php foreach ( $tabs as $tab_id => $tab ) : ?>
						<a
							class="item mwpgswp-tab-btn<?php echo $first ? ' active' : ''; ?>"
							data-tab="<?php echo esc_attr( $tab_id ); ?>"
							role="tab"
						><?php echo esc_html( $tab['label'] ); ?></a>
						<?php $first = false; ?>
					<?php endforeach; ?>
				</div>

				<?php
				$first = true;
				foreach ( $tabs as $tab_id => $tab ) :
					?>
					<div
						class="ui bottom attached tab segment mwpgswp-tabpanel<?php echo $first ? ' active' : ''; ?>"
						data-tab="<?php echo esc_attr( $tab_id ); ?>"
						role="tabpanel"
						<?php echo $first ? '' : 'hidden'; ?>
					>
						<?php if ( ! empty( $tab['requires_enterprise'] ) && 'enterprise' !== $key_type ) : ?>
							<div class="ui message mwpgswp-enterprise-notice">
								<?php esc_html_e( 'This tab requires a reCAPTCHA Enterprise key. Set the Key Type on the API Credentials tab first.', 'mainwp-for-google-security-for-wordpress' ); ?>
							</div>
						<?php endif; ?>

						<?php
						foreach ( $tab['fields'] as $key => $field ) :
							if ( ! empty( $field['requires_woocommerce'] ) && ! $woocommerce_active ) {
								continue;
							}
							$this->render_field( $key, $field, isset( $settings[ $key ] ) ? $settings[ $key ] : $field['default'], $roles );
						endforeach;
						?>
					</div>
					<?php
					$first = false;
				endforeach;
				?>

				<p class="mwpgswp-actions">
					<button type="submit" class="ui big green button" id="mwpgswp-save-btn">
						<?php esc_html_e( 'Save Settings', 'mainwp-for-google-security-for-wordpress' ); ?>
					</button>
					<span id="mwpgswp-save-status" class="mwpgswp-save-status"></span>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Render one field row.
	 *
	 * @param string $key    Settings key.
	 * @param array  $field  Field definition from the schema.
	 * @param mixed  $value  Current value.
	 * @param array  $roles  Child's role list (role slug => label), for type 'roles' only.
	 */
	private function render_field( $key, array $field, $value, array $roles ) {
		$requires_attr = '';
		if ( ! empty( $field['requires'] ) ) {
			$requires_attr = sprintf(
				' data-requires-field="%s" data-requires-value="%s"',
				esc_attr( key( $field['requires'] ) ),
				esc_attr( current( $field['requires'] ) )
			);
		}
		$name = 'fields[' . $key . ']';
		?>
		<div class="field mwpgswp-field mwpgswp-field-<?php echo esc_attr( $field['type'] ); ?>"<?php echo $requires_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_attr() above. ?>>
			<?php if ( 'toggle' !== $field['type'] ) : ?>
				<label for="mwpgswp-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
			<?php endif; ?>
			<?php
			switch ( $field['type'] ) :
				case 'toggle':
					?>
					<div class="ui toggle checkbox">
						<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="0" />
						<input
							type="checkbox"
							id="mwpgswp-<?php echo esc_attr( $key ); ?>"
							name="<?php echo esc_attr( $name ); ?>"
							value="1"
							<?php checked( '1', $value ); ?>
						/>
						<label for="mwpgswp-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
					</div>
					<?php
					break;

				case 'text':
				case 'email_list':
				case 'login_list':
					?>
					<div class="ui input">
						<input
							type="text"
							id="mwpgswp-<?php echo esc_attr( $key ); ?>"
							name="<?php echo esc_attr( $name ); ?>"
							value="<?php echo esc_attr( $value ); ?>"
						/>
					</div>
					<?php
					break;

				case 'secret':
					?>
					<div class="ui action input mwpgswp-secret-wrap">
						<input
							type="password"
							id="mwpgswp-<?php echo esc_attr( $key ); ?>"
							name="<?php echo esc_attr( $name ); ?>"
							value="<?php echo esc_attr( $value ); ?>"
							autocomplete="off"
						/>
						<button type="button" class="ui button mwpgswp-secret-toggle" data-target="mwpgswp-<?php echo esc_attr( $key ); ?>">
							<?php esc_html_e( 'Show', 'mainwp-for-google-security-for-wordpress' ); ?>
						</button>
					</div>
					<?php
					break;

				case 'select':
					?>
					<select class="ui dropdown" id="mwpgswp-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $name ); ?>">
						<?php foreach ( $field['options'] as $option_value => $option_label ) : ?>
							<option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( $option_value, $value ); ?>>
								<?php echo esc_html( $option_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<?php
					break;

				case 'threshold':
					?>
					<div class="ui input mwpgswp-threshold-input">
						<input
							type="number"
							id="mwpgswp-<?php echo esc_attr( $key ); ?>"
							name="<?php echo esc_attr( $name ); ?>"
							value="<?php echo esc_attr( $value ); ?>"
							min="0"
							max="1"
							step="0.05"
						/>
					</div>
					<?php
					break;

				case 'int':
					?>
					<div class="ui input mwpgswp-threshold-input">
						<input
							type="number"
							id="mwpgswp-<?php echo esc_attr( $key ); ?>"
							name="<?php echo esc_attr( $name ); ?>"
							value="<?php echo esc_attr( $value ); ?>"
							min="<?php echo esc_attr( $field['min'] ); ?>"
							max="<?php echo esc_attr( $field['max'] ); ?>"
							step="1"
						/>
					</div>
					<?php
					break;

				case 'roles':
					$selected_roles = is_array( $value ) ? $value : array();
					foreach ( $roles as $role_slug => $role_label ) :
						?>
						<div class="ui checkbox mwpgswp-role-checkbox">
							<input
								type="checkbox"
								id="mwpgswp-<?php echo esc_attr( $key . '-' . $role_slug ); ?>"
								name="<?php echo esc_attr( $name ); ?>[]"
								value="<?php echo esc_attr( $role_slug ); ?>"
								<?php checked( in_array( $role_slug, $selected_roles, true ), true ); ?>
							/>
							<label for="mwpgswp-<?php echo esc_attr( $key . '-' . $role_slug ); ?>"><?php echo esc_html( $role_label ); ?></label>
						</div>
						<?php
					endforeach;
					if ( empty( $roles ) ) :
						esc_html_e( 'The child site reported no roles.', 'mainwp-for-google-security-for-wordpress' );
					endif;
					break;
			endswitch;
			?>
			<?php if ( ! empty( $field['description'] ) ) : ?>
				<p class="mwpgswp-field-description"><?php echo esc_html( $field['description'] ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * AJAX handler: build a settings payload from the posted form and push it
	 * to the child through the connector, then return what the child actually
	 * persisted so the form can re-hydrate with the post-save truth.
	 */
	public function ajax_save() {
		check_ajax_referer( 'mwpgswp_save_settings', 'nonce' );

		if ( ! function_exists( 'mainwp_current_user_can' ) || ! mainwp_current_user_can( 'dashboard', 'edit_sites' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to edit this site.', 'mainwp-for-google-security-for-wordpress' ) ) );
		}

		$site_id = isset( $_POST['site_id'] ) ? intval( $_POST['site_id'] ) : 0;
		if ( ! $site_id ) {
			wp_send_json_error( array( 'message' => __( 'No site selected.', 'mainwp-for-google-security-for-wordpress' ) ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above via check_ajax_referer().
		$posted = isset( $_POST['fields'] ) && is_array( $_POST['fields'] ) ? wp_unslash( $_POST['fields'] ) : array();

		$settings = array();
		foreach ( MWPGSWP_Settings_Schema::get_fields() as $key => $field ) {
			if ( ! empty( $field['requires_woocommerce'] ) && ! array_key_exists( $key, $posted ) ) {
				// Field was hidden client-side for a non-WooCommerce site and
				// never posted; leave it out rather than forcing a default.
				continue;
			}

			switch ( $field['type'] ) {
				case 'toggle':
					$settings[ $key ] = ! empty( $posted[ $key ] ) ? '1' : '0';
					break;

				case 'threshold':
					$settings[ $key ] = isset( $posted[ $key ] ) ? floatval( $posted[ $key ] ) : $field['default'];
					break;

				case 'int':
					$settings[ $key ] = isset( $posted[ $key ] ) ? intval( $posted[ $key ] ) : $field['default'];
					break;

				case 'roles':
					$submitted        = isset( $posted[ $key ] ) && is_array( $posted[ $key ] ) ? $posted[ $key ] : array();
					$settings[ $key ] = array_values( array_map( 'sanitize_key', $submitted ) );
					break;

				case 'select':
				case 'text':
				case 'email_list':
				case 'login_list':
				case 'secret':
				default:
					$settings[ $key ] = isset( $posted[ $key ] ) ? sanitize_text_field( $posted[ $key ] ) : $field['default'];
					break;
			}
		}

		$result = MWPGSWP_Connector::update_settings( $site_id, $settings );

		if ( ! $result['ok'] ) {
			wp_send_json_error(
				array(
					'message'    => $result['message'],
					'error_type' => $result['error_type'],
				)
			);
		}

		wp_send_json_success( $result['data'] );
	}
}
