# PLAN — MainWP Configurator for Google Security for WordPress

## Goal

A MainWP Dashboard extension that lets an operator configure the **Google Security
for WordPress** (GSWP) plugin on each connected child site **from the MainWP
Dashboard**, without logging in to the child site. Because reCAPTCHA site/secret
keys are issued **per domain**, configuration is **per child site**: the
configurator lives as a tab ("subpage") inside each child site's own MainWP
screen (Manage Sites → *site* → **Google Security**), not as a global
push-to-all screen.

**Scope: configuration only.** Read and write the GSWP settings that the plugin
itself exposes on its settings screen — nothing else. No 2FA user management, no
alert-log viewing, no bulk apply, no install/update orchestration (MainWP core
already handles plugin installs/updates).

Sources of truth used for this plan:

- GSWP plugin `v2.8.1` (`onedogsolutions/google-security-for-wordpress`):
  `gswp_default_options()` in `google-security-for-wordpress.php`,
  `GSWP_Rest_Api::get_settings()`/`update_settings()` in
  `includes/class-gswp-rest-api.php`, the admin localizer in
  `includes/class-gswp-admin.php`, and the Phase 22 tab grouping in
  `src/components/SettingsTabs.jsx` / `STATE.md`.
- MainWP Dashboard source (`mainwp/mainwp`): `mainwp_getextensions`
  (`pages/page-mainwp-extensions.php`), `mainwp_getsubpages_sites`
  (`pages/page-mainwp-manage-sites.php`), `mainwp_fetchurlauthed`
  (`class/class-mainwp-system-handler.php` →
  `MainWP_Extensions_Handler::hook_fetch_url_authed()` in
  `pages/page-mainwp-extensions-handler.php`).
- MainWP Child source (`mainwp/mainwp-child`): the `extra_execution` callable →
  `mainwp_child_extra_execution` filter (`class/class-mainwp-child-callable.php`;
  note the legacy `extra_excution` spelling is deprecated — use
  `extra_execution`).
- The official starter, `mainwp/mainwp-development-extension` (activator
  pattern, autoloader, `sitetab` subpage registration,
  `mainwp_pageheader_sites`/`mainwp_pagefooter_sites` wrappers).

---

## Architecture

Two moving parts. Only the first lives in this repository.

```
┌────────────────────────────┐         signed HTTPS request           ┌──────────────────────────────┐
│ MainWP Dashboard site      │  fetch_url_authed(): openssl_sign()    │ Child site                   │
│                            │ ───────────────────────────────────►  │                              │
│ THIS REPO:                 │   function=extra_execution             │ MainWP Child: openssl_verify │
│ MainWP extension plugin    │   mwpgswp_action=get_settings |        │  └ filter                    │
│  ├ Extensions page (index) │                 update_settings        │    'mainwp_child_extra_      │
│  └ per-site "Google        │                                        │     execution'               │
│    Security" tab           │  ◄───────────────────────────────────  │     └ GSWP bridge class      │
│    (ManageSitesGSWP…&id=N) │         JSON settings payload          │       (ships IN GSWP core)   │
└────────────────────────────┘                                        └──────────────────────────────┘
```

### Part 1 — Dashboard extension (this repo)

A standard WordPress plugin installed **on the MainWP Dashboard site only**,
modeled on the official MainWP Development Extension skeleton.

### Part 2 — Child-side bridge (small addition to GSWP core)

`mainwp_child_extra_execution` is a filter *someone on the child site must
hook*; MainWP Child only dispatches it. Two options:

1. **Recommended: add the handler to GSWP itself** as
   `includes/class-gswp-mainwp-child.php` (GSWP `v2.9.0`). ~100 lines, loaded
   unconditionally, completely inert unless MainWP Child dispatches the filter
   with our action key. Child sites then need only what they already run: GSWP +
   MainWP Child. This is the pattern established integrations use, and GSWP
   already anticipates MainWP (STATE.md Phase 21 verified the MainWP Child RSA
   handshake against the app-password block).
