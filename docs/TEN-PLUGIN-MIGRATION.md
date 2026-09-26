# Techn controller migration — 26 September 2026

Completed the ten-plugin batch. TN Scroll Depth was excluded at your request. All feature releases require **WordPress 7.0+ and PHP 8.5+**.

| Plugin | Migration release | Previous package used in bulk test |
| --- | --- | --- |
| Help Guides (Wiki-style) | [1.2.2](https://github.com/cchatterton/help-guides/releases/tag/v1.2.2) | 1.2.1 |
| Menubot | [1.10.1](https://github.com/cchatterton/menubot/releases/tag/v1.10.1) | 1.10 |
| Persona26 | [0.7.5](https://github.com/cchatterton/persona26/releases/tag/v0.7.5) | 0.7.4 |
| TN Authenticator | [1.6.1](https://github.com/cchatterton/tn-authenticator/releases/tag/v1.6.1) | 1.6 |
| TN Content Planner | [0.3.33](https://github.com/cchatterton/tn-content-planner/releases/tag/v0.3.33) | 0.3.32 |
| TN Environments | [1.10.1](https://github.com/cchatterton/tn-environments/releases/tag/v1.10.1) | 1.10 |
| TN Pallet | [0.1.13](https://github.com/cchatterton/tn-pallet/releases/tag/v0.1.13) | 0.1.12 |
| TN QR Codes | [1.5.1](https://github.com/cchatterton/tn-qrcodes/releases/tag/v1.5.1) | 1.5 |
| TN User Management | [1.32.1](https://github.com/cchatterton/tn-user-management/releases/tag/v1.32.1) | 1.8 |
| WP Migrate - Release Management | [0.11.4](https://github.com/cchatterton/tn-wp-migrate-code-diff/releases/tag/v0.11.4) | 0.11.3 |

## Controller and catalogue

Use [TN Update Controller 0.1.1](https://github.com/cchatterton/tn-update-controller/releases/tag/v0.1.1), which includes reviewed legacy compatibility for TN User Management 1.8. All ten plugins already had registry identities; their verified catalogue entries now advertise the migration releases with controller API 1. No Scroll Depth entry was added. The catalogue remains Techn-only.

The release publisher downloaded and checked every catalogue ZIP before publication. For all ten migration releases, published ZIP SHA-256 values matched the committed local packages; header, version constant, readme stable tag, release tag and catalogue version agree. Existing update.json endpoints were advanced only after verification, so old installations can discover the transition release. Migrated feature plugins no longer read those endpoints.

Catalogue automation is still not enabled because the available GitHub CLI credential lacks workflow permission. The current catalogue is published; future releases require running the publisher or enabling the supplied workflow template.

## Verified behaviour

- A candidate-package bulk run completed all ten upgrades with one catalogue request and no metadata HTTP during repeated legacy reads.
- A second run used the actual controller browser interface and published GitHub catalogue/packages. Select all / Update selected completed all ten with individual success results and zero updates remaining.
- Network activation state and timestamps were preserved, Menubot stayed site-active, and QR Codes stayed inactive.
- Saved palette, content plan, Persona26 options and environment selection matched their pre-update snapshots. Completed batches were idempotent.
- All ten clients together produced the correct Install / Activate / Check links with the controller absent, inactive and active; unauthorised users saw no privileged action. Repeated metadata reads made zero HTTP requests.
- Native Plugins rows show By Techn, one GitHub link and Check for updates, without Visit plugin site. Native View details and update controls remain.
- Persona26 integration checks, 55 Content Planner single-site assertions, eight WP Migrate standalone suites, package/source checks and PHP syntax checks passed. The bundled QR encoder produced a valid PNG on PHP 8.5.7.
- TN Environments save processing was moved before page output after browser testing exposed a headers-already-sent redirect error. Browser saving now redirects correctly; anonymous and invalid-nonce saves were rejected without changing saved settings.

## Scope and limitations

Testing used disposable WordPress 7.1.2 / PHP 8.5.7 single-site and multisite installations. No customer website was changed. The minimum WordPress 7.0 runtime and every feature/dependency combination were not exhaustively tested. The screenshot showed QR Codes 1.4, but its published release was unavailable, so the tested QR baseline was 1.5.

Applied the general development, WordPress plugin, GitHub update and branding/UX standards. Author Branded metadata/native WordPress row controls were standardised; existing feature branding/layouts were retained, including WP Migrate’s extension interface. Browser verification covered native rows, labelled checkboxes/buttons, update progress/results and the environment save flow at the desktop viewport. The unchanged controller responsive design had already been checked at 390 pixels; full screen-reader testing was not performed.

Install/update the controller on staging, use Check all registered plugins, review the ten versions, then Update selected plugins. Existing site-level MU updater/forced-refresh code remains outside these repository changes and should be checked during that site test.
