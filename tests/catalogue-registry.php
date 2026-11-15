<?php
/** Standalone publication fixtures; no WordPress installation is modified. */
define('ABSPATH', __DIR__ . '/');
define('TNUC_DIR', dirname(__DIR__) . '/tn-update-controller/');
class WP_Error {
    private $message;
    public function __construct($code, $message) { $this->message = $message; }
    public function get_error_message() { return $this->message; }
}
function is_wp_error($value) { return $value instanceof WP_Error; }
function sanitize_text_field($value) { return trim(strip_tags($value)); }
function sanitize_textarea_field($value) { return trim(strip_tags($value)); }
require TNUC_DIR . 'functions/catalogue.php';
require TNUC_DIR . 'functions/actions.php';
require TNUC_DIR . 'functions/catalog-registry.php';
require TNUC_DIR . 'functions/catalog-sources.php';

function hub_assert($condition, $message) { if (!$condition) { throw new RuntimeException($message); } echo "PASS: $message\n"; }
$root = dirname(__DIR__);
$registry = json_decode((string) file_get_contents($root . '/catalogue-registry.json'), true);
hub_assert($registry === json_decode((string) file_get_contents(TNUC_DIR . 'data/catalogue-registry.json'), true), 'published and bundled registries match');
$validated = tnuc_catalog_registry_validate($registry);
hub_assert(!is_wp_error($validated) && count($validated['catalogs']) === 5 && $validated['revision'] === 3, 'published registry has five valid collection releases');
hub_assert(count(array_filter($registry['catalogs'], static function ($catalog) { return ($catalog['year'] ?? '') !== ($catalog['release'] ?? ''); })) === 0, 'every collection release retains the legacy year field for older controllers');
hub_assert($validated['catalogs']['techn-27']['name'] === 'Techn' && $validated['catalogs']['techn-27']['release'] === '27', 'collection name and release are separate registry fields');
hub_assert($validated['catalogs']['techn-27']['status'] === 'current' && $validated['catalogs']['techn-26']['status'] === 'supported' && $validated['catalogs']['techn-25']['status'] === 'supported', 'Techn 27 is current and earlier editions remain supported');
hub_assert(count($validated['catalogs']['techn-27']['plugins']) === 20 && count($validated['catalogs']['techn-26']['plugins']) === 25 && count($validated['catalogs']['techn-25']['plugins']) === 8, 'Techn catalog memberships have the requested sizes');
hub_assert(array_column($validated['catalogs']['techn-25']['plugins'], 'name') === ['TN Clean My Posts', 'List of Values', 'Menubot', 'TN Environments', 'TN Login with Email', 'TN Post Families', 'TN QR Codes', 'TN Update Controller'], 'Techn 25 contains exactly the requested plugins');
hub_assert(array_column($validated['catalogs']['techn-27']['plugins'], 'name') === ['TN Exploitation Audit', 'Help Guides (Wiki-style)', 'Menubot', 'Persona26', 'TN Authenticator', 'TN Content Planner', 'TN Display Density', 'TN Environments', 'TN Login with Email', 'TN LOV', 'TN Pallet', 'TN Performance Advisor', 'TN Post Families', 'TN QR Codes', 'TN Sentinel', 'TN Sticky Posts', 'TN Tax Manager', 'TN Update Controller', 'TN User Management', 'WP Migrate - Release Management'], 'Techn 27 contains exactly the requested plugins');
hub_assert($validated['catalogs']['techn-27']['type'] === 'hosted' && $validated['catalogs']['techn-26']['type'] === 'hosted' && $validated['catalogs']['techn-25']['type'] === 'hosted' && $validated['catalogs']['alphasys-26']['type'] === 'hosted' && $validated['catalogs']['fusion-26']['type'] === 'mirrored', 'catalog types are published');
hub_assert(count(array_filter($validated['catalogs'], static function ($catalog) { return $catalog['last_updated'] !== '2026-11-15'; })) === 0, 'catalog last updated dates are published');
$custom = $registry; $custom['catalogs'][] = ['id' => 'editorial-26', 'collection' => 'editorial', 'release' => '26', 'name' => 'Editorial', 'status' => 'current', 'plugins' => []];
hub_assert(!is_wp_error(tnuc_catalog_registry_validate($custom)), 'registry accepts a future catalog collection');
$bad = $registry; $bad['catalogs'][0]['type'] = 'unknown';
hub_assert(is_wp_error(tnuc_catalog_registry_validate($bad)), 'unknown catalog type is rejected');
$bad = $registry; $bad['catalogs'][0]['last_updated'] = '2026-02-30';
hub_assert(is_wp_error(tnuc_catalog_registry_validate($bad)), 'invalid catalog date is rejected');
$bad = $registry; $bad['catalogs'][0]['year'] = '25';
hub_assert(is_wp_error(tnuc_catalog_registry_validate($bad)), 'conflicting legacy year and release values are rejected');
$bad = $registry; $bad['catalogs'][0]['plugins'][0]['owner'] = 'untrusted';
hub_assert(is_wp_error(tnuc_catalog_registry_validate($bad)), 'untrusted GitHub owner is rejected');
$bad = $registry; $bad['catalogs'][1]['plugins'][0]['source'] = 'arbitrary-url';
hub_assert(is_wp_error(tnuc_catalog_registry_validate($bad)), 'unknown package source is rejected');
$alpha = json_decode((string) file_get_contents($root . '/tests/fixtures/as-catalogue.json'), true);
$releases = tnuc_hub_validate_as_feed($alpha, $validated['catalogs']);
hub_assert(!is_wp_error($releases) && count($releases) === 23, 'AlphaSys releases match registered identities');
$bad = $alpha; $bad['plugins'][0]['repo'] = 'different-repository';
hub_assert(is_wp_error(tnuc_hub_validate_as_feed($bad, $validated['catalogs'])), 'changed AlphaSys repository is rejected');
$fusion = json_decode((string) file_get_contents($root . '/tests/fixtures/fusion-index-v3.json'), true);
$packages = tnuc_hub_validate_fusion_feed($fusion, $validated['catalogs']);
hub_assert(!is_wp_error($packages) && count($packages) === 13, 'Fusion mirror packages match registered slugs');
$bad = $fusion; $first = array_key_first($bad['packages']); $version = $bad['packages'][$first]['latest']; $bad['packages'][$first]['versions'][$version]['url'] = 'https://example.com/package.zip';
hub_assert(is_wp_error(tnuc_hub_validate_fusion_feed($bad, $validated['catalogs'])), 'unexpected mirror URL is rejected');
