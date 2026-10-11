<?php
/** Migration execution against SQLite with INSERT IGNORE translated; not a MySQL integration test. */
$coreRoot = $argv[1] ?? '';
if (!is_file($coreRoot.'/core/modules/DolibarrModules.class.php')) { throw new RuntimeException('Provide Dolibarr htdocs path'); }
define('DOL_DOCUMENT_ROOT', $coreRoot);
define('MAIN_DB_PREFIX', 'test_');
require __DIR__.'/../core/modules/modLmdbWurthPunchout.class.php';
class PermissionTestDb
{
	public $pdo;
	public $failure = '';
	public $error = '';
	public function __construct() { $this->pdo = new PDO('sqlite::memory:'); }
	public function query($sql) {
		if ($this->failure !== '' && strpos($sql, $this->failure) !== false) { $this->error = 'Injected query failure'; return false; }
		try { return $this->pdo->query(str_replace('INSERT IGNORE', 'INSERT OR IGNORE', $sql)); }
		catch (PDOException $error) { $this->error = $error->getMessage(); return false; }
	}
	public function fetch_object($result) { return $result->fetchObject(); }
	public function escape($value) { return str_replace("'", "''", $value); }
	public function lasterror() { return $this->error; }
	public function begin() { return $this->pdo->beginTransaction(); }
	public function commit() { return $this->pdo->commit(); }
	public function rollback() { return $this->pdo->rollBack(); }
}
function newPermissionTestDb() {
	$db = new PermissionTestDb();
	$db->query('CREATE TABLE test_rights_def (id INTEGER PRIMARY KEY, module TEXT, perms TEXT, subperms TEXT)');
	$db->query("INSERT INTO test_rights_def VALUES (45001500, 'lmdbwurthpunchout', 'punchout', 'use')");
	foreach (array('user_rights' => 'fk_user', 'usergroup_rights' => 'fk_usergroup') as $table => $owner) {
		$db->query('CREATE TABLE test_'.$table.' (entity INTEGER, '.$owner.' INTEGER, fk_id INTEGER, UNIQUE(entity, '.$owner.', fk_id))');
		$db->query('INSERT INTO test_'.$table.' VALUES (1, 7, 45001500), (2, 8, 45001500), (1, 7, 45001501), (2, 9, 45001502)');
	}
	return $db;
}
$method = new ReflectionMethod(modLmdbWurthPunchout::class, 'migratePunchoutPermission');
$db = newPermissionTestDb();
$module = new modLmdbWurthPunchout($db);
foreach (array(1, 2) as $pass) {
	if ($method->invoke($module) !== 1) { throw new RuntimeException($module->error); }
	foreach (array('user_rights', 'usergroup_rights') as $table) {
		$rows = $db->pdo->query('SELECT entity, fk_id FROM test_'.$table.' ORDER BY entity, fk_id')->fetchAll(PDO::FETCH_ASSOC);
		if (count($rows) !== 4 || $rows[1]['fk_id'] !== 45001503 || $rows[3]['fk_id'] !== 45001503) {
			throw new RuntimeException('Grants lost, duplicated or reassigned during migration');
		}
	}
}
$db = newPermissionTestDb();
$db->failure = 'DELETE FROM test_usergroup_rights';
if ($method->invoke(new modLmdbWurthPunchout($db)) !== -1 || (int) $db->pdo->query('SELECT COUNT(*) FROM test_user_rights WHERE fk_id = 45001500')->fetchColumn() !== 2) {
	throw new RuntimeException('Failed migration did not restore original grants');
}
$db = newPermissionTestDb();
$db->query("INSERT INTO test_rights_def VALUES (45001503, 'foreign', 'punchout', 'use')");
if ($method->invoke(new modLmdbWurthPunchout($db)) !== -1) { throw new RuntimeException('Permission collision was accepted'); }
echo "Permission migration, replay, failure rollback and collision checks OK (SQLite adapter)\n";
