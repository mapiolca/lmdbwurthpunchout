<?php
/** Targeted simulations: public receipt, access denial, REP isolation and CSRF. */
require __DIR__.'/importer_rep_test.php';
require __DIR__.'/../public/return_common.php';
function isModEnabled($module) { return true; }
function GETPOST($key, $type) { return $GLOBALS['testToken']; }
function currentToken() { return 'expected-token'; }
function accessforbidden($message = '') { throw new RuntimeException('Access denied: '.$message); }
$conf = (object) array('entity' => 2);
$langs = new class {
	public function trans($key) { return $key; }
};
$session = new class {
	public $id = 9;
	public $protocol = 'OCI';
	public $entity = 2;
	public $fk_user = 7;
	public $status = 'sent';
	public $writes = 0;
	public function isExpired() { return false; }
	public function storeReturn($raw, $lines, $basket) { $this->writes++; $this->status = 'returned'; return 1; }
};
lmdbwurthpunchoutStoreReturn($session, 'OCI', 'payload', array(array('vendor_ref' => 'sample')));
if ($session->writes !== 1 || $session->status !== 'returned') {
	throw new RuntimeException('Public receipt did not store the basket');
}
try {
	lmdbwurthpunchoutStoreReturn($session, 'OCI', 'replay', array(array('vendor_ref' => 'sample')));
	throw new LogicException('Replayed receipt was accepted');
} catch (RuntimeException $exception) {
	if ($exception instanceof LogicException) { throw $exception; }
}
if ($session->writes !== 1) { throw new RuntimeException('Replay changed stored basket'); }
$user = new class {
	public $admin = 1;
	public $id = 7;
	public $socid = 0;
	public $calls = 0;
	public function hasRight(...$right) { $this->calls++; return false; }
};
try {
	createRepImporter(new FakeRepDb())->importStoredSession($session, $user);
	throw new LogicException('Administrator without permissions imported a basket');
} catch (RuntimeException $exception) {
	if ($exception->getMessage() !== 'Punchout import access denied') { throw $exception; }
}
if ($user->calls === 0) { throw new RuntimeException('Native permission was not checked'); }
$db = new FakeRepDb();
$db->repmap[] = array('rowid' => 1, 'entity' => 1, 'vendor_ref' => '5964590680', 'amount_ht' => 0.01, 'status' => 'active', 'label' => 'Foreign');
$method = new ReflectionMethod(LmdbWurthPunchoutImporter::class, 'findRepRule');
if ($method->invoke(createRepImporter($db), '5964590680', 2, array('active')) !== null) {
	throw new RuntimeException('Entity 2 inherited entity 1 REP rules');
}
$db->repmap[] = array('rowid' => 2, 'entity' => 2, 'vendor_ref' => '5964590680', 'amount_ht' => 0.03, 'status' => 'active', 'label' => 'Local');
$rule = $method->invoke(createRepImporter($db), '5964590680', 2, array('active'));
if (!is_array($rule) || $rule['amount_ht'] !== 0.03) { throw new RuntimeException('Local REP rule was not resolved'); }
foreach (array('' => false, 'wrong' => false, 'expected-token' => true) as $token => $expected) {
	$GLOBALS['testToken'] = $token;
	if (LmdbWurthPunchoutSecurity::checkToken() !== $expected) { throw new RuntimeException('Unexpected CSRF result'); }
}
$user = new class {
	public $admin = 1;
	public $id = 7;
	public $socid = 0;
	public function hasRight(...$right) { return true; }
};
$session->id = 9;
$lockDb = new class {
	public $rolledBack = false;
	public function begin() { return 1; }
	public function query($sql) { return true; }
	public function fetch_object($result) { return (object) array('status' => 'imported'); }
	public function rollback() { $this->rolledBack = true; return 1; }
};
try {
	(new LmdbWurthPunchoutImporter($lockDb))->importStoredSession($session, $user);
	throw new LogicException('Already imported session passed the locked status check');
} catch (RuntimeException $exception) {
	if ($exception->getMessage() !== 'Punchout session is no longer importable') { throw $exception; }
}
if (!$lockDb->rolledBack) { throw new RuntimeException('Stale session lock failure did not roll back'); }
$receiptDb = new class {
	public $status = 'returned';
	public $commitOk = true;
	public $rolledBack = false;
	public $writes = 0;
	public function begin() { return 1; }
	public function query($sql) { if (strpos($sql, 'SELECT') !== 0) { $this->writes++; } return true; }
	public function fetch_object($result) { return (object) array('status' => $this->status); }
	public function escape($value) { return addslashes($value); }
	public function idate($date) { return '2026-10-11 12:00:00'; }
	public function commit() { return $this->commitOk; }
	public function rollback() { $this->rolledBack = true; return 1; }
};
$receipt = new LmdbWurthPunchoutSession($receiptDb);
$receipt->id = 9;
$receipt->entity = 2;
$receipt->status = 'sent';
if ($receipt->storeReturn('payload', array()) !== -1 || $receiptDb->writes !== 0 || !$receiptDb->rolledBack) {
	throw new RuntimeException('Concurrent receipt overwrote an already received basket');
}
$receiptDb->status = 'sent';
$receiptDb->commitOk = false;
if ($receipt->storeReturn('payload', array()) !== -1 || $receipt->status !== 'sent') {
	throw new RuntimeException('Receipt succeeded after a failed commit');
}
$receiptDb->commitOk = true;
if ($receipt->storeReturn('payload', array()) !== 1 || $receipt->status !== 'returned') {
	throw new RuntimeException('Valid receipt did not complete');
}
echo "Public receipt, replay, permissions, REP isolation and CSRF simulations OK\n";
