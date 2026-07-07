# State Tracker - MainWP for Google Security for WordPress

A MainWP Dashboard extension for configuring the Google Security for WordPress
(GSWP) plugin on each connected child site. Installed on the MainWP Dashboard
site only. Companion plugin: `onedogsolutions/google-security-for-wordpress`
(its own STATE.md tracks the child-side work).

## Current Phase: Phase 4 (Release packaging + GSWP 2.9.0 bridge handoff)

**Blocked on:** GSWP v2.9.0 shipping the child-side bridge. Until then, every
child-site call from this extension intentionally resolves to the typed
`bridge_missing` state ("Google Security for WordPress is not active on this
child site, or is older than version 2.9.0") — that is the designed behavior,
not a bug. The extension itself is feature-complete for v1.0.0.

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
