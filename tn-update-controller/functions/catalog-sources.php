<?php
if (!defined('ABSPATH')) { exit; }

define('TNUC_AS_FEED_URL', 'https://raw.githubusercontent.com/cchatterton/as-update-controller/main/catalogue.json');
define('TNUC_FUSION_FEED_URL', 'https://raw.githubusercontent.com/cchatterton/fusion-controller/main/fusion-index-v3.json');

function tnuc_hub_download_json(string $url) {
    $response = wp_safe_remote_get(add_query_arg('check', wp_generate_uuid4(), $url), ['timeout' => 10, 'redirection' => 0, 'limit_response_size' => 1048576, 'headers' => ['Accept' => 'application/json', 'Cache-Control' => 'no-cache']]);
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) { return new WP_Error('feed_download', 'A package source could not be checked. Previous results are retained.'); }
    $decoded = tnuc_decode_catalogue_response((string) wp_remote_retrieve_body($response));
    return is_array($decoded) ? $decoded : new WP_Error('feed_format', 'A package source returned invalid JSON. Previous results are retained.');
}

function tnuc_hub_validate_as_feed($candidate, array $catalogs) {
    if (!is_array($candidate) || ($candidate['schema'] ?? null) !== 1 || !is_array($candidate['plugins'] ?? null) || count($candidate['plugins']) > 200) { return new WP_Error('as_feed', 'The AlphaSys release feed is invalid.'); }
    $known = [];
    foreach ($catalogs as $catalog) {
        foreach ($catalog['plugins'] as $entry) {
            if (($entry['source'] ?? '') === 'github' && ($entry['feed'] ?? $catalog['collection']) === 'alphasys') { $known[$entry['id']] = $entry; }
        }
    }
    $releases = [];
    foreach ($candidate['plugins'] as $entry) {
        if (!is_array($entry)) { return new WP_Error('as_feed', 'The AlphaSys release feed contains an invalid entry.'); }
        $id = $entry['id'] ?? '';
        if (!isset($known[$id])) { continue; }
        $identity = $known[$id];
        foreach (['id', 'owner', 'repo', 'file', 'slug', 'asset', 'author'] as $key) {
            if (($entry[$key] ?? null) !== ($identity[$key] ?? null)) { return new WP_Error('as_identity', 'An AlphaSys package identity changed. Previous results are retained.'); }
        }
        if (isset($releases[$id]) || !preg_match('/^\d+\.\d+(?:\.\d+){0,2}$/D', (string) ($entry['version'] ?? '')) || !in_array($entry['tag'] ?? '', [$entry['version'], 'v' . $entry['version'], 'V' . $entry['version']], true) || !preg_match('/^[a-f0-9]{64}$/D', (string) ($entry['sha256'] ?? ''))) {
            return new WP_Error('as_release', 'An AlphaSys package version or checksum is invalid.');
        }
        $policy = ['allowed_domains' => $entry['allowed_domains'] ?? [], 'include_subdomains' => $entry['include_subdomains'] ?? false];
        if (!tnuc_valid_domain_policy($policy) || !is_array($entry['dependencies'] ?? null) || !is_int($entry['controller_api'] ?? null)) { return new WP_Error('as_policy', 'An AlphaSys release policy is invalid.'); }
        $releases[$id] = $entry;
    }
    return $releases;
}

function tnuc_hub_validate_fusion_feed($candidate, array $catalogs) {
    if (!is_array($candidate) || ($candidate['schema'] ?? null) !== 1 || !is_array($candidate['packages'] ?? null) || count($candidate['packages']) > 200) { return new WP_Error('fusion_feed', 'The Fusion mirror feed is invalid.'); }
    $known = [];
    foreach ($catalogs as $catalog) {
        foreach ($catalog['plugins'] as $entry) {
            if (($entry['source'] ?? '') === 'mirror') { $known[$entry['slug'] ?? $entry['id']] = $entry; }
        }
    }
    $packages = [];
    foreach ($candidate['packages'] as $slug => $package) {
        if (!isset($known[$slug])) { continue; }
        if (!is_array($package) || !is_array($package['versions'] ?? null) || count($package['versions']) > 100 || !isset($package['versions'][$package['latest'] ?? ''])) { return new WP_Error('fusion_package', 'A Fusion mirror package is invalid.'); }
        $versions = [];
        foreach ($package['versions'] as $version => $release) {
            $url = 'https://github.com/cchatterton/fusion-controller/releases/download/mirror-' . $slug . '-v' . $version . '/' . $slug . '-' . $version . '.zip';
            if (!preg_match('/^\d+\.\d+(?:\.\d+){0,3}$/D', (string) $version) || !is_array($release) || !preg_match('/^[a-f0-9]{64}$/D', (string) ($release['sha256'] ?? '')) || ($release['url'] ?? '') !== $url) { return new WP_Error('fusion_release', 'A Fusion mirror release is invalid.'); }
            $root = $release['root'] ?? $slug;
            if (!preg_match('/^[a-z0-9][a-z0-9_-]*$/D', (string) $root)) { return new WP_Error('fusion_root', 'A Fusion mirror package directory is invalid.'); }
            $versions[$version] = ['version' => $version, 'sha256' => $release['sha256'], 'url' => $url, 'root' => $root, 'requires' => (string) ($release['requires'] ?? ''), 'requires_php' => (string) ($release['requires_php'] ?? '')];
        }
        $packages[$slug] = ['latest' => $package['latest'], 'versions' => $versions];
    }
    return $packages;
}

