=== TN Update Controller ===
Contributors:
Tags: updates, plugins, catalogue, techn
Requires at least: 6.5
Tested up to: 7.1.2
Stable tag: 0.5.1
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A cached catalogue, background update checks and guided installation for Techn plugins.

== Description ==

Manage verified Techn-authored plugins from cchatterton's GitHub repositories. AlphaSys plugins are managed separately by AS Update Controller. Both controllers can run together.

Plugins > Techn Plugins provides Installed, Catalogue and Settings tabs. Checks use one aggregate catalogue. Ordinary page rendering makes no update-metadata requests, even when the cache is empty. Installations and updates use WordPress's native upgrader and verify package checksums.

Automatic discovery defaults to approximately every six hours. Manual-only mode is available. Discovery does not install updates or change WordPress auto-update settings.

Reviewed legacy updater files are held inactive while this controller runs. This is a compatibility bridge, not a claim that all plugin repositories have been migrated. Unknown legacy implementations and site-level forced refresh code require review.

On multisite, network activate this controller and use Network Admin. Inactive recognised plugins can receive updates without being activated.

== Installation ==

1. Upload tn-update-controller.zip through Plugins > Add Plugin > Upload Plugin.
2. Activate (network activate on multisite).
3. Open Plugins > Techn Plugins and check the catalogue.
4. Review installed/available versions and choose Update selected plugins when ready.

Installing or activating the controller does not automatically update other plugins. Batch operations preserve active/inactive state. Test migration on staging and verify recovery arrangements before production rollout.

If filesystem credentials are required, use the native WordPress update/upload screen. A failed or interrupted batch can be reviewed and resumed from Installed. Use a verified controller ZIP for manual recovery if the controller cannot run.

== Frequently Asked Questions ==

= Does this require the controller for normal plugin functionality? =
No. Feature plugins continue to operate without it. Legacy updater suppression only applies while the controller is active.

= Why is a GitHub repository absent? =
Only explicitly approved Techn-authored WordPress plugin packages are included. Themes, blocks, unrelated authors and ambiguous packages are excluded. New package identities require a registry update.

= Are checks immediate? =
Manual checks bypass the normal six-hour interval and the recent-success cooldown, but still respect in-progress checks and remote retry limits.

== External services ==

GitHub hosts the public catalogue and release packages. A scheduled or explicit manual check sends an HTTPS GET for the catalogue with the controller version in its User-Agent. It does not submit site inventory or credentials. GitHub receives the server IP address and normal connection metadata. An explicit installation/update downloads the selected release package from github.com and approved GitHub release-asset hosts. Repository/release links open GitHub only when clicked.

Terms: https://docs.github.com/en/site-policy/github-terms/github-terms-of-service
Privacy: https://docs.github.com/en/site-policy/privacy-policies/github-general-privacy-statement

== Changelog ==

= 0.5.1 =
* Refresh the Techn catalogue during WordPress native forced update checks so newly published plugin releases appear in the standard update flow.
* Read the published catalogue through GitHub's contents API and decode both API and raw JSON responses to avoid stale raw-content cache results.
* Force explicit manual catalogue checks to bypass the recent-success cooldown and show installed/latest versions on catalogue cards.

= 0.5.0 =
* Read domain restrictions from plugin release headers via the verified catalogue. Show all catalogue plugins on localhost. Accept new approved same-brand catalogue entries without controller releases; keep executable legacy trust bundled.

= 0.4.5 =
* Support exact localhost allowlist entries alongside domain/subdomain restrictions.

= 0.4.4 =
* Remove the redundant Update discovery heading and introductory text from Settings.

= 0.4.3 =
* Promote the controller to reviewed Alpha status and remove its Beta chip.


= 0.4.2 =
* Remove the catalogue heading/search toolbar.
* Remove redundant status strips from catalogue cards; group headings convey lifecycle state.


= 0.4.1 =
* Group cards as Active, Installed, Available and Beta, with state-specific actions.
* Refresh through authenticated AJAX so state changes do not reuse cached page HTML.


= 0.4.0 =
* Show only available updates on the first tab. Add catalogue activation, deactivation and deletion.
* Enforce domain availability, network permissions and deletion protection across sites.
* Move smaller Beta chips to the bottom-right of cards.


= 0.3.0 =
* Group the catalogue into Active, Available and Beta plugins.
* Keep active beta plugins in Active with a visible Beta chip.
* Track readiness explicitly in catalogue metadata, with safe handling of older cached catalogues.

= 0.2.0 =
* Simplify installed plugin columns and catalogue cards; remove persistent setup, success and recovery commentary.
* Add accessible checking and update-progress dialogs with concise results, failure details and interruption recovery.

= 0.1.2 =
* Support PHP 7.4 by replacing PHP 8-only return declarations with equivalent PHPDoc types.

= 0.1.1 =
* Recognise the audited TN User Management 1.8 updater during migration, alongside the reviewed current implementation.

= 0.1.0 =
* First release: author-specific catalogue, background/manual checks, native update integration, guided batch updates and audited legacy compatibility.
