<?php
if (!defined('ABSPATH')) { exit; }

function tnuc_hub_as_legacy(): array {
    static $reviewed;
    if ($reviewed === null) {
        $value = json_decode((string) file_get_contents(TNUC_DIR . 'data/as-legacy.json'), true);
        $reviewed = is_array($value) ? $value : [];
    }
    return $reviewed;
}

function tnuc_hub_as_legacy_match(array $entry, array $match): bool {
    $reviewed = tnuc_hub_as_legacy()[$entry['id']] ?? [];
    if (($reviewed['file'] ?? '') !== $match['file'] || ($reviewed['repo'] ?? '') !== ($entry['repo'] ?? '')) { return false; }
    $author = strtolower(trim(wp_strip_all_tags((string) ($match['data']['Author'] ?? ''))));
    foreach ($reviewed['legacy_identity'] ?? [] as $identity) {
        $expected = strtolower(trim(wp_strip_all_tags((string) ($identity['author'] ?? ''))));
        $maximum = (string) ($identity['max_version'] ?? '');
        if ($expected !== '' && hash_equals($expected, $author) && $maximum !== '' && version_compare((string) ($match['data']['Version'] ?? '0'), $maximum, '<=')) { return true; }
    }
    return false;
}

function tnuc_hub_suppress_as_legacy(): void {
    global $wp_filter;
    $selected_catalogs = tnuc_selected_catalogs('alphasys');
    if (!$selected_catalogs) { return; }
    $installed = tnuc_plugins(); $approved = [];
    foreach ($selected_catalogs as $catalog) { foreach ($catalog['plugins'] as $entry) {
        $reviewed = tnuc_hub_as_legacy()[$entry['id']] ?? [];
        if (!$reviewed || ($reviewed['file'] ?? '') !== ($entry['file'] ?? '') || ($reviewed['repo'] ?? '') !== ($entry['repo'] ?? '') || !isset($installed[$entry['file']])) { continue; }
        foreach ($reviewed['legacy'] ?? [] as $legacy) {
            $path = WP_PLUGIN_DIR . '/' . dirname($entry['file']) . '/' . ($legacy['path'] ?? '');
            if (is_file($path) && preg_match('/^[a-f0-9]{64}$/D', (string) ($legacy['sha256'] ?? '')) && hash_equals($legacy['sha256'], (string) hash_file('sha256', $path))) { $approved[wp_normalize_path(realpath($path))] = true; }
        }
    } }
    if (!$approved) { return; }
    $hooks = ['pre_set_site_transient_update_plugins', 'site_transient_update_plugins', 'update_plugins_github.com', 'plugins_api', 'plugin_row_meta', 'admin_init', 'load-plugins.php', 'admin_notices', 'network_admin_notices', 'upgrader_process_complete'];
    foreach ($selected_catalogs as $catalog) { foreach ($catalog['plugins'] as $entry) { $hooks[] = 'plugin_action_links_' . $entry['file']; $hooks[] = 'network_admin_plugin_action_links_' . $entry['file']; } }
    foreach ($hooks as $hook) {
        if (empty($wp_filter[$hook])) { continue; }
        foreach ($wp_filter[$hook]->callbacks as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                try {
                    $fn = $callback['function'];
                    $reflection = is_array($fn) ? new ReflectionMethod($fn[0], $fn[1]) : (is_string($fn) && strpos($fn, '::') !== false ? new ReflectionMethod($fn) : new ReflectionFunction($fn));
                    $file = $reflection->getFileName();
                    if ($file && isset($approved[wp_normalize_path($file)])) { remove_filter($hook, $fn, $priority); }
                } catch (ReflectionException $error) { /* Unrecognised callbacks remain owned by their provider. */ }
                  catch (TypeError $error) { /* Unrecognised callbacks remain owned by their provider. */ }
            }
        }
    }
}

function tnuc_hub_as_row_meta(array $links, string $file): array {
    foreach (tnuc_selected_catalogs('alphasys') as $catalog) { foreach ($catalog['plugins'] as $entry) {
        if (($entry['file'] ?? '') !== $file) { continue; }
        $links = array_values(array_filter($links, static function ($link) {
            return !in_array(trim(wp_strip_all_tags((string) $link)), ['Install AlphaSys Update Controller', 'Activate AlphaSys Update Controller', 'Update AlphaSys Update Controller', 'Check for updates'], true);
        }));
        if (tnuc_authorised()) { $links[] = '<a href="' . esc_url(tnuc_hub_url('installed', 'tnuc-' . $catalog['id'])) . '">' . esc_html($catalog['name']) . ' Plugins</a>'; }
        return $links;
    } }
    return $links;
}