2. Alternative: ship a third, standalone "child stub" plugin from this repo.
   Rejected — one more plugin to deploy/update on every site, and its only job
   would be calling into GSWP anyway.

The bridge **reuses GSWP's existing REST callbacks internally** so validation
stays single-source (see "Child-side bridge" below). No new REST route, no new
authentication surface: requests arrive only through MainWP Child's
openssl-signed channel.

**Why not call GSWP's existing `gswp/v1/settings` REST route from the
dashboard?** It authenticates with `manage_options` cookies/application
passwords. GSWP itself can disable application passwords
(`gswp_2fa_block_app_passwords`), so the configurator must not depend on them.
The MainWP channel is already established, per-site, and key-signed.

---

## Dashboard extension design (this repo)

### Identity & conventions

| Item | Value |
| --- | --- |
| Plugin name | MainWP for Google Security for WordPress |
| Main file | `mainwp-for-google-security-for-wordpress.php` |
| Handle / text domain | `mainwp-for-google-security-for-wordpress` |
| PHP namespace | `MainWP\Extensions\GSWP` |
| Prefix (options, AJAX, assets) | `mwpgswp_` |
| Subpage slug | `GSWPConfig` → page `admin.php?page=ManageSitesGSWPConfig&id={siteId}` |
| Requirements | PHP 7.4+, WP 5.8+ (match GSWP), MainWP Dashboard 5.x, MainWP Child 4.0+ (first version with `mainwp_child_extra_execution`), GSWP ≥ 2.9.0 on the child |

No build step: server-rendered PHP forms using Fomantic UI markup (already
loaded on every MainWP admin page) plus one small vanilla-JS file and one small
CSS file under `assets/`. GSWP's React/Tailwind stack stays out of this repo —
the MainWP admin brings its own design system and the settings form is plain
inputs.

### File layout

```
mainwp-for-google-security-for-wordpress.php   Activator (bootstraps everything)
class/
  class-mwpgswp-admin.php        Hooks, assets, AJAX endpoints
  class-mwpgswp-connector.php    fetchurlauthed wrapper + response normalization
  class-mwpgswp-individual.php   Per-site tab: render + save round trip
  class-mwpgswp-overview.php     Extensions-page landing (site status table)
  class-mwpgswp-settings-schema.php  The settings contract (single source, see below)
assets/
  js/mwpgswp-admin.js            Tab switching, AJAX save, dirty-state guard
  css/mwpgswp-admin.css          Minimal layout tweaks only
```

### Activator (main file)

Mirrors `mainwp-development-extension.php`:

- Constants (`MWPGSWP_PLUGIN_FILE|DIR|URL`), `spl_autoload_register` for
  `class/class-mwpgswp-*.php`.
- `add_filter( 'mainwp_getextensions', … )` returning
  `array( 'plugin' => __FILE__, 'api' => $handle, 'mainwp' => true,
  'callback' => [overview render], 'apiManager' => false )` — `apiManager`
  false because this is our free in-house extension (no MainWP API-manager
  licensing). Registration is **required** even though the real UI is
  per-site: `MainWP_Extensions_Handler::hook_fetch_url_authed()` verifies the
  caller via `hook_verify( $pluginFile, $key )`, and the key comes from
  `apply_filters( 'mainwp_extension_enabled_check', __FILE__ )`.
- `mainwp_activated_check` / `mainwp_activated` gate + the standard
  admin-notice when MainWP Dashboard is absent.
- In `activate_this_plugin()`: capture `$this->childKey`, register
  `add_filter( 'mainwp_getsubpages_sites', … )` adding:

  ```php
  $subPage[] = array(
      'title'       => __( 'Google Security', 'mainwp-for-google-security-for-wordpress' ),
      'slug'        => 'GSWPConfig',
      'sitetab'     => true,   // appears in the individual site's tab menu
      'menu_hidden' => true,   // not in the global Sites left menu — per-site only
      'callback'    => array( MWPGSWP_Individual::get_instance(), 'render_page' ),
  );
  ```

  `sitetab => true` + `menu_hidden => true` is exactly what the user asked for:
  a menu item within the child site's own settings area, since keys are
  per-domain.

