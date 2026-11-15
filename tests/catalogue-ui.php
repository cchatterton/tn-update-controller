<?php
/** Render the catalogue controls without a WordPress installation. */
define('ABSPATH', __DIR__ . '/');
define('TNUC_FILE', '/tmp/tn-update-controller/tn-update-controller.php');
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value) { return esc_html($value); }
function esc_url($value) { return esc_html($value); }
function selected($left, $right, $echo = true) { return $left === $right ? ' selected="selected"' : ''; }
function sanitize_key($value) { return strtolower(preg_replace('/[^a-z0-9_-]/', '', (string) $value)); }
function wp_unslash($value) { return $value; }
function is_multisite() { return false; }
function is_plugin_active($file) { return $file === 'example/example.php'; }
function is_plugin_active_for_network($file) { return false; }
function admin_url($path) { return '/wp-admin/' . $path; }
function network_admin_url($path) { return '/wp-admin/network/' . $path; }
function self_admin_url($path) { return '/wp-admin/' . $path; }
function current_user_can($cap) { return true; }
function wp_is_file_mod_allowed($context) { return true; }
function plugin_basename($path) { return 'tn-update-controller/tn-update-controller.php'; }
function tnuc_get($key, $default = []) { return $key === 'hub_other_details' ? ['site/site.php' => ['slug' => 'site-plugin', 'last_updated' => '2026-11-01', 'downloaded' => 100, 'num_ratings' => 5]] : $default; }
function number_format_i18n($number) { return number_format($number); }
function tnuc_hub_wporg_slug($file) { return ''; }
function tnuc_hub_native_updates() { return []; }
function tnuc_hub_eligible($item, $kind) { return empty($item['issue']) && empty($item['conflict']) && ($kind === 'install' ? !$item['match'] && (bool) $item['release'] : $item['match'] && (bool) $item['update']); }
function tnuc_hub_compatibility($release) { return ''; }
require dirname(__DIR__) . '/tn-update-controller/functions/catalog-admin.php';

function ui_assert($condition, $message) { if (!$condition) { throw new RuntimeException($message); } echo "PASS: $message\n"; }
$catalogs = [
    'fusion-26' => ['id' => 'fusion-26', 'collection' => 'fusion', 'year' => '26', 'name' => 'Fusion 26', 'status' => 'current', 'notice' => 'Review before archive.', 'plugins' => []],
    'fusion-25' => ['id' => 'fusion-25', 'collection' => 'fusion', 'year' => '25', 'name' => 'Fusion 25', 'status' => 'supported', 'notice' => '', 'plugins' => []],
];
$selected = ['fusion' => 'fusion-26'];
$items = [
    'fusion-26:example' => ['key' => 'fusion-26:example', 'catalog' => 'fusion-26', 'entry' => ['id' => 'example', 'name' => 'Example', 'description' => 'Example plugin', 'role' => 'Content', 'beta' => false], 'match' => ['file' => 'example/example.php', 'data' => ['Version' => '1.0.0']], 'release' => ['version' => '1.1.0', 'notes' => 'https://example.test/release'], 'update' => ['version' => '1.1.0', 'source' => 'mirror'], 'issue' => '', 'conflict' => false],
    'other:site/site.php' => ['key' => 'other:site/site.php', 'catalog' => 'other', 'entry' => ['id' => 'site/site.php', 'name' => 'Site Plugin', 'description' => 'Local plugin'], 'match' => ['file' => 'site/site.php', 'data' => ['Version' => '1.0.0']], 'release' => [], 'update' => [], 'issue' => '', 'conflict' => false],
];
$_GET['catalog'] = 'fusion-26'; ob_start(); tnuc_hub_render_catalogue($catalogs, $selected, $items); $html = ob_get_clean();
ui_assert(strpos($html, 'Catalogue subscriptions') !== false && strpos($html, 'data-subscribe') !== false, 'subscription controls render');
ui_assert(strpos($html, 'Fusion 25 · Supported') !== false && strpos($html, 'Fusion 26 · Current') !== false, 'current and supported editions are selectable');
ui_assert(strpos($html, 'Review before archive.') !== false, 'edition notice renders');
ui_assert(strpos($html, 'data-kind="update"') !== false && strpos($html, 'Release notes') !== false, 'managed update and release notes actions render');
$_GET['catalog'] = 'other'; ob_start(); tnuc_hub_render_catalogue($catalogs, $selected, $items); $html = ob_get_clean();
ui_assert(strpos($html, 'Site Plugin') !== false && strpos($html, 'Example plugin') === false, 'Other lists only unclaimed installed plugins');
ui_assert(strpos($html, '100 downloads · 5 ratings') !== false && strpos($html, 'wordpress.org/plugins/site-plugin/#developers') !== false, 'Other retains public details and release notes');
