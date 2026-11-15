=== TN Update Controller ===
Contributors:
Tags: updates, plugins, catalog, techn
Requires at least: 6.5
Tested up to: 7.1.2
Stable tag: 0.10.7
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A collection release registry with shared collection screens and guided updates.

== Description ==

Collections Catalog lists the available releases. Techn 27 is current and selected by default on new sites; Techn 25, Techn 26, AlphaSys 26 and Fusion 26 remain available. At least one Techn release must remain selected so the controller can update. Each selected collection gets one Plugins submenu containing the unique plugins across its selected releases. Other Plugins is always available and shows installed plugins outside the selected collections. Publishing a new release does not change a site's saved selection or uninstall plugins from an older release.

Plugins > Collections lists collection releases with checkboxes, Hosted or Mirrored type, a last updated date, and a link to each readable GitHub plugin list. Each collection and Other Plugins has Updates available and Catalog tabs, with grouped plugin cards and a modal update flow. Collection screens show the collection name without release numbers. Older Plugins submenu URLs redirect to the collection screen after a controller update. Explicit checks read the public registry, subscribed GitHub release feeds, the Fusion mirror index and WordPress's existing plugin update providers. Ordinary page rendering makes no metadata requests. GitHub packages are checked against their published SHA-256 and expected ZIP directory; native updates use WordPress's own package provider.

Checks are manual on each site. The separate Fusion mirror publisher may refresh its packages in the background. Each site check verifies this controller first. If its update is available, update it and check again before processing the selected catalogs. Discovery does not install updates or change WordPress auto-update settings.

Reviewed legacy updater files are held inactive while this controller runs. This is a compatibility bridge, not a claim that all plugin repositories have been migrated. Unknown legacy implementations and site-level forced refresh code require review.

On multisite, network activate this controller and use Network Admin. Inactive recognised plugins can receive updates without being activated.

== Installation ==

1. Upload tn-update-controller.zip through Plugins > Add Plugin > Upload Plugin.
2. Activate (network activate on multisite).
3. Open Plugins > Collections, check the releases this site uses, then use each collection submenu to check for updates.
4. Review installed/available versions and choose Update selected plugins when ready.

Installing or activating the controller does not automatically update other plugins. Batch operations preserve active/inactive state. Test migration on staging and verify recovery arrangements before production rollout.

If filesystem credentials are required, use the native WordPress update/upload screen. A failed or interrupted batch can be reviewed and resumed from Installed. Use a verified controller ZIP for manual recovery if the controller cannot run.

== Frequently Asked Questions ==

= Does this require the controller for normal plugin functionality? =
No. Feature plugins continue to operate without it. Legacy updater suppression only applies while the controller is active.

= Why is a GitHub repository absent? =
The published catalog includes verified public stable same-brand releases. Every plugin release must update and publish its catalog entry. New plugins need no manual registry registration, but will not appear until that publication succeeds.

= Are checks immediate? =
Each click requests fresh public catalog metadata and WordPress update state. No controller cooldown applies. The controller entry is evaluated first; if newer, update it and check again.

= What are exclusive plugins? =
Exclusive plugins are hidden from the catalog until manually installed. Once installed, they have the same lifecycle and update actions as other plugins. Exclusive is independent of domain restrictions and beta readiness.

== External services ==

GitHub hosts the catalog registry, Techn and AlphaSys release feeds, Fusion mirror index and release packages. An explicit check requests the registry and the selected feeds from raw.githubusercontent.com; GitHub receives the server IP and normal connection metadata. GitHub receives no token or site inventory. Installing or updating a GitHub package downloads its ZIP from github.com. Native WordPress updates use the site's existing WordPress.org or vendor update service and may send that service the normal plugin update information. The controller itself makes no scheduled site checks.

Terms: https://docs.github.com/en/site-policy/github-terms/github-terms-of-service
Privacy: https://docs.github.com/en/site-policy/privacy-policies/github-general-privacy-statement

== Changelog ==

= 0.10.7 =
* Keep the public collection registry readable by older controllers while they discover this update.

= 0.10.6 =
* Group selected releases under one submenu per collection, showing each plugin once.
* Require a Techn release containing the controller; default new sites to the latest current Techn release.
* Rename the last Plugins submenu to Collections and split collection name and release in the Collections Catalog.

