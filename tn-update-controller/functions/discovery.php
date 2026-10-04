<?php
if (!defined('ABSPATH')) { exit; }
/** Both controllers use the same owner index, with separate branded catalogue projections. */
function tnuc_begin_scan(bool $full = false) {
    if (!tnuc_authorised() || (defined('DOING_CRON') && DOING_CRON)) { return new WP_Error('manual_only', 'Use an authorised Check for updates action.'); }
    $scan = ghc_v2_begin('tn-update-controller', TNUC_VERSION);
    if (!is_wp_error($scan)) { tnuc_put('check', ['status'=>'running','last_attempt'=>time(),'job_id'=>$scan['id']]); }
    return $scan;
}
function tnuc_scan_step(string $id) {
    if (!tnuc_authorised() || (defined('DOING_CRON') && DOING_CRON)) { return new WP_Error('manual_only', 'Use an authorised Check for updates action.'); }
    $scan = ghc_v2_step($id);
    if (!is_wp_error($scan)) {
        $state = (array) tnuc_get('check');
        $state['status'] = $scan['status'] === 'complete' ? 'success' : $scan['status'];
        $state['error'] = in_array($scan['status'], ['partial','failed'], true) ? $scan['message'] : '';
        $state['retry_at'] = 0;
        $state['message'] = $scan['message'];
        if ($scan['status'] === 'complete') { $state['last_success'] = time(); }
        tnuc_put('check', $state);
    }
    return $scan;
}
/** Local database reads only. Preserve the last verified package when its next inspection fails. */
function tnuc_import_shared(): void {
    $catalogue = tnuc_catalogue(); $plugins = $catalogue['plugins'] ?? []; $before = $plugins;
    foreach (ghc_v2_rows() as $row) {
        foreach ($plugins as $id=>$entry) {
            if ($entry['repo'] === $row['repo'] && ($row['status'] === 'excluded' || ($row['status'] === 'verified' && $row['brand'] !== 'Techn') || $row['status'] === 'not_plugin')) { unset($plugins[$id]); }
        }
        if ($row['brand'] !== 'Techn' || !in_array($row['status'], ['verified','error'], true)) { continue; }
        $entry = json_decode($row['package_data'] ?? '', true);
        if (!is_array($entry)) { continue; }
        $entry['beta'] = $row['alpha_beta'] === 'beta';
        $candidate = $plugins; $candidate[$entry['id']] = $entry;
        $validated = tnuc_validate_catalogue(['schema'=>1,'published_at'=>gmdate('c'),'plugins'=>array_values($candidate)]);
        if (is_wp_error($validated)) { throw new RuntimeException($row['repo'].': '.$validated->get_error_message()); }
        $plugins = $validated['plugins'];
    }
    if ($plugins !== $before) { tnuc_store_catalogue(['published_at'=>gmdate('c'),'plugins'=>$plugins]); }
}

/** Only the verified controller update is listed until that installed version is current. */
function tnuc_controller_update_pending(): bool {
    if ((tnuc_get('check')['status'] ?? '') !== 'controller_update') { return false; }
    $entry = tnuc_catalogue()['plugins']['tn-update-controller'] ?? null;
    $installed = tnuc_plugins()['tn-update-controller/tn-update-controller.php']['Version'] ?? TNUC_VERSION;
    return $entry && version_compare($entry['version'], $installed, '>');
}