### Connector

One class owning every dashboard→child call:

```php
$response = apply_filters(
    'mainwp_fetchurlauthed',
    $activator->get_child_file(),   // this extension's __FILE__
    $activator->get_child_key(),    // from mainwp_extension_enabled_check
    $site_id,
    'extra_execution',              // MainWP Child callable (not the deprecated 'extra_excution')
    array(
        'mwpgswp_action' => 'get_settings' | 'update_settings',
        'settings'       => array( … ),  // update only; JSON-encoded per MainWP convention
    )
);
```

Normalizes the three failure classes into typed results the UI can message
distinctly:

1. **Transport/site error** — `$response['error']` set (site unreachable,
   suspended → `SUSPENDED_SITE`, dashboard user lacks `can_edit_website`).
2. **Bridge missing** — response lacks our `mwpgswp` envelope key: MainWP Child
   answered but nothing hooked our action ⇒ "GSWP is not active on this child
   site, or is older than 2.9.0" (offer a link to MainWP's Install Plugins
   page).
3. **Bridge error** — envelope present with `success => false` + message
   (validation refused, etc.).

### Per-site tab (`MWPGSWP_Individual`)

- `render_page()` wraps everything in
  `do_action( 'mainwp_pageheader_sites', 'GSWPConfig' )` /
  `do_action( 'mainwp_pagefooter_sites', 'GSWPConfig' )` so MainWP draws the
  site header, tab bar, and left navigation. Site id from
  `intval( $_GET['id'] )` (MainWP has already set current-wpid context).
- On render, call `get_settings` synchronously. On failure render the typed
  error message and stop — no form is shown for a site we can't read
  (prevents blind overwrites).
