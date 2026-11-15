<?php
if (!defined('ABSPATH')) { exit; }

function tnuc_hub_batch(): array { return (array) tnuc_get('hub_batch'); }
function tnuc_hub_store_batch(array $batch): void { tnuc_put('hub_batch', $batch); }

function tnuc_hub_eligible(array $item, string $kind): bool {
    if (!empty($item['issue'])) { return false; }
    if (tnuc_controller_update_pending() && ($item['entry']['id'] ?? '') !== 'tn-update-controller') { return false; }
    if ($kind === 'update') {
        $update = $item['update'];
        return !empty($item['match']) && !empty($update) && ($update['source'] !== 'native' || !empty($update['package']));
    }
    return empty($item['conflict']) && empty($item['match']) && !empty($item['release']) && in_array($item['release']['source'], ['github', 'mirror'], true);
}

function tnuc_hub_start_batch(array $keys, string $kind) {
    if (!in_array($kind, ['update', 'install'], true) || !tnuc_authorised($kind === 'install' ? 'install_plugins' : 'update_plugins') || !wp_is_file_mod_allowed('tnuc')) { return new WP_Error('permission', 'You cannot change plugins on this site.'); }
    $keys = array_values(array_unique(array_map('tnuc_hub_current_key', array_filter($keys, 'is_string'))));
    if (!$keys || count($keys) > 100) { return new WP_Error('selection', 'Select at least one eligible plugin.'); }
    if (tnuc_controller_update_pending()) {
        foreach ($keys as $key) { if (substr($key, -strlen(':tn-update-controller')) !== ':tn-update-controller') { return new WP_Error('controller_first', 'Update the controller first, then check for updates again.'); } }
    }
    $lock = tnuc_lock('hub_batch', 300);
    if (!$lock) { return new WP_Error('busy', 'Another plugin operation is running.'); }
    try {
    $old = tnuc_hub_batch();
    if (($old['status'] ?? '') === 'running' && ($old['updated_at'] ?? 0) > time() - HOUR_IN_SECONDS) { return new WP_Error('batch_exists', 'Resume the existing update before starting another.'); }
    $available = tnuc_hub_items(); $items = [];
    foreach ($keys as $key) {
        $item = $available[$key] ?? null;
        if (!$item || !tnuc_hub_eligible($item, $kind)) { return new WP_Error('selection', 'An update is no longer available. Check for updates again.'); }
        $release = $kind === 'install' ? $item['release'] : $item['update'];
        $items[] = ['id' => $key, 'version' => $release['version'], 'source' => $release['source'], 'sha256' => $release['sha256'] ?? '', 'file' => $item['match']['file'] ?? '', 'status' => 'pending'];
    }
    usort($items, static function ($left, $right) {
        return (int) (strpos($left['id'], ':tn-update-controller') !== false) <=> (int) (strpos($right['id'], ':tn-update-controller') !== false);
    });
    $batch = ['id' => wp_generate_uuid4(), 'owner' => get_current_user_id(), 'kind' => $kind, 'items' => $items, 'status' => 'running', 'updated_at' => time()];
    tnuc_hub_store_batch($batch);
    return $batch;
    } finally { tnuc_unlock('hub_batch', $lock); }
}

function tnuc_hub_download_verified(array $release) {
    $url = (string) ($release['url'] ?? '');
    if (!preg_match('~^https://github\.com/cchatterton/[A-Za-z0-9_.-]+/releases/download/[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+\.zip$~D', $url) || !preg_match('/^[a-f0-9]{64}$/D', (string) ($release['sha256'] ?? ''))) { return new WP_Error('package_url', 'The package URL or checksum is invalid.'); }
    $download = download_url($url, 120);
    if (is_wp_error($download)) { return $download; }
    $size = filesize($download);
    if (!$size || $size > 104857600 || !hash_equals($release['sha256'], (string) hash_file('sha256', $download))) { @unlink($download); return new WP_Error('checksum', 'The downloaded package failed verification.'); }
    if (!class_exists('ZipArchive')) { @unlink($download); return new WP_Error('zip', 'ZIP support is unavailable on this server.'); }
    $zip = new ZipArchive();
    if ($zip->open($download) !== true) { @unlink($download); return new WP_Error('zip', 'The downloaded package is not a valid ZIP.'); }
    $root = (string) ($release['root'] ?? '');
    $valid = $root !== '' && $zip->numFiles > 0;
    for ($index = 0; $index < $zip->numFiles; $index++) {
        $name = (string) $zip->getNameIndex($index);
        if (strpos($name, $root . '/') !== 0 || strpos($name, '..') !== false || strpos($name, '\\') !== false) { $valid = false; break; }
    }
    $zip->close();
    if (!$valid) { @unlink($download); return new WP_Error('package_root', 'The package has an unexpected plugin directory.'); }
    return $download;
}

