# PLAN — v1.1.0: display title, MainWP-native layout, and child-site install button

Follow-up to the first live test on the MainWP Dashboard (MainWP 6.1.2, dark
theme, `mainwp.onedog.solutions`). Three requested changes plus one bug the
test screenshots exposed. All root causes below were verified against the
`mainwp/mainwp` and `mainwp/mainwp-child` source, with file/line references so
the implementation doesn't have to re-derive them.

## 1. Display title shows "for Google Security for WordPress"

**Root cause.** MainWP "polishes" extension names before display:
`MainWP_Extensions_Handler::polish_string_name()`
(`pages/page-mainwp-extensions-handler.php:134`) strips the literal tokens
`Extensions`, `Mainwp`, `Extension`, `MainWP`, `Add-on` from the name and
trims. Our plugin header `Plugin Name: MainWP for Google Security for
WordPress` therefore renders as **"for Google Security for WordPress"** on the
Add-ons card, the left menu, and the page title. The name only falls back to
the plugin header when the extension array carries no explicit name:
`pages/page-mainwp-extensions.php:178` — `if ( ! isset( $extension['name'] ) )
{ $extension['name'] = $plugin_data['Name']; }`.

**Fix** (one line, in `MWPGSWP_Activator::get_this_extension()`): add an
explicit name to the registration array:

```php
$extensions[] = array(
    'plugin'   => __FILE__,
    'api'      => $this->plugin_handle,
    'mainwp'   => true,
    'name'     => 'Google Security for WordPress',   // NEW — display title
    'callback' => array( $this, 'render_extensions_page' ),
    'apiManager' => false,
);
```

`polish_string_name()` still runs on it but is a no-op (the string contains
none of the stripped tokens). Do **not** rename the plugin header — "MainWP
for Google Security for WordPress" remains correct on the WP Plugins screen,
and the header is also what distinguishes it from GSWP itself in a plugin
list. No i18n wrapper on the name (brand string, matches how MainWP's own
extensions register).

## 2. Bug: overview table's Site column is empty

**Root cause.** `MWPGSWP_Overview::get_sites()` treats the
`mainwp_getsites` result as objects (`$site->id`, `$site->name`,
`$site->url`). The filter actually returns an array of **associative arrays**
— `array( 'id' => …, 'url' => …, 'name' => …, 'totalsize' => …,
'sync_errors' => …, 'client_id' => … )`, built in
`MainWP_DB::get_sites()` (`class/class-mainwp-db.php:3776-3784`; the
single-site branch at :3751 has the same shape). Object property reads on an
array return null quietly in the template, hence the blank Site cells in the
test screenshot (the `—` Status placeholders are by design).

**Fix.** Switch the overview render loop to array access
(`$site['id']`, `$site['name']`, `$site['url']`), with an
`is_array( $site )` guard per row. Note `url` comes back pre-niced by
`MainWP_Utility::get_nice_url( $url, true )` — display as-is.

## 3. Screens don't fit MainWP's window / theme

**Observed.** The overview (and by extension the per-site tab) renders raw
wp-admin markup — `wp-list-table`, `.notice`, hand-rolled tab buttons — with
hardcoded light-theme colors (`#fff`, `#f0f0f1`, `#c3c4c7`) and
`.mwpgswp-wrap { max-width: 900px }`. Inside MainWP 6's Fomantic-UI dark
chrome this fights the content area instead of filling it: wrong width, wrong
background, unthemed table.

**Fix: adopt MainWP's own Fomantic UI markup and let its theme (light and
dark) do the styling.** MainWP admin pages ship Fomantic CSS globally; the
official Development Extension renders bare Fomantic elements directly between
the page header/footer actions.

- **Both screens:** immediately inside
  `mainwp_pageheader_extensions` / `mainwp_pageheader_sites`, wrap content in
  `<div class="ui segment">` (the standard MainWP content container). Remove
  `.mwpgswp-wrap` and its max-width — the segment fills MainWP's content
  column.
- **Overview:** replace the `wp-list-table` with
  `<table class="ui unstackable table" id="mwpgswp-sites-table">`; buttons
  become `ui mini button` / `ui mini green button`. The intro paragraph
  becomes a `ui info message`.
- **Per-site tab:** replace the hand-rolled tab strip with Fomantic tabs —
  `<div class="ui top attached tabular menu">` with `<a class="item">` per
  tab and `<div class="ui bottom attached tab segment">` per panel. Keep our
  own plain-JS activation (toggling `active` classes and `hidden`) — Fomantic
  *behaviors* require jQuery, which we deliberately dropped; class-toggling
  needs no JS framework and the CSS styles `active` states by class. Field
  rows become `ui form` markup: `<div class="field">` /
  `<div class="ui toggle checkbox">` for toggles, `ui input` wrappers,
  standard `<select class="ui dropdown">` (native select — no jQuery
  dropdown init; Fomantic styles it acceptably as a plain element). Error
  and success notices become `ui red message` / `ui green message`. Save
  button becomes `ui big green button`.
- **CSS:** delete every hardcoded color and layout rule that duplicated what
  Fomantic provides. `mwpgswp-admin.css` shrinks to a handful of rules
  (secret-field inline layout, role-checkbox spacing, threshold input width)
  — **no color values at all**, so dark/light theme both inherit.
