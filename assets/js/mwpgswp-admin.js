/**
 * MainWP for Google Security for WordPress - admin JS.
 *
 * Plain JS, no dependencies. No build step: this file is enqueued as-is by
 * MWPGSWP_Admin::enqueue_assets().
 */
( function () {
	'use strict';

	var isDirty = false;

	/**
	 * Tabs: show the clicked tab's panel, hide the rest, persist in the hash.
	 */
	function initTabs( form ) {
		var tabs = form.querySelectorAll( '.mwpgswp-tabs .mwpgswp-tab-btn' );
		var panels = form.querySelectorAll( '.mwpgswp-tabpanel' );

		function activate( tabId ) {
			var matchedTab = null;
			var matchedPanel = null;

			// 'active' is Fomantic UI's own class for the current tabular-menu
			// item / tab segment (cosmetic styling); 'hidden' is what actually
			// shows/hides the panel — kept explicit rather than relying on
			// Fomantic's own tab-segment default display, which a segment's
			// unlayered CSS could otherwise win over the [hidden] UA rule.
			tabs.forEach( function ( tab ) {
				var isMatch = tab.getAttribute( 'data-tab' ) === tabId;
				tab.classList.toggle( 'active', isMatch );
				if ( isMatch ) {
					matchedTab = tab;
				}
			} );

			panels.forEach( function ( panel ) {
				var isMatch = panel.getAttribute( 'data-tab' ) === tabId;
				panel.classList.toggle( 'active', isMatch );
				panel.hidden = ! isMatch;
				if ( isMatch ) {
					matchedPanel = panel;
				}
			} );

			if ( ! matchedTab || ! matchedPanel ) {
				tabs.forEach( function ( tab, i ) {
					tab.classList.toggle( 'active', 0 === i );
				} );
				panels.forEach( function ( panel, i ) {
					panel.classList.toggle( 'active', 0 === i );
					panel.hidden = 0 !== i;
				} );
			}
		}

		tabs.forEach( function ( tab ) {
			tab.addEventListener( 'click', function () {
				var tabId = tab.getAttribute( 'data-tab' );
				activate( tabId );
				window.location.hash = 'tab=' + tabId;
			} );
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
	function getFieldValue( form, fieldKey ) {
		var checkbox = form.querySelector( 'input[type="checkbox"][name="fields[' + fieldKey + ']"]' );
		if ( checkbox ) {
			return checkbox.checked ? '1' : '0';
		}
		var control = form.querySelector( '[name="fields[' + fieldKey + ']"]' );
		return control ? control.value : null;
	}

	/**
	 * Conditional field visibility: a field wrapper carrying
	 * data-requires-field/data-requires-value is shown only when that other
	 * field currently holds the matching value. Values still submit either
	 * way (see PLAN — hidden fields stay mounted, same as GSWP's own screen).
	 */
	function refreshConditionalFields( form ) {
		form.querySelectorAll( '[data-requires-field]' ).forEach( function ( row ) {
			var requiredField = row.getAttribute( 'data-requires-field' );
			var requiredValue = String( row.getAttribute( 'data-requires-value' ) );
			var currentValue = getFieldValue( form, requiredField );

			row.style.display = ( null !== currentValue && String( currentValue ) === requiredValue ) ? '' : 'none';
		} );
	}

	/**
	 * Secret field show/hide toggle.
	 */
	function initSecretToggles( form ) {
		form.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '.mwpgswp-secret-toggle' );
			if ( ! btn ) {
				return;
			}

			var input = document.getElementById( btn.getAttribute( 'data-target' ) );
			if ( ! input ) {
				return;
			}
			var isPassword = 'password' === input.getAttribute( 'type' );

			input.setAttribute( 'type', isPassword ? 'text' : 'password' );
			btn.textContent = isPassword ? ( mwpgswpAdmin.hide || 'Hide' ) : ( mwpgswpAdmin.show || 'Show' );
		} );
	}

	/**
	 * Dirty-state guard: warn before navigating away with unsaved changes.
	 */
	function initDirtyGuard( form ) {
		form.addEventListener( 'change', function () {
			isDirty = true;
		} );
		form.addEventListener( 'input', function () {
			isDirty = true;
		} );

		window.addEventListener( 'beforeunload', function ( e ) {
			if ( isDirty ) {
				e.preventDefault();
				e.returnValue = mwpgswpAdmin.confirmUnsaved;
				return mwpgswpAdmin.confirmUnsaved;
			}
		} );
	}

	/**
	 * POST a form-urlencoded body to admin-ajax.php and resolve the parsed
	 * JSON response. Rejects only on a network/transport failure — a
	 * WP-style { success:false, data:{...} } payload still resolves so
	 * callers can read the error message out of it.
	 */
	function postAjax( body ) {
		return fetch( mwpgswpAdmin.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body,
		} ).then( function ( response ) {
			return response.json();
		} );
	}

	/**
	 * AJAX save.
	 */
	function initSave( form ) {
		var status = document.getElementById( 'mwpgswp-save-status' );
		var notice = document.getElementById( 'mwpgswp-notice' );
		var button = document.getElementById( 'mwpgswp-save-btn' );

		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();

			notice.hidden = true;
			notice.textContent = '';
			status.textContent = mwpgswpAdmin.saving;
			button.disabled = true;

			postAjax( new URLSearchParams( new FormData( form ) ).toString() )
				.then( function ( response ) {
					if ( response && response.success ) {
						status.textContent = mwpgswpAdmin.saved;
						isDirty = false;
					} else {
						var message = response && response.data && response.data.message
							? response.data.message
							: mwpgswpAdmin.saveError;
						status.textContent = '';
						notice.hidden = false;
						notice.textContent = message;
					}
				} )
				.catch( function () {
					status.textContent = '';
					notice.hidden = false;
					notice.textContent = mwpgswpAdmin.saveError;
				} )
				.then( function () {
					button.disabled = false;
				} );
		} );
	}

	/**
	 * Overview page: per-row "Check" status button.
	 */
	function initOverviewChecks() {
		document.querySelectorAll( '.mwpgswp-check-btn' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var siteId = btn.getAttribute( 'data-site-id' );
				var cell = btn.closest( 'tr' ).querySelector( '.mwpgswp-status-cell' );

				cell.textContent = mwpgswpAdmin.checking;
				btn.disabled = true;

				postAjax( new URLSearchParams( {
					action: 'mwpgswp_check_site',
					nonce: mwpgswpAdmin.checkNonce,
					site_id: siteId,
				} ).toString() )
					.then( function ( response ) {
						if ( response && response.success ) {
							var d = response.data;
							cell.textContent = d.version + ' – ' + d.key_type + ( d.protected ? ' ✓' : '' );
						} else {
							cell.textContent = ( response && response.data && response.data.message ) || mwpgswpAdmin.saveError;
						}
					} )
					.catch( function () {
						cell.textContent = mwpgswpAdmin.saveError;
					} )
					.then( function () {
						btn.disabled = false;
					} );
			} );
		} );
	}

	/**
	 * "Install GSWP" button: appears on overview rows (with a status cell to
	 * update) and inside the per-site tab's bridge-missing notice (with no
	 * status cell — reload the page on success instead, since the settings
	 * form only renders once the fetch succeeds).
	 */
	function initInstallButtons() {
		document.querySelectorAll( '.mwpgswp-install-btn' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				if ( ! window.confirm( mwpgswpAdmin.confirmInstall ) ) {
					return;
				}

				var siteId = btn.getAttribute( 'data-site-id' );
				var row = btn.closest( 'tr' );
				var cell = row ? row.querySelector( '.mwpgswp-status-cell' ) : null;

				btn.disabled = true;
				if ( cell ) {
					cell.textContent = mwpgswpAdmin.installing;
				}

				postAjax( new URLSearchParams( {
					action: 'mwpgswp_install_gswp',
					nonce: mwpgswpAdmin.installNonce,
					site_id: siteId,
				} ).toString() )
					.then( function ( response ) {
						if ( response && response.success ) {
							if ( cell ) {
								cell.textContent = mwpgswpAdmin.installed;
								btn.disabled = false;
							} else {
								window.location.reload();
							}
						} else {
							var message = ( response && response.data && response.data.message ) || mwpgswpAdmin.saveError;
							if ( cell ) {
								cell.textContent = message;
							} else {
								window.alert( message );
							}
							btn.disabled = false;
						}
					} )
					.catch( function () {
						if ( cell ) {
							cell.textContent = mwpgswpAdmin.saveError;
						}
						btn.disabled = false;
					} );
			} );
		} );
	}

	/**
	 * Overview page: the GSWP package (ZIP URL) setting, with an optional
	 * media-library upload that fills the URL field.
	 */
	function initPackageForm() {
		var form = document.getElementById( 'mwpgswp-package-form' );
		if ( ! form ) {
			return;
		}

		var status = document.getElementById( 'mwpgswp-package-status' );
		var urlInput = document.getElementById( 'mwpgswp-package-url' );
		var uploadBtn = document.getElementById( 'mwpgswp-package-upload' );

		if ( uploadBtn && window.wp && window.wp.media ) {
			uploadBtn.addEventListener( 'click', function ( e ) {
				e.preventDefault();

				var frame = window.wp.media( {
					title: mwpgswpAdmin.selectZip,
					library: { type: 'application/zip' },
					multiple: false,
					button: { text: mwpgswpAdmin.useThisFile },
				} );

				frame.on( 'select', function () {
					var attachment = frame.state().get( 'selection' ).first().toJSON();
					urlInput.value = attachment.url;
				} );

				frame.open();
			} );
		}

		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();

			status.textContent = mwpgswpAdmin.saving;

			postAjax( new URLSearchParams( new FormData( form ) ).toString() )
				.then( function ( response ) {
					status.textContent = ( response && response.success )
						? mwpgswpAdmin.saved
						: ( ( response && response.data && response.data.message ) || mwpgswpAdmin.saveError );
				} )
				.catch( function () {
					status.textContent = mwpgswpAdmin.saveError;
				} );
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var form = document.getElementById( 'mwpgswp-settings-form' );

		if ( form ) {
			initTabs( form );
			initSecretToggles( form );
			initDirtyGuard( form );
			initSave( form );

			refreshConditionalFields( form );
			form.addEventListener( 'change', function () {
				refreshConditionalFields( form );
			} );
		}

		initOverviewChecks();
		initInstallButtons();
		initPackageForm();
	} );
} )();
