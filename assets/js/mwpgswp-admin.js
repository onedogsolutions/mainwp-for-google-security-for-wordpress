/**
 * MainWP for Google Security for WordPress - admin JS.
 *
 * Vanilla-jQuery (jQuery already ships with every MainWP admin screen). No
 * build step: this file is enqueued as-is by MWPGSWP_Admin::enqueue_assets().
 */
( function ( $ ) {
	'use strict';

	var isDirty = false;

	/**
	 * Tabs: show the clicked tab's panel, hide the rest, persist in the hash.
	 */
	function initTabs( $form ) {
		var $tabs = $form.find( '.mwpgswp-tabs .mwpgswp-tab-btn' );
		var $panels = $form.find( '.mwpgswp-tabpanel' );

		function activate( tabId ) {
			$tabs.removeClass( 'is-active' );
			$panels.attr( 'hidden', true ).removeClass( 'is-active' );

			var $tab = $tabs.filter( '[data-tab="' + tabId + '"]' );
			var $panel = $panels.filter( '[data-tab="' + tabId + '"]' );

			if ( ! $tab.length || ! $panel.length ) {
				$tab = $tabs.first();
				$panel = $panels.first();
			}

			$tab.addClass( 'is-active' );
			$panel.removeAttr( 'hidden' ).addClass( 'is-active' );
		}

		$tabs.on( 'click', function () {
			var tabId = $( this ).data( 'tab' );
			activate( tabId );
			window.location.hash = 'tab=' + tabId;
		} );

		var hashMatch = /tab=([\w-]+)/.exec( window.location.hash );
		if ( hashMatch ) {
			activate( hashMatch[ 1 ] );
		}
	}

	/**
	 * Reads the current value of a `fields[key]` control, whichever input
	 * type it is (checkbox with a hidden 0/1 companion, or a select).
	 */
	function getFieldValue( $form, fieldKey ) {
		var $checkbox = $form.find( 'input[type="checkbox"][name="fields[' + fieldKey + ']"]' );
		if ( $checkbox.length ) {
			return $checkbox.is( ':checked' ) ? '1' : '0';
		}
		var $control = $form.find( '[name="fields[' + fieldKey + ']"]' );
		return $control.length ? $control.val() : null;
	}

	/**
	 * Conditional field visibility: a field wrapper carrying
	 * data-requires-field/data-requires-value is shown only when that other
	 * field currently holds the matching value. Values still submit either
	 * way (see PLAN — hidden fields stay mounted, same as GSWP's own screen).
	 */
	function refreshConditionalFields( $form ) {
		$form.find( '[data-requires-field]' ).each( function () {
			var $row = $( this );
			var requiredField = $row.data( 'requires-field' );
			var requiredValue = String( $row.data( 'requires-value' ) );
			var currentValue = getFieldValue( $form, requiredField );

			$row.toggle( null !== currentValue && String( currentValue ) === requiredValue );
		} );
	}

	/**
	 * Secret field show/hide toggle.
	 */
	function initSecretToggles( $form ) {
		$form.on( 'click', '.mwpgswp-secret-toggle', function () {
			var $btn = $( this );
			var $input = $( '#' + $btn.data( 'target' ) );
			var isPassword = 'password' === $input.attr( 'type' );

			$input.attr( 'type', isPassword ? 'text' : 'password' );
			$btn.text( isPassword ? mwpgswpAdmin.hide || 'Hide' : mwpgswpAdmin.show || 'Show' );
		} );
	}

	/**
	 * Dirty-state guard: warn before navigating away with unsaved changes.
	 */
	function initDirtyGuard( $form ) {
		$form.on( 'change input', function () {
			isDirty = true;
		} );

		$( window ).on( 'beforeunload', function ( e ) {
			if ( isDirty ) {
				e.returnValue = mwpgswpAdmin.confirmUnsaved;
				return mwpgswpAdmin.confirmUnsaved;
			}
		} );
	}

	/**
	 * AJAX save.
	 */
	function initSave( $form ) {
		var $status = $( '#mwpgswp-save-status' );
		var $notice = $( '#mwpgswp-notice' );
		var $button = $( '#mwpgswp-save-btn' );

		$form.on( 'submit', function ( e ) {
			e.preventDefault();

			$notice.attr( 'hidden', true ).removeClass( 'notice-error' ).text( '' );
			$status.text( mwpgswpAdmin.saving );
			$button.prop( 'disabled', true );

			$.post( mwpgswpAdmin.ajaxUrl, $form.serialize() )
				.done( function ( response ) {
					if ( response && response.success ) {
						$status.text( mwpgswpAdmin.saved );
						isDirty = false;
					} else {
						var message = response && response.data && response.data.message
							? response.data.message
							: mwpgswpAdmin.saveError;
						$status.text( '' );
						$notice.removeAttr( 'hidden' ).addClass( 'notice-error' ).text( message );
					}
				} )
				.fail( function () {
					$status.text( '' );
					$notice.removeAttr( 'hidden' ).addClass( 'notice-error' ).text( mwpgswpAdmin.saveError );
				} )
				.always( function () {
					$button.prop( 'disabled', false );
				} );
		} );
	}

	/**
	 * Overview page: per-row "Check" status button.
	 */
	function initOverviewChecks() {
		$( '.mwpgswp-check-btn' ).on( 'click', function () {
			var $btn = $( this );
			var siteId = $btn.data( 'site-id' );
			var $cell = $btn.closest( 'tr' ).find( '.mwpgswp-status-cell' );

			$cell.text( mwpgswpAdmin.checking );
			$btn.prop( 'disabled', true );

			$.post( mwpgswpAdmin.ajaxUrl, {
				action: 'mwpgswp_check_site',
				nonce: mwpgswpAdmin.checkNonce,
				site_id: siteId,
			} )
				.done( function ( response ) {
					if ( response && response.success ) {
						var d = response.data;
						$cell.text( d.version + ' – ' + d.key_type + ( d.protected ? ' ✓' : '' ) );
					} else {
						$cell.text( ( response && response.data && response.data.message ) || mwpgswpAdmin.saveError );
					}
				} )
				.fail( function () {
					$cell.text( mwpgswpAdmin.saveError );
				} )
				.always( function () {
					$btn.prop( 'disabled', false );
				} );
		} );
	}

	$( function () {
		var $form = $( '#mwpgswp-settings-form' );

		if ( $form.length ) {
			initTabs( $form );
			initSecretToggles( $form );
			initDirtyGuard( $form );
			initSave( $form );

			refreshConditionalFields( $form );
			$form.on( 'change', function () {
				refreshConditionalFields( $form );
			} );
		}

		initOverviewChecks();
	} );
} )( jQuery );
