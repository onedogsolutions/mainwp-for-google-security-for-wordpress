# State Tracker - MainWP for Google Security for WordPress

A MainWP Dashboard extension for configuring the Google Security for WordPress
(GSWP) plugin on each connected child site. Installed on the MainWP Dashboard
site only. Companion plugin: `onedogsolutions/google-security-for-wordpress`
(its own STATE.md tracks the child-side work).

## Current Phase: Phase 9 (Settings schema synced to GSWP 2.15.0)

Added the 9 new settings and 1 read-only capability flag introduced in GSWP
v2.10.0–v2.13.0. The child-side bridge already validates all of these (single-
source REST validation in `class-gswp-rest-api.php`), so the dashboard only
needed matching schema entries to render and submit them. No new JS, CSS, or
AJAX endpoints — the schema-driven architecture handles render, conditional
visibility, and save generically.

### Phase 9 Modifications (v1.2.0)
- **9 new schema entries** in `class-mwpgswp-settings-schema.php`:
  - Enterprise Defense tab: `ad_block_signup` (Block Suspicious Sign-ups),
    `ad_share_email` (Send Email Identifiers to Google), `password_defense`
    (master toggle), `pd_login` (Check Credentials on Login), `pd_block_choice`
    (Block Leaked Password at Reset/Profile), `pd_force_reset` (Refuse Login
    With Leaked Password). The Password Defense sub-toggles use
    `'requires' => array( 'password_defense' => '1' )` for conditional
    visibility; the Account Defender sub-toggles use the existing
    `'requires' => array( 'account_defender' => '1' )` pattern.
  - Two-Factor Auth tab: `tfa_env_binding` (Disable 2FA on Cloned or Moved
    Sites, default on), placed after `tfa_remember`.
  - Alerts & Compatibility tab: `alert_registration` (Alert on Suspicious
    Sign-up) and `alert_leak` (Alert on Leaked Credentials), placed after
    `alert_checkout`.
- **`pd_supported` capability notice** in `class-mwpgswp-individual.php`:
  when the child's `get_settings` response includes `pd_supported => false`
  (no GMP/BCMath or 32-bit PHP), a Fomantic `ui warning message` is printed
  inside the Enterprise Defense tab explaining the toggles will be inert.
  Display-only — the toggles still render and save (the child ignores them
  gracefully), matching GSWP's own behavior. The `pd_supported` key is not in
  the schema, so it is naturally excluded from the save payload.
- **No render/JS/save-path changes needed** — the existing generic loops
  (`render_field()`, conditional-visibility JS via `data-requires-field`/
  `-value`, `ajax_save()` toggle coercion) handle all 9 new fields without
  modification.
- Version bumped to 1.2.0 (plugin header, `MWPGSWP_VERSION`, `readme.txt`
  stable tag + changelog + updated feature bullets). `php -l` clean on all
  touched PHP; `node --check` clean on the JS (unchanged).

## Historical Phase: Phase 8 (Drag-and-drop package upload replaces wp.media dialog)

Replaced the WordPress media-library dialog (`wp.media`) for uploading the
GSWP plugin ZIP with a self-contained drag-and-drop upload zone, modeled on
the shadcn/origin comp-545 pattern (drop area + button + preview + remove)
but implemented in the project's plain-JS, no-build-step, Fomantic-UI style.

### Phase 8 Modifications
- **Removed `wp_enqueue_media()`** from `MWPGSWP_Overview::render_page()` —
  the media-library frame is no longer needed.
- **New drop-zone markup** in the Extensions-page package form: a dashed-border
  container with an upload icon, "Drop your ZIP here" prompt, file-type/size
  hint ("ZIP only, max. 10 MB"), a "Select file" button, a file-preview row
  (filename + × remove button), and a `role="alert"` error line. The URL
  input remains below (auto-populated on successful upload, or manually
  entered for a self-hosted ZIP) with a helper description.
- **New AJAX endpoint `mwpgswp_upload_package`**
  (`MWPGSWP_Overview::ajax_upload_package()`): validates the upload (ZIP
  extension, ≤ 10 MB, `UPLOAD_ERR_OK`), stores the file in
  `wp-content/uploads/mwpgswp-packages/` with a unique timestamped name,
  writes `.htaccess` (Deny from all) + `index.php` (silence) guards on first
  use, and returns the public URL. Gated behind `upload_files` +
  `install_plugins` capabilities and its own nonce.