function tnuc_hub_check() {
    if (!tnuc_authorised()) { return new WP_Error('permission', 'You cannot check plugin updates.'); }
    $registry = tnuc_catalog_registry_refresh();
    if (is_wp_error($registry)) { return $registry; }
    $catalogs = $registry['catalogs']; $selected = tnuc_catalog_selection(); $errors = [];
    // The controller is always checked, even when Techn is not subscribed.
    $techn = tnuc_refresh();
    if (is_wp_error($techn)) { $errors[] = $techn->get_error_message(); }
    if (tnuc_controller_update_pending()) {
        return ['status' => 'controller_update', 'message' => 'Update Techn Update Controller first, then check the selected catalogues.', 'done' => 1, 'total' => 1];
    }
    $active_catalogs = [];
    foreach ($selected as $catalog_id) { if (isset($catalogs[$catalog_id]) && $catalogs[$catalog_id]['status'] !== 'archived') { $active_catalogs[] = $catalogs[$catalog_id]; } }
    $needs_as = false; $needs_mirror = false;
    foreach ($active_catalogs as $catalog) {
        foreach ($catalog['plugins'] as $entry) {
            if (($entry['source'] ?? '') === 'mirror') { $needs_mirror = true; }
            if (($entry['source'] ?? '') === 'github' && ($entry['feed'] ?? $catalog['collection']) === 'alphasys') { $needs_as = true; }
        }
    }
    if ($needs_as) {
        $feed = tnuc_hub_download_json(TNUC_AS_FEED_URL);
        if (!is_wp_error($feed)) { $feed = tnuc_hub_validate_as_feed($feed, $catalogs); }
        if (is_wp_error($feed)) { $errors[] = $feed->get_error_message(); }
        else { tnuc_put('as_catalogue', $feed); }
    }
    if ($needs_mirror) {
        $feed = tnuc_hub_download_json(TNUC_FUSION_FEED_URL);
        if (!is_wp_error($feed)) { $feed = tnuc_hub_validate_fusion_feed($feed, $catalogs); }
        if (is_wp_error($feed)) { $errors[] = $feed->get_error_message(); }
        else { tnuc_put('fusion_mirror', $feed); }
    }
    // Other and native fallback use the site's existing providers.
    wp_update_plugins(['manual_check' => 'plugin-catalogue']);
    tnuc_put('hub_checked_at', time());
    tnuc_put('hub_check_errors', $errors);
    return ['status' => $errors ? 'partial' : 'complete', 'message' => $errors ? implode(' ', $errors) : 'Selected catalogues and WordPress plugin updates checked.', 'done' => 1, 'total' => 1];
}

function tnuc_hub_native_updates(): array {
    $state = get_site_transient('update_plugins');
    return is_object($state) && is_array($state->response ?? null) ? $state->response : [];
}

function tnuc_hub_match(array $entry, array $installed): array {
    if (isset($entry['file'], $installed[$entry['file']])) { return ['file' => $entry['file'], 'data' => $installed[$entry['file']]]; }
    foreach ($installed as $file => $plugin) {
        if (!empty($entry['slug']) && dirname($file) === $entry['slug']) { return ['file' => $file, 'data' => $plugin]; }
        if (empty($entry['slug']) && strcasecmp((string) ($entry['name'] ?? ''), (string) ($plugin['Name'] ?? '')) === 0) { return ['file' => $file, 'data' => $plugin]; }
    }
    return [];
}

