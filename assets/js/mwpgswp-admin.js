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

			tabs.forEach( function ( tab ) {
				var isMatch = tab.getAttribute( 'data-tab' ) === tabId;
				tab.classList.toggle( 'is-active', isMatch );
				if ( isMatch ) {
					matchedTab = tab;
				}
			} );

			panels.forEach( function ( panel ) {
				var isMatch = panel.getAttribute( 'data-tab' ) === tabId;
				panel.classList.toggle( 'is-active', isMatch );
				panel.hidden = ! isMatch;
				if ( isMatch ) {
					matchedPanel = panel;
				}
			} );

			if ( ! matchedTab || ! matchedPanel ) {
				tabs.forEach( function ( tab, i ) {
					tab.classList.toggle( 'is-active', 0 === i );
				} );
				panels.forEach( function ( panel, i ) {
					panel.classList.toggle( 'is-active', 0 === i );
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
			notice.classList.remove( 'notice-error' );
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
						notice.classList.add( 'notice-error' );
						notice.textContent = message;
					}
				} )
				.catch( function () {
					status.textContent = '';
					notice.hidden = false;
					notice.classList.add( 'notice-error' );
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
	} );
} )();
