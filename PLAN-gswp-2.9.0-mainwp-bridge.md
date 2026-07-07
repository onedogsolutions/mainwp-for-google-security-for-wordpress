# PLAN — GSWP v2.9.0: MainWP child-side bridge

Handoff document for a **new session opened on the `onedogsolutions/google-security-for-wordpress`
repository**. The dashboard-side MainWP extension (this repository) is complete
and merged to `main` at v1.0.0; every call it makes currently resolves to its
"bridge missing" state because nothing on the child sites hooks
`mainwp_child_extra_execution` yet. This plan specifies the GSWP core change
that closes that gap.

---

## Copy-paste prompt for the new session

> Release Google Security for WordPress v2.9.0, whose only feature is a MainWP
> child-side bridge, following `PLAN-gswp-2.9.0-mainwp-bridge.md` from the
> companion repo `onedogsolutions/mainwp-for-google-security-for-wordpress`
> (the full spec, wire-format contract, and reference implementation are in
> that file — read it first; if the repo isn't attached to the session, ask
> for it or for the file's contents).
>
> Summary: add `includes/class-gswp-mainwp-child.php` — a class that hooks the
> `mainwp_child_extra_execution` filter (MainWP Child 4.0+) and answers two
> actions, `get_settings` and `update_settings`, by reusing the existing
> `GSWP_Rest_Api` callbacks so every validation rule stays single-source. It
> must be completely inert when MainWP Child is absent, must never disturb the
> `$information` array when the request isn't ours, and must return the exact
> `mwpgswp` response envelope the already-shipped dashboard extension expects.
> Then do the standard GSWP release pass: version 2.9.0 in the plugin header,
> `GSWP_VERSION`, `readme.txt` (stable tag + feature bullet + changelog),
> `package.json` and the `package-lock.json` root entry; add a Phase entry to
> `STATE.md` per its existing format; `php -l` everything touched; make sure
> the new class file is added to the distribution-ZIP file list. PHP only — no
> React/webpack rebuild is needed. Do not change the REST route, options, or
> any existing class behavior.

---

## Context the new session needs

- **Companion extension (already shipped, do not change its contract):**
  `onedogsolutions/mainwp-for-google-security-for-wordpress` v1.0.0. Its
  `class/class-mwpgswp-connector.php` is the other end of the wire: it calls
  `apply_filters( 'mainwp_fetchurlauthed', $extFile, $extKey, $siteId,
  'extra_execution', $postData )` on the dashboard, which MainWP delivers to
  the child site as an authenticated POST. MainWP Child's `extra_execution`
  callable then runs `apply_filters( 'mainwp_child_extra_execution',
  $information, $post )` with `$post = $_POST` and writes `$information` back
  as the JSON response (`mainwp-child/class/class-mainwp-child-callable.php`,
  `extra_execution()`).
- **Timing:** MainWP Child dispatches callables from `parse_init` on `init`
  priority 9999 (`mainwp-child/class/class-mainwp-child.php`). GSWP constructs
  its classes in `gswp_init()` on `plugins_loaded` priority 10 — registering
  the filter there is safely early.
- **Authentication:** the request has already passed MainWP Child's
  RSA-signature verification (`openssl_verify`) and runs as the connected
  admin user before the filter fires. That is why the bridge calls the REST
  **callbacks** and not the REST **route**, and why it must not re-run
  `current_user_can()` itself.
- **Why this design:** routing writes through
  `GSWP_Rest_Api::update_settings()` reuses, rather than duplicates, every
  existing rule — threshold clamping, enum whitelists, alert-email
  validation, the alert-mode digest-cron reschedule, role validation against
  the child's real roles, and exempt-login resolution against real child
  users (which cannot be validated dashboard-side). Any future GSWP option
  wired into the REST route becomes manageable from MainWP automatically.

## Wire-format contract (fixed — the extension is already deployed)

**Request** (fields in `$_POST`; WordPress slashes superglobals, so string
values must be `wp_unslash()`ed before use):

| Field | Value |
| --- | --- |
| `mwpgswp_action` | `get_settings` or `update_settings` |
| `settings` | `update_settings` only: JSON-encoded object of settings, keyed exactly as `GSWP_Rest_Api::update_settings()` expects (same keys as `get_settings()` returns — e.g. `site_key`, `tfa_enforced_roles` as an array, toggles as `'1'`/`'0'` strings) |

**Response**: add ONE key, `mwpgswp`, to the `$information` array (never touch
any other key — other extensions share this filter):

```
// Success (both actions):
$information['mwpgswp'] = array(
    'success'            => true,
    'version'            => GSWP_VERSION,
    'woocommerce_active' => (bool),           // class_exists( 'WooCommerce' )
    'roles'              => wp_roles()->get_names(),
    'settings'           => (array),          // full settings map, post-save values for update_settings
);

// Failure (unknown action, undecodable settings payload):
$information['mwpgswp'] = array( 'success' => false, 'error' => '...' );
```

When `$_POST['mwpgswp_action']` is absent, return `$information` **unchanged**
— this is how the dashboard distinguishes "GSWP missing/old" (no `mwpgswp`
key in an otherwise-successful response) from an error, and how the bridge
stays invisible to every other extension using `extra_execution`.

## Implementation

### 1. New file: `includes/class-gswp-mainwp-child.php`

Reference implementation (adjust doc-comment style to match the neighboring
GSWP classes):

```php
<?php
/**
 * MainWP child-side bridge.
 *
 * Lets the MainWP for Google Security for WordPress dashboard extension read
 * and update this plugin's settings over MainWP's signed dashboard-to-child
 * channel. Requests arrive only through MainWP Child's 'extra_execution'
 * callable, strictly after its RSA-signature authentication, running as the
 * connected admin user — so the REST callbacks are invoked directly and no
 * capability re-check happens here. Inert unless MainWP Child dispatches the
 * filter with our action key.
 *
 * @package Google_Security_For_WordPress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GSWP_MainWP_Child {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'mainwp_child_extra_execution', array( $this, 'handle' ), 10, 2 );
	}

	/**
	 * Answer the dashboard extension's get_settings / update_settings actions.
	 *
	 * Only the 'mwpgswp' key is ever added to $information; requests that do
	 * not carry our action key pass through untouched so other MainWP
	 * extensions sharing this filter are never disturbed.
	 *
	 * @param array $information Response accumulator shared by all subscribers.
	 * @param array $post        The raw (slashed) POST of the child request.
	 * @return array $information, with 'mwpgswp' added when the request is ours.
	 */
	public function handle( $information, $post ) {
		if ( ! is_array( $post ) || empty( $post['mwpgswp_action'] ) ) {
			return $information;
		}

		$rest   = new GSWP_Rest_Api();
		$action = wp_unslash( $post['mwpgswp_action'] );

		if ( 'get_settings' === $action ) {
			$settings = $rest->get_settings()->get_data();
		} elseif ( 'update_settings' === $action ) {
			$incoming = json_decode( isset( $post['settings'] ) ? wp_unslash( $post['settings'] ) : '', true );
			if ( ! is_array( $incoming ) ) {
				$information['mwpgswp'] = array(
					'success' => false,
					'error'   => 'invalid settings payload',
				);
				return $information;
			}
			// Route through the REST callback so every validation rule
			// (threshold clamping, enum whitelists, email/login/role
			// validation, the alert-mode cron reschedule) applies unchanged.
			$request = new WP_REST_Request( 'POST', '/gswp/v1/settings' );
			foreach ( $incoming as $key => $value ) {
				$request->set_param( $key, $value );
			}
			$settings = $rest->update_settings( $request )->get_data();
		} else {
			$information['mwpgswp'] = array(
				'success' => false,
				'error'   => 'unknown action',
			);
			return $information;
		}

		$information['mwpgswp'] = array(
			'success'            => true,
			'version'            => GSWP_VERSION,
			'woocommerce_active' => class_exists( 'WooCommerce' ),
			'roles'              => wp_roles()->get_names(),
			'settings'           => $settings,
		);

		return $information;
	}
}
```

### 2. Wiring in `google-security-for-wordpress.php`

- Add the require alongside the other unconditional requires (natural spot:
  right after the `class-gswp-rest-api.php` require), with a short comment in
  the file's established style, e.g.: *MainWP Child dispatches dashboard
  requests on the front end (`is_admin()` is false), so the bridge must load
  unconditionally; it is inert unless MainWP Child fires its filter.*
- Construct it in `gswp_init()` alongside the other `new GSWP_*()` calls:
  `new GSWP_MainWP_Child();` with a one-line comment matching the pattern of
  the neighbors ("Inert unless …").
- **No changes** to `gswp_default_options()`, activation, migration, the REST
  class, or anything else.

### 3. Release pass (GSWP house style — mirror what STATE.md records for past releases)

1. Version `2.9.0` in: the plugin header `Version:`, the `GSWP_VERSION`
   define, `readme.txt` (`Stable tag`, a feature bullet describing MainWP
   dashboard management, a changelog entry), `package.json` version, and the
   `package-lock.json` **root** version entry.
2. Consider one `readme.txt` FAQ: "Can I manage these settings from MainWP?"
   → yes, with the companion extension on the dashboard and GSWP ≥ 2.9.0 on
   the child; the connection uses MainWP's own signed channel, not
   application passwords.
3. `STATE.md`: add a new "Current Phase" section (Phase 23 if unchanged
   upstream — check the file first) in the established format, demoting the
   previous phase to historical. Record: the bridge class, the wire contract,
   the reuse-the-REST-callbacks rationale, and that this is PHP-only.
4. **Packaging:** the distribution ZIP copies an explicit file list (that is
   how `tests/manual/` stays excluded — see STATE.md Phase 21). Find that
   list/script and add `includes/class-gswp-mainwp-child.php`, or the release
   ZIP will silently ship without the feature.
5. No React/webpack rebuild — nothing under `src/` or `assets/` changes.

## Verification

1. `php -l` on both touched PHP files.
2. **Inertness:** with MainWP Child absent, confirm the class only registers
   a filter nobody fires — no output, no hooks on any core action, no
   front-end effect.
3. **Round trip on staging** (dashboard site with the extension v1.0.0 ZIP +
   a child site on this build):
   - The per-site **Google Security** tab (Manage Sites → site → Google
     Security) loads live settings instead of the "not active … or older
     than 2.9.0" message.
   - Save each of the five tabs; re-open and confirm persisted values match
     GSWP's own settings screen on the child.
   - Child-side validation shows through: an invalid username in the 2FA
     exempt list comes back dropped; an out-of-range threshold comes back
     clamped; an `alert_mode` change reschedules the digest cron on the child.
   - Switch `key_type` classic ↔ enterprise and confirm the Enterprise
     Defense tab gating follows.
   - A non-WooCommerce child hides the WooCommerce toggles (bridge reports
     `woocommerce_active`).
   - Deactivate GSWP on the child → the tab shows the bridge-missing message
     again (and no PHP notice appears in the child's logs from other MainWP
     traffic).
4. Confirm a normal MainWP sync of the child site is unaffected (the filter
   passes non-matching requests through untouched).

## Explicitly out of scope

Same boundaries as the extension: no bulk/group apply, no 2FA user
administration, no alert-log surfacing, no new REST routes, no changes to
existing GSWP behavior. Configuration transport only.
