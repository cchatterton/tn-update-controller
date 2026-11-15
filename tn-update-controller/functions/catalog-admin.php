<?php
if (!defined('ABSPATH')) { exit; }

function tnuc_hub_url(string $tab = 'installed', string $catalog = ''): string {
    $args = ['page' => 'tnuc', 'tab' => $tab];
    if ($catalog !== '') { $args['catalog'] = $catalog; }
    return add_query_arg($args, is_multisite() ? network_admin_url('plugins.php') : admin_url('plugins.php'));
}

function tnuc_hub_assets(string $hook): void {
    if ($hook !== 'plugins_page_tnuc') { return; }
    wp_enqueue_style('tnuc-admin', plugins_url('styles/admin.css', TNUC_FILE), [], TNUC_VERSION);
    wp_enqueue_script('tnuc-admin', plugins_url('scripts/catalog-admin.js', TNUC_FILE), [], TNUC_VERSION, true);
    $names = [];
    foreach (tnuc_hub_items() as $key => $item) { $names[$key] = $item['entry']['name']; }
    wp_localize_script('tnuc-admin', 'tnucAdmin', ['url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('tnuc_hub'), 'names' => $names]);
}

function tnuc_hub_render_admin(): void {
    if (!tnuc_authorised()) { wp_die('You cannot manage plugin updates.'); }
    $tab = sanitize_key(wp_unslash($_GET['tab'] ?? 'installed'));
    if (!in_array($tab, ['installed', 'catalogue'], true)) { $tab = 'installed'; }
    $catalogs = tnuc_catalogs(); $selected = tnuc_catalog_selection(); $items = tnuc_hub_items();
    $available = array_filter($items, static function ($item) { return !empty($item['update']); });
    if (tnuc_controller_update_pending()) { $available = array_filter($available, static function ($item) { return $item['entry']['id'] === 'tn-update-controller'; }); }
    $count = count(array_filter($items, static function ($item) { return !empty($item['match']); }));
    $checked = (int) tnuc_get('hub_checked_at', 0);
    $batch = tnuc_hub_batch();
    echo '<div class="wrap tnuc-wrap"><h1>Plugin Catalogue</h1><div id="tnuc-view">';
    $notice = get_transient('tnuc_notice_' . get_current_user_id());
    if (is_array($notice)) {
        delete_transient('tnuc_notice_' . get_current_user_id());
        echo '<div class="notice ' . (!empty($notice['error']) ? 'notice-error' : 'notice-success') . ' is-dismissible"><p>' . esc_html($notice['message'] ?? '') . '</p></div>';
    }
    echo '<header class="tnuc-header"><span class="tnuc-version" aria-label="Version ' . esc_attr(TNUC_VERSION) . '">v' . esc_html(TNUC_VERSION) . '</span><p class="tnuc-eyebrow">Plugin Catalogue</p><h2>Selected plugins. Simple updates.</h2><p>Choose the collections your site uses and update their plugins here.</p><div class="tnuc-header-bottom"><span>' . esc_html($count) . ' installed · ' . esc_html(count($available)) . ' updates available</span><button type="button" class="button tnuc-primary" data-check>Check for updates</button></div></header>';
    $errors = (array) tnuc_get('hub_check_errors');
    echo '<div class="tnuc-status"><span>' . ($checked ? 'Last checked ' . esc_html(wp_date('j M Y, H:i', $checked)) : 'Never checked. Check for updates to load current releases.') . ($errors ? ' · Some sources could not be checked.' : '') . '</span></div>';
    echo '<nav class="nav-tab-wrapper" aria-label="Plugin Catalogue">';
    $view_catalog = sanitize_key(wp_unslash($_GET['catalog'] ?? ''));
    foreach (['installed' => 'Updates available', 'catalogue' => 'Catalogue'] as $key => $label) { echo '<a class="nav-tab ' . ($tab === $key ? 'nav-tab-active' : '') . '" href="' . esc_url(tnuc_hub_url($key, $view_catalog)) . '">' . esc_html($label) . '</a>'; }
    echo '</nav><div id="tnuc-feedback" role="status" aria-live="polite"></div>';
    if (($batch['status'] ?? '') === 'running') { echo '<p class="tnuc-resume"><button type="button" class="button" data-resume="' . esc_attr($batch['id']) . '">Resume updates</button></p>'; }
    elseif (($batch['status'] ?? '') === 'partial') { echo '<p class="tnuc-resume">Some plugins could not be updated. <button type="button" class="button-link" data-results>View results</button></p>'; }
    if ($tab === 'catalogue') { tnuc_hub_render_catalogue($catalogs, $selected, $items); }
    else { tnuc_hub_render_updates($available, $catalogs); }
    echo '</div>';
    tnuc_render_dialog();
    echo '</div>';
}

function tnuc_hub_render_updates(array $updates, array $catalogs): void {
    echo '<div class="tnuc-toolbar"><h2>Updates available</h2><button type="button" class="button button-primary" id="tnuc-update-selected" disabled>Update selected plugins</button></div>';
    echo '<div class="tnuc-table-scroll"><table class="widefat striped"><thead><tr><td class="check-column"><input type="checkbox" id="tnuc-select-all" aria-label="Select all eligible updates"></td><th scope="col">Plugin</th><th scope="col">Installed</th><th scope="col">Available</th><th scope="col">Action</th></tr></thead><tbody>';
    foreach ($updates as $key => $item) {
        $release = $item['update']; $eligible = tnuc_hub_eligible($item, 'update') && tnuc_hub_compatibility($release) === '' && wp_is_file_mod_allowed('tnuc');
        $name = $item['entry']['name']; $current = $item['match']['data']['Version'] ?? '';
        echo '<tr><th class="check-column">';
        if ($eligible) { echo '<input type="checkbox" name="tnuc-selected" value="' . esc_attr($key) . '" aria-label="Update ' . esc_attr($name) . '">'; }
        echo '</th><td><strong>' . esc_html($name) . '</strong><br><span class="description">' . esc_html($item['catalog'] === 'other' ? 'Other' : ($item['catalog'] === 'controller' ? 'Controller' : ($catalogs[$item['catalog']]['name'] ?? $item['catalog']))) . '</span></td><td>' . esc_html($current) . '</td><td>';
        if (!empty($release['notes'])) { echo '<a href="' . esc_url($release['notes']) . '" target="_blank" rel="noopener noreferrer">' . esc_html($release['version']) . '<span class="screen-reader-text"> release notes (opens in a new tab)</span></a>'; }
        else { echo esc_html($release['version']); }
        $issue = $item['issue'] ?: tnuc_hub_compatibility($release);
        if ($issue) { echo '<p class="tnuc-warning">' . esc_html($issue) . '</p>'; }
        echo '</td><td>';
        if (!$eligible && ($release['source'] ?? '') === 'native') { echo '<a href="' . esc_url(self_admin_url('plugins.php')) . '">Update in WordPress Plugins</a>'; }
        else { echo esc_html($release['source'] === 'native' ? 'WordPress' : ($release['source'] === 'mirror' ? 'GitHub mirror' : 'GitHub release')); }
        echo '</td></tr>';
    }
    if (!$updates) { echo '<tr><td colspan="5">No updates are available in the selected catalogues or Other.</td></tr>'; }
    echo '</tbody></table></div>';
}

function tnuc_hub_catalogue_choices(array $catalogs, array $selected): array {
    $choices = [];
    foreach ($selected as $catalog_id) { if (isset($catalogs[$catalog_id])) { $choices[$catalog_id] = $catalogs[$catalog_id]['name']; } }
    $choices['other'] = 'Other installed plugins';
    return $choices;
}

function tnuc_hub_render_catalogue(array $catalogs, array $selected, array $items): void {
    $can_select = current_user_can(is_multisite() ? 'manage_network_options' : 'manage_options');
    echo '<section class="tnuc-subscriptions" aria-labelledby="tnuc-subscriptions-title"><h2 id="tnuc-subscriptions-title">Catalogue subscriptions</h2><p>Select one edition from each collection. Changing editions does not install or remove plugins.</p>';
    echo '<div class="tnuc-subscription-fields">';
    foreach (['techn' => 'Techn', 'alphasys' => 'AlphaSys', 'fusion' => 'Fusion'] as $collection => $label) {
        echo '<label>' . esc_html($label) . '<select data-subscription="' . esc_attr($collection) . '"' . ($can_select ? '' : ' disabled') . '><option value="">Not subscribed</option>';
        foreach ($catalogs as $id => $catalog) {
            if ($catalog['collection'] !== $collection) { continue; }
            $archived = $catalog['status'] === 'archived' && ($selected[$collection] ?? '') !== $id;
            echo '<option value="' . esc_attr($id) . '"' . selected($selected[$collection] ?? '', $id, false) . ($archived ? ' disabled' : '') . '>' . esc_html($catalog['name'] . ' · ' . ucwords(str_replace('_', ' ', $catalog['status']))) . '</option>';
        }
        echo '</select></label>';
    }
    if ($can_select) { echo '<button type="button" class="button button-primary" data-subscribe>Save catalogues</button>'; }
    echo '</div></section>';
    $choices = tnuc_hub_catalogue_choices($catalogs, $selected);
    $requested = sanitize_key(wp_unslash($_GET['catalog'] ?? ''));
    $catalog_id = isset($choices[$requested]) ? $requested : (array_key_first($choices) ?? 'other');
    echo '<div class="tnuc-catalogue-toolbar"><div><h2>' . esc_html($choices[$catalog_id]) . '</h2>';
    if ($catalog_id !== 'other' && !empty($catalogs[$catalog_id]['notice'])) { echo '<p class="tnuc-catalogue-notice">' . esc_html($catalogs[$catalog_id]['notice']) . '</p>'; }
    echo '</div><form method="get" action="' . esc_url(is_multisite() ? network_admin_url('plugins.php') : admin_url('plugins.php')) . '"><input type="hidden" name="page" value="tnuc"><input type="hidden" name="tab" value="catalogue"><label for="tnuc-catalog">Catalogue</label> <select id="tnuc-catalog" name="catalog">';
    foreach ($choices as $id => $name) { echo '<option value="' . esc_attr($id) . '"' . selected($catalog_id, $id, false) . '>' . esc_html($name) . '</option>'; }
    echo '</select> <button type="submit" class="button">Select</button></form></div>';
    $groups = ['active' => [], 'installed' => [], 'available' => [], 'beta' => [], 'licensed' => []];
    foreach ($items as $key => $item) {
        if ($item['catalog'] !== $catalog_id) { continue; }
        $match = $item['match']; $entry = $item['entry'];
        $active = $match && (is_multisite() ? is_plugin_active_for_network($match['file']) : is_plugin_active($match['file']));
        $group = $active ? 'active' : ($match ? 'installed' : (!empty($entry['beta']) ? 'beta' : (($entry['native_source'] ?? '') === 'vendor' && !$item['release'] ? 'licensed' : 'available')));
        $groups[$group][$key] = $item;
    }
    foreach (['active' => 'Active', 'installed' => 'Installed', 'available' => 'Available', 'beta' => 'Beta', 'licensed' => 'Licensed'] as $group => $label) {
        if (!$groups[$group]) { continue; }
        echo '<section class="tnuc-catalogue-group"><h2>' . esc_html($label) . '</h2><div class="tnuc-grid">';
        foreach ($groups[$group] as $item) { tnuc_hub_render_card($item); }
        echo '</div></section>';
    }
    if (!array_filter($groups)) { echo '<p>No plugins in this catalogue.</p>'; }
}

function tnuc_hub_render_card(array $item): void {
    $entry = $item['entry']; $match = $item['match']; $release = $item['release']; $update = $item['update']; $key = $item['key'];
    echo '<article class="tnuc-card"><div class="tnuc-card-content">';
    if (!empty($entry['role'])) { echo '<p class="tnuc-role">' . esc_html($entry['role']) . '</p>'; }
    echo '<h3>' . esc_html($entry['name']) . '</h3><p class="tnuc-card-description">' . esc_html($entry['description']) . '</p>';
    $latest = $release['version'] ?? ($update['version'] ?? '');
    $details = ($match ? 'Installed ' . ($match['data']['Version'] ?? '') : 'Not installed') . ($latest !== '' ? ' · Latest ' . $latest : '');
    echo '<p class="description">' . esc_html($details) . '</p>';
    if ($match && !$release) {
        $requirements = [];
        if (!empty($match['data']['RequiresWP'])) { $requirements[] = 'WordPress ' . $match['data']['RequiresWP'] . '+'; }
        if (!empty($match['data']['RequiresPHP'])) { $requirements[] = 'PHP ' . $match['data']['RequiresPHP'] . '+'; }
        if ($requirements) { echo '<p class="description">' . esc_html(implode(' · ', $requirements)) . '</p>'; }
    }
    if (!empty($item['conflict'])) { echo '<p class="tnuc-warning">Plugin identity conflicts with another selected catalogue or the installed plugin. Review before updating.</p>'; }
    if (!empty($item['issue'])) { echo '<p class="tnuc-warning">' . esc_html($item['issue']) . '</p>'; }
    if ($release) {
        $requirements = [];
        if (!empty($release['requires'])) { $requirements[] = 'WordPress ' . $release['requires'] . '+'; }
        if (!empty($release['requires_php'])) { $requirements[] = 'PHP ' . $release['requires_php'] . '+'; }
        if ($requirements) { echo '<p class="description">' . esc_html(implode(' · ', $requirements)) . '</p>'; }
    }
    echo '<div class="tnuc-card-actions">';
    if (!$match && tnuc_hub_eligible($item, 'install') && tnuc_hub_compatibility($release) === '') { echo '<button type="button" class="button button-primary" data-install="' . esc_attr($key) . '" data-kind="install"' . (wp_is_file_mod_allowed('tnuc') ? '' : ' disabled') . '>Install</button>'; }
    if ($match && tnuc_hub_eligible($item, 'update') && tnuc_hub_compatibility($update) === '') { echo '<button type="button" class="button button-primary" data-install="' . esc_attr($key) . '" data-kind="update"' . (wp_is_file_mod_allowed('tnuc') ? '' : ' disabled') . '>Update</button>'; }
    elseif ($match && $update && ($update['source'] ?? '') === 'native') { echo '<a href="' . esc_url(self_admin_url('plugins.php')) . '">Update in WordPress Plugins</a>'; }
    if (!$match && !$release && ($entry['native_source'] ?? '') === 'vendor') { echo '<a class="button" href="' . esc_url(self_admin_url('plugin-install.php?tab=upload')) . '">Upload licensed ZIP</a>'; }
    if (!$match && !$release && ($entry['native_source'] ?? '') === 'wordpress.org' && !empty($entry['slug'])) { echo '<a class="button" href="' . esc_url(self_admin_url('plugin-install.php?tab=plugin-information&plugin=' . rawurlencode($entry['slug']))) . '">Install from WordPress</a>'; }
    if ($match && $match['file'] !== plugin_basename(TNUC_FILE)) {
        $active = is_multisite() ? is_plugin_active_for_network($match['file']) : is_plugin_active($match['file']);
        $action = $active ? 'deactivate' : 'activate';
        echo '<button type="button" class="button" data-plugin-action="' . esc_attr($action) . '" data-plugin-id="' . esc_attr($key) . '">' . esc_html((is_multisite() ? 'Network ' : '') . ($active ? 'Deactivate' : 'Activate')) . '</button>';
        if (!$active && current_user_can('delete_plugins')) { echo '<button type="button" class="button-link-delete" data-plugin-action="delete" data-plugin-id="' . esc_attr($key) . '">Delete</button>'; }
    }
    if (!empty($release['notes'])) { echo '<a href="' . esc_url($release['notes']) . '" target="_blank" rel="noopener noreferrer">Release notes<span class="screen-reader-text"> (opens in a new tab)</span></a>'; }
    echo '</div>';
    if (!empty($entry['beta'])) { echo '<span class="tnuc-beta">Beta</span>'; }
    echo '</div></article>';
}

function tnuc_hub_dispatch(string $operation, array $input) {
    if (!tnuc_authorised()) { return new WP_Error('permission', 'You cannot manage plugin updates.'); }
    if ($operation === 'view') {
        $previous_tab = $_GET['tab'] ?? null; $previous_catalog = $_GET['catalog'] ?? null;
        $_GET['tab'] = sanitize_key($input['tab'] ?? 'installed');
        $_GET['catalog'] = sanitize_key($input['catalog'] ?? '');
        ob_start();
        try { tnuc_hub_render_admin(); $html = ob_get_contents(); }
        finally { ob_end_clean(); if ($previous_tab === null) { unset($_GET['tab']); } else { $_GET['tab'] = $previous_tab; } if ($previous_catalog === null) { unset($_GET['catalog']); } else { $_GET['catalog'] = $previous_catalog; } }
        return ['html' => $html];
    }
    if ($operation === 'save_subscriptions') { return tnuc_save_catalog_selection((array) ($input['catalogs'] ?? [])); }
    if ($operation === 'check') { return tnuc_hub_check(); }
    if ($operation === 'start') { return tnuc_hub_start_batch((array) ($input['ids'] ?? []), sanitize_key($input['kind'] ?? 'update')); }
    if ($operation === 'step') { return tnuc_hub_step_batch(sanitize_text_field($input['job'] ?? '')); }
    if ($operation === 'status') { return ['batch' => tnuc_hub_batch()]; }
    if ($operation === 'plugin_action') { return tnuc_hub_plugin_action(sanitize_text_field($input['plugin_id'] ?? ''), sanitize_key($input['plugin_action'] ?? '')); }
    return new WP_Error('operation', 'Unknown Plugin Catalogue action.');
}

function tnuc_hub_ajax(): void {
    if (!check_ajax_referer('tnuc_hub', 'nonce', false)) { wp_send_json_error(['message' => 'Your session expired. Reload the page and try again.'], 403); }
    $operation = sanitize_key(wp_unslash($_POST['operation'] ?? ''));
    $input = wp_unslash($_POST);
    $result = tnuc_hub_dispatch($operation, $input);
    tnuc_clean_ajax_output();
    if (is_wp_error($result)) { wp_send_json_error(['message' => $result->get_error_message()], 400); }
    wp_send_json_success($result);
}
