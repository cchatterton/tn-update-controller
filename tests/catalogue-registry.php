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
hub_assert(!is_wp_error($validated) && count($validated['catalogs']) === 3, 'published registry has three valid collections');
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