function tnuc_hub_compatibility(array $release): string {
    global $wp_version;
    $php = (string) ($release['requires_php'] ?? ''); $wp = (string) ($release['requires'] ?? '');
    if ($php !== '' && version_compare(PHP_VERSION, $php, '<')) { return 'Requires PHP ' . $php . '+'; }
    if ($wp !== '' && version_compare((string) $wp_version, $wp, '<')) { return 'Requires WordPress ' . $wp . '+'; }
    return '';
}

function tnuc_hub_step_batch(string $id) {
    $batch = tnuc_hub_batch();
    if (($batch['id'] ?? '') !== $id || ($batch['status'] ?? '') !== 'running' || !tnuc_authorised(($batch['kind'] ?? '') === 'install' ? 'install_plugins' : 'update_plugins')) { return new WP_Error('batch', 'This update batch is unavailable.'); }
    if (!wp_is_file_mod_allowed('tnuc')) { return new WP_Error('files_disabled', 'Plugin file changes are disabled.'); }
    $lock = tnuc_lock('hub_batch', 300);
    if (!$lock) { return new WP_Error('busy', 'Another plugin operation is running.'); }
    try {
        $available = tnuc_hub_items();
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        if (get_filesystem_method() !== 'direct') { return new WP_Error('filesystem', 'This site requires filesystem credentials. Use the WordPress Plugins screen.'); }
        foreach ($batch['items'] as $index => $queued) {
            if (!in_array($queued['status'], ['pending', 'working'], true)) { continue; }
            $item = $available[tnuc_hub_current_key($queued['id'])] ?? null;
            $release = $item ? ($batch['kind'] === 'install' ? $item['release'] : $item['update']) : [];
            $error = (!$item || !$release || ($release['version'] ?? '') !== $queued['version'] || ($release['source'] ?? '') !== $queued['source'] || ($release['sha256'] ?? '') !== $queued['sha256']) ? 'The package changed. Check for updates again.' : tnuc_hub_compatibility($release);
            $file = $item['match']['file'] ?? $queued['file'];
            if ($file !== '' && version_compare((string) (tnuc_plugins()[$file]['Version'] ?? '0'), $queued['version'], '>=')) {
                $batch['items'][$index]['status'] = 'success'; $batch['items'][$index]['message'] = 'Installed version verified.';
            } elseif (!$error) {
                $batch['items'][$index]['status'] = 'working'; $batch['updated_at'] = time(); tnuc_hub_store_batch($batch);
                if ($release['source'] === 'native') {
                    if (empty($release['package']) || $file === '') { $error = 'WordPress did not provide a downloadable update package.'; }
                    else {
                        $result = (new Plugin_Upgrader(new Automatic_Upgrader_Skin()))->bulk_upgrade([$file], ['clear_update_cache' => false]);
                        $result = is_array($result) ? ($result[$file] ?? false) : $result;
                    }
                } else {
                    $download = tnuc_hub_download_verified($release);
                    if (is_wp_error($download)) { $error = $download->get_error_message(); }
                    else {
                        try { $result = (new Plugin_Upgrader(new Automatic_Upgrader_Skin()))->install($download, ['overwrite_package' => $batch['kind'] === 'update']); }
                        finally { @unlink($download); }
                    }
                }
                if (!$error) {
                    wp_clean_plugins_cache(false);
                    $after = tnuc_hub_match($item['entry'], tnuc_plugins());
                    $actual = (string) ($after['data']['Version'] ?? '');
                    if (is_wp_error($result) || !$result || $actual === '' || version_compare($actual, $queued['version'], '<')) { $error = is_wp_error($result) ? $result->get_error_message() : 'WordPress could not verify the installed version.'; }
                    else { $batch['items'][$index]['status'] = 'success'; $batch['items'][$index]['message'] = 'Installed ' . $actual . '.'; }
                }
            }
            if ($error) { $batch['items'][$index]['status'] = 'failed'; $batch['items'][$index]['message'] = sanitize_text_field($error); }
            break;
        }
        $pending = array_filter($batch['items'], static function ($item) { return in_array($item['status'], ['pending', 'working'], true); });
        $batch['status'] = $pending ? 'running' : (array_filter($batch['items'], static function ($item) { return $item['status'] === 'failed'; }) ? 'partial' : 'complete');
        $batch['updated_at'] = time(); tnuc_hub_store_batch($batch);
        return $batch;
    } finally { tnuc_unlock('hub_batch', $lock); }
}

