<?php
/** Execute unmodified native helpers from a supplied Dolibarr functions.lib.php. */
$nativeSourcePath = $argv[1] ?? '';
if (!is_file($nativeSourcePath)) { throw new RuntimeException('Provide a pinned functions.lib.php source file'); }
$source = file_get_contents($nativeSourcePath);
foreach (array('getDolGlobalString', 'getDolGlobalInt', 'dol_strlen', 'price2num') as $name) {
	if (!preg_match('/^function '.preg_quote($name, '/').'\b.*?^\}/ms', $source, $match)) {
		throw new RuntimeException('Native helper missing: '.$name);
	}
	eval($match[0]);
}
$conf = (object) array('global' => (object) array('MAIN_MAX_DECIMALS_UNIT' => 5, 'MAIN_MAX_DECIMALS_TOT' => 2));
require __DIR__.'/importer_rep_test.php';
$langs = null;
$method = new ReflectionMethod(LmdbWurthPunchoutImporter::class, 'calculateVatAmount');
$importer = createRepImporter(new FakeRepDb());
foreach (array(2 => 0.01, 3 => 0.005) as $precision => $expected) {
	$conf->global->MAIN_MAX_DECIMALS_TOT = $precision;
	if (abs($method->invoke($importer, 0.025, 20) - $expected) > 0.0000001) {
		throw new RuntimeException('VAT calculation ignored native total precision');
	}
}
echo "Native monetary precision OK: ".$nativeSourcePath."\n";
