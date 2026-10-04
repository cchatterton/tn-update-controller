=== TN Update Controller ===
Contributors:
Tags: updates, plugins, catalogue, techn
Requires at least: 6.5
Tested up to: 7.1.2
Stable tag: 0.8.1
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A cached catalogue, manual update checks and guided installation for Techn plugins.

== Description ==

Manage verified Techn-authored plugins from cchatterton's GitHub repositories. AlphaSys plugins are managed separately by AS Update Controller. Both controllers can run together.

Plugins > Techn Plugins provides Installed, Catalogue and Settings tabs. Checks use the shared github-cchatterton table and fresh GitHub API metadata. Ordinary page rendering makes no update-metadata requests, even when the cache is empty. Installations and updates use WordPress's native upgrader and verify package checksums.

Checks are strictly manual. Each check verifies this controller first. If its update is available, only that update is listed and the check stops. Update the controller, then check again. When current, the check refreshes available plugins and installed update status together. Old controller schedules are removed on upgrade. Discovery does not install updates or change WordPress auto-update settings.

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
Public stable WordPress plugin releases from the trusted owner with verified Techn authorship are discovered directly from GitHub when Check for updates is clicked. Exceptions handle ambiguous identities, exclusions and legacy packages. No feed publication is required. The PHP ZIP extension is required; the next explicit check starts fresh while reusing verified records.

= Are checks immediate? =
Every click checks fresh API metadata, with no controller cooldown or retry deadline. A server-side GITHUB_CCHATTERTON_TOKEN enables batched checks; GitHub still applies its own API quotas.

== External services ==

GitHub hosts repository metadata and release packages. An explicit check calls GitHub's API and inspects new or changed published ZIPs. Authenticated GraphQL batches repository/release versions; anonymous REST checks each repository. An optional server-side token is sent only to api.github.com. No site inventory is submitted. GitHub receives the server IP and connection metadata. Installation/update downloads the selected package from github.com and approved release-asset hosts. Repository/release links open GitHub only when clicked.

Terms: https://docs.github.com/en/site-policy/github-terms/github-terms-of-service
Privacy: https://docs.github.com/en/site-policy/privacy-policies/github-general-privacy-statement

== Changelog ==

= 0.8.1 =
* Check this controller first; stop and list its update before checking other repositories.
* Continue to other plugins only when the controller is current.

= 0.8.0 =
* Share github-cchatterton rows with author, released/local versions, installed state and alpha/beta.
* Every click checks fresh API data, with no cooldown or stored backoff.
* Batch authenticated checks and reuse unchanged package metadata across controllers.

= 0.7.1 =
* Remember verified authors and ignore other-author/non-plugin repositories for 24 hours.
* Check known stable release versions without per-repository API calls; unchanged releases need no ZIP.
* Keep known-plugin checks working when API limits defer new-repository discovery.
* Batch short steps to reduce WordPress request overhead; add explicit full revalidation.

= 0.7.0 =
* Scan GitHub directly on Check for updates for both new plugins and installed releases.
* Verify released ZIP identities and checksums in bounded, resumable steps.
* Preserve results on interruptions and respect GitHub retry deadlines; no background checks.

= 0.6.0 =
* Discover released same-brand plugins by default; keep registry entries for exceptions.
* Make update discovery strictly manual and remove old schedules and force-check triggers.
* Refresh available plugins and installed updates together, preserving retry and identity safeguards.

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
