# mainwp-for-google-security-for-wordpress

A MainWP Dashboard extension for configuring the [Google Security for WordPress](https://github.com/onedogsolutions/google-security-for-wordpress) plugin on each connected child site, without logging in to that site individually.

reCAPTCHA site/secret keys are issued per domain, so configuration is scoped per site: installing this plugin on your MainWP Dashboard adds a "Google Security" tab to each individual child site's own screen (**Sites -> *site name* -> Google Security**), covering credentials, form protection, Enterprise Defense, Two-Factor Auth, and Alerts & Compatibility — the same five tabs as GSWP's own settings screen.

Requires the child site to run [Google Security for WordPress](https://github.com/onedogsolutions/google-security-for-wordpress) 2.9.0 or later, which carries the bridge this extension talks to over MainWP's existing signed connection.

Configuration only in this release — see [`PLAN-mainwp-configurator.md`](PLAN-mainwp-configurator.md) for the full design and roadmap.
