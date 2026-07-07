<?php
/**
 * Settings schema.
 *
 * Single source of truth for every Google Security for WordPress (GSWP)
 * setting this extension can read/write, grouped into the same five tabs as
 * GSWP's own settings screen (GSWP Phase 22 / v2.8.1). Render and save both
 * iterate this schema, so a future GSWP option only needs one entry here.
 *
 * @package MainWP/Extensions/GSWP
 */

namespace MainWP\Extensions\GSWP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MWPGSWP_Settings_Schema
 *
 * @package MainWP/Extensions/GSWP
 */
class MWPGSWP_Settings_Schema {

	/**
	 * Field types this schema declares:
	 *
	 * - toggle    On/off checkbox. Stored/sent as '1'/'0'.
	 * - text      Plain single-line text.
	 * - secret    Masked text input with a reveal toggle.
	 * - select    Enum dropdown, `options` => array( value => label ).
	 * - threshold Float 0.0-1.0 range input (reCAPTCHA score threshold).
	 * - int       Integer input with `min`/`max`.
	 * - email_list  Comma-separated email addresses (free text; child validates).
	 * - login_list  Comma-separated WordPress usernames (free text; child validates).
	 * - roles     Dynamic checkbox list, populated at render time from the
	 *             child site's own role list (never static — roles differ
	 *             per site).
	 *
	 * @return array<string,array<string,mixed>> Ordered tab => tab definition.
	 */
	public static function get_tabs() {
		return array(
			'credentials' => array(
				'label'  => __( 'API Credentials', 'mainwp-for-google-security-for-wordpress' ),
				'fields' => array(
					'key_type'       => array(
						'type'    => 'select',
						'label'   => __( 'Key Type', 'mainwp-for-google-security-for-wordpress' ),
						'options' => array(
							'classic'    => __( 'reCAPTCHA v3 (Classic)', 'mainwp-for-google-security-for-wordpress' ),
							'enterprise' => __( 'reCAPTCHA Enterprise', 'mainwp-for-google-security-for-wordpress' ),
						),
						'default' => 'classic',
					),
					'site_key'       => array(
						'type'        => 'text',
						'label'       => __( 'Site Key', 'mainwp-for-google-security-for-wordpress' ),
						'description' => __( 'reCAPTCHA keys are issued per domain — confirm this key matches the child site\'s own domain.', 'mainwp-for-google-security-for-wordpress' ),
						'default'     => '',
					),
					'secret_key'     => array(
						'type'    => 'secret',
						'label'   => __( 'Secret Key', 'mainwp-for-google-security-for-wordpress' ),
						'default' => '',
					),
					'gcp_project_id' => array(
						'type'      => 'text',
						'label'     => __( 'GCP Project ID', 'mainwp-for-google-security-for-wordpress' ),
						'default'   => '',
						'requires'  => array( 'key_type' => 'enterprise' ),
					),
					'gcp_api_key'    => array(
						'type'      => 'secret',
						'label'     => __( 'GCP API Key', 'mainwp-for-google-security-for-wordpress' ),
						'default'   => '',
						'requires'  => array( 'key_type' => 'enterprise' ),
					),
				),
			),
			'protection'  => array(
				'label'  => __( 'Form Protection', 'mainwp-for-google-security-for-wordpress' ),
				'fields' => array(
					'enable_wp_login'           => array(
						'type'    => 'toggle',
						'label'   => __( 'WordPress Login', 'mainwp-for-google-security-for-wordpress' ),
						'default' => '0',
					),
					'threshold_wp_login'        => array(
						'type'     => 'threshold',
						'label'    => __( 'WordPress Login Threshold', 'mainwp-for-google-security-for-wordpress' ),
						'default'  => '0.5',
						'requires' => array( 'enable_wp_login' => '1' ),
					),
					'enable_wp_register'        => array(
						'type'    => 'toggle',
						'label'   => __( 'WordPress Registration', 'mainwp-for-google-security-for-wordpress' ),
						'default' => '0',
					),
					'threshold_wp_register'     => array(
						'type'     => 'threshold',
						'label'    => __( 'WordPress Registration Threshold', 'mainwp-for-google-security-for-wordpress' ),
						'default'  => '0.5',
						'requires' => array( 'enable_wp_register' => '1' ),
					),
					'enable_wp_lostpassword'    => array(
						'type'    => 'toggle',
						'label'   => __( 'WordPress Lost Password', 'mainwp-for-google-security-for-wordpress' ),
						'default' => '0',
					),
					'threshold_wp_lostpassword' => array(
						'type'     => 'threshold',
						'label'    => __( 'WordPress Lost Password Threshold', 'mainwp-for-google-security-for-wordpress' ),
						'default'  => '0.5',
						'requires' => array( 'enable_wp_lostpassword' => '1' ),
					),
					'enable_login'               => array(
						'type'             => 'toggle',
						'label'            => __( 'WooCommerce Login', 'mainwp-for-google-security-for-wordpress' ),
						'default'          => '0',
						'requires_woocommerce' => true,
					),
					'threshold_login'            => array(
						'type'                 => 'threshold',
						'label'                => __( 'WooCommerce Login Threshold', 'mainwp-for-google-security-for-wordpress' ),
						'default'              => '0.5',
						'requires'             => array( 'enable_login' => '1' ),
						'requires_woocommerce' => true,
					),
					'enable_registration'        => array(
						'type'                 => 'toggle',
						'label'                => __( 'WooCommerce Registration', 'mainwp-for-google-security-for-wordpress' ),
						'default'              => '0',
						'requires_woocommerce' => true,
					),
					'threshold_registration'     => array(
						'type'                 => 'threshold',
						'label'                => __( 'WooCommerce Registration Threshold', 'mainwp-for-google-security-for-wordpress' ),
						'default'              => '0.5',
						'requires'             => array( 'enable_registration' => '1' ),
						'requires_woocommerce' => true,
					),
					'enable_checkout'            => array(
						'type'                 => 'toggle',
						'label'                => __( 'WooCommerce Checkout', 'mainwp-for-google-security-for-wordpress' ),
						'default'              => '0',
						'requires_woocommerce' => true,
					),
					'threshold_checkout'         => array(
						'type'                 => 'threshold',
						'label'                => __( 'WooCommerce Checkout Threshold', 'mainwp-for-google-security-for-wordpress' ),
						'default'              => '0.5',
						'requires'             => array( 'enable_checkout' => '1' ),
						'requires_woocommerce' => true,
					),
				),
			),
			'enterprise'  => array(
				'label'              => __( 'Enterprise Defense', 'mainwp-for-google-security-for-wordpress' ),
				'requires_enterprise' => true,
				'fields'             => array(
					'txn_defense'    => array(
						'type'                 => 'toggle',
						'label'                => __( 'Transaction Defense', 'mainwp-for-google-security-for-wordpress' ),
						'default'              => '0',
						'requires_woocommerce' => true,
					),
					'txn_block'      => array(
						'type'                 => 'toggle',
						'label'                => __( 'Block High-Risk Checkouts', 'mainwp-for-google-security-for-wordpress' ),
						'default'              => '0',
						'requires'             => array( 'txn_defense' => '1' ),
						'requires_woocommerce' => true,
					),
					'threshold_txn'  => array(
						'type'                 => 'threshold',
						'label'                => __( 'Transaction Risk Threshold', 'mainwp-for-google-security-for-wordpress' ),
						'default'              => '0.8',
						'requires'             => array( 'txn_defense' => '1' ),
						'requires_woocommerce' => true,
					),
					'account_defender' => array(
						'type'    => 'toggle',
						'label'   => __( 'Account Defender', 'mainwp-for-google-security-for-wordpress' ),
						'default' => '0',
					),
					'ad_step_up'       => array(
						'type'     => 'toggle',
						'label'    => __( 'Require 2FA on Suspicious Logins', 'mainwp-for-google-security-for-wordpress' ),
						'default'  => '0',
						'requires' => array( 'account_defender' => '1' ),
					),
					'ad_events'        => array(
						'type'     => 'toggle',
						'label'    => __( 'Assess Account Changes', 'mainwp-for-google-security-for-wordpress' ),
						'default'  => '1',
						'requires' => array( 'account_defender' => '1' ),
					),
				),
			),
			'twofactor'   => array(
				'label'  => __( 'Two-Factor Auth', 'mainwp-for-google-security-for-wordpress' ),
				'fields' => array(
					'tfa_enabled'                    => array(
						'type'    => 'toggle',
						'label'   => __( 'Enable Two-Factor Authentication', 'mainwp-for-google-security-for-wordpress' ),
						'default' => '1',
					),
					'tfa_enforced_roles'             => array(
						'type'     => 'roles',
						'label'    => __( 'Require For Roles', 'mainwp-for-google-security-for-wordpress' ),
						'default'  => array(),
						'requires' => array( 'tfa_enabled' => '1' ),
					),
					'tfa_grace_days'                 => array(
						'type'     => 'int',
						'label'    => __( 'Grace Period (days)', 'mainwp-for-google-security-for-wordpress' ),
						'default'  => '14',
						'min'      => 0,
						'max'      => 30,
						'requires' => array( 'tfa_enabled' => '1' ),
					),
					'tfa_remember'                   => array(
						'type'     => 'toggle',
						'label'    => __( 'Allow "Remember This Browser"', 'mainwp-for-google-security-for-wordpress' ),
						'default'  => '1',
						'requires' => array( 'tfa_enabled' => '1' ),
					),
					'tfa_block_app_passwords'        => array(
						'type'        => 'toggle',
						'label'       => __( 'Block Application Passwords for Enforced Roles', 'mainwp-for-google-security-for-wordpress' ),
						'description' => __( 'Existing application passwords for accounts in an enforced role stop working immediately. Standard MainWP Child connections use their own RSA-key handshake, not application passwords, so managing this site from MainWP keeps working.', 'mainwp-for-google-security-for-wordpress' ),
						'default'     => '0',
						'requires'    => array( 'tfa_enabled' => '1' ),
					),
					'tfa_app_password_exempt_users'  => array(
						'type'     => 'login_list',
						'label'    => __( 'Exempt Accounts (comma-separated usernames)', 'mainwp-for-google-security-for-wordpress' ),
						'default'  => '',
						'requires' => array( 'tfa_block_app_passwords' => '1' ),
					),
				),
			),
			'alerts'      => array(
				'label'  => __( 'Alerts & Compatibility', 'mainwp-for-google-security-for-wordpress' ),
				'fields' => array(
					'alerts'          => array(
						'type'    => 'toggle',
						'label'   => __( 'Enable Admin Email Alerts', 'mainwp-for-google-security-for-wordpress' ),
						'default' => '0',
					),
					'alert_email'     => array(
						'type'     => 'email_list',
						'label'    => __( 'Recipients (comma-separated)', 'mainwp-for-google-security-for-wordpress' ),
						'default'  => '',
						'requires' => array( 'alerts' => '1' ),
					),
					'alert_mode'      => array(
						'type'     => 'select',
						'label'    => __( 'Delivery', 'mainwp-for-google-security-for-wordpress' ),
						'options'  => array(
							'immediate' => __( 'Immediate', 'mainwp-for-google-security-for-wordpress' ),
							'hourly'    => __( 'Hourly digest', 'mainwp-for-google-security-for-wordpress' ),
							'daily'     => __( 'Daily digest', 'mainwp-for-google-security-for-wordpress' ),
						),
						'default'  => 'immediate',
						'requires' => array( 'alerts' => '1' ),
					),
					'alert_login'     => array(
						'type'     => 'toggle',
						'label'    => __( 'Alert on Suspicious Admin Login', 'mainwp-for-google-security-for-wordpress' ),
						'default'  => '1',
						'requires' => array( 'alerts' => '1' ),
					),
					'alert_checkout'  => array(
						'type'                 => 'toggle',
						'label'                => __( 'Alert on Blocked Checkout', 'mainwp-for-google-security-for-wordpress' ),
						'default'              => '1',
						'requires'             => array( 'alerts' => '1' ),
						'requires_woocommerce' => true,
					),
					'conflict_mode'   => array(
						'type'    => 'select',
						'label'   => __( 'reCAPTCHA Conflict Handling', 'mainwp-for-google-security-for-wordpress' ),
						'options' => array(
							'off'    => __( 'Off', 'mainwp-for-google-security-for-wordpress' ),
							'active' => __( 'Only where this plugin is active', 'mainwp-for-google-security-for-wordpress' ),
							'site'   => __( 'Site-wide', 'mainwp-for-google-security-for-wordpress' ),
						),
						'default' => 'off',
					),
					'verbose_logging' => array(
						'type'    => 'toggle',
						'label'   => __( 'Verbose Logging', 'mainwp-for-google-security-for-wordpress' ),
						'default' => '0',
					),
				),
			),
		);
	}

	/**
	 * Flatten the tabs into a single key => field-definition map, each field
	 * annotated with the tab it belongs to.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function get_fields() {
		$fields = array();
		foreach ( self::get_tabs() as $tab_id => $tab ) {
			foreach ( $tab['fields'] as $key => $field ) {
				$field['tab']    = $tab_id;
				$fields[ $key ]  = $field;
			}
		}
		return $fields;
	}

	/**
	 * The default value for every field, keyed by settings key.
	 *
	 * Used to fill in a row before a site has been contacted, and as the
	 * fallback when the child's response is missing a key.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_defaults() {
		$defaults = array();
		foreach ( self::get_fields() as $key => $field ) {
			$defaults[ $key ] = $field['default'];
		}
		return $defaults;
	}
}
