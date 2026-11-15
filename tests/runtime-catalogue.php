<?php
/** Run only on a disposable WordPress site with the controller active. */
if (!defined('ABSPATH') || !defined('TNUC_VERSION')) { throw new RuntimeException('Activate TN Update Controller on a disposable WordPress site.'); }
wp_set_current_user(1);
function runtime_assert($condition, $message) { if (!$condition) { throw new RuntimeException($message); } echo "PASS: $message\n"; }
$root = getenv('TNUC_TEST_REPO');
if (!$root || !is_file($root . '/catalogue-registry.json')) { throw new RuntimeException('Set TNUC_TEST_REPO to the repository root.'); }
$registry = json_decode((string) file_get_contents($root . '/catalogue-registry.json'), true);
$catalogs = tnuc_catalogs();
runtime_assert(count($catalogs) === 3 && count($catalogs['techn-26']['plugins']) === 25 && count($catalogs['alphasys-26']['plugins']) === 23 && count($catalogs['fusion-26']['plugins']) === 24, 'bundled editions and retired-controller exclusions load');
runtime_assert(tnuc_catalog_selection() === [], 'new installation starts unsubscribed');
$selection = tnuc_save_catalog_selection(['techn-26', 'alphasys-26', 'fusion-26']);
runtime_assert(!is_wp_error($selection) && tnuc_catalog_selection() === ['techn' => 'techn-26', 'alphasys' => 'alphasys-26', 'fusion' => 'fusion-26'], 'site subscriptions persist');
$network_calls = 0;
$deny = static function ($pre, $args, $url) use (&$network_calls) { $network_calls++; return new WP_Error('unexpected_http', 'Rendering made an HTTP request.'); };
add_filter('pre_http_request', $deny, 10, 3);
$_GET['tab'] = 'catalogue';
ob_start(); tnuc_hub_render_admin(); $html = ob_get_clean();
runtime_assert(strpos($html, 'Plugin Catalogue') !== false && strpos($html, 'Catalogue subscriptions') !== false && strpos($html, '<dialog') !== false, 'shared screen, subscriptions and progress modal render');
runtime_assert(strpos($html, 'Techn 26') !== false && strpos($html, 'AlphaSys 26') !== false && strpos($html, 'Fusion 26') !== false && strpos($html, 'Other installed plugins') !== false, 'subscribed catalogues and Other appear in picker');
runtime_assert($network_calls === 0, 'normal page render makes no HTTP requests');
remove_filter('pre_http_request', $deny, 10);
$registry_response = static function ($pre, $args, $url) use ($registry) {
    if (strtok($url, '?') !== TNUC_REGISTRY_URL) { return $pre; }
    return ['response' => ['code' => 200], 'headers' => [], 'body' => wp_json_encode($registry)];
};
add_filter('pre_http_request', $registry_response, 10, 3);
$check = tnuc_hub_check();
remove_filter('pre_http_request', $registry_response, 10);
runtime_assert(!is_wp_error($check) && in_array($check['status'], ['complete', 'partial', 'controller_update'], true), 'manual check reaches published source feeds');
runtime_assert(count((array) tnuc_get('as_catalogue')) === 23 && count((array) tnuc_get('fusion_mirror')) === 13, 'AlphaSys and Fusion package feeds cache their registered releases');
runtime_assert(isset(tnuc_hub_items()['fusion-26:disable-comments']), 'Fusion membership remains available after check');
unset($_GET['tab']);