function tnuc_hub_plugin_action(string $key, string $operation) {
    $item = tnuc_hub_items()[tnuc_hub_current_key($key)] ?? null;
    if (!$item || empty($item['match']) || !in_array($operation, ['activate', 'deactivate', 'delete'], true)) { return new WP_Error('action', 'This plugin action is unavailable.'); }
    $file = $item['match']['file'];
    if ($file === plugin_basename(TNUC_FILE)) { return new WP_Error('controller', 'Use WordPress Plugins to manage the controller.'); }
    $cap = $operation === 'delete' ? 'delete_plugins' : ($operation === 'activate' ? 'activate_plugin' : 'deactivate_plugin');
    if (!tnuc_authorised() || !current_user_can($cap, $file)) { return new WP_Error('permission', 'You cannot change this plugin.'); }
    $lock = tnuc_lock('hub_batch', 300);
    if (!$lock) { return new WP_Error('busy', 'Another plugin operation is running.'); }
    try {
        if ((tnuc_hub_batch()['status'] ?? '') === 'running') { return new WP_Error('busy', 'Finish the current update batch first.'); }
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        if ($operation === 'activate') {
            $requirements = validate_plugin_requirements($file);
            if (is_wp_error($requirements)) { return $requirements; }
            if (class_exists('WP_Plugin_Dependencies')) {
                WP_Plugin_Dependencies::initialize();
                if (WP_Plugin_Dependencies::has_unmet_dependencies($file)) { return new WP_Error('dependencies', 'Activate the required plugins first.'); }
            }
            $result = activate_plugin($file, '', is_multisite());
            if (is_wp_error($result)) { return $result; }
        } elseif ($operation === 'deactivate') {
            if (class_exists('WP_Plugin_Dependencies')) {
                WP_Plugin_Dependencies::initialize();
                if (WP_Plugin_Dependencies::has_active_dependents($file)) { return new WP_Error('dependents', 'Deactivate dependent plugins first.'); }
            }
            deactivate_plugins($file, false, is_multisite());
        } else {
            if (!wp_is_file_mod_allowed('tnuc') || tnuc_active_anywhere($file)) { return new WP_Error('delete', 'Deactivate this plugin on every site before deleting it.'); }
            require_once ABSPATH . 'wp-admin/includes/file.php';
            if (get_filesystem_method() !== 'direct') { return new WP_Error('filesystem', 'Use WordPress Plugins to provide filesystem credentials.'); }
            $result = delete_plugins([$file]);
            if (is_wp_error($result) || !$result) { return is_wp_error($result) ? $result : new WP_Error('delete', 'WordPress could not delete this plugin.'); }
        }
        $active = is_multisite() ? is_plugin_active_for_network($file) : is_plugin_active($file);
        if (($operation === 'activate' && !$active) || ($operation === 'deactivate' && $active) || ($operation === 'delete' && file_exists(WP_PLUGIN_DIR . '/' . $file))) { return new WP_Error('verification', 'The requested plugin state could not be verified.'); }
        wp_clean_plugins_cache(false);
        return ['message' => $item['entry']['name'] . ': ' . ['activate' => 'activated.', 'deactivate' => 'deactivated.', 'delete' => 'deleted.'][$operation]];
    } finally { tnuc_unlock('hub_batch', $lock); }
}
