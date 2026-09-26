<?php
if (!defined('ABSPATH')) { exit; }
function tnuc_available(): bool {
    if (!is_multisite()) { return true; }
    $active = get_site_option('active_sitewide_plugins', []);
    return isset($active[plugin_basename(TNUC_FILE)]);
}
function tnuc_get(string $key, $default = []) { return get_site_option('tnuc_' . $key, $default); }
function tnuc_put(string $key, $value): void { update_site_option('tnuc_' . $key, $value); }
function tnuc_settings(): array { return array_merge(['mode' => 'scheduled', 'hours' => 6], (array) tnuc_get('settings')); }
function tnuc_url(string $tab = 'installed'): string { return add_query_arg(['page' => 'tnuc', 'tab' => $tab], network_admin_url('plugins.php')); }
function tnuc_authorised(string $cap = 'update_plugins'): bool {
    return current_user_can($cap) && (!is_multisite() || current_user_can('manage_network_plugins'));
}
function tnuc_activate(bool $network_wide = false): void {
    if (is_multisite() && !$network_wide) { wp_die(esc_html__('Network activate the controller to manage shared plugin files.', 'tn-update-controller')); }
    tnuc_put('setup_pending', true);
    tnuc_schedule();
}
function tnuc_deactivate(): void {
    tnuc_on_main(static function () { wp_clear_scheduled_hook('tnuc_scheduled_check'); });
}
function tnuc_on_main(callable $callback) {
    $switch = is_multisite() && get_current_blog_id() !== get_main_site_id();
    if ($switch) { switch_to_blog(get_main_site_id()); }
    try { return $callback(); } finally { if ($switch) { restore_current_blog(); } }
}
function tnuc_schedule(): void {
    tnuc_on_main(static function () {
        wp_clear_scheduled_hook('tnuc_scheduled_check');
        $settings = tnuc_settings();
        if ($settings['mode'] === 'scheduled') {
            $at = max(time() + 60, (int) (tnuc_get('check')['next_check'] ?? (time() + 300)));
            wp_schedule_single_event($at, 'tnuc_scheduled_check');
        }
    });
}
function tnuc_scheduled_check(): void {
    if (tnuc_settings()['mode'] !== 'scheduled') { return; }
    tnuc_refresh(false);
    tnuc_schedule();
}
/**
 * Unique option_name provides atomic acquisition; compare-and-delete protects a replacement owner.
 * @return string|false
 */
function tnuc_lock(string $name, int $ttl = 120) {
    return tnuc_on_main(static function () use ($name, $ttl) {
        global $wpdb;
        $key = 'tnuc_lock_' . $name;
        $old = get_option($key);
        if (is_array($old) && ($old['expires'] ?? 0) < time()) {
            $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $key, maybe_serialize($old)));
            wp_cache_delete($key, 'options');
        }
        $token = wp_generate_uuid4();
        return add_option($key, ['token' => $token, 'expires' => time() + $ttl], '', false) ? $token : false;
    });
}
function tnuc_unlock(string $name, string $token): void {
    tnuc_on_main(static function () use ($name, $token) {
        global $wpdb;
        $key = 'tnuc_lock_' . $name;
        $old = get_option($key);
        if (is_array($old) && hash_equals((string) $old['token'], $token)) {
            $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $key, maybe_serialize($old)));
            wp_cache_delete($key, 'options');
        }
    });
}
