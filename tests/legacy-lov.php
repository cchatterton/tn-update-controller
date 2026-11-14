<?php
/** Check the bounded installed author exception without changing WordPress state. */
define('ABSPATH', __DIR__ . '/');
define('TNUC_DIR', dirname(__DIR__) . '/tn-update-controller/');
function tnuc_get($key) { return $key === 'catalogue' ? ['plugins' => ['lov' => ['id'=>'lov','owner'=>'cchatterton','repo'=>'lov','file'=>'lov/lov.php','slug'=>'lov','asset'=>'lov.zip','author'=>'Techn']]] : []; }
function wp_strip_all_tags($text) { return strip_tags($text); }
require TNUC_DIR . 'functions/catalogue.php';
$entry = tnuc_registry()['lov'];
$cases = [
    ['Chris Chatterton', '1.4', true],
    ['Chris Chatterton', '1.4.1', false],
    ['Someone Else', '1.4', false],
    ['Techn', '1.4.2', true],
];
foreach ($cases as [$author, $version, $expected]) {
    $actual = tnuc_match($entry, ['lov/lov.php' => ['Author' => $author, 'Version' => $version, 'UpdateURI' => '']]);
    if ($actual !== $expected) { throw new RuntimeException("Mismatch for $author $version"); }
}
echo "Bounded List of Values legacy identity verified.\n";
