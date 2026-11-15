<?php
/** Standalone batch test using a local ZIP and fake WordPress upgrader. */
define('ABSPATH', __DIR__ . '/fixtures/');
define('TNUC_FILE', '/tmp/tn-update-controller/tn-update-controller.php');
define('HOUR_IN_SECONDS', 3600);
class WP_Error {
    private $message;
    public function __construct($code, $message) { $this->message = $message; }
    public function get_error_message() { return $this->message; }
}
class Automatic_Upgrader_Skin {}
class Plugin_Upgrader {
    public function __construct($skin) {}
    public function bulk_upgrade($files, $options = []) {
        $file = $files[0];
        $GLOBALS['installed'][$file]['Version'] = '1.1.0';
        return [$file => true];
    }
    public function install($package, $options = []) {
        $GLOBALS['installed']['mirror-plugin/mirror-plugin.php'] = ['Version' => '2.0.0'];
        return true;
    }
}
function is_wp_error($value) { return $value instanceof WP_Error; }
function tnuc_authorised($cap = 'update_plugins') { return true; }
function wp_is_file_mod_allowed($context) { return true; }
function tnuc_controller_update_pending() { return false; }
function tnuc_get($key, $default = []) { return $GLOBALS['options'][$key] ?? $default; }
function tnuc_put($key, $value) { $GLOBALS['options'][$key] = $value; }
function tnuc_lock($name, $time) { return 'lock'; }
function tnuc_unlock($name, $token) {}
function tnuc_plugins() { return $GLOBALS['installed']; }
function tnuc_hub_items() { return $GLOBALS['items']; }
function tnuc_hub_current_key($key) { return $key; }
function tnuc_hub_match($entry, $installed) { $file = $entry['file']; return isset($installed[$file]) ? ['file' => $file, 'data' => $installed[$file]] : []; }
function wp_generate_uuid4() { return uniqid('batch-', true); }
function get_current_user_id() { return 1; }
function get_filesystem_method() { return 'direct'; }
function wp_clean_plugins_cache($clear) {}
function sanitize_text_field($value) { return $value; }
function download_url($url, $timeout) { $path = tempnam(sys_get_temp_dir(), 'tnuc-package-'); copy($GLOBALS['package_zip'], $path); return $path; }
require dirname(__DIR__) . '/tn-update-controller/functions/catalog-batch.php';

function batch_assert($condition, $message) { if (!$condition) { throw new RuntimeException($message); } echo "PASS: $message\n"; }
$GLOBALS['options'] = [];
$GLOBALS['installed'] = ['native-plugin/native-plugin.php' => ['Version' => '1.0.0']];
$GLOBALS['items'] = [
    'other:native-plugin/native-plugin.php' => ['key' => 'other:native-plugin/native-plugin.php', 'entry' => ['file' => 'native-plugin/native-plugin.php', 'name' => 'Native Plugin'], 'match' => ['file' => 'native-plugin/native-plugin.php'], 'update' => ['source' => 'native', 'version' => '1.1.0', 'package' => 'https://vendor.example/update.zip'], 'release' => [], 'conflict' => false, 'issue' => ''],
];
batch_assert(tnuc_hub_eligible(array_merge($GLOBALS['items']['other:native-plugin/native-plugin.php'], ['conflict' => true]), 'update'), 'installed plugin identity metadata does not hide a usable update');
$job = tnuc_hub_start_batch(['other:native-plugin/native-plugin.php'], 'update');
batch_assert(!is_wp_error($job) && $job['status'] === 'running', 'native update batch starts');
$result = tnuc_hub_step_batch($job['id']);
batch_assert($result['status'] === 'complete' && $GLOBALS['installed']['native-plugin/native-plugin.php']['Version'] === '1.1.0', 'native provider update completes and verifies version');
$job = tnuc_hub_start_batch(['other:native-plugin/native-plugin.php'], 'update');
$result = tnuc_hub_step_batch($job['id']);
batch_assert($result['items'][0]['status'] === 'success', 'interrupted completed update is recognised on resume');

$zip_path = tempnam(sys_get_temp_dir(), 'tnuc-test-zip-');
$zip = new ZipArchive(); $zip->open($zip_path, ZipArchive::OVERWRITE); $zip->addFromString('mirror-plugin/mirror-plugin.php', "<?php\n/* Plugin Name: Mirror Plugin\nVersion: 2.0.0 */\n"); $zip->close();
$GLOBALS['package_zip'] = $zip_path;
$release = ['source' => 'mirror', 'version' => '2.0.0', 'sha256' => hash_file('sha256', $zip_path), 'url' => 'https://github.com/cchatterton/fusion-controller/releases/download/mirror-mirror-plugin-v2.0.0/mirror-plugin-2.0.0.zip', 'root' => 'mirror-plugin'];
$GLOBALS['items']['fusion-26:mirror-plugin'] = ['key' => 'fusion-26:mirror-plugin', 'entry' => ['file' => 'mirror-plugin/mirror-plugin.php', 'name' => 'Mirror Plugin'], 'match' => [], 'release' => $release, 'update' => [], 'conflict' => false, 'issue' => ''];
$job = tnuc_hub_start_batch(['fusion-26:mirror-plugin'], 'install');
$result = tnuc_hub_step_batch($job['id']);
batch_assert($result['status'] === 'complete' && $GLOBALS['installed']['mirror-plugin/mirror-plugin.php']['Version'] === '2.0.0', 'mirrored ZIP installs and verifies version');
$bad = $release; $bad['sha256'] = str_repeat('0', 64);
batch_assert(is_wp_error(tnuc_hub_download_verified($bad)), 'checksum mismatch blocks mirrored package');
@unlink($zip_path);