- On success render **the same five task-oriented tabs as GSWP 2.8.1's own
  screen** (Phase 22 grouping), as a Fomantic
  `ui top attached tabular menu` + `ui bottom attached tab segment` set:

  | Tab | Fields (GSWP REST keys) |
  | --- | --- |
  | **API Credentials** | `site_key`, `secret_key`, `key_type` (classic/enterprise radio), `gcp_project_id`, `gcp_api_key` (Enterprise fields shown only when `key_type=enterprise`) |
  | **Form Protection** | WooCommerce: `enable_login`, `enable_registration`, `enable_checkout` + `threshold_login`, `threshold_registration`, `threshold_checkout`; WP core: `enable_wp_login`, `enable_wp_register`, `enable_wp_lostpassword` + `threshold_wp_login`, `threshold_wp_register`, `threshold_wp_lostpassword` (WooCommerce block gated on the child's reported `woocommerce_active`) |
  | **Enterprise Defense** | `txn_defense`, `txn_block`, `threshold_txn`, `account_defender`, `ad_step_up`, `ad_events` (whole tab shows the "requires an Enterprise key" notice when `key_type=classic`, mirroring the React panels) |
  | **Two-Factor Auth** | `tfa_enabled`, `tfa_enforced_roles` (checkboxes built from the child's reported role list — roles differ per site), `tfa_grace_days` (0–30), `tfa_remember`, `tfa_block_app_passwords`, `tfa_app_password_exempt_users` (with the same "existing app passwords stop working immediately" warning copy as GSWP's UI — and a MainWP-specific note that standard MainWP Child connections use the RSA handshake, not app passwords, so managing the site keeps working) |
  | **Alerts & Compatibility** | `alerts`, `alert_email`, `alert_mode` (immediate/hourly/daily), `alert_login`, `alert_checkout` (checkout gated on `woocommerce_active`), `conflict_mode` (off/active/site), `verbose_logging` |

- All tabs sit inside **one form with one Save button** submitting the complete
  settings object (same decision as GSWP Phase 22 — inactive tabs stay in the
  DOM, `hidden`), so partial saves can't tear related options apart.
- Save posts to a dashboard `admin-ajax` action (`mwpgswp_save_settings`) with
  a per-site nonce; the handler checks the nonce +
  `mainwp_current_user_can( 'dashboard', 'edit_sites' )`, forwards via the
  connector, and returns the child's **post-save settings** which re-populate
  the form (so what you see is what the child actually stored after its
  validation, e.g. a dropped invalid exempt login or clamped threshold).
- Secrets (`secret_key`, `gcp_api_key`) render as `type="password"` inputs with
  a reveal toggle; they are round-tripped as values (not write-only) to match
  GSWP's own screen, and they only ever transit MainWP's signed channel.
- Small JS extras: dirty-state "unsaved changes" beforeunload guard; active tab
  persisted in the URL hash (`#tab=<id>`), same pattern as GSWP's screen.

### Settings schema class

`class-mwpgswp-settings-schema.php` is the **single dashboard-side source of
truth**: every field key, its tab, its input type, its enum values
(`key_type`, `alert_mode`, `conflict_mode`), and numeric ranges (thresholds
0–1 step 0.05; `tfa_grace_days` 0–30). Render and save both iterate the
schema, so adding a future GSWP option is a one-array-entry change. Dashboard
performs **presentation-level** constraint enforcement only (HTML `min/max`,
enum selects); the child remains the authoritative validator.

### Extensions-page landing (`MWPGSWP_Overview`)

The `mainwp_getextensions` callback needs to render *something* under
Extensions. Keep it a thin index, not a second configurator:

- Table of connected child sites (via `apply_filters( 'mainwp_getsites', … )`)
  with a "Configure" link to each site's
  `admin.php?page=ManageSitesGSWPConfig&id={id}` tab.
- Optional (cheap) status column filled lazily per row via the same
  `get_settings` action: GSWP detected version, key type, protection on/off.
  No caching layer in v1; a "Check" button per row rather than N automatic
  requests on page load.

---

## Child-side bridge (change to the GSWP repo, shipped as GSWP v2.9.0)

New `includes/class-gswp-mainwp-child.php`, required unconditionally from the
main file (like the other always-on classes — MainWP Child requests are not
`is_admin()`), constructed in `gswp_init()`:

```php
class GSWP_MainWP_Child {
    public function __construct() {
        add_filter( 'mainwp_child_extra_execution', array( $this, 'handle' ), 10, 2 );
    }

    public function handle( $information, $post ) {
        if ( empty( $post['mwpgswp_action'] ) ) {
            return $information;              // not ours — pass through untouched
        }
        $rest = new GSWP_Rest_Api();
        switch ( $post['mwpgswp_action'] ) {
            case 'get_settings':
                $settings = $rest->get_settings()->get_data();
                break;
            case 'update_settings':
                $request = new WP_REST_Request( 'POST', '/gswp/v1/settings' );
                foreach ( (array) json_decode( wp_unslash( $post['settings'] ?? '' ), true ) as $k => $v ) {
                    $request->set_param( $k, $v );
                }
                $settings = $rest->update_settings( $request )->get_data();
                break;
            default:
                $information['mwpgswp'] = array( 'success' => false, 'error' => 'unknown action' );
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

Key properties:

- **Validation is reused, not duplicated.** Routing `update_settings` through
  `GSWP_Rest_Api::update_settings()` gets every existing rule for free:
  threshold clamping, enum whitelists, alert-email validation, the alert-mode
  cron reschedule, role validation against the child's real roles, and the
  exempt-login resolution against real child users (which **cannot** be
  validated dashboard-side — those users only exist on the child). Any future
  GSWP option wired into the REST route is automatically manageable from
  MainWP.
- **No new attack surface.** The filter only fires inside MainWP Child's
  `extra_execution` callable, which runs strictly after the RSA-signature
  authentication (`openssl_verify`) of a registered dashboard. The handler
  no-ops (returns `$information` untouched) for every request that doesn't
  carry `mwpgswp_action`, so it cannot interfere with other extensions
  sharing the filter.
- The permission callback (`current_user_can( 'manage_options' )`) is not
  re-run here — correct, because MainWP Child requests execute as the
  connected admin user by design; we call the REST **callbacks**, not the REST
  **route**.
- The `roles`/`woocommerce_active`/`version` meta mirrors what GSWP's own
  admin localizer feeds its React app, letting the dashboard UI make the same
  gating decisions.

Version gating: the extension sends `mwpgswp_action` unconditionally and infers
"GSWP missing/old" from the absent envelope (failure class 2 above), so no
separate handshake action is needed.

---

## Explicitly out of scope (v1) / roadmap

- **Bulk / group apply.** Site keys are per-domain, so a blind global push is
  wrong by construction — the core reason this lives as a per-site tab.
  Later: a curated "apply non-key settings to selected sites" action
  (thresholds, toggles, 2FA policy — everything except the credentials tab),
  and Enterprise keys registered for multiple domains could relax even that.
- 2FA user administration (enrollment status, resets) — GSWP handles that on
  the child's profile screens.
- Alert log / event surfacing on the dashboard, sync-time status collection,
  and a MainWP Overview metabox (`mainwp_getmetaboxes`).
- MainWP Pro Reports / Client Reports tokens.

---

## Milestones

**M1 — Extension skeleton (this repo).** Activator, autoloader,
`mainwp_getextensions` registration, MainWP-absent notice, empty per-site tab
rendering between the MainWP page header/footer, empty overview page.
*Verify:* extension appears under Extensions; "Google Security" tab appears on
an individual site and renders inside MainWP chrome.

**M2 — Child bridge (GSWP repo, v2.9.0).** `class-gswp-mainwp-child.php` as
specified + require/init wiring + version bump per GSWP's release checklist
(header, `GSWP_VERSION`, `readme.txt`, `package.json`; no JS rebuild needed —
PHP only). *Verify:* `php -l`; on a dev child site, a hand-built signed request
(or the M3 dashboard) round-trips `get_settings`.

**M3 — Read path.** Connector + settings schema + full five-tab form rendered
from live child settings, including the three typed failure states and
Enterprise/WooCommerce gating. *Verify:* tab shows real settings from a
connected child; disable GSWP on the child and confirm the "not active or
< 2.9.0" message; suspend the site and confirm the suspended message.

**M4 — Write path.** AJAX save (nonce + `mainwp_current_user_can`), child-side
persist through the REST callbacks, form re-hydration from the post-save
response, dirty-state guard. *Verify:* every field on every tab saves and
re-loads; child's GSWP screen shows the same values; invalid exempt login is
dropped; threshold >1 comes back clamped; alert-mode change reschedules the
digest cron on the child.

**M5 — Overview + packaging.** Overview site table with per-row "Check"
status, readme, distribution ZIP (mirror GSWP's explicit-file-list packaging),
tag v1.0.0. *Verify:* full manual pass of the testing checklist below.

## Testing checklist (M5 gate)

Environment per MainWP's Lesson 1 dev setup: one dashboard site + two child
sites (one with WooCommerce, one without; different reCAPTCHA keys).

1. Fetch/save round trip on both children; values stay independent per site
   (per-domain keys never bleed across sites).
2. Non-Woo child: WooCommerce toggles hidden, WP-core protections save.
3. Classic key: Enterprise Defense tab shows the notice; switching key type to
   enterprise reveals GCP fields without a reload.
4. Roles list on the 2FA tab matches the child's actual roles (add a custom
   role on one child).
5. Failure states: child offline; GSWP deactivated; GSWP 2.8.x (bridge
   absent); suspended site; dashboard user without `edit_sites`.
6. `tfa_block_app_passwords` enabled from the dashboard → MainWP connection
   still works afterwards (RSA handshake, already verified in GSWP Phase 21 —
   re-confirm through this new write path).
7. No output/regressions on child front end with the bridge merged but MainWP
   Child inactive (class must be inert).
