<?php
/** Standalone catalogue routing test; no WordPress installation is modified. */
define('ABSPATH', __DIR__ . '/');
define('TNUC_DIR', dirname(__DIR__) . '/tn-update-controller/');
define('TNUC_FILE', TNUC_DIR . 'tn-update-controller.php');
define('TNUC_API_VERSION', 1);
class WP_Error { public function __construct($code, $message) {} }
function is_wp_error($value) { return $value instanceof WP_Error; }
function sanitize_text_field($value) { return trim(strip_tags($value)); }
function sanitize_textarea_field($value) { return trim(strip_tags($value)); }
function wp_strip_all_tags($value) { return strip_tags($value); }
function plugin_basename($path) { return 'tn-update-controller/tn-update-controller.php'; }
function is_multisite() { return false; }
function home_url($path = '/') { return 'https://example.test' . $path; }
function wp_parse_url($url, $component) { return parse_url($url, $component); }
function get_site_transient($key) { return $GLOBALS['native_updates']; }
function tnuc_get($key, $default = []) { return $GLOBALS['options'][$key] ?? $default; }
function tnuc_plugins() { return $GLOBALS['installed']; }
function tnuc_match($entry, $plugins) { return isset($plugins[$entry['file']]); }
function tnuc_compatibility($entry) { return ''; }
function tnuc_valid_domain_policy($rule) { return is_array($rule['allowed_domains']) && is_bool($rule['include_subdomains']); }
function tnuc_controller_update_pending() { return false; }
function tnuc_bundled_registry() { return json_decode((string) file_get_contents(TNUC_DIR . 'data/registry.json'), true); }
function tnuc_catalogue() { return $GLOBALS['techn_feed']; }
require TNUC_DIR . 'functions/catalog-registry.php';
require TNUC_DIR . 'functions/catalog-sources.php';
require TNUC_DIR . 'functions/catalog-batch.php';

function route_assert($condition, $message) { if (!$condition) { throw new RuntimeException($message); } echo "PASS: $message\n"; }
$root = dirname(__DIR__);
$wp_version = '7.1.2';
$techn = json_decode((string) file_get_contents($root . '/catalogue.json'), true);
$GLOBALS['techn_feed'] = ['plugins' => array_column($techn['plugins'], null, 'id')];
$alpha = json_decode((string) file_get_contents($root . '/tests/fixtures/as-catalogue.json'), true);
$fusion = json_decode((string) file_get_contents($root . '/tests/fixtures/fusion-index-v3.json'), true);
$catalogs = tnuc_catalogs();
$GLOBALS['options'] = [
    'catalog_selection' => ['alphasys-26', 'fusion-26'],
    'as_catalogue' => tnuc_hub_validate_as_feed($alpha, $catalogs),
    'fusion_mirror' => tnuc_hub_validate_fusion_feed($fusion, $catalogs),
];
$GLOBALS['installed'] = [
    'tn-update-controller/tn-update-controller.php' => ['Name' => 'TN Update Controller', 'Version' => '0.9.7', 'Author' => 'Techn'],
    'disable-comments/disable-comments.php' => ['Name' => 'Disable Comments', 'Version' => '0.1.0', 'Author' => 'WP'],
    'custom-plugin/custom-plugin.php' => ['Name' => 'Custom Plugin', 'Version' => '1.0.0', 'Description' => 'A site plugin'],
];
$GLOBALS['native_updates'] = (object) ['response' => [
    'disable-comments/disable-comments.php' => (object) ['new_version' => '99.0.0', 'package' => 'https://wordpress.org/plugin.zip'],
    'custom-plugin/custom-plugin.php' => (object) ['new_version' => '1.1.0', 'package' => 'https://vendor.example/update.zip'],
]];
$items = tnuc_hub_items();
route_assert(isset($items['fusion:disable-comments']) && $items['fusion:disable-comments']['update']['source'] === 'mirror', 'mirrored package wins for a subscribed Fusion plugin');
route_assert(isset($items['other:custom-plugin/custom-plugin.php']) && $items['other:custom-plugin/custom-plugin.php']['update']['source'] === 'native', 'unclaimed installed plugin uses its native provider');
route_assert(!isset($items['other:disable-comments/disable-comments.php']), 'subscribed plugin is excluded from Other');
route_assert(isset($items['techn:tn-update-controller']), 'required Techn collection includes the controller');
route_assert(isset($items['techn:tn-meeting-polls']) && $items['techn:tn-meeting-polls']['release']['version'] === $GLOBALS['techn_feed']['plugins']['tn-meeting-polls']['version'], 'current Techn collection exposes TN Meeting Polls with its verified release');
route_assert(tnuc_hub_eligible($items['techn:tn-meeting-polls'], 'install'), 'verified TN Meeting Polls release enables Install');
$verified_feed = $GLOBALS['techn_feed'];
unset($GLOBALS['techn_feed']['plugins']['tn-meeting-polls']);
$stale_items = tnuc_hub_items();
route_assert(!isset($stale_items['techn:tn-meeting-polls']), 'a new unverified GitHub release cannot render as an un-installable card');
$GLOBALS['techn_feed'] = $verified_feed;
route_assert(isset($items['alphasys:AS-Relevansi']) && !$items['alphasys:AS-Relevansi']['match'], 'AlphaSys collection lists an uninstalled plugin');
$expanded = json_decode((string) file_get_contents($root . '/catalogue-registry.json'), true);
$fusion25 = current(array_filter($expanded['catalogs'], static function ($catalog) { return $catalog['id'] === 'fusion-26'; }));
$fusion25['id'] = 'fusion-25'; $fusion25['release'] = '25'; $fusion25['year'] = '25'; $fusion25['name'] = 'Fusion'; $fusion25['status'] = 'supported';
$fusion25['plugins'][] = ['id' => 'old-only', 'name' => 'Old Only', 'description' => 'Included only in the older release.', 'source' => 'native', 'native_source' => 'wordpress.org', 'slug' => 'old-only', 'file' => 'old-only/old-only.php'];
$expanded['catalogs'][] = $fusion25;
$expanded['revision'] = 4;
$GLOBALS['options']['catalog_registry'] = $expanded;
$GLOBALS['options']['catalog_selection'] = ['fusion-25', 'fusion-26'];
$items = tnuc_hub_items();
route_assert(isset($items['fusion:disable-comments']) && count(array_filter($items, static function ($item) { return $item['catalog'] === 'fusion' && $item['entry']['id'] === 'disable-comments'; })) === 1, 'selected releases show a shared plugin only once');
route_assert($items['fusion:disable-comments']['release_id'] === 'fusion-26', 'newest selected release supplies shared plugin details');
route_assert(isset($items['fusion:old-only']) && $items['fusion:old-only']['release_id'] === 'fusion-25', 'older release contributes its exclusive plugin to the same collection');
route_assert(tnuc_hub_current_key('fusion-25:disable-comments') === 'fusion:disable-comments', 'old release update keys resolve to the collection item');
route_assert(!isset($items['other:disable-comments/disable-comments.php']), 'Other excludes plugins represented in either selected edition');
