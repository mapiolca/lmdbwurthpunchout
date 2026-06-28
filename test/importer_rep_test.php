<?php
/**
 * Lightweight REP pre-import examples.
 *
 * This test uses minimal Dolibarr stubs and does not mutate real data.
 */

if (!defined('MAIN_DB_PREFIX')) {
	define('MAIN_DB_PREFIX', 'llx_');
}
if (!defined('LOG_ERR')) {
	define('LOG_ERR', 3);
}
if (!function_exists('price2num')) {
	function price2num($value, $mode = '')
	{
		return (string) ((float) $value);
	}
}
if (!function_exists('dol_now')) {
	function dol_now()
	{
		return 1700000000;
	}
}
if (!function_exists('dol_trunc')) {
	function dol_trunc($value, $length)
	{
		return substr($value, 0, $length);
	}
}
if (!function_exists('getDolGlobalString')) {
	function getDolGlobalString($key, $default = '')
	{
		$defaults = array(
			'LMDBWURTHPUNCHOUT_CURRENCY' => 'EUR',
			'LMDBWURTHPUNCHOUT_CXML_IMPORT_REP' => '1',
			'LMDBWURTHPUNCHOUT_CXML_INFER_SHIPPING_FROM_TAX_DELTA' => '1',
			'LMDBWURTHPUNCHOUT_DEFAULT_VAT' => '20',
		);
		return $defaults[$key] ?? $default;
	}
}
if (!function_exists('getDolGlobalInt')) {
	function getDolGlobalInt($key, $default = 0)
	{
		return (int) getDolGlobalString($key, (string) $default);
	}
}
if (!function_exists('dol_syslog')) {
	function dol_syslog($message, $level = 0)
	{
	}
}

$stubRoot = sys_get_temp_dir().'/lmdbwurthpunchout_dolibarr_stubs';
$stubFiles = array(
	'/fourn/class/fournisseur.commande.class.php' => '<?php class CommandeFournisseur { const STATUS_DRAFT = 0; }',
	'/fourn/class/fournisseur.product.class.php' => '<?php class ProductFournisseur {}',
	'/product/class/product.class.php' => '<?php class Product {}',
	'/societe/class/societe.class.php' => '<?php class Societe {}',
	'/core/class/commonobject.class.php' => '<?php class CommonObject {}',
);
foreach ($stubFiles as $path => $content) {
	$fullPath = $stubRoot.$path;
	if (!is_dir(dirname($fullPath))) {
		mkdir(dirname($fullPath), 0777, true);
	}
	file_put_contents($fullPath, $content);
}
if (!defined('DOL_DOCUMENT_ROOT')) {
	define('DOL_DOCUMENT_ROOT', $stubRoot);
}

require_once __DIR__.'/../class/lmdbwurthpunchoutimporter.class.php';

class FakeRepResult
{
	/** @var array<int,object> */
	private $rows;

	/** @var int */
	private $position = 0;

	/**
	 * @param array<int,object> $rows Rows
	 */
	public function __construct($rows)
	{
		$this->rows = $rows;
	}

	/**
	 * @return object|false
	 */
	public function fetch()
	{
		if (!isset($this->rows[$this->position])) {
			return false;
		}
		$row = $this->rows[$this->position];
		$this->position++;
		return $row;
	}
}

class FakeRepDb
{
	/** @var array<int,array{rowid:int,entity:int,vendor_ref:string,amount_ht:float,label:string,status:string}> */
	public $repmap = array();

	/** @var int */
	private $nextId = 1;

	/**
	 * @param string $value Value
	 * @return string
	 */
	public function escape($value)
	{
		return addslashes($value);
	}

	/**
	 * @param int $date Date
	 * @return string
	 */
	public function idate($date)
	{
		return date('Y-m-d H:i:s', $date);
	}

