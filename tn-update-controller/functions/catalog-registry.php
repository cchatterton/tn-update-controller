<?php
if (!defined('ABSPATH')) { exit; }

define('TNUC_REGISTRY_URL', 'https://raw.githubusercontent.com/cchatterton/tn-update-controller/main/catalogue-registry.json');

function tnuc_catalog_registry(): array {
    $stored = tnuc_get('catalog_registry');
    if (is_array($stored) && ($stored['schema'] ?? null) === 1) { return $stored; }
    $bundled = json_decode((string) file_get_contents(TNUC_DIR . 'data/catalogue-registry.json'), true);
    return is_array($bundled) ? $bundled : ['schema' => 1, 'catalogs' => []];
}

function tnuc_catalog_selection(): array {
    $selected = tnuc_get('catalog_selection', null);
    // Existing controller installations retain their Techn catalogue on upgrade.
    if ($selected === null) { return ['techn' => 'techn-26']; }
    return is_array($selected) ? $selected : [];
}

function tnuc_catalog_registry_validate($candidate) {
    if (!is_array($candidate) || ($candidate['schema'] ?? null) !== 1 || !is_array($candidate['catalogs'] ?? null) || count($candidate['catalogs']) > 30) {
        return new WP_Error('registry_schema', 'The catalogue registry is invalid.');
    }
    $catalogs = []; $ids = [];
    foreach ($candidate['catalogs'] as $catalog) {
        if (!is_array($catalog)) { return new WP_Error('registry_catalog', 'A catalogue entry is invalid.'); }
        $id = $catalog['id'] ?? ''; $collection = $catalog['collection'] ?? ''; $year = $catalog['year'] ?? '';
        if (!is_string($id) || !preg_match('/^[a-z][a-z0-9-]*-\d{2}$/D', $id) || !in_array($collection, ['techn', 'alphasys', 'fusion'], true) || !preg_match('/^\d{2}$/D', (string) $year) || $id !== $collection . '-' . $year || isset($catalogs[$id]) || !in_array($catalog['status'] ?? '', ['current', 'supported', 'archive_planned', 'archived'], true) || !is_array($catalog['plugins'] ?? null) || count($catalog['plugins']) > 200) {
            return new WP_Error('registry_catalog', 'A catalogue entry is invalid.');
        }
        $catalog['name'] = sanitize_text_field((string) ($catalog['name'] ?? ucfirst($collection) . ' ' . $year));
        $catalog['notice'] = sanitize_textarea_field((string) ($catalog['notice'] ?? ''));
        $plugins = []; $files = [];
        foreach ($catalog['plugins'] as $plugin) {
            if (!is_array($plugin) || !is_string($plugin['id'] ?? null) || !preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]*$/D', $plugin['id']) || isset($plugins[$plugin['id']])) {
                return new WP_Error('registry_plugin', 'A catalogue contains an invalid or duplicate plugin.');
            }
            $source = $plugin['source'] ?? '';
            if ($source === 'github') {
                $feed = $plugin['feed'] ?? $collection;
                $reviewed = $collection === 'techn' ? (tnuc_bundled_registry()[$plugin['id']] ?? []) : [];
                $regular = is_string($plugin['slug'] ?? null) && preg_match('/^[a-z0-9][a-z0-9-]*$/D', $plugin['slug']) && ($plugin['asset'] ?? '') === $plugin['slug'] . '.zip' && ($plugin['file'] ?? '') === $plugin['slug'] . '/' . $plugin['slug'] . '.php';
                $exception = $reviewed && ($plugin['slug'] ?? '') === ($reviewed['slug'] ?? null) && ($plugin['file'] ?? '') === ($reviewed['file'] ?? null) && ($plugin['asset'] ?? '') === ($reviewed['asset'] ?? null);
                if (!in_array($feed, ['techn', 'alphasys'], true) || ($collection === 'fusion' && !isset($plugin['feed'])) || ($plugin['owner'] ?? '') !== 'cchatterton' || ($plugin['author'] ?? '') !== ($feed === 'techn' ? 'Techn' : 'AlphaSys') || !is_string($plugin['repo'] ?? null) || !preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]*$/D', $plugin['repo']) || (!$regular && !$exception)) {
                    return new WP_Error('registry_identity', 'A GitHub plugin identity is invalid.');
                }
            } elseif ($source === 'mirror' || $source === 'native') {
                if (!in_array($plugin['native_source'] ?? '', ['wordpress.org', 'vendor', 'github'], true) || !is_string($plugin['name'] ?? null)) {
                    return new WP_Error('registry_identity', 'A catalogue plugin identity is invalid.');
                }
                if (isset($plugin['slug']) && !preg_match('/^[a-z0-9][a-z0-9-]*$/D', (string) $plugin['slug'])) {
                    return new WP_Error('registry_identity', 'A Fusion plugin slug is invalid.');
                }
            } else { return new WP_Error('registry_source', 'A catalogue package source is not supported.'); }
            $file = (string) ($plugin['file'] ?? $plugin['slug'] ?? strtolower($plugin['name'] ?? ''));
            if ($file === '' || isset($files[$file])) { return new WP_Error('registry_identity', 'A catalogue has conflicting plugin identities.'); }
            $files[$file] = true;
            $plugin['name'] = sanitize_text_field((string) ($plugin['name'] ?? $plugin['id']));
            $plugin['description'] = sanitize_text_field((string) ($plugin['description'] ?? ''));
            $plugin['role'] = sanitize_text_field((string) ($plugin['role'] ?? ''));
            $plugins[$plugin['id']] = $plugin;
        }
        $catalog['plugins'] = $plugins;
        $catalogs[$id] = $catalog;
        $ids[$collection][] = $id;
    }
    if (!$catalogs) { return new WP_Error('registry_empty', 'No catalogues were published.'); }
    return ['schema' => 1, 'catalogs' => $catalogs];
}