- **Rewrote `initPackageForm()` in `mwpgswp-admin.js`**: client-side
  validation (extension + size), `FormData`/`fetch()` multipart upload,
  drag-enter/over/leave/drop handlers with a `--dragging` visual state,
  click-anywhere-to-browse (when no file is loaded), preview/remove toggle,
  and inline error display. The existing save-package form-submit handler is
  unchanged.
- **New CSS** in `mwpgswp-admin.css`: drop-zone layout (flexbox centering,
  min-height, dashed border, rounded corners), visually-hidden file input,
  icon circle, prompt/preview states, remove button, error text. Layout-only,
  no color values — inherits MainWP's Fomantic theme (light/dark).
- **Localized strings updated** in `MWPGSWP_Admin::enqueue_assets()`: added
  `uploadNonce`, `uploading`, `uploadError`, `invalidType`, `invalidSize`;
  removed the now-unused `selectZip`/`useThisFile`.
- `php -l` clean on all touched PHP; `node --check` clean on the JS. No new
  files; same four source files modified in place.

## Historical Phase: Phase 7 (GSWP 2.9.0 bridge confirmed live end-to-end; Secret Key field gating, v1.1.2)

**No longer blocked.** GSWP 2.9.0 has shipped the child-side bridge and it
was verified working end-to-end on the live staging dashboard: the per-site
tab loaded live settings ("Google Security for WordPress 2.9.0 on this
site"), a settings save round-tripped and persisted on the child (API
Credentials tab, Enterprise key type, GCP project ID, save confirmed with
"Saved."), and the "Install GSWP" package-upload + install-to-child flow
both worked. The extension is functionally complete for its v1 scope.

