# Collections Catalog registry

`catalogue-registry.json` is the public directory of collection releases. The identical bundled copy at `tn-update-controller/data/catalogue-registry.json` lets the screen render without a network request. An explicit **Check for updates** replaces the site's cached copy only after validation.

Each release has an ID such as `fusion-26`, a collection key, a collection `name` such as `Fusion`, a two-digit `release` such as `26`, type, status, `last_updated` date, optional notice, and a fixed list of plugin identities. Type is `hosted` for direct GitHub packages or `mirrored` for mirror packages. `last_updated` records when membership, status, or notice changed; it does not track individual plugin package releases. Sites can select multiple releases. Each selected collection gets one Plugins submenu containing unique plugins across its selected releases. New installations select the latest current Techn release by default, and a Techn release containing the controller must remain selected. Publishing another release does not alter valid saved choices.

The root `revision` increases whenever the registry changes. It lets a newly installed controller use its bundled list immediately, while preserving a newer validated registry that a site has already checked. Each release has a readable GitHub page under `catalogs/<id>.md`, generated from the registry. Collections Catalog links to those pages in a new tab.

Statuses are `current`, `supported`, `archive_planned`, and `archived`. Current, supported and archive-planned releases remain available for site selection. `archive_planned` can carry a notice for sites using that release. An archived release remains visible to selected sites but cannot be newly selected; its exclusive packages are not refreshed or offered by the controller. Installed plugins can use their existing WordPress update providers. The controller itself remains updateable from the Techn source even when its selected release is archived.

The registry records **membership**, not the latest version. When multiple selected releases contain the same plugin, the newest non-archived release supplies its card and update source. Techn and AlphaSys package versions come from their published release catalogs. Fusion package versions come from its mirror index. An entry may designate `github`, `mirror`, or `native`; mirror entries can fall back to the site's native WordPress update state. Other is calculated from installed plugins outside the site's selected collections and has no registry entry.

## Publishing a collection release

1. Copy the current release to a new year and review every member. Keep, replace, add or drop plugins deliberately.
2. Set the new release to `current` and the former current release to `supported` or `archive_planned`. Write a short notice when a transition needs explanation.
3. Keep the former release's membership unchanged. Retain archived releases in the registry so subscribed sites can still identify their choice.
4. Update `last_updated` on every changed release and increase the root `revision`. Write the same JSON to both registry paths.
5. Run `python3 scripts/render-catalogs.py` and `php tests/catalogue-registry.php`, then publish the root registry and generated Markdown pages together.
6. Verify the public raw JSON and the readable GitHub pages against the intended release list before releasing the controller ZIP.

Adding a plugin to a current collection release is an editorial change to the registry. The package must first have a verified version in its source feed or, for native-only plugins, a working WordPress update provider. Publishing a new package version for an existing member changes the source feed and does not change collection membership.

The old AlphaSys, Fusion and Other controller plugins are excluded from the initial releases. During migration they may still be installed and will appear under Other. Retire them on a site only after its selected collections and client integrations have been verified in the shared controller.