	/**
	 * @param string $sql SQL query
	 * @return FakeRepResult|bool
	 */
	public function query($sql)
	{
		if (strpos($sql, 'SELECT entity, vendor_ref, amount_ht, status') === 0) {
			return $this->selectRepRules($sql);
		}
		if (strpos($sql, 'SELECT rowid FROM '.MAIN_DB_PREFIX.'lmdbwurthpunchout_repmap') === 0) {
			return $this->selectRepRuleRowId($sql);
		}
		if (strpos($sql, 'INSERT INTO '.MAIN_DB_PREFIX.'lmdbwurthpunchout_repmap') === 0) {
			return $this->insertRepRule($sql);
		}
		if (strpos($sql, 'UPDATE '.MAIN_DB_PREFIX.'lmdbwurthpunchout_repmap SET') === 0) {
			return $this->updateRepRule($sql);
		}

		throw new RuntimeException('Unexpected SQL in REP test: '.$sql);
	}

	/**
	 * @param FakeRepResult $result Result
	 * @return object|false
	 */
	public function fetch_object($result)
	{
		return $result->fetch();
	}

	/**
	 * @param string $sql SQL query
	 * @return FakeRepResult
	 */
	private function selectRepRules($sql)
	{
		$entities = array();
		if (preg_match('/entity IN \\(([^)]*)\\)/', $sql, $matches)) {
			foreach (explode(',', $matches[1]) as $entity) {
				$entities[] = (int) trim($entity);
			}
		}
		$statuses = array();
		if (preg_match('/status IN \\(([^)]*)\\)/', $sql, $matches)) {
			foreach (explode(',', $matches[1]) as $status) {
				$statuses[] = trim($status, " '");
			}
		}

		$rows = array();
		foreach ($this->repmap as $rule) {
			if (!in_array((int) $rule['entity'], $entities, true)) {
				continue;
			}
			if (!empty($statuses) && !in_array($rule['status'], $statuses, true)) {
				continue;
			}
			$rows[] = (object) $rule;
		}

		return new FakeRepResult($rows);
	}

	/**
	 * @param string $sql SQL query
	 * @return FakeRepResult
	 */
	private function selectRepRuleRowId($sql)
	{
		preg_match('/entity = ([0-9]+)/', $sql, $entityMatches);
		preg_match("/vendor_ref = '([^']*)'/", $sql, $refMatches);
		$entity = (int) ($entityMatches[1] ?? 0);
		$vendorRef = stripslashes((string) ($refMatches[1] ?? ''));

		foreach ($this->repmap as $rule) {
			if ((int) $rule['entity'] === $entity && $rule['vendor_ref'] === $vendorRef) {
				return new FakeRepResult(array((object) array('rowid' => $rule['rowid'])));
			}
		}

		return new FakeRepResult(array());
	}

	/**
	 * @param string $sql SQL query
	 * @return bool
	 */
	private function insertRepRule($sql)
	{
		if (!preg_match("/VALUES \\(([0-9]+), '([^']*)', ([^,]+), '([^']*)', '([^']*)'/", $sql, $matches)) {
			return false;
		}

		$this->repmap[] = array(
			'rowid' => $this->nextId,
			'entity' => (int) $matches[1],
			'vendor_ref' => stripslashes($matches[2]),
			'amount_ht' => (float) $matches[3],
			'label' => stripslashes($matches[4]),
			'status' => stripslashes($matches[5]),
		);
		$this->nextId++;

		return true;
	}

	/**
	 * @param string $sql SQL query
	 * @return bool
	 */
	private function updateRepRule($sql)
	{
		preg_match('/amount_ht = ([^,]+)/', $sql, $amountMatches);
		preg_match("/label = '([^']*)'/", $sql, $labelMatches);
		preg_match("/status = '([^']*)'/", $sql, $statusMatches);
		preg_match('/rowid = ([0-9]+)/', $sql, $rowidMatches);
		$rowid = (int) ($rowidMatches[1] ?? 0);

		foreach ($this->repmap as $index => $rule) {
			if ((int) $rule['rowid'] !== $rowid) {
				continue;
			}
			$this->repmap[$index]['amount_ht'] = (float) ($amountMatches[1] ?? 0);
			$this->repmap[$index]['label'] = stripslashes((string) ($labelMatches[1] ?? ''));
			$this->repmap[$index]['status'] = stripslashes((string) ($statusMatches[1] ?? 'pending'));
			return true;
		}

		return false;
	}
}

