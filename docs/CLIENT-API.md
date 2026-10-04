# Client integration contract v1

Controller basename: `tn-update-controller/tn-update-controller.php`. Runtime capability: `defined('TNUC_API_VERSION') && TNUC_API_VERSION >= 1 && function_exists('tnuc_available') && tnuc_available()`.

1. Preserve the existing plugin directory and main filename. Publish a correctly authored stable release ZIP. The publisher discovers its identity by default; add a registry exception only for ambiguous/legacy identity or an explicit override.
2. Remove independent updater code, hooks, schedules, transient invalidation and forced-check redirects. Old scheduled events should be cleared by a versioned local migration.
3. Use `Author: Techn`, `Author URI: https://techn.com.au`, and `Update URI: https://github.com/cchatterton/EXACT-REPOSITORY`. Omit `Plugin URI`.
4. Add `Techn Controller API: 1` to the main plugin header.
5. Copy `integration/controller-client.php` into the plugin (for example `functions/controller-client.php`). From its main file:

```php
require_once __DIR__ . '/functions/controller-client.php';
tnuc_client_register(__FILE__, 'EXACT-REPOSITORY');
```

The guarded shared helper collects registrations from multiple plugins independently of load order. It adds GitHub plus Install/Activate/Update Techn Update Controller as appropriate. With a compatible active controller it yields; the controller supplies GitHub and Check for updates to recognised active and inactive plugins. `tnuc_check_url($plugin_basename)` returns a nonce-protected URL for explicit discovery. Although the action originates from one plugin, transport refreshes the single aggregate catalogue; it never fetches per-repository metadata.

No controller is needed for feature operation. Inactive clients cannot execute bootstrap code; install the controller directly from its official release ZIP. Install and activation are separate explicit actions. On multisite use Network Admin and network activation. Install/update authority is checked separately from feature access.

AlphaSys clients must use the corresponding AS Update Controller helper, `asuc_` API, `ASUC_API_VERSION` and `AlphaSys Controller API` header instead. Do not include both helpers in a single-brand plugin. Review standardised row labels and author headers as part of each client migration.
