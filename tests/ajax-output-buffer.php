<?php
/** Verify that theme bootstrap output cannot prefix a controller JSON response. */
define('ABSPATH', __DIR__ . '/');
require dirname(__DIR__) . '/tn-update-controller/functions/operations.php';

ob_start();
$base_level = ob_get_level();
ob_start();
define('TNUC_AJAX_BUFFER_LEVEL', ob_get_level());
echo 'stray theme output';
ob_start();
echo 'nested upgrader output';

tnuc_clean_ajax_output();
if (ob_get_level() !== $base_level) {
    throw new RuntimeException('Controller output buffers were not closed.');
}
echo '{"success":true}';
$response = ob_get_clean();
if ($response !== '{"success":true}') {
    throw new RuntimeException('Controller response contains non-JSON output.');
}

echo "PASS: Controller AJAX output stays valid JSON.\n";
