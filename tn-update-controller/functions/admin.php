<?php
if (!defined('ABSPATH')) { exit; }
function tnuc_menu(): void {
    if (is_multisite() && !is_network_admin()) { return; }
    add_submenu_page('plugins.php', 'Techn Plugins', 'Techn Plugins', 'update_plugins', 'tnuc', 'tnuc_render_admin');
}
function tnuc_settings_link(array $links): array { array_unshift($links, '<a href="' . esc_url(tnuc_url()) . '">Techn Plugins</a>'); return $links; }
function tnuc_assets(string $hook): void {
    if ($hook !== 'plugins_page_tnuc') { return; }
    wp_enqueue_style('tnuc-admin', plugins_url('styles/admin.css', TNUC_FILE), [], TNUC_VERSION);
    wp_enqueue_script('tnuc-admin', plugins_url('scripts/admin.js', TNUC_FILE), [], TNUC_VERSION, true);
    // Consume only the one-time continuation created by the authorised row/form action.
    $continuation = get_transient('tnuc_continue_' . get_current_user_id());
    delete_transient('tnuc_continue_' . get_current_user_id());
    wp_localize_script('tnuc-admin', 'tnucAdmin', ['continueScan' => $continuation ?: '', 'url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('tnuc_action'), 'names' => array_map(static fn($entry) => $entry['name'], tnuc_registry())]);
}
function tnuc_check_summary(): string {
    $s = tnuc_get('check');
    if (($s['status'] ?? '') === 'controller_update') { return tnuc_controller_update_pending() ? ($s['message'] ?? 'Update the controller first, then check again.') : 'Controller updated. Check for updates to refresh the other plugins.'; }
    if (($s['status'] ?? '') === 'partial') { return (!empty($s['known_checked']) ? 'Known plugins checked. ' : 'Check incomplete. ') . ($s['error'] ?? 'Verified results are retained. Check again to resume.'); }
    if (($s['status'] ?? '') === 'failed') { return 'Last check failed. ' . (!empty($s['last_success']) ? 'Showing results from ' . wp_date('j M Y, H:i', $s['last_success']) . '.' : 'No successful check yet.'); }
    if (($s['status'] ?? '') === 'running') { return ($s['last_attempt'] ?? 0) < time() - 60 ? 'Previous check was interrupted. Check again to recover.' : 'A catalogue check is running.'; }
    return !empty($s['last_success']) ? 'Last checked ' . wp_date('j M Y, H:i', $s['last_success']) : 'Never checked. Check the catalogue to discover available releases.';
}
function tnuc_form_start(string $operation): void {
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="tnuc_action"><input type="hidden" name="operation" value="' . esc_attr($operation) . '">'; wp_nonce_field('tnuc_action');
}
function tnuc_render_admin(): void {
    if (!tnuc_authorised()) { wp_die('You cannot manage plugin updates.'); }
    $tab = sanitize_key($_GET['tab'] ?? 'installed'); if (!in_array($tab, ['installed','catalogue','settings'], true)) { $tab = 'installed'; }
    $registry = array_filter(tnuc_registry(), 'tnuc_domain_allowed'); $releases = tnuc_catalogue()['plugins'] ?? []; $plugins = tnuc_plugins();
    $installed = array_filter($registry, static fn($e) => isset($plugins[$e['file']]));
    $updates = array_filter($releases, static fn($e) => tnuc_domain_allowed($e) && tnuc_match($e, $plugins) && version_compare($e['version'], $plugins[$e['file']]['Version'], '>'));
    if (tnuc_controller_update_pending()) { $updates = array_intersect_key($updates, ['tn-update-controller'=>true]); }
    echo '<div class="wrap tnuc-wrap"><h1>Techn Plugins</h1><div id="tnuc-view">';
    $notice = get_transient('tnuc_notice_' . get_current_user_id());
    if ($notice) { delete_transient('tnuc_notice_' . get_current_user_id()); }
    if ($notice && $notice['error']) { echo '<div class="notice ' . ($notice['error'] ? 'notice-error' : 'notice-success') . '"><p>' . esc_html($notice['message']) . '</p></div>'; }
    echo '<header class="tnuc-header"><span class="tnuc-version" aria-label="Version ' . esc_attr(TNUC_VERSION) . '">v' . esc_html(TNUC_VERSION) . '</span><p class="tnuc-eyebrow">Techn / Plugin library</p><h2>Your plugins. One place.</h2><p>Discover released Techn plugins directly from GitHub.</p><div class="tnuc-header-bottom"><span>' . count($installed) . ' installed · ' . count($updates) . ' updates available</span><button class="button tnuc-primary" data-check="">Check for updates</button></div></header>';
    echo '<div class="tnuc-status"><span>' . esc_html(tnuc_check_summary()) . '</span><span>' . 'Manual checks only' . '</span></div>';
    echo '<nav class="nav-tab-wrapper" aria-label="Plugin library">';
    foreach (['installed'=>'Updates available','catalogue'=>'Catalogue','settings'=>'Settings'] as $key=>$label) { echo '<a class="nav-tab ' . ($key === $tab ? 'nav-tab-active' : '') . '" href="' . esc_url(tnuc_url($key)) . '">' . esc_html($label) . '</a>'; }
    echo '</nav><div id="tnuc-feedback" role="status" aria-live="polite"></div>';
    $batch = tnuc_get('batch');
    if (($batch['status'] ?? '') === 'running') {
        echo '<p class="tnuc-resume"><button class="button" data-resume="' . esc_attr($batch['id']) . '">Resume updates</button></p>';
    } elseif (($batch['status'] ?? '') === 'partial') {
        echo '<p class="tnuc-resume">Some plugins could not be updated. <button class="button-link" data-results="">View results</button></p>';
    }
    if ($tab === 'settings') { tnuc_render_settings(); }
    elseif ($tab === 'catalogue') { tnuc_render_catalogue($registry, $releases, $plugins); }
    else {
        echo '<div class="tnuc-toolbar"><h2>Updates available</h2><button class="button button-primary" id="tnuc-update-selected" disabled>Update selected plugins</button></div><div class="tnuc-table-scroll"><table class="widefat striped"><thead><tr><td class="check-column"><input type="checkbox" id="tnuc-select-all" aria-label="Select all eligible updates"></td><th scope="col">Plugin</th><th scope="col">Installed</th><th scope="col">Available</th><th scope="col">GitHub</th></tr></thead><tbody>';
        foreach ($installed as $id => $e) {
            if (!isset($updates[$id])) { continue; }
            $release = $releases[$id] ?? null;
            $issue = !tnuc_match($e, $plugins) ? 'Identity conflict: review before updating.' : ($release ? tnuc_compatibility($release) : '');
            $can_update = isset($updates[$id]) && !$issue && tnuc_authorised() && wp_is_file_mod_allowed('tnuc');
            $reason = $issue ?: (!$release ? 'Not checked' : (!isset($updates[$id]) ? 'Up to date' : (!$can_update ? 'Updates are disabled on this site.' : '')));
            echo '<tr><th class="check-column"><input type="checkbox" name="tnuc-selected" value="' . esc_attr($id) . '" aria-label="Update ' . esc_attr($e['name']) . '"' . (!$can_update ? ' disabled aria-describedby="tnuc-reason-' . esc_attr($id) . '"' : '') . '></th><td><strong>' . esc_html($e['name']) . '</strong> ' . tnuc_beta_badge($release ?? $e) . '<br><span class="description">' . (is_plugin_active($e['file']) || is_plugin_active_for_network($e['file']) ? 'Active' : 'Inactive') . '</span></td><td>' . esc_html($plugins[$e['file']]['Version']) . '</td><td>';
            if ($release) {
                echo '<a href="' . esc_url(tnuc_release_url($release)) . '" target="_blank" rel="noopener noreferrer" aria-label="' . esc_attr($e['name'] . ' ' . $release['version'] . ' release notes (opens in a new tab)') . '">' . esc_html($release['version']) . '</a>';
            } else { echo '—'; }
            if ($reason) { echo '<p id="tnuc-reason-' . esc_attr($id) . '" class="' . ($issue ? 'tnuc-warning' : 'description') . '">' . esc_html($reason) . '</p>'; }
            echo '</td><td><a href="' . esc_url('https://github.com/' . $e['owner'] . '/' . $e['repo']) . '" target="_blank" rel="noopener noreferrer">GitHub<span class="screen-reader-text"> (opens in a new tab)</span></a></td></tr>';
        }
        echo '</tbody></table></div>';
        if (!$updates) { echo '<p>No updates available in the cached catalogue.</p>'; }
    }
    echo '<noscript><p>Use the native Plugins screen to install updates. Checking remains available below without JavaScript.</p>'; tnuc_form_start('check'); echo '<button class="button">Check catalogue</button></form></noscript></div>';
    tnuc_render_dialog();
    echo '</div>';
}
function tnuc_render_dialog(): void {
    echo '<dialog id="tnuc-dialog" class="tnuc-dialog" aria-labelledby="tnuc-dialog-title" aria-describedby="tnuc-dialog-message"><h2 id="tnuc-dialog-title" tabindex="-1">Checking for updates</h2><div class="tnuc-activity"><span class="spinner is-active" aria-hidden="true"></span><p id="tnuc-dialog-message" role="status" aria-live="polite"></p></div><div id="tnuc-progress-area" hidden><progress id="tnuc-progress" max="1" value="0" aria-label="Plugins processed"></progress><p id="tnuc-current"></p></div><details id="tnuc-failures" hidden><summary>Failed plugins</summary><ul></ul></details><div class="tnuc-dialog-actions"><button type="button" class="button" id="tnuc-retry" hidden>Retry</button><button type="button" class="button button-primary" id="tnuc-close" disabled>Close</button></div></dialog>';
}
function tnuc_details_link(array $entry): void { echo '<a href="' . esc_url(tnuc_release_url($entry)) . '" target="_blank" rel="noopener noreferrer">Release notes<span class="screen-reader-text"> (opens in a new tab)</span></a>'; }
function tnuc_catalogue_version_label(array $entry, array $plugins, bool $installed): string {
    $requirements = 'WordPress ' . $entry['requires'] . '+ · PHP ' . $entry['requires_php'] . '+';
    if (!$installed || !isset($plugins[$entry['file']])) {
        return 'Latest ' . $entry['version'] . ' · ' . $requirements;
    }
    $installed_version = $plugins[$entry['file']]['Version'] ?: 'unknown';
    return 'Installed ' . $installed_version . ' · Latest ' . $entry['version'] . ' · ' . $requirements;
}
function tnuc_render_catalogue(array $registry, array $releases, array $plugins): void {
    if (!$releases) { echo '<p class="tnuc-intro">This library lists the plugins recognised by this controller. Refresh the catalogue to load verified releases and enable installation.</p>'; }
    $labels = ['active' => 'Active', 'installed' => 'Installed', 'available' => 'Available', 'beta' => 'Beta'];
    foreach (tnuc_catalogue_groups($registry, $releases, $plugins) as $group => $entries) {
    echo '<section class="tnuc-catalogue-group" data-catalogue-group="' . esc_attr($group) . '" aria-labelledby="tnuc-group-' . esc_attr($group) . '"' . (!$entries ? ' hidden' : '') . '><h2 id="tnuc-group-' . esc_attr($group) . '">' . esc_html($labels[$group]) . '</h2><div class="tnuc-grid">';
    foreach ($entries as $id=>$identity) {
        $e = $releases[$id] ?? $identity; $has = isset($plugins[$e['file']]);
        $conflict = $has && !tnuc_match($e, $plugins); $issue = $conflict ? 'Installed plugin identity needs review.' : (isset($releases[$id]) ? tnuc_compatibility($e) : 'Check the catalogue to load this release.');
        echo '<article class="tnuc-card" data-search="' . esc_attr(strtolower($e['name'] . ' ' . $e['description'])) . '"><div class="tnuc-card-content"><h3>' . esc_html($e['name']) . '</h3><p class="tnuc-card-description">' . esc_html($e['description']) . '</p><p class="description">' . (isset($releases[$id]) ? esc_html(tnuc_catalogue_version_label($e, $plugins, $has)) : 'Release not checked') . '</p>';
        if ($issue) { echo '<p class="tnuc-warning">' . esc_html($issue) . '</p>'; }
        echo '<div class="tnuc-card-actions">';
        if (!$has) { echo '<button class="button button-primary" data-install="' . esc_attr($id) . '" data-kind="install"' . ($issue || !tnuc_authorised('install_plugins') || !wp_is_file_mod_allowed('tnuc') ? ' disabled' : '') . '>Install</button>'; }
        tnuc_card_actions($e, $has, $conflict);
        if (isset($releases[$id])) { tnuc_details_link($e); }
        echo '</div>' . tnuc_beta_badge($e) . '</div></article>';
    }
    echo '</div></section>';
    }
}
function tnuc_render_settings(): void {
    echo '<p>Both controllers share the github-cchatterton repository table: author, released version, local version, installed state and alpha/beta. Each click checks this controller first and stops if its update is available. When current, it checks other repositories; unchanged packages reuse their verified metadata.</p>';
    echo '<h2>Manual checks only</h2><p>Choose Check for updates to refresh available plugins and installed update status together. Keep this window open until the check completes. No scheduled checks or controller waiting periods apply.</p><p>For frequent release testing, configure GITHUB_CCHATTERTON_TOKEN in wp-config.php to check up to 100 repositories and their releases in one GitHub request. Without a token, GitHub requires a separate public API request per repository and applies its own anonymous quota.</p>';

}