### Phase 7 Modifications (v1.1.2)
- Same live test surfaced one more polish item: the API Credentials tab
  showed the classic **Secret Key** field even when Key Type was set to
  reCAPTCHA Enterprise, where it's unused (Enterprise verifies through the
  GCP Project ID / API Key pair instead — confirmed with the operator, who
  owns GSWP). Fixed with the same conditional-visibility mechanism already
  used for the `gcp_project_id`/`gcp_api_key` fields: added `'requires' =>
  array( 'key_type' => 'classic' )` to `secret_key` in
  `class-mwpgswp-settings-schema.php`. No render/JS/save-path changes needed
  — `render_field()` already emits `data-requires-field`/`-value` from any
  field's `requires` entry, and the existing JS re-evaluates visibility
  against the live `key_type` select on load and on every change. The field
  still stays mounted and submits its value when hidden (same "hidden
  fields stay mounted" design as every other conditional field), so no
  behavior changed beyond presentation.
- Version bumped to 1.1.2 (plugin header, `MWPGSWP_VERSION`, `readme.txt`
  stable tag + changelog). `php -l` clean.

## Historical Phase: Phase 6 (Second live-test fix: extensions-page header title, v1.1.1)

### Phase 6 Modifications (v1.1.1)
- Re-tested v1.1.0 on the live staging dashboard. The Add-ons grid card and
  left-sidebar link now correctly read "Google Security for WordPress" (the
  Phase 5 fix worked), but the `<h1>`-style title MainWP prints atop this
  extension's *own* Extensions-page screen still read "For Google Security
  For Wordpress".
- **Root cause, found by reading further into MainWP's source rather than
  guessing from the symptom:** that title is a *different* string than the
  one the Phase 5 fix corrected. `MainWP_Extensions_View::render_header()`
  (`class/class-mainwp-extensions-view.php`) builds it from `$_GET['page']`
  — the current admin page's URL slug — via
  `polish_string_name( str_replace( '-', ' ', $_GET['page'] ) )`. It never
  looks at the extension's registered `'name'` at all. And that slug isn't
  ours to set either: `MainWP_Extensions::init_menu()`
  (`pages/page-mainwp-extensions.php`) unconditionally *overwrites*
  `$extension['page']` with `'Extensions-' . ucwords(str_replace('-',' ',
  dirname(plugin_basename)))` — i.e. this plugin's installed **folder
  name**, Title-Cased — with no `isset()` guard, so supplying our own
  `'page'` key would have been silently discarded. For this plugin's folder
  (`mainwp-for-google-security-for-wordpress`) that naive transform produces
  "Mainwp For Google Security For Wordpress", and the same
  `MainWP`-token-stripping pass used for the extension name (`polish_string_
  name()`) then removes "Mainwp", leaving "For Google Security For
  Wordpress" — wrong case throughout (`ucwords()` capitalizes every word and
  has no notion of "WordPress"'s internal capital), and completely
  independent of the Phase 5 fix.
- **Fix:** MainWP exposes exactly one escape hatch for this —
  `apply_filters( 'mainwp_extensions_page_top_header', $title, $raw_page )`
  — hooked in the main activator's constructor
  (`fix_extensions_page_title()`). It recomputes the same slug MainWP would
  (mirroring `init_menu()`'s formula against `plugin_basename( __FILE__ )`,
  so it self-corrects if the installed folder is ever renamed) and only
  overrides the title when `$raw_page` matches this extension's own page —
  every other extension's title passes through unchanged.
- Version bumped to 1.1.1 (plugin header, `MWPGSWP_VERSION`, `readme.txt`
  stable tag + changelog). `php -l` clean.
- **Second symptom from the same test, closed — not a plugin bug.** "On
  initial load of the Add-ons page the left menu registers the add-on, but
  the card is missing until you click into Add-ons from the sidebar."
  Traced `MainWP_Extensions_Handler::get_extensions()` and `init_menu()`: the
  extensions list is force-refreshed from a *fresh* `apply_filters(
  'mainwp_getextensions', ... )` call at the end of *every* `init_menu()`
  run, and `init_menu()` itself runs on the `admin_menu` hook — i.e. on
  every single admin page load, not just the Extensions page. No code path
  (ours or MainWP's) was found that would cache a stale extensions list
  across a full page navigation, and confirmed with the operator: a hard
  refresh, or a fresh logout/login, shows the card immediately, and the
  dashboard site runs RunCloud Hub (which purges its cache on a new plugin
  install, but evidently not every layer instantly — most likely leftover
  PHP opcache bytecode from before the plugin file swap, or a browser/edge
  cache on that one navigation). Environment-level caching artifact on
  install, not a MainWP or plugin registration bug; self-resolves on the
  next normal page load and does not affect actual use. No code change.

## Historical Phase: Phase 5 (First live-test fixes: title, MainWP-native layout, child-site install, v1.1.0)

### Phase 5 Modifications (v1.1.0)
- First real click-through on a live MainWP Dashboard (MainWP 6.1.2, dark
  theme) surfaced three cosmetic issues and one bug; all four are fixed, plus
  the requested child-site install button. Root causes were verified against
  `mainwp/mainwp` / `mainwp/mainwp-child` source before writing any fix (file
  and line references live in `PLAN-v1.1.0-title-layout-install.md`).
- **Display title fix.** MainWP's `polish_string_name()`
  (`pages/page-mainwp-extensions-handler.php`) strips the literal token
  `MainWP` (among others) from any extension name derived from the plugin
  header, so "MainWP for Google Security for WordPress" rendered as "for
  Google Security for WordPress" on the Add-ons card, left menu, and page
  title. Fixed by passing an explicit `'name' => 'Google Security for
  WordPress'` in the `mainwp_getextensions` registration array in
  `MWPGSWP_Activator::get_this_extension()` — MainWP only derives the name
  from the header when the extension supplies none. The plugin header itself
  is unchanged (still correct on the WP Plugins screen).
- **Bug fix: empty Site column on the overview table.**
  `MWPGSWP_Overview` read `$site->id`/`->name`/`->url` as if `mainwp_getsites`
  returned objects; it actually returns associative arrays
  (`MainWP_DB::get_sites()` builds plain `array( 'id' => …, 'name' => …, 'url'
  => … )` rows, both in the all-sites branch and the single-site branch).
  Switched to array access with an `is_array()`/`empty($site['id'])` guard
  per row.
- **MainWP-native layout.** Both screens previously used raw wp-admin markup
  (`wp-list-table`, `.notice`) with a hardcoded light-theme palette and a
  900px max-width, so they rendered as a mismatched white block inside
  MainWP 6's Fomantic-UI dark chrome instead of filling the content column.
  Converted both to MainWP's own Fomantic UI classes: `ui segment` wrappers,
  `ui unstackable table` for the site list, `ui top attached tabular menu` /
  `ui bottom attached tab segment` for the per-site tabs (kept our own
  plain-JS class/`hidden`-attribute toggling for switching — Fomantic's *JS*
  tab behavior needs jQuery, which this project deliberately dropped; the
  *CSS* classes need no JS to render correctly), `ui form` fields (`ui toggle
  checkbox`, `ui input`, `ui action input` for the secret-reveal pair, native
  `select.ui.dropdown`), and `ui negative message` / `ui message` for
  notices. `mwpgswp-admin.css` shrank to layout-only rules with **no color
  values at all**, so light/dark theme is inherited entirely from MainWP.
  One defensive rule was added: `.mwpgswp-tabpanel[hidden]{ display: none
  !important; }`, since a Fomantic tab segment's own (unlayered) display CSS
  could otherwise beat the browser's low-specificity `[hidden]` default —
  the same class of bug GSWP hit with Tailwind layers vs. wp-admin's
  unlayered `a` color rule in its own Phase 22.
- **"Install GSWP" button.** New per-site action — on every Extensions-page
  site row, and inside a site's own tab when it shows the `bridge_missing`
  message — that installs or upgrades Google Security for WordPress on a
  child using MainWP's *standard* plugin-install mechanism: the
  `installplugintheme` child callable (whitelisted in MainWP Child's callable
  map since 4.0), called via the same `mainwp_fetchurlauthed` filter this
  extension already uses, with `type=plugin`, a JSON-encoded package `url`,
  `activatePlugin=yes`, and `overwrite=true` (so the identical call doubles
  as an upgrade for a site stuck on pre-2.9.0 GSWP — `overwrite` clears the
  destination first). The child downloads and unpacks the ZIP itself, so the
  package URL must be reachable *from the child*, not just the dashboard.
  Because GSWP is not on wordpress.org, added a **package-URL setting**
  (`mwpgswp_package` option) on the Extensions page: a `wp.media` upload
  button (ZIP only) or a plain URL field, saved via a new
  `wp_ajax_mwpgswp_save_settings`-sibling action `mwpgswp_save_package`
  (nonce + `current_user_can('install_plugins')`). The install call itself is
  `MWPGSWP_Connector::install_package()` (new; success = child response
  `installation === 'SUCCESS'`) behind AJAX action `mwpgswp_install_gswp`
  (nonce + `mainwp_current_user_can('dashboard','edit_sites')` +
  `current_user_can('install_plugins')` — installing code is gated stricter
  than editing settings). On the overview row the button updates the status
  cell in place; inside the tab's bridge-missing notice (no status cell to
  update) a successful install reloads the page, which naturally lands on
  the live settings form once GSWP is present. No bulk "install to all
  sites" — offered site-by-site where the operator is already looking at
  that site, consistent with the per-domain-keys posture of the whole
  extension.
- Refactored `MWPGSWP_Connector`: extracted `is_transport_failure()` /
  `transport_error_message()` helpers shared between the existing
  `get_settings`/`update_settings` path and the new `install_package()`,
  which has a different success signature (`installation === 'SUCCESS'`, not
  the `mwpgswp` envelope) since it talks to a stock MainWP callable, not the
  GSWP bridge.
- Wrote `PLAN-v1.1.0-title-layout-install.md` before implementing (the spec
  for everything in this phase, with source verification for the title bug,
  the array-shape bug, the Fomantic conversion approach, and the
  `installplugintheme` wire format).
- Version bumped to 1.1.0 (plugin header, `MWPGSWP_VERSION`, `readme.txt`
  stable tag + changelog + two new FAQs). `php -l` clean on all touched PHP;
  `node --check` clean on the JS. No new files under `class/`; `assets/`
  unchanged in file count (same two files, rewritten).
- **Not yet done:** re-verification on the live staging dashboard (the
  fixes were driven by the first test's screenshots + source analysis, not
  yet re-tested in the browser); the Install button's live path is
  untestable end-to-end until either GSWP 2.9.0 exists or a pre-2.9.0 GSWP
  ZIP is staged as the package URL to exercise the install-only half.

## Historical Phase: Phase 4 (Release packaging + GSWP 2.9.0 bridge handoff)

### Phase 4 Modifications
- Merged the development branch (`claude/mainwp-configurator-addon-ro1623`)
  to `main` (clean fast-forward; `main` had only the initial commit).
- Built the first distribution ZIP
  (`mainwp-for-google-security-for-wordpress-1.0.0.zip`) from an **explicit
  file list** — main plugin file, `class/`, `assets/`, `readme.txt`,
  `LICENSE` — rooted under the plugin's own folder name so a WP admin upload
  installs it correctly. Repo/dev artifacts (`.git`, `PLAN-*.md`,
  `README.md`, `STATE.md`) are excluded. The ZIP is not committed; it is
  rebuilt from the tree for each release.
- Wrote `PLAN-gswp-2.9.0-mainwp-bridge.md`: the handoff plan (with a
  copy-paste session prompt) for the GSWP-core change. It **freezes the wire
  contract** this extension already ships — request field `mwpgswp_action`
  (`get_settings` | `update_settings`, plus a JSON-encoded `settings` field
  for updates, `wp_unslash()` required child-side since `$_POST` arrives
  slashed) and the `mwpgswp` response envelope (`success`, `version`,
  `woocommerce_active`, `roles`, `settings`). Includes a near-drop-in
  reference implementation of `includes/class-gswp-mainwp-child.php` that
  routes writes through the existing `GSWP_Rest_Api` callbacks so all
  validation stays single-source, the verified timing facts (MainWP Child
  dispatches callables at `init` 9999; GSWP registers at `plugins_loaded`
  10), the GSWP release checklist, and the staging verification list.
- **Not yet done:** end-to-end runtime verification. Everything here was
  validated against MainWP Dashboard / MainWP Child / Development Extension
  source (exact hook signatures, site-object fields, URL scheme confirmed by
  reading `mainwp/mainwp` and `mainwp/mainwp-child`), but the extension has
  not been clicked through on a live MainWP install. First manual pass should
  confirm: extension appears under Extensions, the per-site tab renders
  inside MainWP chrome, and a pre-2.9.0 child shows the bridge-missing
  message.

## Historical Phase: Phase 3 (Plain-JS refactor)

### Phase 3 Modifications
- Rewrote `assets/js/mwpgswp-admin.js` from jQuery to dependency-free plain
  JS at the user's direction: `querySelectorAll`/`addEventListener` for tabs,
  conditional field visibility, the secret-reveal toggle, and the dirty-state
  guard; `fetch()` + `URLSearchParams( new FormData( form ) )` replaced
  `$.post()`/`serialize()` for the save and per-row check AJAX calls. A shared
  `postAjax()` helper posts form-urlencoded bodies to `admin-ajax.php` and
  resolves parsed JSON (rejecting only on transport failure, so WP-style
  `{ success: false }` payloads still reach the caller's error path).
- Dropped the `'jquery'` dependency from `wp_enqueue_script()` in
  `MWPGSWP_Admin::enqueue_assets()`.
- No markup, naming, or behavior changes — the `fields[key]` convention and
  the hidden-input-then-checkbox `0`/`1` pattern for toggles are unchanged.

## Historical Phase: Phase 2 (Extension implementation, v1.0.0)

### Phase 2 Modifications
- Built the full dashboard-side extension per `PLAN-mainwp-configurator.md`.
  Server-rendered PHP + one small JS/CSS pair; **no build step** (deliberate
  contrast with GSWP's React/Tailwind stack — the MainWP admin brings its own
  chrome and the settings form is plain inputs). Namespace
  `MainWP\Extensions\GSWP`, prefix `mwpgswp_`, autoloaded from
  `class/class-mwpgswp-*.php`.
- **`mainwp-for-google-security-for-wordpress.php`** (`MWPGSWP_Activator`,
  modeled on the official MainWP Development Extension): registers via
  `mainwp_getextensions` (`apiManager => false` — free in-house extension;
  registration is still required because MainWP's
  `hook_fetch_url_authed()` verifies callers via the key from
  `mainwp_extension_enabled_check`); gates on
  `mainwp_activated_check`/`mainwp_activated` with the standard
  Plugins-screen notice when MainWP Dashboard is absent; and, once active,
  registers the per-site tab through `mainwp_getsubpages_sites` with
  `'slug' => 'GSWPConfig'`, `'sitetab' => true`, `'menu_hidden' => true` —
  a "Google Security" tab on each individual child site's own screen
  (`admin.php?page=ManageSitesGSWPConfig&id={siteId}`), and nothing in the
  global Sites menu, **because reCAPTCHA keys are issued per domain** (the
  core product decision of this extension).
- **`class-mwpgswp-settings-schema.php`**: single source of truth for all 33
  GSWP settings (matched 1:1 to GSWP v2.8.1's `gswp/v1/settings` REST
  contract), grouped into the same five task-oriented tabs as GSWP's own
  screen — API Credentials, Form Protection, Enterprise Defense, Two-Factor
  Auth, Alerts & Compatibility. Each field declares type (`toggle`, `text`,
  `secret`, `select`, `threshold`, `int`, `email_list`, `login_list`,
  `roles`), enums, ranges, conditional visibility (`requires`), and
  WooCommerce/Enterprise gating. Render and save both iterate the schema, so
  a future GSWP option is a one-array-entry change here.
- **`class-mwpgswp-connector.php`**: owns every dashboard→child call.
  Wraps `apply_filters( 'mainwp_fetchurlauthed', $extFile, $extKey, $siteId,
  'extra_execution', $postData )` (the non-deprecated callable spelling) and
  normalizes responses into **three typed outcomes** the UI messages
  distinctly: `transport` (site unreachable/suspended/not editable),
  `bridge_missing` (MainWP Child answered but no `mwpgswp` envelope — GSWP
  absent or < 2.9.0), `bridge_error` (envelope present, `success => false`).
  The MainWP channel was chosen over GSWP's own REST route because that
  route authenticates with `manage_options` cookies/application passwords —
  and GSWP itself can disable application passwords
  (`gswp_2fa_block_app_passwords`), so the configurator must not depend on
  them.
- **`class-mwpgswp-individual.php`**: the per-site tab. Renders between
  `mainwp_pageheader_sites`/`mainwp_pagefooter_sites` ('GSWPConfig'); site id
  from `$_GET['id']`. Fetches settings synchronously on render and **shows no
  form on any failure** (prevents blind overwrites of a site it can't read).
  One form, one Save button, all five tabs mounted (inactive panels `hidden`)
  — same partial-save-prevention decision as GSWP Phase 22. Secrets render
  as password inputs with a reveal toggle. Save posts to
  `wp_ajax_mwpgswp_save_settings` (nonce + `mainwp_current_user_can(
  'dashboard', 'edit_sites' )`), coerces each posted value per schema type
  (toggles to `'1'`/`'0'`, thresholds/ints numeric, roles sanitized array),
  skips WooCommerce-gated fields that were never rendered, and re-hydrates
  the form from the child's **post-save** response — so what the operator
  sees is what the child actually persisted after its own validation
  (dropped invalid exempt logins, clamped thresholds).
- **`class-mwpgswp-overview.php`**: the Extensions-page landing kept as a
  thin index, not a second configurator — site table via the
  `mainwp_getsites` filter with per-site "Configure" links and an on-demand
  per-row "Check" button (`wp_ajax_mwpgswp_check_site`) reporting GSWP
  version/key-type/protection rather than N automatic requests on page load.
- **`class-mwpgswp-admin.php`**: AJAX endpoint registration and the shared,
  idempotent `enqueue_assets()` (script/style + `mwpgswpAdmin` localized
  strings/nonces), called from each screen's render method.
- **`assets/`**: tab switching with URL-hash persistence (`#tab=<id>`),
  schema-driven conditional field show/hide, beforeunload dirty guard, AJAX
  save/check. `readme.txt` (per-site scope rationale, GSWP ≥ 2.9.0
  requirement, MainWP-connection-unaffected FAQ) and README refresh.
- All PHP `php -l` clean; JS `node --check` clean.

## Historical Phase: Phase 1 (Implementation plan)

### Phase 1 Modifications
- Researched GSWP v2.8.1 (settings contract from `gswp_default_options()`,
  `GSWP_Rest_Api`, the admin localizer, and the Phase 22 tab grouping) and
  the MainWP platform source directly — `mainwp/mainwp`,
  `mainwp/mainwp-child`, and the official `mainwp-development-extension`
  starter — to verify every hook signature rather than relying on docs
  (mainwp.dev blocks automated fetches).
- Wrote `PLAN-mainwp-configurator.md`: two-part architecture (dashboard
  extension in this repo + a small child-side bridge recommended for GSWP
  core rather than a third stub plugin), the per-site-tab product decision,
  the five-tab layout mirroring GSWP's own screen, the settings contract,
  the three typed failure states, milestones M1–M5, and the M5 testing
  checklist. Key verified facts recorded there: `mainwp_getsubpages_sites`
  builds `ManageSites{slug}` pages, `hook_fetch_url_authed()` verifies the
  extension key, MainWP Child's `extra_execution` callable applies the
  `mainwp_child_extra_execution` filter (the `extra_excution` spelling is
  deprecated), and dashboard→child requests are RSA-signed
  (`openssl_sign`/`openssl_verify`).
- Roadmap explicitly deferred: bulk/group apply of non-key settings, 2FA
  user administration, alert-log surfacing, sync-time status collection, a
  MainWP Overview metabox.
