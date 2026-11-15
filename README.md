# TN Update Controller

The controller behind **Plugins → Plugin Catalogue**. A site subscribes to one edition of each collection it wants: Techn 26, AlphaSys 26 and Fusion 26 are the initial editions. Installed plugins outside the selected collections appear under Other. Installable ZIP: [latest release](https://github.com/cchatterton/tn-update-controller/releases/latest/download/tn-update-controller.zip).

WordPress 6.5+, PHP 7.4+. On multisite, network activate it. Open **Plugins → Plugin Catalogue** (Network Admin on multisite).

- **Updates available:** version comparisons and selected bulk updates from the appropriate package provider.
- **Catalogue:** site subscriptions, an edition picker, grouped cards, release notes and lifecycle actions. New installs remain inactive.
**Check for updates** refreshes the public registry, subscribed release feeds and WordPress update state together.

Activation removes obsolete controller schedules. It performs no discovery or plugin upgrades. Review updates and choose **Update selected plugins**. Each update has a separate result and an interrupted batch can be resumed. The controller updates itself last. Existing activation and settings are preserved by native WordPress installation APIs. Keep your normal backup and staging process; a batch is not an atomic transaction or a site rollback service.

## Catalogue registry

The root `catalogue-registry.json` publishes edition membership, status and notices. The bundled copy allows a cold-cache screen to render without HTTP. The controller stores the latest validated copy after an explicit check. Each site saves one edition per collection. A new installation starts unsubscribed; an existing Techn controller installation retains Techn 26 on upgrade. Publishing a new edition never changes an existing site's selection.

Release metadata stays with its package source. Techn and AlphaSys entries use their verified public GitHub release feeds. Fusion entries prefer the verified GitHub mirror and use the site's native WordPress update provider when the mirror has no newer package. Other installed plugins use their existing WordPress provider. A selected catalogue's membership does not install or delete plugins.

## Ownership and coexistence

The existing Techn release publisher continues to verify Techn-authored packages and publish `catalogue.json`. AlphaSys continues to publish its own release feed; Fusion continues to publish its mirror index. The Plugin Catalogue registry chooses which verified plugin identities belong to each edition. During migration, the separate AlphaSys and Fusion controllers can remain active; retire them only after the shared controller and their client integrations have been checked on the site. See [discovery and exceptions](docs/DISCOVERY.md).

## Performance

Every explicit Check for updates downloads the registry and selected public release feeds from `raw.githubusercontent.com`, using unique freshness queries and `Cache-Control: no-cache`, then asks WordPress to refresh its existing plugin update state. It makes no GitHub API calls, owner listings or release ZIP inspections. No GitHub token is needed on WordPress sites. Checks are manual only, with no controller cooldown, cron or page-load HTTP.

The controller evaluates its own release first. If newer, it shows only that update; update it explicitly and check again. When current, each selected source refreshes independently. A failed or invalid feed retains its previous verified snapshot and is reported as a partial check. Package downloads and checksum/root checks happen only when installing or updating.

The shared, unprefixed `github-cchatterton` table remains a local mirror of verified catalogue records, including author, released version, local version, installed state and alpha/beta. It is not a repository scanner. Existing other-author/non-plugin rows may remain as history. Independent WordPress installations should use separate databases. Normal rendering and native update projections read local state only.

Each public JSON request is bounded to ten seconds and 1 MiB, with redirects disabled. Public file delivery can still fail; that is reported without erasing verified results. Update availability depends on publication by the package source.

## Existing plugins

The catalogue contains verified public release assets, including migrated and legacy plugins. Its update-management status identifies each installed plugin. For reviewed legacy updater files, exact SHA-256 matches allow narrowly scoped updater callbacks to be suspended while the controller runs. Unknown file versions remain flagged for review. Deactivating this controller can allow unchanged legacy updater code to resume; migrated client plugins never run a fallback updater.

A complete migration still requires a new release of each feature plugin removing its updater hooks, cron jobs and forced-refresh code, and adding the [client integration](docs/CLIENT-API.md). Site-level MU plugins and custom forced-refresh redirects require separate inspection. Plugin settings are not removed by controller deactivation/deletion. Controller state is retained for recovery.

## Release catalogue publishing (required)

Every stable Techn plugin release, including this controller, must publish its release entry after the GitHub ZIP is verified. The publisher verifies ownership, exact package author, identity, requirements, domain/readiness metadata and checksum. Inclusion in an annual collection is a separate editorial change to `catalogue-registry.json`; see [registry publishing](docs/CATALOGUE-REGISTRY.md).

From a current checkout of this controller repository, run:

```sh
python3 scripts/publish-catalogue.py --repo <released-plugin-repo> --expect-version <released-version>
git add catalogue.json
git commit -m "Publish verified <released-plugin-repo> <released-version>"
git push origin main
```

Then fetch the public `https://raw.githubusercontent.com/cchatterton/tn-update-controller/main/catalogue.json` with a unique freshness query and verify the plugin's version, tag, basename, asset and SHA-256 against the published ZIP. If it is missing/stale or publication fails, the release is incomplete. Preserve other concurrent release entries; update the checkout and regenerate if a push conflicts, never force-push the catalogue.

`--repo` verifies only that release and preserves other previously verified entries. A full `python3 scripts/publish-catalogue.py` scan is available for an explicit reconciliation; `--check` verifies without writing and does not count as publication. No publishing scans are scheduled. GitHub authentication is used by the release publisher, not customer WordPress sites.

## Build and recovery

Run `bash scripts/build-plugin-zip.sh`. The root ZIP and `dist/tn-update-controller.zip` contain only the plugin directory. Commit the root ZIP, tag matching header/changelog/readme versions, upload the ZIP to the GitHub release, then run the catalogue publisher. For recovery use the verified official release ZIP via WordPress Upload Plugin or trusted hosting deployment tools.

The integration helper is source for feature-plugin migrations, not part of the installed controller ZIP. It resolves the official controller ZIP only after an authorised install action. No WordPress.org listing is assumed; readme Contributors is intentionally blank pending a confirmed WordPress.org username.

See [validation](docs/VALIDATION.md) for tested behaviour and limits. See [standards](https://github.com/cchatterton/codex-standards).

## Catalogue readiness

See [beta classification and catalogue grouping](docs/BETA-CATALOGUE.md). Set `beta` explicitly for every registry entry. Approved readiness changes are included in verified catalogue metadata.