- **JS:** selectors updated for the new markup (`.tabular.menu .item`,
  `.tab.segment`), same logic; toggle checkboxes keep the
  hidden-input-then-checkbox `0`/`1` pattern (Fomantic's toggle is a CSS
  skin over a real checkbox, so nothing changes in the save path).

Verification for this item is visual: both screens fill the content area,
follow the dark theme with no white blocks, and the tab strip matches other
MainWP screens.

## 4. "Install plugin" button for child sites missing GSWP

**Goal.** When a site shows the `bridge_missing` state, offer a one-click
install/update of GSWP on that child, using MainWP's standard mechanism —
no SSH/FTP, no logging in to the child.

**Verified mechanism.** MainWP Child whitelists the callable
`'installplugintheme' => 'install_plugin_theme'`
(`mainwp-child/class/class-mainwp-child-callable.php:45`). The handler
(`class-mainwp-child-install.php:398`) accepts POST fields:

| Field | Value |
| --- | --- |
| `type` | `'plugin'` |
| `url` | `wp_json_encode( $zip_url )` — JSON-encoded string or array of package URLs (the child `json_decode`s it; this matches how MainWP's own Install page sends it, `pages/page-mainwp-install-bulk.php:453`) |
| `activatePlugin` | `'yes'` to activate after install |
| `overwrite` | `true` to clear the destination first — this makes the same call an **upgrade** for a child stuck on GSWP < 2.9.0 |

Success response: `installation => 'SUCCESS'`, `destination_name`, and
`install_results` (map of package basename => bool). The child downloads the
package itself from `url` via `WP_Upgrader` (with an automatic
`sslverify`-off retry on TLS failure), so **the URL must be reachable from
the child site**.

Extensions reach this callable through the same signed channel we already
use: `apply_filters( 'mainwp_fetchurlauthed', $extFile, $extKey, $siteId,
'installplugintheme', $post_data )` —
`hook_fetch_url_authed()` passes any `$what` through after key verification.

**Package source.** GSWP is not on wordpress.org and its GitHub repo is
private, so the child cannot fetch it from either. v1.1.0 stores a
dashboard-side package reference in one option, `mwpgswp_package`
(`array( 'url' => …, 'label' => … )`), settable on the overview page:

- A small "GSWP plugin package" form at the top of the overview segment
  (`ui form` in its own segment): a media-library upload button
  (`wp.media`, restricted to `.zip`; stores the attachment URL — dashboard
  uploads are publicly reachable, which is exactly how MainWP's own
  Install → Upload flow serves packages to children) **or** a plain URL
  field for a ZIP hosted elsewhere (e.g. a private update server). Saved
  via a new AJAX action with nonce + capability check; show the stored
  label/URL and when it was set.
- The GSWP release checklist gains one step: after packaging a new GSWP
  ZIP, update the stored package here (note this in STATE.md when
  implementing).

**Buttons.**

- **Overview row:** an "Install GSWP" `ui mini green button` per site row,
  shown always (with `overwrite => true` it doubles as "reinstall/update");
  disabled with a tooltip when no package is configured.
- **Per-site tab:** in the `bridge_missing` error state, render the same
  button inside the message (plus a "Reload" link for after it completes).
  This is the state the button exists for.

**New AJAX action** `mwpgswp_install_gswp` (registered in `MWPGSWP_Admin`,
handled in a new small method — `MWPGSWP_Connector::install_package(
$site_id )` does the fetchurlauthed call):

1. Nonce check + `mainwp_current_user_can( 'dashboard', 'edit_sites' )` +
   `current_user_can( 'install_plugins' )` (installing code is a step above
   editing settings; require both).
2. Read `mwpgswp_package`; error out with a "configure the package first"
   message when unset.
3. Call `installplugintheme` with the verified fields above.
4. Success = `installation === 'SUCCESS'` and the package basename `true` in
   `install_results`; anything else surfaces the child's error message.
5. On the per-site tab, the JS then re-fetches the page (full reload — the
   form only renders after a successful settings read, so a reload naturally
   lands on the working form). On the overview, re-run the row's Check.

**Out of scope, unchanged:** no bulk "install to all sites" in this release
(the per-domain-keys posture stands — install is offered site-by-site where
the operator is already looking at that site); no auto-update orchestration
(MainWP's own plugin-update management covers GSWP once installed).

## Release pass

- Version 1.1.0 (plugin header + `MWPGSWP_VERSION`), `readme.txt` stable tag
  + changelog (title fix, MainWP-native theming, Site-column fix, install
  button + package setting), README note for the package setting.
- STATE.md: new current phase documenting all four items (this file is the
  spec; STATE.md records what shipped), demote Phase 4 to historical.
- `php -l` all touched PHP; `node --check` the JS. Rebuild the distribution
  ZIP with the same explicit file list.
- Manual retest on the staging dashboard, tied to the observed symptoms:
  1. Add-ons card, left menu, and page title all read "Google Security for
     WordPress" (no leading "for").
  2. Overview table shows site names/URLs; fills the content area; dark theme
     applies (no white blocks); Check still works.
  3. Per-site tab: Fomantic tabs render; `bridge_missing` message shows the
     Install button; with a package configured, Install runs, the child gets
     GSWP installed+activated, and a reload shows the live settings form
     (requires the GSWP 2.9.0 ZIP — coordinate with the bridge release).
  4. Install with no package configured → clean error, not a broken call.
  5. Overview package form: media upload and manual URL both persist.
