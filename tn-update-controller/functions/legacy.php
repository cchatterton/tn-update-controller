<?php
if (!defined('ABSPATH')) { exit; }
/** Remove only callbacks whose implementation file exactly matches a reviewed release file. */
function tnuc_legacy_file_matches(string $path, array $legacy): bool {
    if (!is_file($path)) { return false; }
    $hash = hash_file('sha256', $path);
    $approved = array_merge([$legacy['sha256']], $legacy['sha256_alternatives'] ?? []);
    return in_array($hash, $approved, true);
}
function tnuc_suppress_legacy(): void {
    global $wp_filter;
    $plugins = tnuc_plugins(); $approved = [];
    foreach (tnuc_registry() as $entry) {
        if (!tnuc_match($entry, $plugins)) { continue; }
        foreach ($entry['legacy'] ?? [] as $legacy) {
            $path = WP_PLUGIN_DIR . '/' . dirname($entry['file']) . '/' . $legacy['path'];
            if (tnuc_legacy_file_matches($path, $legacy)) { $approved[wp_normalize_path(realpath($path))] = $entry['id']; }
        }
    }
    if (!$approved) { return; }
    $hooks = ['pre_set_site_transient_update_plugins', 'site_transient_update_plugins', 'update_plugins_github.com', 'plugins_api', 'plugin_row_meta', 'admin_init', 'load-plugins.php', 'admin_notices', 'network_admin_notices', 'upgrader_process_complete'];
    foreach (tnuc_registry() as $entry) { $hooks[] = 'plugin_action_links_' . $entry['file']; $hooks[] = 'network_admin_plugin_action_links_' . $entry['file']; }
    foreach ($hooks as $hook) {
        if (empty($wp_filter[$hook])) { continue; }
        foreach ($wp_filter[$hook]->callbacks as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                try {
                    $fn = $callback['function'];
                    $ref = is_array($fn) ? new ReflectionMethod($fn[0], $fn[1]) : (is_string($fn) && str_contains($fn, '::') ? new ReflectionMethod($fn) : new ReflectionFunction($fn));
                    $file = $ref->getFileName();
                    if ($file && isset($approved[wp_normalize_path($file)])) { remove_filter($hook, $fn, $priority); }
                } catch (ReflectionException|TypeError $error) { /* Unrecognised callbacks remain owned by their provider. */ }
            }
        }
    }
}
function tnuc_migration_status(array $entry): string {
    if (($entry['id'] ?? '') === 'tn-update-controller') { return 'Controller integration'; }
    $path = WP_PLUGIN_DIR . '/' . $entry['file'];
    if (is_file($path)) {
        $headers = get_file_data($path, ['api' => 'Techn Controller API']);
        if ((int) $headers['api'] >= 1) { return 'Controller integration'; }
    }
    $trusted = tnuc_registry()[$entry['id']]['legacy'] ?? [];
    if (!$trusted) { return 'Legacy updater needs review'; }
    foreach ($trusted as $item) {
        $path = WP_PLUGIN_DIR . '/' . dirname($entry['file']) . '/' . $item['path'];
        if (!tnuc_legacy_file_matches($path, $item)) { return 'Legacy updater needs review'; }
    }
    return 'Audited legacy updater held inactive while controller runs';
}
