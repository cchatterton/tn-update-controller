# Plugin Catalog registry

`catalogue-registry.json` is the public directory of catalog editions. The identical bundled copy at `tn-update-controller/data/catalogue-registry.json` lets the screen render without a network request. An explicit **Check for updates** replaces the site's cached copy only after validation.

Each edition has an ID such as `fusion-26`, a collection, year, name, type, status, `last_updated` date, optional notice, and a fixed list of plugin identities. Type is `hosted` for direct GitHub release catalogs or `mirrored` for mirror catalogs. `last_updated` is the date the catalog's membership, status, or notice was last edited; it does not track individual plugin releases. The catalog list shows the type and date. A site can check any combination of editions; each creates its own Plugins submenu. New installations select the latest current Techn catalog by default. A new edition does not alter saved site choices.

The root `revision` increases whenever the registry changes. It lets a newly installed controller use its bundled catalog list immediately, while preserving a newer validated registry that a site has already checked. Each catalog has a readable GitHub page under `catalogs/<id>.md`, generated from the registry. Plugin Catalogs links to those pages in a new tab.

Statuses are `current`, `supported`, `archive_planned`, and `archived`. Current, supported and archive-planned editions remain available for site selection. `archive_planned` can carry a notice for sites using that edition. An archived edition remains visible to selected sites but cannot be newly selected; its catalog packages are not refreshed or offered by Plugin Catalogs. Installed plugins can use their existing WordPress update providers.

The registry records **membership**, not the latest version. Techn and AlphaSys package versions come from their published release catalogs. Fusion package versions come from its mirror index. An entry may designate `github`, `mirror`, or `native`; mirror entries can fall back to the site's native WordPress update state. Other is calculated from installed plugins outside the site's selected editions and has no registry entry.

## Publishing an edition

1. Copy the current edition to a new year and review every member. Keep, replace, add or drop plugins deliberately.
2. Set the new edition to `current` and the former current edition to `supported` or `archive_planned`. Write a short notice when a transition needs explanation.
3. Keep the former edition's membership unchanged. Retain archived editions in the registry so subscribed sites can still identify their choice.
4. Update `last_updated` on every changed catalog and increase the root `revision`. Write the same JSON to both registry paths.
5. Run `python3 scripts/render-catalogs.py` and `php tests/catalogue-registry.php`, then publish the root registry and generated Markdown pages together.
6. Verify the public raw JSON and the readable GitHub pages against the intended edition list before releasing the controller ZIP.

Adding a plugin to a current edition is an editorial change to the registry. The package must first have a verified release in its source feed or, for native-only plugins, a working WordPress update provider. Publishing a new package version for an existing member changes the source feed and does not change edition membership.

The old AlphaSys, Fusion and Other controller plugins are excluded from the initial editions. During migration they may still be installed and will appear under Other. Retire them on a site only after its selected editions and client integrations have been verified in the shared controller.
