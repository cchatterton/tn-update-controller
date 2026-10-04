<?php
if (!defined('ABSPATH')) { exit; }
/** The manual operation completes in one public catalogue request. */
function tnuc_begin_scan(bool $full = false) { return tnuc_refresh(); }
function tnuc_scan_step(string $id) {
    return new WP_Error('scan_retired', 'Repository scanning has been replaced by the published catalogue. Click Check for updates again.');
}

/** Only the verified controller update is listed until that installed version is current. */
function tnuc_controller_update_pending(): bool {
    if ((tnuc_get('check')['status'] ?? '') !== 'controller_update') { return false; }
    $entry = tnuc_catalogue()['plugins']['tn-update-controller'] ?? null;
    $installed = tnuc_plugins()['tn-update-controller/tn-update-controller.php']['Version'] ?? TNUC_VERSION;
    return $entry && version_compare($entry['version'], $installed, '>');
}