function tnuc_hub_match_trusted(array $entry, array $match, string $collection): bool {
    if (!$match || ($entry['source'] ?? '') !== 'github') { return true; }
    if (($entry['feed'] ?? $collection) === 'techn') { return tnuc_match($entry, tnuc_plugins()); }
    $plugin = $match['data'];
    $uri = rtrim((string) ($plugin['UpdateURI'] ?? ''), '/');
    if ($uri !== '') { return $uri === 'https://github.com/' . $entry['owner'] . '/' . $entry['repo']; }
    return strcasecmp(trim(wp_strip_all_tags((string) ($plugin['Author'] ?? ''))), $entry['author']) === 0 || tnuc_hub_as_legacy_match($entry, $match);
}

function tnuc_hub_release(array $entry, string $collection): array {
    if ($entry['source'] === 'github') {
        $feed = ($entry['feed'] ?? $collection) === 'techn' ? (tnuc_catalogue()['plugins'] ?? []) : (array) tnuc_get('as_catalogue');
        $release = $feed[$entry['id']] ?? [];
        if (!$release) { return []; }
        foreach (['owner', 'repo', 'file', 'slug', 'asset', 'author'] as $key) {
            if (($release[$key] ?? null) !== ($entry[$key] ?? null)) { return []; }
        }
        return ['source' => 'github', 'version' => $release['version'], 'sha256' => $release['sha256'], 'url' => 'https://github.com/' . $entry['owner'] . '/' . $entry['repo'] . '/releases/download/' . rawurlencode($release['tag']) . '/' . rawurlencode($entry['asset']), 'notes' => 'https://github.com/' . $entry['owner'] . '/' . $entry['repo'] . '/releases/tag/' . rawurlencode($release['tag']), 'root' => dirname($entry['file']), 'requires' => $release['requires'] ?? '', 'requires_php' => $release['requires_php'] ?? ''];
    }
    if ($entry['source'] === 'mirror') {
        $packages = (array) tnuc_get('fusion_mirror');
        $slug = $entry['slug'] ?? $entry['id'];
        $package = $packages[$slug] ?? [];
        $version = $package['latest'] ?? '';
        $release = $package['versions'][$version] ?? [];
        if ($release) { return ['source' => 'mirror', 'version' => $version, 'sha256' => $release['sha256'], 'url' => $release['url'], 'root' => $release['root'], 'notes' => 'https://github.com/cchatterton/fusion-controller/releases/tag/mirror-' . $slug . '-v' . $version, 'requires' => $release['requires'] ?: ($entry['requires'] ?? ''), 'requires_php' => $release['requires_php'] ?: ($entry['requires_php'] ?? '')]; }
    }
    return [];
}

function tnuc_hub_policy_issue(array $entry, array $release, string $collection, array $installed): string {
    if (!$release) { return ''; }
    $requirements = tnuc_hub_compatibility($release);
    if ($requirements) { return $requirements; }
    if (($entry['feed'] ?? $collection) === 'techn' && ($entry['source'] ?? '') === 'github') {
        $published = tnuc_catalogue()['plugins'][$entry['id']] ?? null;
        return $published ? tnuc_compatibility($published) : '';
    }
    if (($entry['feed'] ?? $collection) !== 'alphasys' || ($entry['source'] ?? '') !== 'github') { return ''; }
    $published = ((array) tnuc_get('as_catalogue'))[$entry['id']] ?? [];
    if (!$published) { return ''; }
    $host = strtolower(rtrim((string) wp_parse_url(is_multisite() ? network_home_url('/') : home_url('/'), PHP_URL_HOST), '.'));
    $allowed = $published['allowed_domains'] ?? [];
    if ($host !== 'localhost' && $allowed) {
        $permitted = false;
        foreach ($allowed as $domain) {
            if ($host === $domain || (!empty($published['include_subdomains']) && substr($host, -strlen('.' . $domain)) === '.' . $domain)) { $permitted = true; break; }
        }
        if (!$permitted) { return 'This plugin is unavailable for this domain.'; }
    }
    if (!empty($published['exclusive']) && !isset($installed[$entry['file']])) { return 'This exclusive plugin must be installed manually first.'; }
    if (($published['controller_api'] ?? 1) > TNUC_API_VERSION) { return 'Update Techn Update Controller first.'; }
    foreach ($published['dependencies'] ?? [] as $slug) {
        $found = false;
        foreach ($installed as $file => $plugin) {
            if (dirname($file) === $slug && (is_plugin_active($file) || is_plugin_active_for_network($file))) { $found = true; break; }
        }
        if (!$found) { return 'Requires active plugin: ' . $slug; }
    }
    return '';
}