class FakeRepSession
{
	public $protocol = 'CXML';
	public $entity = 1;
	public $basket_payload = '';

	/** @var array<int,array<string,mixed>> */
	private $lines = array();

	/**
	 * @param array<string,mixed>            $basket Basket payload
	 * @param array<int,array<string,mixed>> $lines  Lines
	 */
	public function __construct($basket, $lines)
	{
		$this->basket_payload = json_encode($basket, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		$this->lines = $lines;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function fetchLines()
	{
		return $this->lines;
	}
}

/**
 * @param FakeRepDb $db Database
 * @return LmdbWurthPunchoutImporter
 */
function createRepImporter($db)
{
	return new LmdbWurthPunchoutImporter($db);
}

/**
 * @param LmdbWurthPunchoutImporter $importer Importer
 * @param FakeRepSession            $session  Session
 * @return void
 */
function assertRepRulesReady($importer, $session)
{
	$method = new ReflectionMethod($importer, 'assertCxmlRepRulesReady');
	$method->invoke($importer, $session);
}

/**
 * @param float $qty       Quantity
 * @param float $unitPrice Unit price
 * @param float $taxAmount Tax amount
 * @return array<string,mixed>
 */
function repLine($qty, $unitPrice, $taxAmount)
{
	return array(
		'vendor_ref' => '5964590680',
		'label' => 'Assortiment de chevilles',
		'qty' => $qty,
		'unit_price_ht' => $unitPrice,
		'vat_rate' => 20.0,
		'tax_amount' => $taxAmount,
	);
}

/**
 * @param float $total    Total amount
 * @param float $shipping Shipping amount
 * @param float $tax      Header tax amount
 * @return array<string,mixed>
 */
function repBasket($total, $shipping, $tax)
{
	return array(
		'header' => array(
			'total' => array('has_value' => true, 'amount' => $total, 'currency' => 'EUR'),
			'shipping' => array('has_value' => true, 'amount' => $shipping, 'currency' => 'EUR'),
			'tax' => array('has_value' => true, 'amount' => $tax, 'currency' => 'EUR'),
		),
	);
}

$line = repLine(1, 115.0, 23.0);
$session = new FakeRepSession(repBasket(115.0, 0.0, 24.40), array($line));
$db = new FakeRepDb();
try {
	assertRepRulesReady(createRepImporter($db), $session);
	throw new RuntimeException('REP tax-delta-only basket did not block import');
} catch (LmdbWurthPunchoutRepRulesRequiredException $exception) {
	if ($exception->getPendingRefs() !== array('5964590680')) {
		throw new RuntimeException('REP pending refs were not reported as expected');
	}
	if (count($db->repmap) !== 1 || $db->repmap[0]['status'] !== 'pending' || abs($db->repmap[0]['amount_ht']) > 0.000001) {
		throw new RuntimeException('REP pending rule was not created as expected');
	}
}

$db = new FakeRepDb();
$db->repmap[] = array('rowid' => 1, 'entity' => 1, 'vendor_ref' => '5964590680', 'amount_ht' => 0.0, 'label' => 'No REP', 'status' => 'active');
assertRepRulesReady(createRepImporter($db), $session);

$db = new FakeRepDb();
$session = new FakeRepSession(repBasket(237.02, 7.0, 47.40), array(repLine(2, 115.0, 46.0)));
assertRepRulesReady(createRepImporter($db), $session);
if (count($db->repmap) !== 1 || $db->repmap[0]['status'] !== 'active' || abs($db->repmap[0]['amount_ht'] - 0.01) > 0.000001) {
	throw new RuntimeException('Reliable cXML HT delta did not activate the REP rule with the expected unit amount');
}

$db = new FakeRepDb();
$session = new FakeRepSession(repBasket(115.0, 0.0, 23.0), array($line));
assertRepRulesReady(createRepImporter($db), $session);
if (!empty($db->repmap)) {
	throw new RuntimeException('REP rules were created without a usable HT or tax delta');
}

echo "Importer REP examples OK\n";