function tnuc_catalogs(): array {
    $validated = tnuc_catalog_registry_validate(tnuc_catalog_registry());
    return is_wp_error($validated) ? [] : $validated['catalogs'];
}

function tnuc_save_catalog_selection(array $input) {
    if (!tnuc_authorised() || !current_user_can(is_multisite() ? 'manage_network_options' : 'manage_options')) { return new WP_Error('permission', 'You cannot change catalogue subscriptions.'); }
    $catalogs = tnuc_catalogs(); $selected = [];
    foreach ($input as $id) {
        if (!is_string($id) || !isset($catalogs[$id])) { return new WP_Error('selection', 'An unknown catalogue was selected.'); }
        $collection = $catalogs[$id]['collection'];
        if (isset($selected[$collection])) { return new WP_Error('selection', 'Select only one edition of each collection.'); }
        if ($catalogs[$id]['status'] === 'archived' && (tnuc_catalog_selection()[$collection] ?? '') !== $id) { return new WP_Error('selection', 'Archived editions cannot be newly subscribed.'); }
        $selected[$collection] = $id;
    }
    tnuc_put('catalog_selection', $selected);
    return ['message' => 'Catalogue subscriptions saved. Check for updates to refresh the selected catalogues.'];
}

function tnuc_catalog_registry_refresh() {
    $url = add_query_arg('check', wp_generate_uuid4(), TNUC_REGISTRY_URL);
    $response = wp_safe_remote_get($url, ['timeout' => 10, 'redirection' => 0, 'limit_response_size' => 1048576, 'headers' => ['Accept' => 'application/json', 'Cache-Control' => 'no-cache']]);
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) { return new WP_Error('registry_download', 'The catalogue registry could not be checked. Previous subscriptions are retained.'); }
    $validated = tnuc_catalog_registry_validate(json_decode((string) wp_remote_retrieve_body($response), true));
    if (is_wp_error($validated)) { return $validated; }
    tnuc_put('catalog_registry', $validated);
    return $validated;
}