= 0.10.5 =
* Publish Techn 25 with eight plugins and Techn 27 with twenty plugins; keep Techn 26 supported.
* Link each catalog to its readable GitHub plugin list in a new tab and show the catalog's last updated date.
* Keep generated plugin lists in sync with the registry during publication.
* Keep a newer release selectable when an installed plugin's identity metadata differs from its catalog entry.

= 0.10.4 =
* Redirect older Plugins submenu URLs and translate in-progress update modal refreshes after controller updates.
* Show each catalog's Hosted or Mirrored type in Plugin Catalogs.

= 0.10.3 =
* List published catalogs as independent checkboxes, with a separate Plugins submenu for each checked edition.
* Keep Other Plugins and Plugin Catalogs as the final two submenus. Allow new catalog collections and multiple editions of one collection.

= 0.10.2 =
* Move edition selection to Plugins > Plugin Catalogs and give each selected catalog its own Plugins submenu.
* Keep Other Plugins visible for plugins outside selected catalogs, default new sites to the latest Techn catalog, and use Catalog in interface copy.

= 0.10.1 =
* Restore WordPress.org update dates, download and rating counts, and release notes on Other plugin cards.

= 0.10.0 =
* Add a published catalog registry and site subscriptions for Techn 26, AlphaSys 26 and Fusion 26.
* Show one shared update table and grouped catalog cards, including Other installed plugins.
* Use verified GitHub releases, Fusion mirror packages or native WordPress providers according to each plugin's source.

= 0.9.7 =
* Trust the installed List of Values 1.4 author for a bounded controller-managed beta update.

= 0.9.6 =

* Remove the redundant Manual checks only label from the controller status line.

= 0.9.5 =

* Remove the redundant repository column from Updates available.

= 0.9.4 =

* Remove the unused Settings tab.

= 0.9.3 =
* Keep controller responses valid when theme bootstrap prints output.
* Provide clearer messages for malformed responses and expired sessions.

= 0.9.2 =
* Hide exclusive plugins until manually installed; retain normal activation, deactivation and updates after installation.

= 0.9.1 =
* Close successful update checks automatically and focus the refreshed Updates available tab.
* Keep failed or incomplete checks visible for review.

= 0.9.0 =
* Restore one public JSON catalog request per manual check; remove WordPress-side repository scans and token requirements.
* Keep controller-first evaluation and shared local version/readiness records.
* Require catalog publication after every plugin release; add targeted release publishing.

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
* Refresh the Techn catalog during WordPress native forced update checks so newly published plugin releases appear in the standard update flow.
* Read the published catalog through GitHub's contents API and decode both API and raw JSON responses to avoid stale raw-content cache results.
* Force explicit manual catalog checks to bypass the recent-success cooldown and show installed/latest versions on catalog cards.

= 0.5.0 =
* Read domain restrictions from plugin release headers via the verified catalog. Show all catalog plugins on localhost. Accept new approved same-brand catalog entries without controller releases; keep executable legacy trust bundled.

= 0.4.5 =
* Support exact localhost allowlist entries alongside domain/subdomain restrictions.

= 0.4.4 =
* Remove the redundant Update discovery heading and introductory text from Settings.

= 0.4.3 =
* Promote the controller to reviewed Alpha status and remove its Beta chip.


= 0.4.2 =
* Remove the catalog heading/search toolbar.
* Remove redundant status strips from catalog cards; group headings convey lifecycle state.


= 0.4.1 =
* Group cards as Active, Installed, Available and Beta, with state-specific actions.
* Refresh through authenticated AJAX so state changes do not reuse cached page HTML.


= 0.4.0 =
* Show only available updates on the first tab. Add catalog activation, deactivation and deletion.
* Enforce domain availability, network permissions and deletion protection across sites.
* Move smaller Beta chips to the bottom-right of cards.


= 0.3.0 =
* Group the catalog into Active, Available and Beta plugins.
* Keep active beta plugins in Active with a visible Beta chip.
* Track readiness explicitly in catalog metadata, with safe handling of older cached catalogs.

= 0.2.0 =
* Simplify installed plugin columns and catalog cards; remove persistent setup, success and recovery commentary.
* Add accessible checking and update-progress dialogs with concise results, failure details and interruption recovery.

= 0.1.2 =
* Support PHP 7.4 by replacing PHP 8-only return declarations with equivalent PHPDoc types.

= 0.1.1 =
* Recognise the audited TN User Management 1.8 updater during migration, alongside the reviewed current implementation.

= 0.1.0 =
* First release: author-specific catalog, background/manual checks, native update integration, guided batch updates and audited legacy compatibility.