function tnuc_hub_items(): array {
    $catalogs = tnuc_catalogs(); $selected = tnuc_catalog_selection(); $installed = tnuc_plugins(); $native = tnuc_hub_native_updates(); $items = []; $owned = [plugin_basename(TNUC_FILE) => true];
    foreach ($selected as $collection => $catalog_id) {
        if (!isset($catalogs[$catalog_id]) || $catalogs[$catalog_id]['collection'] !== $collection) { continue; }
        foreach ($catalogs[$catalog_id]['plugins'] as $entry) {
            $match = tnuc_hub_match($entry, $installed); $file = $match['file'] ?? '';
            $release = $catalogs[$catalog_id]['status'] === 'archived' ? [] : tnuc_hub_release($entry, $collection); $current = (string) ($match['data']['Version'] ?? '0');
            $native_update = $file !== '' ? ($native[$file] ?? null) : null;
            $native_version = is_object($native_update) ? (string) ($native_update->new_version ?? '') : '';
            $newer_mirror = $release && $file !== '' && version_compare($release['version'], $current, '>');
            $newer_native = $native_version !== '' && version_compare($native_version, $current, '>');
            $native_allowed = ($entry['fallback'] ?? '') === 'native' || $entry['source'] === 'native' || $catalogs[$catalog_id]['status'] === 'archived';
            $update = $newer_mirror ? $release : (($newer_native && $native_allowed) ? ['source' => 'native', 'version' => $native_version, 'package' => (string) ($native_update->package ?? '')] : []);
            $key = $catalog_id . ':' . $entry['id'];
            $issue = tnuc_hub_policy_issue($entry, $release, $collection, $installed);
            $items[$key] = ['key' => $key, 'catalog' => $catalog_id, 'collection' => $collection, 'entry' => $entry, 'match' => $match, 'release' => $release, 'update' => $update, 'conflict' => !tnuc_hub_match_trusted($entry, $match, $collection), 'issue' => $issue];
            if ($file !== '') { $owned[$file] = true; }
        }
    }
    if (!isset($selected['techn']) && isset($catalogs['techn-26']['plugins']['tn-update-controller'])) {
        $entry = $catalogs['techn-26']['plugins']['tn-update-controller'];
        $match = tnuc_hub_match($entry, $installed); $release = tnuc_hub_release($entry, 'techn');
        $update = $match && $release && version_compare($release['version'], (string) ($match['data']['Version'] ?? '0'), '>') ? $release : [];
        $key = 'controller:tn-update-controller';
        $items[$key] = ['key' => $key, 'catalog' => 'controller', 'collection' => 'techn', 'entry' => $entry, 'match' => $match, 'release' => $release, 'update' => $update, 'conflict' => false, 'issue' => ''];
    }
    $identities = [];
    foreach ($items as $key => $item) {
        $entry = $item['entry'];
        $identity = !empty($entry['slug']) ? 'slug:' . strtolower($entry['slug']) : 'name:' . strtolower($entry['name']);
        if (isset($identities[$identity])) {
            $items[$key]['conflict'] = true;
            $items[$identities[$identity]]['conflict'] = true;
        } else { $identities[$identity] = $key; }
    }
    foreach ($installed as $file => $plugin) {
        if (isset($owned[$file])) { continue; }
        $notice = $native[$file] ?? null; $version = is_object($notice) ? (string) ($notice->new_version ?? '') : '';
        $update = $version !== '' && version_compare($version, (string) ($plugin['Version'] ?? '0'), '>') ? ['source' => 'native', 'version' => $version, 'package' => (string) ($notice->package ?? '')] : [];
        $key = 'other:' . $file;
        $items[$key] = ['key' => $key, 'catalog' => 'other', 'collection' => 'other', 'entry' => ['id' => $file, 'file' => $file, 'name' => $plugin['Name'] ?? $file, 'description' => wp_strip_all_tags((string) ($plugin['Description'] ?? '')), 'source' => 'native'], 'match' => ['file' => $file, 'data' => $plugin], 'release' => [], 'update' => $update, 'conflict' => false, 'issue' => ''];
    }
    return $items;
}
